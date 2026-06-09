<?php

declare(strict_types=1);

$database = sys_get_temp_dir() . '/samen-test-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('SAMEN_DATABASE=' . $database);
$_SERVER['SCRIPT_NAME'] = '/development/public/index.php';
$_SERVER['REQUEST_URI'] = '/development/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\View;
use App\Models\AppSetting;
use App\Models\TodoList;
use App\Models\User;
use App\Services\OneSignalSettings;
use App\Services\InvitationEmailSettings;
use App\Services\InvitationMailer;
use App\Services\ListNotificationService;
use App\Services\DueTaskNotificationService;
use App\Services\OneSignalNotificationService;
use App\Services\NotificationSubscriptionService;

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

function render_page(string $view, array $variables): string
{
    ob_start();
    View::render($view, $variables);
    return (string) ob_get_clean();
}

$users = new User();
$owner = $users->findOrCreate('owner@example.nl');
$member = $users->findOrCreate('member@example.nl');
$outsider = $users->findOrCreate('outsider@example.nl');
$pendingRemoval = $users->findOrCreate('pending-removal@example.nl');
$activeRemoval = $users->findOrCreate('active-removal@example.nl');
assert_true($owner['name'] === 'Owner', 'account is created from an e-mail address');
assert_true((int) $owner['is_admin'] === 1, 'the first account becomes the initial administrator');
assert_true((int) $member['is_admin'] === 0, 'later accounts do not receive administrator access');
$adminProfilePage = render_view('settings/index', ['user' => $owner]);
$memberProfilePage = render_view('settings/index', ['user' => $member]);
assert_true(str_contains($adminProfilePage, 'href="/development/admin"'), 'the administrator profile links to the admin page');
assert_true(!str_contains($memberProfilePage, 'href="/development/admin"'), 'regular profiles do not expose the admin page link');
assert_true($users->findOrCreate('OWNER@example.nl')['id'] === $owner['id'], 'e-mail addresses are case-insensitively unique');
assert_true(!array_key_exists('push_external_id', $owner), 'new user records no longer expose the legacy provider identifier');
$users->setPassword((int) $member['id'], 'veilig-wachtwoord');
$users->recordLogin((int) $member['id']);
assert_true($users->find((int) $member['id'])['last_login_at'] !== null, 'a successful login timestamp can be recorded');
assert_true(str_contains(file_get_contents(dirname(__DIR__) . '/app/Core/Auth.php'), 'recordLogin'), 'successful authentication records the last login timestamp');

$lists = new TodoList();
$listId = $lists->create((int) $owner['id'], 'Vakantie', '✈️', 'coral');
$_SESSION['user_id'] = (int) $owner['id'];
$homePage = render_page('lists/index', [
    'title' => 'Mijn lijstjes',
    'user' => $owner,
    'lists' => $lists->forUser((int) $owner['id']),
]);
assert_true(str_contains($homePage, 'class="nav-create" data-open-modal="new-list"'), 'the dashboard plus button opens the new-list modal when lists already exist');
assert_true(str_contains($homePage, '<dialog class="modal" id="new-list">'), 'the dashboard renders the modal targeted by its plus button');
$lists->share($listId, (int) $owner['id'], (int) $member['id']);
assert_true(count($lists->forUser((int) $member['id'])) === 1, 'pending invitations are visible to invited users');
assert_true((int) $lists->forUser((int) $member['id'])[0]['invitation_pending'] === 1, 'list overviews identify pending invitations');
$pendingHomePage = render_view('lists/index', [
    'user' => $member,
    'lists' => $lists->forUser((int) $member['id']),
]);
assert_true(str_contains($pendingHomePage, 'Openstaande uitnodigingen'), 'pending invitations are shown directly below the dashboard greeting');
assert_true(str_contains($pendingHomePage, '/lists/' . $listId . '/accept'), 'dashboard invitations include an acceptance action');
assert_true(!$lists->canParticipate($listId, (int) $member['id']), 'invited users are not active participants before accepting');
$pendingState = $lists->liveState($listId);
$pendingMember = array_values(array_filter($pendingState['members'], static fn(array $person): bool => (int) $person['id'] === (int) $member['id']))[0];
assert_true($pendingMember['is_active'] === false, 'pending members are exposed as invited instead of active');
$pendingPage = render_view('lists/show', [
    'user' => $member,
    'list' => $lists->findAccessible($listId, (int) $member['id']),
    'items' => $pendingState['items'],
    'members' => $pendingState['members'],
    'initialState' => $pendingState,
]);
assert_true(!str_contains($pendingPage, 'Uitnodiging accepteren'), 'the invitation acceptance window is no longer duplicated inside the list');
assert_true(str_contains($pendingPage, 'Uitgenodigd'), 'pending members are labelled as invited in the member list');
assert_true($lists->acceptInvitation($listId, (int) $member['id']), 'an invited user can accept the invitation');
assert_true($lists->canParticipate($listId, (int) $member['id']), 'accepted users become active participants');

