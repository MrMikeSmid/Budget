<?php

namespace App\Controllers;

use App\Models\EmailVerification;
use App\Models\User;
use App\Support\View;

final class VerifyController
{
    public static function verify(): void
    {
        $token = $_GET['token'] ?? '';
        $verification = $token !== '' ? EmailVerification::consume($token) : null;

        if (!$verification) {
            View::render('auth/verify-notice', [
                'invalid' => true,
            ], 'layout-guest');
            return;
        }

        User::markVerified((int) $verification['user_id']);

        View::flash('E-mailadres bevestigd! Je kunt nu inloggen.');
        header('Location: ' . View::url('login'));
        exit;
    }
}
