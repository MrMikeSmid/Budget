<?php

namespace App\Support;

/**
 * DKIM-ondertekening (RSA-SHA256, relaxed/relaxed-canonicalisatie per RFC
 * 6376) voor uitgaande mail. Samen met een SPF/DMARC-record op het domein
 * zelf (die de gebruiker via zijn hostingpaneel moet instellen — dat kan
 * niet vanuit de app) is dit de grootste concrete verbetering tegen
 * spam-plaatsing die vanuit de code te doen is.
 */
final class DkimSigner
{
    /**
     * @param array<string,string> $headers Header-naam => waarde, in de volgorde waarin ze in de mail staan.
     * @return string|null De complete "DKIM-Signature: ..."-regel, of null als ondertekenen niet lukte
     *                      (bijv. een ongeldige sleutel) — de mail wordt dan gewoon zonder DKIM verstuurd.
     */
    public static function sign(array $headers, string $body, string $domain, string $selector, string $privateKeyPem): ?string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            return null;
        }

        $signedHeaderNames = array_keys($headers);
        $bodyHash = base64_encode(hash('sha256', self::canonicalizeBody($body), true));

        $dkimTag = 'v=1; a=rsa-sha256; c=relaxed/relaxed; d=' . $domain . '; s=' . $selector
            . '; t=' . time() . '; h=' . implode(':', $signedHeaderNames) . '; bh=' . $bodyHash . '; b=';

        $signedData = '';
        foreach ($headers as $name => $value) {
            $signedData .= self::canonicalizeHeader($name, $value) . "\r\n";
        }
        // De DKIM-Signature-header zelf, met b= nog leeg en zonder afsluitende
        // CRLF — dit is de laatste regel van de te ondertekenen data.
        $signedData .= self::canonicalizeHeader('DKIM-Signature', $dkimTag);

        $signature = '';
        if (!openssl_sign($signedData, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return 'DKIM-Signature: ' . $dkimTag . base64_encode($signature);
    }

    /**
     * Genereert een nieuw 2048-bit RSA-sleutelpaar. Geeft private key (PEM,
     * om op te slaan) en de publieke sleutel terug in de vorm die in een
     * DNS TXT-record hoort (kale base64, zonder PEM-headers/regeleinden).
     *
     * @return array{private_key: string, public_key_dns: string}|null
     */
    public static function generateKeyPair(): ?array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            return null;
        }

        $exported = '';
        if (!openssl_pkey_export($resource, $exported)) {
            return null;
        }

        $publicKeyDns = self::publicKeyDnsFromPrivateKey($exported);
        if ($publicKeyDns === null) {
            return null;
        }

        return ['private_key' => $exported, 'public_key_dns' => $publicKeyDns];
    }

    /**
     * Leidt de publieke sleutel (in DNS TXT-vorm) af van een opgeslagen
     * private key — zodat het admin-paneel de DNS-record kan tonen zonder
     * de publieke sleutel apart te hoeven bewaren.
     */
    public static function publicKeyDnsFromPrivateKey(string $privateKeyPem): ?string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            return null;
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || empty($details['key'])) {
            return null;
        }

        return preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $details['key']);
    }

    private static function canonicalizeHeader(string $name, string $value): string
    {
        $name = strtolower(trim($name));
        $value = preg_replace('/\s+/', ' ', trim($value));

        return $name . ':' . $value;
    }

    private static function canonicalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], ["\n", "\n"], $body);
        $lines = explode("\n", $body);

        foreach ($lines as &$line) {
            $line = rtrim($line, " \t");
            $line = preg_replace('/[ \t]+/', ' ', $line);
        }
        unset($line);

        while (count($lines) > 1 && end($lines) === '') {
            array_pop($lines);
        }

        $canon = implode("\r\n", $lines);

        return $canon === '' ? "\r\n" : $canon . "\r\n";
    }
}
