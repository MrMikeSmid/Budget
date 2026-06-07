<?php

declare(strict_types=1);

$database = sys_get_temp_dir() . '/samen-test-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('SAMEN_DATABASE=' . $database);
$_SERVER['SCRIPT_NAME'] = '/development/public/index.php';
$_SERVER['REQUEST_URI'] = '/development/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/app/bootstrap.php';


use App\Models\TodoList;
use App\Models\User;
use App\Models\AppSetting;
use App\Services\InvitationEmailSettings;
use App\Services\InvitationMailer;
use App\Services\OneSignalSettings;
use App\Services\OneSignalSubscriptionService;
use App\Services\PushNotificationService;

function assert_true(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}

function render_lists_index(array $user, array $lists): string {
    ob_start();
    require dirname(__DIR__) . '/app/Views/lists/index.php';
    return (string) ob_get_clean();
}

function render_settings(array $user): string {
    ob_start();
    require dirname(__DIR__) . '/app/Views/settings/index.php';
    return (string) ob_get_clean();
}

function render_admin(string $onesignal_app_id, bool $onesignal_configured, InvitationEmailSettings $emailSettings): string {
    $invitation_sender_name = $emailSettings->senderName();
    $invitation_sender_email = $emailSettings->senderEmail();
    $invitation_message_html = $emailSettings->message();
    $invitation_preview_html = $emailSettings->renderEmail(
        ['name' => 'Mike', 'email' => 'mike@voorbeeld.nl'],
        ['id' => 1, 'title' => 'Weekendje weg'],
        'vriend@voorbeeld.nl'
    );
    $invitation_tokens = InvitationEmailSettings::tokens();
    $push_users = [];
    $push_subscriptions = [];
    $active_push_subscription_count = 0;
    $push_subscription_error = null;
    ob_start();
    require dirname(__DIR__) . '/app/Views/admin/index.php';
    return (string) ob_get_clean();
}

function render_list_show(array $user, array $list, array $items, array $members): string {
    ob_start();
    require dirname(__DIR__) . '/app/Views/lists/show.php';
    return (string) ob_get_clean();
}

$users = new User();
$owner = $users->findOrCreate('owner@example.nl');
$member = $users->findOrCreate('member@example.nl');
$outsider = $users->findOrCreate('outsider@example.nl');
assert_true($owner['name'] === 'Owner', 'account is created from an e-mail address');
assert_true((int) $owner['is_admin'] === 1, 'the first account becomes the initial administrator');
assert_true((int) $member['is_admin'] === 0, 'later accounts do not receive administrator access');
assert_true($users->findOrCreate('OWNER@example.nl')['id'] === $owner['id'], 'e-mail addresses are case-insensitively unique');

$lists = new TodoList();
$listId = $lists->create((int) $owner['id'], 'Vakantie', '✈️', 'coral');
$lists->share($listId, (int) $owner['id'], (int) $member['id']);
assert_true(count($lists->forUser((int) $member['id'])) === 1, 'shared lists are visible to members');
assert_true($lists->participantIdsExcept($listId, (int) $owner['id']) === [(int) $member['id']], 'push recipients exclude the user who made the change');

$memberLists = $lists->forUser((int) $member['id']);
$homeWithLists = render_lists_index($member, $memberLists);
assert_true(!str_contains($homeWithLists, 'class="hero-card"'), 'the introduction card is hidden when the user has a list');

$emptyHome = render_lists_index($member, []);
assert_true(str_contains($emptyHome, 'class="hero-card"'), 'the introduction card is shown when the user has no lists');
assert_true($lists->findAccessible($listId, (int) $member['id']) !== null, 'members can access a shared list');
assert_true($lists->findAccessible($listId, (int) $outsider['id']) === null, 'users outside the shared list cannot access it');

$lists->addItem($listId, (int) $owner['id'], 'Treinkaartjes boeken');
$item = $lists->items($listId)[0];
$openState = $lists->liveState($listId);
assert_true($openState['stats']['open'] === 1, 'live state reports open items');
$toggledItem = $lists->toggleItem((int) $item['id'], $listId, (int) $member['id']);
assert_true((bool) $toggledItem['is_completed'] === true, 'toggling returns the new item state for notifications');
$item = $lists->items($listId)[0];
assert_true((int) $item['is_completed'] === 1, 'a member can complete an item');
assert_true($item['completer_name'] === 'Member', 'the completing member is recorded');
$completedState = $lists->liveState($listId);
assert_true($completedState['stats']['percent'] === 100, 'live state reports completion progress');
assert_true($completedState['revision'] !== $openState['revision'], 'live state revision changes after an update');
assert_true(count($completedState['members']) === 2, 'live state includes all list members');
assert_true(
    !in_array((int) $outsider['id'], array_column($completedState['members'], 'id'), true),
    'live state does not expose presence for users outside the shared list'
);

