#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\DueTaskNotificationService;

require dirname(__DIR__) . '/app/bootstrap.php';

$result = (new DueTaskNotificationService())->sendPending();
printf(
    "Vervaldatummeldingen: %d herinnering(en), %d verlopen, %d mislukt.\n",
    $result['reminders'],
    $result['expired'],
    $result['failed'],
);

exit($result['failed'] > 0 ? 1 : 0);