$lists->share($listId, (int) $owner['id'], (int) $pendingRemoval['id']);
$pendingRemovalState = $lists->liveState($listId);
$pendingRemovalPage = render_view('lists/show', [
    'user' => $owner,
    'list' => $lists->findAccessible($listId, (int) $owner['id']),
    'items' => $pendingRemovalState['items'],
    'members' => $pendingRemovalState['members'],
    'initialState' => $pendingRemovalState,
]);
assert_true(str_contains($pendingRemovalPage, '/members/' . $pendingRemoval['id'] . '/delete'), 'owners can remove pending invitations from the member list');
assert_true(str_contains($pendingRemovalPage, 'Uitnodiging verwijderen'), 'pending invitation removal is clearly labelled');
assert_true(!$lists->removeMember($listId, (int) $outsider['id'], (int) $pendingRemoval['id']), 'non-owners cannot remove invitations');
assert_true($lists->removeMember($listId, (int) $owner['id'], (int) $pendingRemoval['id']), 'owners can remove pending invitations');
assert_true($lists->findAccessible($listId, (int) $pendingRemoval['id']) === null, 'removed invitees immediately lose list access');
assert_true($lists->forUser((int) $pendingRemoval['id']) === [], 'removed invitations disappear from the invitee dashboard');

$lists->share($listId, (int) $owner['id'], (int) $activeRemoval['id']);
assert_true($lists->acceptInvitation($listId, (int) $activeRemoval['id']), 'a removable test member can accept the invitation');
assert_true(in_array((int) $activeRemoval['id'], $lists->participantIdsExcept($listId, (int) $owner['id']), true), 'active members initially receive list updates');
assert_true($lists->removeMember($listId, (int) $owner['id'], (int) $activeRemoval['id']), 'owners can remove active members');
assert_true(!$lists->canParticipate($listId, (int) $activeRemoval['id']), 'removed members immediately lose participation rights');
assert_true($lists->findAccessible($listId, (int) $activeRemoval['id']) === null, 'removed members can no longer open the list');
assert_true($lists->forUser((int) $activeRemoval['id']) === [], 'removed lists disappear from a former member dashboard');
assert_true(!in_array((int) $activeRemoval['id'], $lists->participantIdsExcept($listId, (int) $owner['id']), true), 'removed members no longer receive list notifications');
assert_true(!$lists->removeMember($listId, (int) $owner['id'], (int) $owner['id']), 'the owner cannot remove themselves as a member');
assert_true($lists->findAccessible($listId, (int) $outsider['id']) === null, 'users outside a shared list cannot access it');

$sortingListId = $lists->create((int) $owner['id'], 'Sorteertest', '✨', 'lavender');
$lists->addItem($sortingListId, (int) $owner['id'], 'Zonder prioriteit, vroeg', 'none', '2026-06-10');
$lists->addItem($sortingListId, (int) $owner['id'], 'Hoog, laat', 'high', '2026-07-20');
$lists->addItem($sortingListId, (int) $owner['id'], 'Hoog, vroeg', 'high', '2026-06-12');
$lists->addItem($sortingListId, (int) $owner['id'], 'Normaal, vroeg', 'medium', '2026-06-11');
$lists->addItem($sortingListId, (int) $owner['id'], 'Hoog, zonder datum', 'high');
$sortedTitles = array_column($lists->items($sortingListId), 'title');
assert_true($sortedTitles === [
    'Hoog, vroeg',
    'Hoog, laat',
    'Hoog, zonder datum',
    'Normaal, vroeg',
    'Zonder prioriteit, vroeg',
], 'tasks are sorted by descending priority and then ascending due date, with missing dates last');

