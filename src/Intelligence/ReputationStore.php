<?php

declare(strict_types=1);

namespace McpEmail\Intelligence;

use RuntimeException;

/** Concurrency-safe local sender history stored as JSON; no message content is retained. */
final class ReputationStore
{
    public function __construct(private readonly string $path = __DIR__ . '/../../data/reputation.json') {}

    /** @return array<string,mixed> */
    public function get(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $data = $this->read();
        $matches = array_values(array_filter($data['senders'] ?? [], static fn (array $row): bool => ($row['domain'] ?? '') === $domain));
        return ['domain' => $domain, 'known' => $matches !== [], 'senders' => $matches, 'totals' => $this->totals($matches)];
    }

    public function record(string $sender, string $domain, string $category): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Kan reputatiemap niet maken.');
        $handle = fopen($this->path, 'c+');
        if ($handle === false) throw new RuntimeException('Kan reputatiebestand niet openen.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('Kan reputatiebestand niet vergrendelen.');
            $raw = stream_get_contents($handle);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : ['senders' => []];
            if (!is_array($data)) $data = ['senders' => []];
            $key = strtolower($sender);
            $now = gmdate('c');
            $row = $data['senders'][$key] ?? ['sender' => $sender, 'domain' => $domain, 'first_seen' => $now, 'last_seen' => $now,
                'times_seen' => 0, 'safe_count' => 0, 'spam_count' => 0, 'newsletter_count' => 0, 'phishing_count' => 0, 'last_category' => 'unknown'];
            $row['last_seen'] = $now; $row['times_seen']++; $row['last_category'] = $category;
            if (isset($row[$category . '_count'])) $row[$category . '_count']++;
            elseif (!in_array($category, ['spam', 'phishing', 'scam'], true)) $row['safe_count']++;
            $data['senders'][$key] = $row;
            rewind($handle); ftruncate($handle, 0);
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{"senders":{}}'); fflush($handle);
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    /** @return array<string,mixed> */
    private function read(): array
    {
        if (!is_file($this->path)) return ['senders' => []];
        $data = json_decode((string) file_get_contents($this->path), true);
        return is_array($data) ? $data : ['senders' => []];
    }

    /** @param list<array<string,mixed>> $rows */
    private function totals(array $rows): array
    {
        $totals = ['times_seen' => 0, 'safe_count' => 0, 'spam_count' => 0, 'newsletter_count' => 0, 'phishing_count' => 0];
        foreach ($rows as $row) foreach ($totals as $key => $_) $totals[$key] += (int) ($row[$key] ?? 0);
        return $totals;
    }
}
