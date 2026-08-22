<?php

namespace App\Support\BankImport;

use RuntimeException;

/**
 * Gegooid met een Nederlandse, gebruiker-vriendelijke boodschap zodra een
 * bestand niet als het gekozen bank/formaat te lezen is — de controller
 * toont $message rechtstreeks als flash-melding.
 */
final class BankImportParseException extends RuntimeException
{
}
