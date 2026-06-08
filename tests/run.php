<?php

declare(strict_types=1);

$database = sys_get_temp_dir() . '/samen-test-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('SAMEN_DATABASE=' . $database);
$_SERVER['SCRIPT_NAME'] = '/development/public/index.php';
$_SERVER['REQUEST_URI'] = '/development/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\AppSetting;
use App\Models\TodoList;
use App\Models\User;
use App\Services\BeamsSettings;
use App\Services\InvitationEmailSettings;
use App\Services\InvitationMailer;
use App\Services\ListNotificationService;
use App\Services\PushNotificationService;
use App\Services\PushSubscriptionService;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function render_view(string $view, array $variables): string
{
    extract($variables, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/app/Views/' . $view . '.php';
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
assert_true(!array_key_exists('push_external_id', $owner), 'new user records no longer expose the legacy provider identifier');

$lists = new TodoList();
$listId = $lists->create((int) $owner['id'], 'Vakantie', '✈️', 'coral');
$lists->share($listId, (int) $owner['id'], (int) $member['id']);
assert_true(count($lists->forUser((int) $member['id'])) === 1, 'shared lists are visible to members');
assert_true($lists->findAccessible($listId, (int) $outsider['id']) === null, 'users outside a shared list cannot access it');
$lists->addItem($listId, (int) $owner['id'], 'Treinkaartjes boeken');
$item = $lists->items($listId)[0];
$toggled = $lists->toggleItem((int) $item['id'], $listId, (int) $member['id']);
assert_true((bool) $toggled['is_completed'], 'members can complete shared tasks');
assert_true($lists->liveState($listId)['stats']['percent'] === 100, 'live state reports completion progress');

$users->setPassword((int) $owner['id'], 'een-veilig-wachtwoord');
assert_true(password_verify('een-veilig-wachtwoord', $users->find((int) $owner['id'])['password_hash']), 'passwords are securely hashed');
assert_true(base_path() === '/development', 'subdirectory base path is detected');

$invitationSettings = new InvitationEmailSettings();
$invitationSettings->save('Samen team', 'uitnodigingen@example.nl', '<h2>Kom erbij!</h2><script>alert(1)</script>');
assert_true((new AppSetting())->get('invitation_sender_name') === 'Samen team', 'invitation settings are persisted');
assert_true(!str_contains($invitationSettings->message(), '<script'), 'unsafe invitation HTML is removed');
$sentMail = null;
$mailer = new InvitationMailer(function (string $to, string $subject, string $message, array $headers) use (&$sentMail): bool {
    $sentMail = compact('to', 'subject', 'message', 'headers');
    return true;
});
assert_true($mailer->send('invitee@example.nl', $owner, $lists->findAccessible($listId, (int) $owner['id'])), 'invitation mail uses the injected transport');
assert_true(str_contains($sentMail['message'], 'http://localhost/development/lists/' . $listId), 'invitation mail contains an absolute list URL');

$beams = new BeamsSettings();
$beams->save('123e4567-e89b-42d3-a456-426614174000', 'secret-456');
assert_true($beams->isConfigured(), 'Pusher Beams is configured with an instance ID and secret key');
assert_true((new AppSetting())->get('beams_instance_id') === '123e4567-e89b-42d3-a456-426614174000', 'the Beams instance ID is stored in the database');
$beams->save('123e4567-e89b-42d3-a456-426614174000', null);
assert_true($beams->secretKey() === 'secret-456', 'leaving the Beams secret blank preserves the stored key');

$subscriptions = new PushSubscriptionService();
$subscriptions->save((int) $owner['id'], 'samen_device_web_1234', 'Test Browser');
$subscriptions->save((int) $owner['id'], 'samen_device_web_1234', 'Updated Browser');
$storedSubscriptions = $subscriptions->forUser((int) $owner['id']);
assert_true(count($storedSubscriptions) === 1, 'a Beams device interest is stored locally without duplicates');
assert_true($storedSubscriptions[0]['user_agent'] === 'Updated Browser', 'a repeated Beams registration refreshes its device metadata');
$subscriptions->save((int) $member['id'], 'samen_device_member_5678', 'Member Browser');
$subscriptions->save((int) $outsider['id'], 'samen_device_outsider_9012', 'Outsider Browser');
assert_true($subscriptions->tokensForUsers([(int) $member['id']]) === ['samen_device_member_5678'], 'device interests can be selected for notification recipients');

$requests = [];
$push = new PushNotificationService(function (string $method, string $url, array $headers, ?string $payload) use (&$requests): array {
    $requests[] = compact('method', 'url', 'headers', 'payload');
    return ['status' => 200, 'body' => json_encode(['publishId' => 'publish-123'], JSON_THROW_ON_ERROR)];
});
assert_true($push->sendToken('samen_device_web_1234', 'Handmatige test', '/admin/notifications'), 'Pusher Beams accepts a successful test message response');
assert_true(count($requests) === 1, 'sending through Beams needs a single provider request');
assert_true(str_contains($requests[0]['url'], '123e4567-e89b-42d3-a456-426614174000.pushnotifications.pusher.com'), 'the Beams instance endpoint is used');
assert_true(in_array('Authorization: Bearer secret-456', $requests[0]['headers'], true), 'the Beams request uses the server-side secret key');
$pushPayload = json_decode($requests[0]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true($pushPayload['interests'] === ['samen_device_web_1234'], 'the notification targets the selected device interest');
assert_true($pushPayload['web']['notification']['deep_link'] === 'http://localhost/development/admin/notifications', 'notification clicks return to the test page');

$list = $lists->findAccessible($listId, (int) $owner['id']);
$notifications = new ListNotificationService($push, $lists);
assert_true($notifications->taskCreated($list, $owner, 'Paspoorten meenemen'), 'shared-list notifications are accepted by Beams');
$listPayload = json_decode($requests[1]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true($listPayload['interests'] === ['samen_device_member_5678'], 'only other participants receive a list notification');
assert_true($listPayload['web']['notification']['title'] === 'Vakantie', 'list notifications use the list title');
assert_true(str_contains($listPayload['web']['notification']['body'], 'Owner heeft “Paspoorten meenemen” toegevoegd.'), 'new-task notifications identify the actor and task');
assert_true($listPayload['web']['notification']['deep_link'] === 'http://localhost/development/lists/' . $listId, 'list notification clicks open the changed list');

assert_true($notifications->taskCompleted($list, $member, 'Treinkaartjes boeken'), 'completed-task notifications are accepted by Beams');
$completedPayload = json_decode($requests[2]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true($completedPayload['interests'] === ['samen_device_web_1234'], 'the actor does not receive their own completed-task notification');
assert_true(str_contains($completedPayload['web']['notification']['body'], 'Member heeft “Treinkaartjes boeken” afgerond.'), 'completed-task notifications describe the completion');
assert_true($notifications->taskChanged($list, $member, 'Treinkaartjes boeken'), 'changed-task notifications are accepted by Beams');
$changedPayload = json_decode($requests[3]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true(str_contains($changedPayload['web']['notification']['body'], 'Member heeft “Treinkaartjes boeken” gewijzigd en weer geopend.'), 'changed-task notifications describe a reopened task');

$notificationPage = render_view('admin/notifications', [
    'beams' => $beams,
    'subscriptions' => $storedSubscriptions,
]);
assert_true(str_contains($notificationPage, 'data-beams-push'), 'the isolated admin test page exposes the Beams client hook');
assert_true(str_contains($notificationPage, 'Stuur testmelding'), 'the test page offers manual delivery');
assert_true(!str_contains($notificationPage, 'secret-456'), 'the stored Beams secret is never rendered into the page');

$adminPage = render_view('admin/index', [
    'invitation_sender_name' => $invitationSettings->senderName(),
    'invitation_sender_email' => $invitationSettings->senderEmail(),
    'invitation_message_html' => $invitationSettings->message(),
    'invitation_preview_html' => $invitationSettings->renderEmail($owner, ['id' => $listId, 'title' => 'Vakantie'], 'invitee@example.nl'),
    'invitation_tokens' => InvitationEmailSettings::tokens(),
]);
assert_true(str_contains($adminPage, '/admin/notifications'), 'the admin page links to the isolated notification test');
assert_true(str_contains($adminPage, 'data-rich-editor'), 'the invitation rich-text editor remains available');

$javascript = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
assert_true(str_contains($javascript, 'PusherPushNotifications.Client'), 'the browser initializes the Pusher Beams client');
assert_true(str_contains($javascript, 'client.start()'), 'the browser can register itself with Beams');
assert_true(str_contains($javascript, 'client.addDeviceInterest'), 'the browser creates a device-specific Beams interest');
assert_true(str_contains($javascript, 'client.stop()'), 'the browser can revoke its Beams registration');
assert_true(str_contains($javascript, 'readJsonResponse'), 'the browser handles empty Beams registration responses gracefully');
assert_true(str_contains($javascript, 'navigator.serviceWorker.ready'), 'Beams reuses the existing root-scoped PWA service worker');
assert_true(str_contains($javascript, 'Notification.permission'), 'the browser asks for notification permission only when needed');
assert_true(str_contains($javascript, 'registerDevice(false)'), 'previously granted devices are synchronized automatically in the background');

$controllerBase = file_get_contents(dirname(__DIR__) . '/app/Core/Controller.php');
assert_true(str_contains($controllerBase, 'protected function json'), 'controllers can send JSON responses for Beams registration');

$manifestController = file_get_contents(dirname(__DIR__) . '/app/Controllers/PwaController.php');
assert_true(str_contains($manifestController, 'beams/service-worker.js'), 'the PWA service worker imports Pusher Beams');
assert_true(str_contains($manifestController, "'display' => 'standalone'"), 'the web app manifest enables standalone display');
$routes = file_get_contents(dirname(__DIR__) . '/public/index.php');
assert_true(str_contains($routes, "'/admin/notifications'"), 'the notification test has a dedicated admin route');
assert_true(str_contains($routes, "'/notifications/subscribe'"), 'all signed-in users can register a notification device');
$repositoryText = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if ($file->isFile() && !str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) && !str_contains($path, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
        $repositoryText .= file_get_contents($path);
    }
}
$removedProviderName = 'fire' . 'base';
assert_true(stripos($repositoryText, $removedProviderName) === false, 'the previous notification provider is absent from application code and documentation');

$deploymentWorkflow = file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy.yml');
assert_true(str_contains($deploymentWorkflow, "--exclude='^storage(/|$)'"), 'FTP deployments preserve the remote SQLite database');
$serviceWorker = file_get_contents(dirname(__DIR__) . '/public/sw.js');
assert_true(str_contains($serviceWorker, "request.mode === 'navigate'"), 'the service worker retains offline navigation support');

@unlink($database);
@unlink($database . '-wal');
@unlink($database . '-shm');
echo "All tests passed.\n";
