<?php

declare(strict_types=1);

namespace McpEmail\Intelligence;

/** Scores attachment metadata without downloading or executing attachment contents. */
final class AttachmentAnalyzer
{
    private const DANGEROUS = ['exe', 'com', 'bat', 'cmd', 'scr', 'js', 'jse', 'vbs', 'vbe', 'jar', 'msi', 'ps1', 'hta', 'lnk', 'iso', 'img'];
    private const SUSPICIOUS = ['zip', 'rar', '7z', 'docm', 'xlsm', 'pptm', 'html', 'htm'];

    /** @param list<array<string,mixed>> $attachments @return list<array<string,mixed>> */
    public function analyze(array $attachments): array
    {
        return array_map(function (array $item): array {
            $filename = (string) ($item['filename'] ?? '(zonder naam)');
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $risk = in_array($extension, self::DANGEROUS, true) ? 'dangerous'
                : (in_array($extension, self::SUSPICIOUS, true) ? 'suspicious' : 'low');
            if (preg_match('/\.(?:pdf|docx?|xlsx?)\.(?:exe|scr|js)$/i', $filename)) $risk = 'dangerous';
            return ['filename' => $filename, 'extension' => $extension, 'mime_type' => (string) ($item['contentType'] ?? 'application/octet-stream'),
                'size' => (int) ($item['size'] ?? 0), 'content_id'=>$item['contentId']??null,'inline'=>(bool)($item['inline']??false),
                'part_number'=>$item['partNumber']??null,'sha256'=>null,'risk' => $risk,
                'safety_classification'=>match($risk){'dangerous'=>'hoog risico','suspicious'=>'mogelijk riskant','low'=>'waarschijnlijk veilig',default=>'onbekend'}];
        }, $attachments);
    }
}