$lists->addItem($listId, (int) $owner['id'], 'Treinkaartjes boeken');
$item = $lists->items($listId)[0];
$toggled = $lists->toggleItem((int) $item['id'], $listId, (int) $member['id']);
assert_true((bool) $toggled['is_completed'], 'members can complete shared tasks');
assert_true($lists->liveState($listId)['stats']['percent'] === 100, 'live state reports completion progress');
$lists->addItem($listId, (int) $owner['id'], 'Niet afgeronde taak');
$openItem = $lists->items($listId)[0];
assert_true($lists->addComment((int) $openItem['id'], $listId, (int) $member['id'], 'Ik regel dit vanmiddag.'), 'members can comment on existing tasks');
$commentedItem = $lists->liveState($listId)['items'][0];
assert_true($commentedItem['comment_count'] === 1, 'live state includes the task comment count');
assert_true($commentedItem['comments'][0]['author_name'] === $member['name'], 'task comments include the author name');
assert_true($commentedItem['comments'][0]['body'] === 'Ik regel dit vanmiddag.', 'task comments include their text');
assert_true(!$lists->deleteCompletedItem((int) $openItem['id'], $listId), 'open tasks cannot be deleted through the completed-task action');
assert_true($lists->deleteCompletedItem((int) $item['id'], $listId), 'completed tasks can be deleted');
assert_true($lists->liveState($listId)['stats']['total'] === 1, 'deleting a completed task refreshes list statistics');
$richItemId = $lists->addItem($listId, (int) $owner['id'], 'Paspoorten klaarleggen', 'high', '2026-08-14', 'voorbeeld.webp');
$richItem = $lists->liveState($listId)['items'][0];
assert_true($richItem['priority'] === 'high', 'task state includes the selected priority');
assert_true($richItem['due_date'] === '2026-08-14', 'task state includes the due date');
$overdueItemId = $lists->addItem($listId, (int) $owner['id'], 'Verlopen taak', 'none', '2025-01-01');
$overdueItem = array_values(array_filter($lists->liveState($listId)['items'], static fn(array $task): bool => $task['id'] === $overdueItemId))[0];
assert_true($overdueItem['is_overdue'] === true, 'live state marks incomplete tasks past their due date as overdue');
$overduePage = render_view('lists/show', [
    'user' => $owner,
    'list' => $lists->findAccessible($listId, (int) $owner['id']),
    'items' => $lists->liveState($listId)['items'],
    'members' => $lists->liveState($listId)['members'],
    'initialState' => $lists->liveState($listId),
]);
assert_true(str_contains($overduePage, 'task--overdue') && str_contains($overduePage, 'Vervallen 01-01-2025'), 'server-rendered overdue tasks receive the pastel-red state and expired label');
$lists->toggleItem($overdueItemId, $listId, (int) $owner['id']);
assert_true($richItem['has_image'] === true, 'task state reports an attached image without exposing its filename');
assert_true($lists->itemImage($richItemId, $listId) === 'voorbeeld.webp', 'task images can be resolved inside their list');
$storedImageBytes = "\x89PNG\r\n\x1a\nblijvende-testafbeelding";
$storedImageId = $lists->addItem(
    $listId,
    (int) $owner['id'],
    'Afbeelding duurzaam bewaren',
    'none',
    null,
    null,
    $storedImageBytes,
    'image/png'
);
$storedImage = $lists->itemImageData($storedImageId, $listId);
assert_true($storedImage !== null && $storedImage['data'] === $storedImageBytes, 'task image bytes remain stored with the task');
assert_true($storedImage['mime_type'] === 'image/png', 'task image MIME types remain stored with the task');
assert_true($lists->liveState($listId)['items'][0]['has_image'] === true, 'database-backed task images remain visible after state reloads');
$renderedState = $lists->liveState($listId);
$listPage = render_view('lists/show', [
    'user' => $owner,
    'list' => $lists->findAccessible($listId, (int) $owner['id']),
    'items' => $renderedState['items'],
    'members' => $renderedState['members'],
    'initialState' => $renderedState,
]);
assert_true(str_contains($listPage, 'id="new-task"'), 'list pages include the detailed task creation modal');
assert_true(str_contains($listPage, 'name="priority"'), 'the task modal offers a priority field');
assert_true(str_contains($listPage, 'name="due_date"'), 'the task modal offers a due date field');
assert_true(str_contains($listPage, 'data-task-sort'), 'list pages offer a task sorting dropdown');
assert_true(str_contains($listPage, '<h2 data-open-count>') && str_contains($listPage, ' open taken</h2>'), 'the open task count is part of the open tasks heading');
assert_true(str_contains($listPage, 'value="priority_due"'), 'priority and due date are the default sorting option');
assert_true(str_contains($listPage, 'name="image"'), 'the task modal offers an image upload');
assert_true(str_contains($listPage, '/lists/' . $listId . '/members/' . $member['id'] . '/delete'), 'owners can remove active members from the member list');
assert_true(str_contains($listPage, 'data-member-delete-url='), 'live member updates retain the member removal endpoint');
assert_true(str_contains($listPage, 'data-is-owner="true"'), 'the live list identifies owners who may remove members');

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