$users->touchPresence((int) $owner['id']);
$presenceState = $lists->liveState($listId);
$presenceById = array_column($presenceState['members'], null, 'id');
assert_true($presenceById[$owner['id']]['is_online'] === true, 'a recently active list member is online');
assert_true($presenceById[$member['id']]['is_online'] === false, 'an inactive list member is offline');
assert_true($presenceState['revision'] !== $completedState['revision'], 'live state revision changes when presence changes');
$listView = render_list_show($owner, $lists->findAccessible($listId, (int) $owner['id']), $lists->items($listId), $lists->members($listId));
assert_true(str_contains($listView, 'member-avatar--online'), 'the shared list marks online members with a status class');
assert_true(str_contains($listView, 'Owner is online'), 'the shared list exposes an accessible online label');

$users->setProfileImage((int) $owner['id'], 'example-profile.png');
$ownerWithImage = $users->find((int) $owner['id']);
assert_true($ownerWithImage['profile_image'] === 'example-profile.png', 'a profile image filename can be saved for a user');
assert_true(str_contains(profile_image_url($ownerWithImage), '/development/settings/profile-image?v='), 'profile image URLs follow the deployment base path');
$settingsWithImage = render_settings($ownerWithImage);
assert_true(str_contains($settingsWithImage, 'enctype="multipart/form-data"'), 'the profile form accepts image uploads');
assert_true(str_contains($settingsWithImage, 'data-avatar-input'), 'the profile page includes an image picker');
$homeWithProfileImage = render_lists_index($ownerWithImage, []);
assert_true(str_contains($homeWithProfileImage, '/settings/profile-image?v='), 'the home avatar uses the uploaded profile image');

$users->setPassword((int) $owner['id'], 'een-veilig-wachtwoord');
$secured = $users->find((int) $owner['id']);
assert_true(password_verify('een-veilig-wachtwoord', $secured['password_hash']), 'passwords are securely hashed');
assert_true(base_path() === '/development', 'subdirectory base path is detected');

$invitationEmailSettings = new InvitationEmailSettings();
$invitationEmailSettings->save(
    'Samen team',
    'uitnodigingen@example.nl',
    '<h2>Kom erbij, {{invitee_email}}!</h2><p><strong>{{inviter_name}}</strong> deelt “{{list_title}}” met je.</p><script>alert(1)</script>'
);
assert_true((new AppSetting())->get('invitation_sender_name') === 'Samen team', 'the invitation sender name is persisted');
assert_true(!str_contains($invitationEmailSettings->message(), '<script'), 'unsafe invitation message elements are removed');
assert_true(str_contains($invitationEmailSettings->message(), 'alert(1)'), 'text inside unsupported message elements is preserved');

$sentMail = null;
$mailer = new InvitationMailer(function (string $to, string $subject, string $message, array $headers) use (&$sentMail): bool {
    $sentMail = compact('to', 'subject', 'message', 'headers');
    return true;
});
$mailSent = $mailer->send('invitee@example.nl', $owner, $lists->findAccessible($listId, (int) $owner['id']));
assert_true($mailSent, 'an invitation e-mail reports a successful transport');
assert_true($sentMail['to'] === 'invitee@example.nl', 'the invitation is sent to the invited e-mail address');
assert_true(str_contains($sentMail['subject'], 'Vakantie'), 'the invitation subject names the shared list');
assert_true(str_contains($sentMail['message'], '<strong>Owner</strong>'), 'the HTML invitation identifies who shared the list');
assert_true(str_contains($sentMail['message'], 'http://localhost/development/lists/' . $listId), 'the invitation includes an absolute link to the shared list');
assert_true(str_contains($sentMail['message'], 'http://localhost/development/privacy'), 'the invitation footer links to the privacy page');
assert_true(str_contains($sentMail['message'], 'http://localhost/development/voorwaarden'), 'the invitation footer links to the terms page');
assert_true(str_contains($sentMail['message'], '/pwa-icon/app-192'), 'the invitation header and footer use the app logo');
assert_true($sentMail['headers']['From'] === 'Samen team <uitnodigingen@example.nl>', 'the invitation uses the editable sender identity');
assert_true($sentMail['headers']['Content-Type'] === 'text/html; charset=UTF-8', 'the invitation is sent as an HTML e-mail');

