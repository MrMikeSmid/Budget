<?php

namespace App\Support;

/**
 * Genereert een advies-tekst voor het dashboard: welke nog niet betaalde
 * lasten passen er met het huidige saldo betaald te worden. Kiest zoveel
 * mogelijk lasten (i.p.v. het hoogste bedrag) door de goedkoopste eerst te
 * proberen — dat is optimaal voor "zoveel mogelijk rekeningen betalen".
 * De tekst zelf wordt willekeurig uit een paar varianten gekozen, zodat het
 * bij elk bezoek net iets anders klinkt, alsof een assistent 'm elke keer
 * opnieuw schrijft.
 */
final class PaymentAdvisor
{
    /**
     * @param array<int, array{description: string, budgeted: float}> $unpaidItems
     */
    public static function advise(float $balance, array $unpaidItems): string
    {
        if (empty($unpaidItems)) {
            return self::pick([
                'Goed bezig — alle lasten voor deze periode staan al op "Betaald". Niets meer te doen.',
                'Alle openstaande lasten zijn al afgehandeld. Even achterover leunen dus.',
                'Er staat niets meer open om te betalen — alle lasten zijn al voldaan.',
            ]);
        }

        usort($unpaidItems, static fn (array $a, array $b) => $a['budgeted'] <=> $b['budgeted']);

        $selected = [];
        $remaining = $balance;
        foreach ($unpaidItems as $item) {
            if ((float) $item['budgeted'] <= $remaining) {
                $selected[] = $item;
                $remaining -= (float) $item['budgeted'];
            }
        }

        $total = count($unpaidItems);
        $count = count($selected);
        $names = implode(', ', array_map(static fn (array $i) => $i['description'], $selected));

        if ($count === 0) {
            if ($balance <= 0) {
                return self::pick([
                    sprintf('Het saldo staat op %s, dus zelfs de goedkoopste openstaande last (%s) past er nu niet bij.', View::money($balance), View::money((float) $unpaidItems[0]['budgeted'])),
                    sprintf('Met een saldo van %s is er helaas niets te betalen — wacht tot er weer geld binnenkomt.', View::money($balance)),
                ]);
            }

            return self::pick([
                sprintf('Met %s saldo kun je helaas nog geen van de %d openstaande lasten betalen — de goedkoopste is %s.', View::money($balance), $total, View::money((float) $unpaidItems[0]['budgeted'])),
                sprintf('Nog even geduld: %s saldo is niet genoeg voor de goedkoopste openstaande last (%s).', View::money($balance), View::money((float) $unpaidItems[0]['budgeted'])),
            ]);
        }

        if ($count === $total) {
            return self::pick([
                sprintf('Met %s saldo kun je alle %d openstaande lasten betalen: %s. Daarna houd je nog %s over.', View::money($balance), $total, $names, View::money($remaining)),
                sprintf('Goed nieuws: %s saldo is genoeg om alles te betalen (%s). Blijft %s over.', View::money($balance), $names, View::money($remaining)),
                sprintf('Alle %d openstaande lasten passen binnen je saldo van %s: %s. Restant: %s.', $total, View::money($balance), $names, View::money($remaining)),
            ]);
        }

        return self::pick([
            sprintf('Met %s saldo kun je %d van de %d openstaande lasten betalen: %s. Daarna blijft nog %s over voor de rest.', View::money($balance), $count, $total, $names, View::money($remaining)),
            sprintf('Advies: betaal eerst %s — dat zijn %d van de %d openstaande lasten, passend binnen je saldo van %s. Resteert: %s.', $names, $count, $total, View::money($balance), View::money($remaining)),
            sprintf('Met een saldo van %s kun je het beste %s alvast betalen (%d van de %d lasten). Je houdt dan nog %s over.', View::money($balance), $names, $count, $total, View::money($remaining)),
        ]);
    }

    /**
     * @param string[] $options
     */
    private static function pick(array $options): string
    {
        return $options[array_rand($options)];
    }
}
