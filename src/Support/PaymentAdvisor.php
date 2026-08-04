<?php

namespace App\Support;

/**
 * Genereert advies-teksten voor het dashboard: welke nog niet betaalde
 * lasten passen er met het huidige saldo betaald te worden, en — als er nog
 * inkomsten verwacht worden — wat er daarbovenop mogelijk wordt zodra die
 * binnenkomen. Kiest steeds zoveel mogelijk lasten (i.p.v. het hoogste
 * bedrag) door de goedkoopste eerst te proberen — dat is optimaal voor
 * "zoveel mogelijk rekeningen betalen". De teksten zelf worden willekeurig
 * uit een flink aantal varianten gekozen, zodat het ook na een paar keer
 * verversen niet eentonig wordt, alsof een assistent 'm elke keer opnieuw
 * schrijft.
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
                'Niets aan de hand hier: elke last is al betaald.',
                'Alles is voldaan — geen actie nodig voor deze periode.',
            ]);
        }

        usort($unpaidItems, static fn (array $a, array $b) => $a['budgeted'] <=> $b['budgeted']);

        $selected = self::affordablePrefix($unpaidItems, $balance);
        $remaining = $balance - self::sum($selected);

        $total = count($unpaidItems);
        $count = count($selected);
        $names = implode(', ', array_map(static fn (array $i) => $i['description'], $selected));

        if ($count === 0) {
            $cheapest = View::money((float) $unpaidItems[0]['budgeted']);

            if ($balance <= 0) {
                return self::pick([
                    sprintf('Het saldo staat op %s, dus zelfs de goedkoopste openstaande last (%s) past er nu niet bij.', View::money($balance), $cheapest),
                    sprintf('Met een saldo van %s is er helaas niets te betalen — wacht tot er weer geld binnenkomt.', View::money($balance)),
                    sprintf('Saldo staat op %s: beter nu niets betalen en eerst wachten op inkomsten.', View::money($balance)),
                ]);
            }

            return self::pick([
                sprintf('Met %s saldo kun je helaas nog geen van de %d openstaande lasten betalen — de goedkoopste is %s.', View::money($balance), $total, $cheapest),
                sprintf('Nog even geduld: %s saldo is niet genoeg voor de goedkoopste openstaande last (%s).', View::money($balance), $cheapest),
                sprintf('Met %s op de rekening kun je nog niets van de %d openstaande lasten betalen; %s is de laagste drempel.', View::money($balance), $total, $cheapest),
            ]);
        }

        if ($count === $total) {
            return self::pick([
                sprintf('Met %s saldo kun je alle %d openstaande lasten betalen: %s. Daarna houd je nog %s over.', View::money($balance), $total, $names, View::money($remaining)),
                sprintf('Goed nieuws: %s saldo is genoeg om alles te betalen (%s). Blijft %s over.', View::money($balance), $names, View::money($remaining)),
                sprintf('Alle %d openstaande lasten passen binnen je saldo van %s: %s. Restant: %s.', $total, View::money($balance), $names, View::money($remaining)),
                sprintf('Je kunt in één keer alles afhandelen: %s past ruim binnen je saldo van %s. Daarna resteert %s.', $names, View::money($balance), View::money($remaining)),
            ]);
        }

        return self::pick([
            sprintf('Met %s saldo kun je %d van de %d openstaande lasten betalen: %s. Daarna blijft nog %s over voor de rest.', View::money($balance), $count, $total, $names, View::money($remaining)),
            sprintf('Advies: betaal eerst %s — dat zijn %d van de %d openstaande lasten, passend binnen je saldo van %s. Resteert: %s.', $names, $count, $total, View::money($balance), View::money($remaining)),
            sprintf('Met een saldo van %s kun je het beste %s alvast betalen (%d van de %d lasten). Je houdt dan nog %s over.', View::money($balance), $names, $count, $total, View::money($remaining)),
            sprintf('Begin met %s (%d van de %d openstaande lasten) — dat past binnen je saldo van %s en laat %s over.', $names, $count, $total, View::money($balance), View::money($remaining)),
        ]);
    }

    /**
     * Advies over wat er, bovenop het huidige saldo, betaald kan worden
     * zodra nog te ontvangen inkomsten daadwerkelijk binnenkomen. Geeft
     * bewust geen tekst terug als er niets te verwachten valt, of als er
     * toch al niets meer te betalen is — dan heeft een projectie geen zin.
     *
     * @param array<int, array{description: string, budgeted: float}> $unpaidCosts
     * @param array<int, array{description: string, budgeted: float}> $unreceivedIncome
     */
    public static function adviseExpectedIncome(float $balance, array $unpaidCosts, array $unreceivedIncome): ?string
    {
        if (empty($unreceivedIncome) || empty($unpaidCosts)) {
            return null;
        }

        usort($unpaidCosts, static fn (array $a, array $b) => $a['budgeted'] <=> $b['budgeted']);

        $expectedTotal = self::sum($unreceivedIncome);
        $projectedBalance = $balance + $expectedTotal;
        $incomeNames = implode(', ', array_map(static fn (array $i) => $i['description'], $unreceivedIncome));
        $expected = View::money($expectedTotal);

        $currentSelected = self::affordablePrefix($unpaidCosts, $balance);
        $projectedSelected = self::affordablePrefix($unpaidCosts, $projectedBalance);
        $newlyAffordable = array_slice($projectedSelected, count($currentSelected));

        $total = count($unpaidCosts);
        $projectedRemaining = $projectedBalance - self::sum($projectedSelected);

        if (empty($newlyAffordable)) {
            if (count($projectedSelected) === $total) {
                return self::pick([
                    sprintf('Je saldo dekt toevallig al alle openstaande lasten — de nog te ontvangen %s (%s) komt dus straks bovenop je reserve.', $incomeNames, $expected),
                    sprintf('Ook zonder %s (nog te ontvangen: %s) kun je al alles betalen — dat bedrag is dus pure winst zodra het binnenkomt.', $incomeNames, $expected),
                ]);
            }

            $nextItem = $unpaidCosts[count($currentSelected)];

            return self::pick([
                sprintf('Zelfs als %s binnenkomt (%s), verandert er nog niets — dat dekt de eerstvolgende last (%s, %s) net niet.', $incomeNames, $expected, $nextItem['description'], View::money((float) $nextItem['budgeted'])),
                sprintf('%s (%s) is helaas nog niet genoeg voor de eerstvolgende openstaande last: %s (%s).', $incomeNames, $expected, $nextItem['description'], View::money((float) $nextItem['budgeted'])),
            ]);
        }

        $newNames = implode(', ', array_map(static fn (array $i) => $i['description'], $newlyAffordable));

        if (count($projectedSelected) === $total) {
            return self::pick([
                sprintf('Zodra %s binnenkomt (%s), kun je ook de rest betalen: %s. Dan is alles voldaan en houd je %s over.', $incomeNames, $expected, $newNames, View::money($projectedRemaining)),
                sprintf('Komt %s (%s) binnen, dan kun je in één keer ook %s afronden — daarna is alles betaald, met %s over.', $incomeNames, $expected, $newNames, View::money($projectedRemaining)),
                sprintf('Met %s (%s) erbij kun je zelfs alles afhandelen, inclusief %s. Restant daarna: %s.', $incomeNames, $expected, $newNames, View::money($projectedRemaining)),
            ]);
        }

        return self::pick([
            sprintf('Zodra %s binnenkomt (%s), kun je daarnaast ook %s betalen. Er blijft dan nog %s over voor de rest.', $incomeNames, $expected, $newNames, View::money($projectedRemaining)),
            sprintf('Komt %s (%s) binnen, dan schuift %s ook binnen bereik. Resteert daarna nog %s.', $incomeNames, $expected, $newNames, View::money($projectedRemaining)),
            sprintf('Met %s (%s) erbij kun je bovendien %s betalen — de rest blijft dan nog %s open staan.', $incomeNames, $expected, $newNames, View::money($projectedRemaining)),
        ]);
    }

    /**
     * @param array<int, array{budgeted: float}> $sortedItems oplopend gesorteerd op budgeted
     * @return array<int, array{description: string, budgeted: float}>
     */
    private static function affordablePrefix(array $sortedItems, float $balance): array
    {
        $selected = [];
        $remaining = $balance;
        foreach ($sortedItems as $item) {
            if ((float) $item['budgeted'] > $remaining) {
                break; // oplopend gesorteerd: verderop past niets meer
            }
            $selected[] = $item;
            $remaining -= (float) $item['budgeted'];
        }

        return $selected;
    }

    /**
     * @param array<int, array{budgeted: float}> $items
     */
    private static function sum(array $items): float
    {
        return array_sum(array_map(static fn (array $i) => (float) $i['budgeted'], $items));
    }

    /**
     * @param string[] $options
     */
    private static function pick(array $options): string
    {
        return $options[array_rand($options)];
    }
}
