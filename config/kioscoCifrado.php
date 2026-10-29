<?php
// Cifrado para datos del kiosco que no deben ir en claro (contraseñas de nivel de Delvis).
// AES-256-CBC + HMAC-SHA256 (cifrar y luego firmar). Las llaves salen de KIOSCO_TOKEN, el token que
// ya tiene cada candado: así el candado lo descifra y quien solo escucha la red (http) no.
// Si KIOSCO_TOKEN cambia, lo guardado ya no se puede leer: hay que volver a escribir las contraseñas.
require_once __DIR__ . '/env.php';

function kioscoLlaves(string $uso): array
{
    $tok = (string)env('KIOSCO_TOKEN', '');
    if (strlen($tok) < 16) throw new RuntimeException('Falta KIOSCO_TOKEN en .env.');
    return [hash_hmac('sha256', $uso . '-cifrado', $tok, true), hash_hmac('sha256', $uso . '-firma', $tok, true)];
}

function kioscoCifrar(string $plano, string $uso): string
{
    [$ke, $km] = kioscoLlaves($uso);
    $iv = random_bytes(16);
    $ct = openssl_encrypt($plano, 'aes-256-cbc', $ke, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ct . hash_hmac('sha256', $iv . $ct, $km, true));
}

function kioscoDescifrar(string $b64, string $uso): ?string
{
    [$ke, $km] = kioscoLlaves($uso);
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 16 + 16 + 32) return null;
    $iv = substr($raw, 0, 16);
    $ct = substr($raw, 16, -32);
    if (!hash_equals(hash_hmac('sha256', $iv . $ct, $km, true), substr($raw, -32))) return null;
    $p = openssl_decrypt($ct, 'aes-256-cbc', $ke, OPENSSL_RAW_DATA, $iv);
    return $p === false ? null : $p;
}