$oneSignalSettings = new OneSignalSettings();
$oneSignalSettings->save('11111111-1111-4111-8111-111111111111', 'test-api-key');
assert_true((new AppSetting())->get('onesignal_app_id') === '11111111-1111-4111-8111-111111111111', 'the OneSignal App ID is persisted in the database');
$oneSignalSettings->save('22222222-2222-4222-8222-222222222222', null);
assert_true($oneSignalSettings->apiKey() === 'test-api-key', 'leaving the API key blank preserves the stored secret');
$oneSignalSettings->save('11111111-1111-4111-8111-111111111111', null);
$adminPage = render_admin($oneSignalSettings->appId(), $oneSignalSettings->isConfigured(), $invitationEmailSettings);
assert_true(str_contains($adminPage, 'OneSignal REST API Key'), 'the admin page contains the OneSignal credentials form');
assert_true(!str_contains($adminPage, 'value="test-api-key"'), 'the stored OneSignal API key is never rendered into the admin form');
assert_true(str_contains($adminPage, 'data-rich-editor'), 'the admin page contains the invitation rich-text editor');
assert_true(str_contains($adminPage, 'Samen team'), 'the admin page renders the saved invitation sender');
assert_true(str_contains($adminPage, 'data-email-preview'), 'the admin page contains an invitation e-mail preview');
assert_true(str_contains($adminPage, '/admin/onesignal/test'), 'the admin page offers a direct push delivery test');
assert_true(str_contains($adminPage, 'pushabonnementen'), 'the admin page contains the push subscription overview');
assert_true(str_contains($adminPage, 'Selecteer e-mailadres'), 'the admin page can target a manual push test by user');
$pushRequest = null;
$push = new PushNotificationService(function (string $url, array $headers, string $payload) use (&$pushRequest): bool {
    $pushRequest = ['url' => $url, 'headers' => $headers, 'payload' => json_decode($payload, true, flags: JSON_THROW_ON_ERROR)];
    return true;
});
assert_true($push->send([(int) $member['id']], 'Een lijst is bijgewerkt.', '/lists/' . $listId), 'a configured push notification reports successful delivery');
assert_true($pushRequest['payload']['include_aliases']['external_id'] === [$member['push_external_id']], 'push notifications target the member opaque external ID');
assert_true($pushRequest['payload']['target_channel'] === 'push', 'the OneSignal request selects the push channel');
assert_true($pushRequest['payload']['url'] === 'http://localhost/development/lists/' . $listId, 'push notifications open the changed list');
assert_true($pushRequest['headers']['Authorization'] === 'Key test-api-key', 'the OneSignal API key is sent using the required authorization scheme');

