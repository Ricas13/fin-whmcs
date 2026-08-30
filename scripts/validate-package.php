<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'modules/addons/captainfin/captainfin.php',
    'modules/addons/captainfin/edition.json',
    'modules/addons/captainfin/lib/autoload.php',
    'modules/servers/captainfin/captainfin.php',
];

$errors = [];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) {
        $errors[] = 'Missing required runtime file: ' . $path;
    }
}

$manifestRaw = @file_get_contents($root . '/modules/addons/captainfin/edition.json');
$manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
if (!is_array($manifest) || !in_array($manifest['edition'] ?? null, ['jellyfin', 'emby', 'suite'], true)) {
    $errors[] = 'Invalid CAPTAiNFiN edition manifest.';
}

$forbiddenRuntimeNames = ['.env', '.git', 'vendor', 'tests', 'phpunit.xml', 'composer.lock'];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/modules', FilesystemIterator::SKIP_DOTS)) as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    foreach ($forbiddenRuntimeNames as $name) {
        if (preg_match('#(^|/)' . preg_quote($name, '#') . '(/|$)#', $relative)) {
            $errors[] = 'Forbidden development/runtime artifact under modules/: ' . $relative;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, array_unique($errors)) . PHP_EOL);
    exit(1);
}

echo "CAPTAiNFiN Marketplace package layout validation passed.\n";
