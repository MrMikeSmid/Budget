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

$users = new User();
$owner = $users->findOrCreate('owner@example.nl');
$member = $users->findOrCreate('member@example.nl');
assert_true($owner['name'] === 'Owner', 'account is created from an e-mail address');
assert_true($users->findOrCreate('OWNER@example.nl')['id'] === $owner['id'], 'e-mail addresses are case-insensitively unique');

$lists = new TodoList();
$listId = $lists->create((int) $owner['id'], 'Vakantie', '✈️', 'coral');
$lists->share($listId, (int) $owner['id'], (int) $member['id']);
assert_true(count($lists->forUser((int) $member['id'])) === 1, 'shared lists are visible to members');

$memberLists = $lists->forUser((int) $member['id']);
$homeWithLists = render_lists_index($member, $memberLists);
assert_true(!str_contains($homeWithLists, 'class="hero-card"'), 'the introduction card is hidden when the user has a list');

$emptyHome = render_lists_index($member, []);
assert_true(str_contains($emptyHome, 'class="hero-card"'), 'the introduction card is shown when the user has no lists');
assert_true($lists->findAccessible($listId, (int) $member['id']) !== null, 'members can access a shared list');

$lists->addItem($listId, (int) $owner['id'], 'Treinkaartjes boeken');
$item = $lists->items($listId)[0];
$openState = $lists->liveState($listId);
assert_true($openState['stats']['open'] === 1, 'live state reports open items');
$lists->toggleItem((int) $item['id'], $listId, (int) $member['id']);
$item = $lists->items($listId)[0];
assert_true((int) $item['is_completed'] === 1, 'a member can complete an item');
assert_true($item['completer_name'] === 'Member', 'the completing member is recorded');
$completedState = $lists->liveState($listId);
assert_true($completedState['stats']['percent'] === 100, 'live state reports completion progress');
assert_true($completedState['revision'] !== $openState['revision'], 'live state revision changes after an update');
assert_true(count($completedState['members']) === 2, 'live state includes all list members');

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

$stylesheet = file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');
assert_true(
    preg_match('/\.bottom-nav\{[^}]*left:50%;[^}]*transform:translateX\(-50%\)/', $stylesheet) === 1,
    'bottom navigation is centered within the viewport'
);
assert_true(
    preg_match('/@media\(max-width:899px\)\{\s*\.bottom-nav \.desktop-only\{display:none\}/', $stylesheet) === 1,
    'desktop-only navigation items stay hidden in the mobile bottom bar'
);
assert_true(
    str_contains($stylesheet, '@media(min-width:900px){.settings-page>.install-card{display:none}'),
    'the profile install notification stays hidden on desktop'
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

assert_true(str_contains($manifestController, 'public function icon(string $name)'), 'PWA icons are generated by a text-only endpoint');
assert_true(str_contains($manifestController, "header('Content-Type: image/png')"), 'the icon endpoint returns PNG images');
assert_true(!str_contains($manifestController, "asset('icons/app-icon"), 'the manifest does not depend on committed binary icons');

@unlink($database); @unlink($database . '-wal'); @unlink($database . '-shm');
echo "All tests passed.\n";
