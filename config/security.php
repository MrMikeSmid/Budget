<?php
/**
 * Lokale detectieconfiguratie. Kopieer en pas waarden aan voor de eigen
 * organisatie; externe lookups zijn bewust niet aanwezig en standaard uit.
 */
return [
    'allowed_sender_domains' => [],
    'trusted_domains' => [],
    'suspicious_extensions' => ['exe','scr','bat','cmd','com','js','jse','vbs','vbe','ps1','jar','msi','iso','img','lnk','html','htm','zip','rar','7z'],
    'url_shorteners' => ['bit.ly','t.co','tinyurl.com','is.gd','cutt.ly'],
    'maximum_message_bytes' => 10 * 1024 * 1024,
    'maximum_attachment_bytes' => 25 * 1024 * 1024,
    'maximum_scan_limit' => 100,
    'risk_weights' => ['spf_fail'=>12,'dkim_fail'=>12,'dmarc_fail'=>18,'reply_to_mismatch'=>15,'dangerous_attachment'=>28],
];