$subscriptionRequests = [];
$subscriptionService = new OneSignalSubscriptionService(function (string $method, string $url, array $headers, ?string $payload) use (&$subscriptionRequests, $member): array {
    $subscriptionRequests[] = compact('method', 'url', 'headers', 'payload');
    if ($method === 'GET') {
        return ['status' => 200, 'body' => json_encode([
            'properties' => ['last_active' => 1710000000],
            'identity' => ['external_id' => $member['push_external_id']],
            'subscriptions' => [[
                'id' => '22222222-2222-4222-8222-222222222222',
                'type' => 'ChromePush',
                'notification_types' => 1,
                'device_model' => 'Chrome',
                'device_os' => 'macOS',
            ], [
                'id' => '33333333-3333-4333-8333-333333333333',
                'type' => 'SafariPush',
                'notification_types' => -2,
                'enabled' => true,
                'device_model' => 'Safari',
                'device_os' => 'iOS',
            ], [
                'id' => '44444444-4444-4444-8444-444444444444',
                'type' => 'FirefoxPush',
                'enabled' => 'true',
                'device_model' => 'Firefox',
                'device_os' => 'Linux',
            ]],
        ], JSON_THROW_ON_ERROR)];
    }
    return ['status' => 202, 'body' => '{}'];
});
$subscriptions = $subscriptionService->forUsers([$member]);
assert_true(count($subscriptions) === 3, 'OneSignal subscriptions are fetched for local users by external ID');
assert_true($subscriptions[0]['email'] === 'member@example.nl', 'fetched subscriptions stay linked to the local email address');
assert_true($subscriptions[0]['subscription_id'] === '22222222-2222-4222-8222-222222222222', 'fetched subscriptions expose the OneSignal subscription ID');
assert_true($subscriptions[0]['enabled'] === true, 'positive OneSignal notification types are shown as active');
assert_true($subscriptions[1]['enabled'] === false, 'an unsubscribed notification type takes precedence over the legacy enabled field');
assert_true($subscriptions[2]['enabled'] === true, 'legacy OneSignal responses still use the enabled field as a fallback');
assert_true($subscriptionRequests[0]['method'] === 'GET' && str_contains($subscriptionRequests[0]['url'], '/users/by/external_id/'), 'subscription lookup uses the OneSignal View User API');
assert_true($subscriptionService->delete('22222222-2222-4222-8222-222222222222'), 'OneSignal subscriptions can be deleted by subscription ID');
assert_true($subscriptionRequests[1]['method'] === 'DELETE' && str_contains($subscriptionRequests[1]['url'], '/subscriptions/22222222-2222-4222-8222-222222222222'), 'subscription deletion calls the OneSignal Delete Subscription API');
$acceptedPush = new PushNotificationService(static fn(string $url, array $headers, string $payload): array => [
    'status' => 200,
    'body' => json_encode(['id' => '33333333-3333-4333-8333-333333333333'], JSON_THROW_ON_ERROR),
]);
assert_true($acceptedPush->send([(int) $member['id']], 'Test'), 'a OneSignal response with a notification id is accepted');
$unmatchedPush = new PushNotificationService(static fn(string $url, array $headers, string $payload): array => [
    'status' => 200,
    'body' => json_encode(['id' => '', 'errors' => ['No valid subscriptions']], JSON_THROW_ON_ERROR),
]);
assert_true(!$unmatchedPush->send([(int) $member['id']], 'Test'), 'a 200 response without a notification id is not reported as sent');
assert_true($unmatchedPush->lastError() === 'OneSignal vond geen actief pushabonnement voor deze gebruiker.', 'an unmatched OneSignal user gets a useful diagnostic');
$unauthorizedPush = new PushNotificationService(static fn(string $url, array $headers, string $payload): array => [
    'status' => 403,
    'body' => json_encode(['errors' => ['Access denied']], JSON_THROW_ON_ERROR),
]);
assert_true(!$unauthorizedPush->send([(int) $member['id']], 'Test'), 'an unauthorized OneSignal response is rejected');
assert_true(str_contains((string) $unauthorizedPush->lastError(), 'API key'), 'an unauthorized response identifies the API key problem');
$settingsWithPush = render_settings($ownerWithImage);
assert_true(str_contains($settingsWithPush, 'data-push-toggle'), 'configured push notifications expose a preference button');
$oneSignalSettings->save('', '');

$deploymentWorkflow = file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy.yml');
assert_true(str_contains($deploymentWorkflow, "--exclude='^storage(/|$)'"), 'FTP deployments preserve the remote storage directory and SQLite database');

$stylesheet = file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');
assert_true(
    preg_match('/\.bottom-nav\{[^}]*left:50%;[^}]*transform:translateX\(-50%\)/', $stylesheet) === 1,
    'bottom navigation is centered within the viewport'
);
assert_true(
    str_contains($stylesheet, '.member-stack i.member-avatar--online::after'),
    'online list members receive a green status indicator'
);
assert_true(
    preg_match('/@media\(max-width:899px\)\{\s*\.bottom-nav \.desktop-only\{display:none\}/', $stylesheet) === 1,
    'desktop-only navigation items stay hidden in the mobile bottom bar'
);
assert_true(
    str_contains($stylesheet, '@media(min-width:900px){.settings-page>.install-card{display:none}'),
    'the profile install notification stays hidden on desktop'
);

