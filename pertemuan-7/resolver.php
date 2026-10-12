<?php
/**
 * Pustaka resolver — mengubah nama logis service menjadi alamat jaringan.
 * Dibaca dari services.json (model file konfigurasi, sesuai materi P07).
 */

/** @return string|null base URL service, atau null bila nama tidak terdaftar */
function resolveService(string $name): ?string
{
    $json = @file_get_contents(__DIR__ . '/services.json');
    if ($json === false) return null;
    $services = json_decode($json, true);
    if (!is_array($services)) return null;
    $url = $services[$name] ?? null;
    return is_string($url) ? $url : null;
}

/** @return array semua binding nama => base URL */
function allServices(): array
{
    $json = @file_get_contents(__DIR__ . '/services.json');
    $services = json_decode($json ?: '[]', true);
    return is_array($services) ? $services : [];
}
