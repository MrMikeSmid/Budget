<?php

namespace App\Controllers;

use App\Models\IconMapping;
use App\Support\View;

final class IconMappingController
{
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    public static function index(): void
    {
        View::render('icon-mappings/index', [
            'mappings' => IconMapping::all(),
        ]);
    }

    public static function save(): void
    {
        $description = trim($_POST['description'] ?? '');
        $file = $_FILES['icon'] ?? null;

        if ($description === '' || !$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            View::flash('Vul een omschrijving in en kies een afbeelding.', 'error');
            header('Location: ' . View::url('iconen'));
            exit;
        }

        $stored = self::storeUpload($file);
        if ($stored === null) {
            View::flash('Dat bestand kon niet gebruikt worden — kies een PNG, JPG, GIF, WEBP of SVG tot 2 MB.', 'error');
            header('Location: ' . View::url('iconen'));
            exit;
        }

        $existing = self::findByDescription($description);
        IconMapping::upsert($description, $stored);
        if ($existing !== null && $existing['icon_path'] !== $stored) {
            self::deleteFile($existing['icon_path']);
        }

        View::flash('Icoon gekoppeld aan "' . $description . '".');
        header('Location: ' . View::url('iconen'));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $mapping = IconMapping::find($id);
        IconMapping::delete($id);
        if ($mapping !== null) {
            self::deleteFile($mapping['icon_path']);
        }
        View::flash('Koppeling verwijderd.');

        header('Location: ' . View::url('iconen'));
        exit;
    }

    /**
     * Levert de geüploade afbeelding voor een koppeling. storage/ is niet
     * direct via de webserver bereikbaar (zie storage/.htaccess), dus dit
     * leest het bestand zelf uit en stuurt het met het juiste Content-Type
     * door. App-breed (net als de koppelingen zelf): elk ingelogd lid van
     * elk huishouden mag een icoon bekijken, alleen het koppelen/verwijderen
     * is voorbehouden aan admins (zie de adminOnly()-routes in index.php).
     */
    public static function image(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $mapping = IconMapping::find($id);
        $path = $mapping !== null ? IconMapping::absolutePath($mapping['icon_path']) : null;

        if ($path === null) {
            http_response_code(404);
            exit;
        }

        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private static function findByDescription(string $description): ?array
    {
        foreach (IconMapping::all() as $row) {
            if (mb_strtolower($row['description']) === mb_strtolower($description)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Valideert en verplaatst een upload naar de (app-brede) iconenmap, met
     * een willekeurige bestandsnaam (voorkomt overschrijven en het raden van
     * andermans bestandsnamen). Geeft de opgeslagen bestandsnaam terug, of
     * null bij een ongeldig bestand.
     */
    private static function storeUpload(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            return null;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        if (!self::looksLikeValidImage($file['tmp_name'], $extension)) {
            return null;
        }

        $dir = IconMapping::iconsDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            return null;
        }

        return $filename;
    }

    private static function looksLikeValidImage(string $tmpPath, string $extension): bool
    {
        if ($extension === 'svg') {
            $content = file_get_contents($tmpPath, false, null, 0, 65536);

            return $content !== false
                && str_contains($content, '<svg')
                && !str_contains(strtolower($content), '<script');
        }

        return @getimagesize($tmpPath) !== false;
    }

    private static function deleteFile(string $filename): void
    {
        $path = IconMapping::absolutePath($filename);
        if ($path !== null) {
            @unlink($path);
        }
    }
}
