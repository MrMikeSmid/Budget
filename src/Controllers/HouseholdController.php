<?php

namespace App\Controllers;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Invite;
use App\Support\Auth;
use App\Support\View;

final class HouseholdController
{
    public static function index(): void
    {
        $user = Auth::user();
        $householdId = (int) $_SESSION['household_id'];

        View::render('household/index', [
            'household' => Household::find($householdId),
            'members' => HouseholdMember::membersOf($householdId),
            'pendingInvites' => Invite::pendingForHousehold($householdId),
            'otherHouseholds' => array_filter(
                HouseholdMember::householdsFor((int) $user['id']),
                fn ($h) => (int) $h['id'] !== $householdId
            ),
            'currentUser' => $user,
        ]);
    }

    public static function rename(): void
    {
        $name = trim($_POST['name'] ?? '');
        $householdId = (int) $_SESSION['household_id'];

        if ($name === '') {
            View::flash('Vul een naam in.', 'error');
        } else {
            Household::rename($householdId, $name);
            View::flash('Huishouden hernoemd.');
        }

        header('Location: ' . View::url('huishouden'));
        exit;
    }

    public static function removeMember(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $householdId = (int) $_SESSION['household_id'];
        $current = Auth::user();

        if ($userId === (int) $current['id'] && HouseholdMember::count($householdId) <= 1) {
            View::flash('Er moet minstens één lid in het huishouden blijven.', 'error');
        } elseif (!HouseholdMember::isMember($householdId, $userId)) {
            View::flash('Dit lid is niet (meer) onderdeel van dit huishouden.', 'error');
        } else {
            HouseholdMember::remove($householdId, $userId);
            View::flash('Lid verwijderd uit het huishouden.');
        }

        header('Location: ' . View::url('huishouden'));
        exit;
    }

    /**
     * Wisselen tussen huishoudens, voor het (uitzonderlijke) geval dat iemand
     * lid is van meer dan één huishouden. Alleen geldige lidmaatschappen van
     * de huidige gebruiker worden geaccepteerd.
     */
    public static function switchHousehold(): void
    {
        $user = Auth::user();
        $requestedId = (int) ($_POST['household_id'] ?? 0);
        $memberships = HouseholdMember::householdsFor((int) $user['id']);

        $match = array_filter($memberships, fn ($h) => (int) $h['id'] === $requestedId);

        if ($match) {
            $_SESSION['household_id'] = $requestedId;
            View::flash('Huishouden gewisseld.');
        } else {
            View::flash('Ongeldig huishouden.', 'error');
        }

        header('Location: ' . View::url('dashboard'));
        exit;
    }
}
