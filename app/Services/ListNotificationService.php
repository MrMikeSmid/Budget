<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TodoList;

final class ListNotificationService
{
    public function __construct(
        private readonly ?OneSignalNotificationService $push = null,
        private readonly ?TodoList $lists = null,
    ) {
    }

    public function taskCreated(array $list, array $actor, string $taskTitle): bool
    {
        return $this->send(
            $list,
            $actor,
            sprintf('%s heeft “%s” toegevoegd.', $actor['name'], $taskTitle),
        );
    }

    public function taskCompleted(array $list, array $actor, string $taskTitle): bool
    {
        return $this->send(
            $list,
            $actor,
            sprintf('%s heeft “%s” afgerond.', $actor['name'], $taskTitle),
        );
    }

    public function taskChanged(array $list, array $actor, string $taskTitle): bool
    {
        return $this->send(
            $list,
            $actor,
            sprintf('%s heeft “%s” gewijzigd en weer geopend.', $actor['name'], $taskTitle),
        );
    }

    private function send(array $list, array $actor, string $message): bool
    {
        $listId = (int) $list['id'];
        $recipientIds = ($this->lists ?? new TodoList())->participantIdsExcept($listId, (int) $actor['id']);
        if ($recipientIds === []) {
            return true;
        }

        return ($this->push ?? new OneSignalNotificationService())->sendUsers(
            $recipientIds,
            (string) $list['title'],
            $message,
            '/lists/' . $listId,
        );
    }
}
