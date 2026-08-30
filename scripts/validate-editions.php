<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) (getenv('CAPTAINFIN_BUILD_VERSION') ?: '0.3.0-dev'));

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP zip extension is required to validate edition packages.\n");
    exit(2);
}

$definitions = [
    'jellyfin' => [
        'sku' => 'captainfin-jellyfin',
        'required' => 'modules/addons/captainfin/lib/Integrations/Jellyfin/JellyfinClient.php',
        'forbidden' => 'modules/addons/captainfin/lib/Integrations/Emby/EmbyClient.php',
    ],
    'emby' => [
        'sku' => 'captainfin-emby',
        'required' => 'modules/addons/captainfin/lib/Integrations/Emby/EmbyClient.php',
        'forbidden' => 'modules/addons/captainfin/lib/Integrations/Jellyfin/JellyfinClient.php',
    ],
    'suite' => [
        'sku' => 'captainfin-media-suite',
        'required' => 'modules/addons/captainfin/lib/Integrations/Jellyfin/JellyfinClient.php',
        'required2' => 'modules/addons/captainfin/lib/Integrations/Emby/EmbyClient.php',
        'forbidden' => null,
    ],
];

$errors = [];
foreach ($definitions as $edition => $definition) {
    $zipPath = sprintf('%s/dist/%s-%s.zip', $root, $definition['sku'], $version);
    if (!is_file($zipPath)) {
        $errors[] = 'Missing edition package: ' . $zipPath;
        continue;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $errors[] = 'Unable to open edition package: ' . $zipPath;
        continue;
    }

    $manifestRaw = $zip->getFromName('modules/addons/captainfin/edition.json');
    $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
    if (!is_array($manifest) || ($manifest['edition'] ?? null) !== $edition || ($manifest['development'] ?? true) !== false) {
        $errors[] = sprintf('%s package has an invalid production edition manifest.', $edition);
    }

    if ($zip->locateName($definition['required']) === false) {
        $errors[] = sprintf('%s package is missing its required provider adapter.', $edition);
    }

    if (isset($definition['required2']) && $zip->locateName($definition['required2']) === false) {
        $errors[] = sprintf('%s package is missing its second provider adapter.', $edition);
    }

    if ($definition['forbidden'] !== null && $zip->locateName($definition['forbidden']) !== false) {
        $errors[] = sprintf('%s package unexpectedly contains the other commercial provider adapter.', $edition);
    }

    foreach (['tests/', 'vendor/', '.git/', '.env', 'composer.lock', 'phpunit.xml'] as $forbidden) {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === $forbidden || str_starts_with($name, $forbidden) || str_contains($name, '/' . $forbidden)) {
                $errors[] = sprintf('%s package contains forbidden development artifact: %s', $edition, $name);
                break;
            }
        }
    }

    $zip->close();
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, array_unique($errors)) . PHP_EOL);
    exit(1);
}

echo "CAPTAiNFiN Jellyfin, Emby and Media Suite package validation passed.\n";
