<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TodoList;
use DateTimeImmutable;
use DateTimeZone;

final class DueTaskNotificationService
{
    public function __construct(
        private readonly ?OneSignalNotificationService $push = null,
        private readonly ?TodoList $lists = null,
    ) {
    }

    /** @return array{reminders: int, expired: int, failed: int} */
    public function sendPending(?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone((string) config('timezone', 'Europe/Amsterdam'));
        $now = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $lists = $this->lists ?? new TodoList();
        $result = ['reminders' => 0, 'expired' => 0, 'failed' => 0];

        foreach ($lists->openItemsWithDueDate() as $item) {
            $deadline = (new DateTimeImmutable((string) $item['due_date'], $timezone))->modify('+1 day');
            $notificationType = $deadline <= $now ? 'expired' : ($deadline->modify('-12 hours') <= $now ? 'reminder' : null);
            if ($notificationType === null || !$lists->claimDueNotification((int) $item['id'], $notificationType)) {
                continue;
            }

            $message = $notificationType === 'reminder'
                ? sprintf('Nog 12 uur voordat de taak “%s” vervalt.', $item['title'])
                : sprintf('De taak “%s” is vervallen.', $item['title']);
            $sent = ($this->push ?? new OneSignalNotificationService())->sendUsers(
                $lists->participantIds((int) $item['list_id']),
                (string) $item['list_title'],
                $message,
                '/lists/' . (int) $item['list_id'],
            );

            if (!$sent) {
                $lists->releaseDueNotification((int) $item['id'], $notificationType);
                $result['failed']++;
                continue;
            }

            $result[$notificationType === 'reminder' ? 'reminders' : 'expired']++;
        }

        return $result;
    }
}