$javascript = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
assert_true(
    str_contains($javascript, "const isInstalledApp = standaloneDisplay.matches || window.navigator.standalone === true;"),
    'installed Android and iOS PWAs are detected in standalone mode'
);
assert_true(
    str_contains($javascript, "} else if (installCard) {
  hideInstallCard();
}"),
    'the profile install card stays hidden when Samen is already installed'
);
assert_true(str_contains($javascript, "document.execCommand(button.dataset.editorCommand"), 'the invitation editor supports rich-text formatting');
assert_true(str_contains($javascript, "input.value = editor.innerHTML"), 'the invitation editor synchronizes HTML before saving');
assert_true(
    !str_contains($javascript, 'install-card--installed'),
    'the installed PWA no longer shows an installation status card'
);


assert_true(
    str_contains($stylesheet, 'img{display:block;max-width:100%;height:auto}'),
    'images are globally constrained to their containers'
);
assert_true(
    str_contains($stylesheet, '.avatar>img{width:100%;height:100%;max-width:100%;max-height:100%;border-radius:50%;object-fit:cover;object-position:center;clip-path:circle(50% at 50% 50%)}'),
    'profile images are cropped proportionally inside round avatar frames'
);
assert_true(
    str_contains(asset('css/app.css'), 'app.css?v='),
    'asset URLs include a file version so updated image styles bypass stale caches'
);


$manifestController = file_get_contents(dirname(__DIR__) . '/app/Controllers/PwaController.php');
assert_true(str_contains($manifestController, "'display' => 'standalone'"), 'the web app manifest enables standalone display');
assert_true(str_contains($manifestController, "'purpose' => 'maskable'"), 'the web app manifest includes an Android maskable icon');
assert_true(str_contains($manifestController, "'start_url' => url('/')"), 'the manifest start URL follows the deployment base path');

$serviceWorker = file_get_contents(dirname(__DIR__) . '/public/sw.js');
assert_true(str_contains($serviceWorker, "request.mode === 'navigate'"), 'the service worker handles offline navigation');
assert_true(str_contains($serviceWorker, 'public/offline.html'), 'the service worker precaches an offline fallback');
assert_true(str_contains($serviceWorker, "CACHE_VERSION = 'samen-shell-v2'"), 'the service worker cache version is refreshed');
assert_true(str_contains($serviceWorker, 'fetch(request).then'), 'app assets are refreshed from the network before using the offline cache');
$layout = file_get_contents(dirname(__DIR__) . '/app/Views/layouts/app.php');
assert_true(
    str_contains($layout, "data-onesignal-worker=\"<?= e(url('/push/onesignal/OneSignalSDKWorker.js')) ?>\""),
    'the OneSignal worker URL remains root-relative when Samen is deployed in a subdirectory'
);
assert_true(
    !str_contains($layout, "ltrim(url('/push/onesignal/OneSignalSDKWorker.js'), '/')"),
    'the OneSignal worker URL is not converted into a page-relative path'
);
assert_true(str_contains($javascript, 'OneSignal.Notifications.requestPermission()'), 'the settings button explicitly asks the browser for notification permission');
assert_true(str_contains($javascript, 'waitForPermission()'), 'enabling push waits for delayed browser permission state updates');
assert_true(str_contains($javascript, 'waitForSubscription()'), 'enabling push waits until OneSignal has created a real device subscription');
assert_true(str_contains($javascript, "permission === 'denied'"), 'blocked browser permissions are explained at user level');
assert_true(str_contains($javascript, 'OneSignal.User.PushSubscription.optIn()'), 'the settings button can subscribe the current device to push');
assert_true(str_contains($javascript, 'await OneSignal.login(oneSignalUser)'), 'push subscriptions are linked to the signed-in user');
assert_true(str_contains($javascript, "OneSignal.User.PushSubscription.optOut()"), 'users can disable notifications and are opted out on logout');
assert_true(str_contains($javascript, "data-push-delete"), 'users can delete the current device push subscription from settings');
assert_true(str_contains($manifestController, 'OneSignalSDK.sw.js'), 'the OneSignal service worker endpoint loads the current web push worker');

assert_true(str_contains($manifestController, 'public function icon(string $name)'), 'PWA icons are generated by a text-only endpoint');
assert_true(str_contains($manifestController, "header('Content-Type: image/png')"), 'the icon endpoint returns PNG images');
assert_true(!str_contains($manifestController, "asset('icons/app-icon"), 'the manifest does not depend on committed binary icons');

@unlink($database); @unlink($database . '-wal'); @unlink($database . '-shm');
echo "All tests passed.\n";