$oneSignal = new OneSignalSettings();
$oneSignal->save('123e4567-e89b-42d3-a456-426614174000', 'os-rest-key-456');
assert_true($oneSignal->isConfigured(), 'OneSignal is configured with an App ID and REST API key');
assert_true((new AppSetting())->get('onesignal_app_id') === '123e4567-e89b-42d3-a456-426614174000', 'the OneSignal App ID is stored in the database');
$oneSignal->save('123e4567-e89b-42d3-a456-426614174000', null);
assert_true($oneSignal->restApiKey() === 'os-rest-key-456', 'leaving the OneSignal REST API key blank preserves the stored key');

$subscriptions = new NotificationSubscriptionService();
$subscriptions->save((int) $owner['id'], '11111111-1111-4111-8111-111111111111', 'Test Browser');
$subscriptions->save((int) $owner['id'], '11111111-1111-4111-8111-111111111111', 'Updated Browser');
$storedSubscriptions = $subscriptions->forUser((int) $owner['id']);
assert_true(count($storedSubscriptions) === 1, 'a OneSignal subscription is stored locally without duplicates');
assert_true($storedSubscriptions[0]['user_agent'] === 'Updated Browser', 'a repeated OneSignal registration refreshes its device metadata');
$subscriptions->save((int) $member['id'], '22222222-2222-4222-8222-222222222222', 'Member Browser');
$subscriptions->save((int) $outsider['id'], '33333333-3333-4333-8333-333333333333', 'Outsider Browser');
assert_true($subscriptions->idsForUsers([(int) $member['id']]) === ['22222222-2222-4222-8222-222222222222'], 'subscription IDs can be selected for notification recipients');
$adminAccounts = $users->allForAdmin();
$memberAccount = array_values(array_filter($adminAccounts, static fn(array $account): bool => (int) $account['id'] === (int) $member['id']))[0];
assert_true((int) $memberAccount['notification_subscription_count'] === 1, 'the admin account overview counts active notification devices');
assert_true((int) $memberAccount['has_password'] === 1, 'the admin account overview exposes whether a password is configured');
assert_true(!array_key_exists('password_hash', $memberAccount), 'the admin account overview does not expose password hashes');
$accountsPage = render_view('admin/accounts', ['accounts' => $adminAccounts]);
assert_true(str_contains($accountsPage, 'member@example.nl'), 'the admin account page lists registered users');
assert_true(str_contains($accountsPage, 'Ingesteld'), 'the admin account page shows configured password status');
assert_true(str_contains($accountsPage, 'Laatst ingelogd'), 'the admin account page shows the last login column');

