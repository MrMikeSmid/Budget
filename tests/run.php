<?php

declare(strict_types=1);

$database = sys_get_temp_dir() . '/samen-test-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('SAMEN_DATABASE=' . $database);
$_SERVER['SCRIPT_NAME'] = '/developement/public/index.php';
$_SERVER['REQUEST_URI'] = '/developement/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/app/bootstrap.php';


use App\Models\TodoList;
use App\Models\User;

function assert_true(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
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
assert_true($lists->findAccessible($listId, (int) $member['id']) !== null, 'members can access a shared list');

$lists->addItem($listId, (int) $owner['id'], 'Treinkaartjes boeken');
$item = $lists->items($listId)[0];
$lists->toggleItem((int) $item['id'], $listId, (int) $member['id']);
$item = $lists->items($listId)[0];
assert_true((int) $item['is_completed'] === 1, 'a member can complete an item');
assert_true($item['completer_name'] === 'Member', 'the completing member is recorded');

$users->setPassword((int) $owner['id'], 'een-veilig-wachtwoord');
$secured = $users->find((int) $owner['id']);
assert_true(password_verify('een-veilig-wachtwoord', $secured['password_hash']), 'passwords are securely hashed');
assert_true(base_path() === '/developement', 'subdirectory base path is detected');

@unlink($database); @unlink($database . '-wal'); @unlink($database . '-shm');
echo "All tests passed.\n";
