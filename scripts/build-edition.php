<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$edition = mb_strtolower(trim((string) ($argv[1] ?? '')), 'UTF-8');
$version = trim((string) (getenv('CAPTAINFIN_BUILD_VERSION') ?: '0.3.0-dev'));

$valid = ['jellyfin', 'emby', 'suite'];
if (!in_array($edition, $valid, true)) {
    fwrite(STDERR, "Usage: php scripts/build-edition.php jellyfin|emby|suite\n");
    exit(2);
}

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP zip extension is required to build CAPTAiNFiN edition packages.\n");
    exit(2);
}

$sku = match ($edition) {
    'jellyfin' => 'captainfin-jellyfin',
    'emby' => 'captainfin-emby',
    default => 'captainfin-media-suite',
};

$dist = $root . '/dist';
if (!is_dir($dist) && !mkdir($dist, 0775, true) && !is_dir($dist)) {
    throw new RuntimeException('Unable to create dist directory.');
}

$zipPath = sprintf('%s/%s-%s.zip', $dist, $sku, $version);
@unlink($zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create edition ZIP: ' . $zipPath);
}

$modulesRoot = $root . '/modules';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modulesRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

    if ($relative === 'modules/addons/captainfin/edition.json') {
        continue;
    }

    if ($edition === 'jellyfin' && str_starts_with($relative, 'modules/addons/captainfin/lib/Integrations/Emby/')) {
        continue;
    }

    if ($edition === 'emby' && str_starts_with($relative, 'modules/addons/captainfin/lib/Integrations/Jellyfin/')) {
        continue;
    }

    if (!$zip->addFile($file->getPathname(), $relative)) {
        throw new RuntimeException('Unable to add runtime file to package: ' . $relative);
    }
}

$manifest = json_encode([
    'edition' => $edition,
    'development' => false,
    'sku' => $sku,
    'version' => $version,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($manifest === false) {
    throw new RuntimeException('Unable to encode edition manifest.');
}

$zip->addFromString('modules/addons/captainfin/edition.json', $manifest . PHP_EOL);
$zip->close();

$checksum = hash_file('sha256', $zipPath);
if (!is_string($checksum)) {
    throw new RuntimeException('Unable to checksum edition package.');
}

file_put_contents($zipPath . '.sha256', $checksum . '  ' . basename($zipPath) . PHP_EOL);

echo sprintf("Built %s (%s)\n", $zipPath, $checksum);