$requests = [];
$push = new OneSignalNotificationService(function (string $method, string $url, array $headers, ?string $payload) use (&$requests): array {
    $requests[] = compact('method', 'url', 'headers', 'payload');
    return ['status' => 200, 'body' => json_encode(['id' => 'notification-123'], JSON_THROW_ON_ERROR)];
});
assert_true($push->sendSubscription('11111111-1111-4111-8111-111111111111', 'Handmatige test', '/admin/notifications'), 'OneSignal accepts a successful test message response');
assert_true(count($requests) === 1, 'sending through OneSignal needs a single provider request');
assert_true($requests[0]['url'] === 'https://api.onesignal.com/notifications', 'the OneSignal notification endpoint is used');
assert_true(in_array('Authorization: Key os-rest-key-456', $requests[0]['headers'], true), 'the OneSignal request uses the server-side REST API key');
$pushPayload = json_decode($requests[0]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true($pushPayload['app_id'] === '123e4567-e89b-42d3-a456-426614174000', 'the notification uses the configured OneSignal App ID');
assert_true($pushPayload['include_subscription_ids'] === ['11111111-1111-4111-8111-111111111111'], 'the notification targets the selected subscription');
assert_true($pushPayload['url'] === 'http://localhost/development/admin/notifications', 'notification clicks return to the test page');

$list = $lists->findAccessible($listId, (int) $owner['id']);
$notifications = new ListNotificationService($push, $lists);
assert_true($notifications->taskCreated($list, $owner, 'Paspoorten meenemen'), 'shared-list notifications are accepted by OneSignal');
$listPayload = json_decode($requests[1]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true($listPayload['include_subscription_ids'] === ['22222222-2222-4222-8222-222222222222'], 'only other participants receive a list notification');
assert_true($listPayload['headings']['en'] === 'Vakantie', 'list notifications use the list title');
assert_true(str_contains($listPayload['contents']['en'], 'Owner heeft “Paspoorten meenemen” toegevoegd.'), 'new-task notifications identify the actor and task');
assert_true($listPayload['url'] === 'http://localhost/development/lists/' . $listId, 'list notification clicks open the changed list');

assert_true($notifications->taskCompleted($list, $member, 'Treinkaartjes boeken'), 'completed-task notifications are accepted by OneSignal');
$completedPayload = json_decode($requests[2]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true($completedPayload['include_subscription_ids'] === ['11111111-1111-4111-8111-111111111111'], 'the actor does not receive their own completed-task notification');
assert_true(str_contains($completedPayload['contents']['en'], 'Member heeft “Treinkaartjes boeken” afgerond.'), 'completed-task notifications describe the completion');
assert_true($notifications->taskChanged($list, $member, 'Treinkaartjes boeken'), 'changed-task notifications are accepted by OneSignal');
$changedPayload = json_decode($requests[3]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true(str_contains($changedPayload['contents']['en'], 'Member heeft “Treinkaartjes boeken” gewijzigd en weer geopend.'), 'changed-task notifications describe a reopened task');

assert_true($notifications->invitationAccepted($list, $member), 'accepted-invitation notifications are accepted by OneSignal');
$acceptedPayload = json_decode($requests[4]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true($acceptedPayload['include_subscription_ids'] === ['11111111-1111-4111-8111-111111111111'], 'only the list owner receives an accepted-invitation notification');
assert_true(str_contains($acceptedPayload['contents']['en'], 'Member heeft de uitnodiging geaccepteerd en is nu lid van dit lijstje.'), 'accepted-invitation notifications identify the new member');
assert_true($acceptedPayload['url'] === 'http://localhost/development/lists/' . $listId, 'accepted-invitation notification clicks open the shared list');

$dueListId = $lists->create((int) $owner['id'], 'Deadline test', '✨', 'violet');
$dueItemId = $lists->addItem($dueListId, (int) $owner['id'], 'Belasting opsturen', 'high', '2025-01-10');
$dueNotifications = new DueTaskNotificationService($push, $lists);
$reminderResult = $dueNotifications->sendPending(new DateTimeImmutable('2025-01-10 12:00:00', new DateTimeZone('Europe/Amsterdam')));
assert_true($reminderResult['reminders'] === 1 && $reminderResult['expired'] === 0, 'due task service sends a reminder twelve hours before the end-of-day deadline');
$reminderPayload = json_decode($requests[5]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true(str_contains($reminderPayload['contents']['en'], 'Nog 12 uur voordat de taak “Belasting opsturen” vervalt.'), 'the twelve-hour notification clearly identifies the task');
assert_true($dueNotifications->sendPending(new DateTimeImmutable('2025-01-10 12:30:00', new DateTimeZone('Europe/Amsterdam')))['reminders'] === 0, 'the twelve-hour reminder is only sent once');
$expiredResult = $dueNotifications->sendPending(new DateTimeImmutable('2025-01-11 00:00:00', new DateTimeZone('Europe/Amsterdam')));
assert_true($expiredResult['expired'] === 1, 'due task service sends a notification when the task expires');
$expiredPayload = json_decode($requests[6]['payload'], true, 32, JSON_THROW_ON_ERROR);
assert_true(str_contains($expiredPayload['contents']['en'], 'De taak “Belasting opsturen” is vervallen.'), 'the expiration notification clearly identifies the expired task');
assert_true($lists->dueNotificationWasSent($dueItemId, 'reminder') && $lists->dueNotificationWasSent($dueItemId, 'expired'), 'sent due notifications are persisted to prevent duplicates');

$notificationPage = render_view('admin/notifications', [
    'oneSignal' => $oneSignal,
    'subscriptions' => $storedSubscriptions,
]);
assert_true(str_contains($notificationPage, 'data-notification-push'), 'the admin test page exposes the OneSignal client hook');
assert_true(str_contains($notificationPage, 'Stuur testmelding'), 'the test page offers manual delivery');
assert_true(str_contains($notificationPage, 'iOS 16.4 of nieuwer'), 'the test page documents the iOS installation requirement');
assert_true(!str_contains($notificationPage, 'os-rest-key-456'), 'the stored OneSignal REST API key is never rendered into the page');

$adminPage = render_view('admin/index', [
    'invitation_sender_name' => $invitationSettings->senderName(),
    'invitation_sender_email' => $invitationSettings->senderEmail(),
    'invitation_message_html' => $invitationSettings->message(),
    'invitation_preview_html' => $invitationSettings->renderEmail($owner, ['id' => $listId, 'title' => 'Vakantie'], 'invitee@example.nl'),
    'invitation_tokens' => InvitationEmailSettings::tokens(),
]);
assert_true(str_contains($adminPage, 'OneSignal-testomgeving'), 'the admin page links to the OneSignal notification test');
assert_true(str_contains($adminPage, 'href="/development/admin/accounts"'), 'the admin page links to the registered account overview');
assert_true(str_contains($adminPage, 'data-rich-editor'), 'the invitation rich-text editor remains available');

$listView = file_get_contents(dirname(__DIR__) . '/app/Views/lists/show.php');
assert_true(str_contains($listView, '>Afgerond</h2>'), 'completed tasks have their own section heading');
assert_true(str_contains($listView, 'data-live-delete'), 'completed tasks expose a dedicated delete action');
assert_true(str_contains($listView, 'data-task-details'), 'task cards open their comments dialog');
assert_true(str_contains($listView, 'data-live-comment'), 'the comments dialog exposes a live comment form');
assert_true(!str_contains($listView, '<small>Verwijder</small>'), 'the oversized completed-task delete label is removed');
$listModal = file_get_contents(dirname(__DIR__) . '/app/Views/layouts/app.php');
assert_true(substr_count($listModal, 'name="color"') === 1, 'the list modal renders its color selector from one reusable loop');
assert_true(str_contains($listModal, "'lavender' => 'Lavendel'"), 'the list modal offers additional pastel colors');
assert_true(str_contains($listModal, 'list_mood_options()'), 'the list modal offers the SVG mood icon collection');
assert_true(str_starts_with(render_list_mood_icon('sparkles'), '<svg class="mood-icon mood-icon--sparkles"'), 'known mood icons render as inline SVG');
assert_true(str_contains(render_list_mood_icon('sparkles'), '<path d="'), 'inline mood icons include their vector path');
assert_true(!str_contains(file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css'), '.mood-icon--sparkles{mask-image'), 'mood icons no longer depend on browser CSS mask support');

$javascript = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
assert_true(str_contains($javascript, 'OneSignal.init'), 'the browser initializes the OneSignal web SDK');
assert_true(str_contains($javascript, 'User.PushSubscription.optIn()'), 'the browser can subscribe through OneSignal');
assert_true(str_contains($javascript, 'User.PushSubscription.optOut()'), 'the browser can unsubscribe through OneSignal');
assert_true(str_contains($javascript, "addEventListener('change'"), 'OneSignal subscription changes are synchronized automatically');
assert_true(str_contains($javascript, 'serviceWorkerPath: serviceWorkerUrl'), 'OneSignal reuses the existing PWA service worker');
assert_true(str_contains($javascript, 'iOS 16.4'), 'iOS users receive the required home-screen installation instructions');
assert_true(str_contains($javascript, 'registerDevice(false)'), 'previously granted devices are synchronized automatically in the background');
assert_true(str_contains($javascript, "state.items.filter((item) => !item.is_completed)"), 'live updates keep open and completed tasks in separate sections');
assert_true(str_contains($javascript, '`${open} open taken`'), 'live updates keep the open task count in the section heading');
assert_true(str_contains($javascript, "[data-live-delete]"), 'live updates submit completed-task deletions asynchronously');
assert_true(str_contains($javascript, "[data-live-comment]"), 'task comments are submitted asynchronously');
assert_true(str_contains($javascript, 'comment.author_name'), 'comment rendering shows the author name');
assert_true(str_contains($javascript, 'const imageItemIds = new Set('), 'live updates remember which tasks have an attached image');
assert_true(str_contains($javascript, "memberDeleteUrl.replace('__MEMBER_ID__'"), 'live member cards keep owner removal actions after synchronization');
assert_true(str_contains($javascript, 'if (isOwner && !member.is_owner)'), 'live updates never expose a removal action for the list owner');
assert_true(str_contains($javascript, 'if (item.has_image) imageItemIds.add(Number(item.id));'), 'live updates preserve known task images across later state refreshes');
assert_true(str_contains($javascript, "thumbnail.addEventListener('error', handleTaskImageError)"), 'task thumbnails retry once after a temporary image loading failure');
assert_true(str_contains($javascript, "liveList.querySelectorAll('.task-thumbnail')"), 'server-rendered task thumbnails also receive image retry handling');
assert_true(str_contains($listPage, 'class="task-thumbnail"'), 'server-rendered task rows include attached image thumbnails');
assert_true(
    str_contains($listPage, '/lists/' . $listId . '/items/' . $storedImageId . '/image'),
    'server-rendered live-state rows show database-backed task images'
);
assert_true(str_contains($listPage, 'width="48" height="48" decoding="async"'), 'task thumbnails reserve stable space and load immediately');

$manifestController = file_get_contents(dirname(__DIR__) . '/app/Controllers/PwaController.php');
assert_true(str_contains($manifestController, 'OneSignalSDK.sw.js'), 'the PWA service worker imports the OneSignal worker');
assert_true(str_contains($manifestController, "'display' => 'standalone'"), 'the web app manifest enables standalone display');
$routes = file_get_contents(dirname(__DIR__) . '/public/index.php');
assert_true(str_contains($routes, "'/admin/accounts'"), 'administrators can open the account overview route');
assert_true(str_contains($routes, "'/notifications/subscribe'"), 'signed-in users can register a OneSignal subscription');
assert_true(str_contains($routes, "'/notifications/unsubscribe'"), 'signed-in users can remove a OneSignal subscription');
assert_true(str_contains($routes, "'/lists/{listId}/items/{itemId}/comments'"), 'task comments have a dedicated signed-in route');
assert_true(str_contains($routes, "'/lists/{id}/accept'"), 'invited users have a dedicated invitation acceptance route');
assert_true(str_contains($routes, "'/lists/{listId}/members/{memberId}/delete'"), 'owners have a dedicated member removal route');
assert_true(str_contains($routes, "'/users/{id}/profile-image'"), 'member profile images have a user-specific route');
$readme = file_get_contents(dirname(__DIR__) . '/README.md');
assert_true(str_contains($readme, 'iOS/iPadOS 16.4'), 'the README documents iOS web-push requirements');

$repositoryText = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if ($file->isFile() && !str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) && !str_contains($path, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
        $repositoryText .= file_get_contents($path);
    }
}
$removedProviderNames = ['fire' . 'base', 'push' . 'er', 'Be' . 'ams'];
foreach ($removedProviderNames as $removedProviderName) {
    assert_true(stripos($repositoryText, $removedProviderName) === false, 'a removed notification provider is absent from application code and documentation');
}

$deploymentWorkflow = file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy.yml');
assert_true(str_contains($deploymentWorkflow, "--exclude='^storage(/|$)'"), 'FTP deployments preserve the remote SQLite database');
$serviceWorker = file_get_contents(dirname(__DIR__) . '/public/sw.js');
assert_true(str_contains($serviceWorker, "request.mode === 'navigate'"), 'the service worker retains offline navigation support');

@unlink($database);
@unlink($database . '-wal');
@unlink($database . '-shm');
echo "All tests passed.\n";
