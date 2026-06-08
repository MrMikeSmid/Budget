<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

final class SettingsController extends Controller
{
    private const MAX_PROFILE_IMAGE_SIZE = 5 * 1024 * 1024;

    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function show(): void
    {
        $user = $this->auth();
        view('settings/index', ['title' => 'Instellingen', 'user' => $user]);
    }

    public function profile(): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            flash('error', 'Vul een geldige naam in.');
            redirect('/settings');
        }

        $newImage = $this->storeProfileImage($_FILES['profile_image'] ?? null);
        if ($newImage === false) {
            redirect('/settings');
        }

        $users = new User();
        $users->updateProfile((int) $user['id'], $name);
        if (is_string($newImage)) {
            $users->setProfileImage((int) $user['id'], $newImage);
            $this->deleteProfileImage($user['profile_image'] ?? null);
        }

        flash('success', 'Je profiel is bijgewerkt.');
        redirect('/settings');
    }

    public function profileImage(): void
    {
        $user = $this->auth();
        $filename = basename((string) ($user['profile_image'] ?? ''));
        $path = $this->profileImageDirectory() . '/' . $filename;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentTypes = array_flip(self::IMAGE_TYPES);

        if ($filename === '' || !isset($contentTypes[$extension]) || !is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $contentTypes[$extension]);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    public function password(): void
    {
        $user = $this->auth(); $this->verifyCsrf();
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (mb_strlen($password) < 8) { flash('error', 'Kies een wachtwoord van minimaal 8 tekens.'); redirect('/settings'); }
        if ($password !== $confirmation) { flash('error', 'De wachtwoorden zijn niet hetzelfde.'); redirect('/settings'); }
        (new User())->setPassword((int) $user['id'], $password);
        flash('success', 'Mooi! Je account is nu beveiligd met een wachtwoord.'); redirect('/settings');
    }




    private function storeProfileImage(?array $upload): string|false|null
    {
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'De afbeelding kon niet worden geüpload. Probeer het opnieuw.');
            return false;
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_PROFILE_IMAGE_SIZE) {
            flash('error', 'Kies een afbeelding van maximaal 5 MB.');
            return false;
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mimeType) || !isset(self::IMAGE_TYPES[$mimeType]) || @getimagesize($temporaryPath) === false) {
            flash('error', 'Gebruik een JPG-, PNG-, WebP- of GIF-afbeelding.');
            return false;
        }

        $directory = $this->profileImageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            flash('error', 'De afbeelding kon niet worden opgeslagen.');
            return false;
        }

        $filename = bin2hex(random_bytes(20)) . '.' . self::IMAGE_TYPES[$mimeType];
        if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
            flash('error', 'De afbeelding kon niet worden opgeslagen.');
            return false;
        }

        return $filename;
    }

    private function deleteProfileImage(mixed $filename): void
    {
        if (!is_string($filename) || $filename === '') {
            return;
        }
        $path = $this->profileImageDirectory() . '/' . basename($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function profileImageDirectory(): string
    {
        return dirname(__DIR__, 2) . '/storage/profile-images';
    }
}
