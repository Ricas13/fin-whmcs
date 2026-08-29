<?php

declare(strict_types=1);

$baseUrl = rtrim((string) (getenv('CAPTAINFIN_TEST_JELLYFIN_URL') ?: 'http://127.0.0.1:18096'), '/');
$adminUser = (string) (getenv('CAPTAINFIN_TEST_JELLYFIN_ADMIN_USER') ?: 'captainfin-admin');
$adminPassword = (string) (getenv('CAPTAINFIN_TEST_JELLYFIN_ADMIN_PASSWORD') ?: 'CaptainFin-Test-Admin-Only!');
$tokenFile = (string) (getenv('CAPTAINFIN_TEST_JELLYFIN_TOKEN_FILE') ?: dirname(__DIR__) . '/.runtime/jellyfin-token');
$clientAuth = 'MediaBrowser Client="CAPTAiNFiN Tests", Device="CI", DeviceId="captainfin-test-bootstrap", Version="0.2.0"';

if (!extension_loaded('curl')) {
    fwrite(STDERR, "PHP cURL extension is required.\n");
    exit(1);
}

function request(string $baseUrl, string $path, string $method = 'GET', ?array $body = null, ?string $authorization = null, array $okStatuses = [200, 204]): array
{
    $ch = curl_init($baseUrl . $path);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialise cURL.');
    }

    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode Jellyfin bootstrap request.');
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }
    if ($authorization !== null) {
        $headers[] = 'Authorization: ' . $authorization;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Jellyfin bootstrap request failed: ' . $error);
    }
    if (!in_array($status, $okStatuses, true)) {
        throw new RuntimeException(sprintf('Jellyfin bootstrap %s %s returned HTTP %d: %s', $method, $path, $status, substr($raw, 0, 500)));
    }

    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Jellyfin bootstrap returned invalid JSON for ' . $path);
    }
    return $decoded;
}

$deadline = microtime(true) + 90;
$publicInfo = null;
while (microtime(true) < $deadline) {
    try {
        $publicInfo = request($baseUrl, '/System/Info/Public');
        break;
    } catch (Throwable) {
        usleep(500_000);
    }
}
if ($publicInfo === null) {
    fwrite(STDERR, "Jellyfin did not become ready within the bootstrap deadline.\n");
    exit(1);
}

$wizardComplete = (bool) ($publicInfo['StartupWizardCompleted'] ?? false);
if (!$wizardComplete) {
    request($baseUrl, '/Startup/Configuration', 'POST', [
        'UICulture' => 'en-US',
        'MetadataCountryCode' => 'US',
        'PreferredMetadataLanguage' => 'en',
        'ServerName' => 'CAPTAiNFiN Runtime Test',
    ], $clientAuth);

    request($baseUrl, '/Startup/User', 'POST', [
        'Name' => $adminUser,
        'Password' => $adminPassword,
    ], $clientAuth);

    request($baseUrl, '/Startup/RemoteAccess', 'POST', [
        'EnableRemoteAccess' => true,
        'EnableAutomaticPortMapping' => false,
    ], $clientAuth);

    request($baseUrl, '/Startup/Complete', 'POST', null, $clientAuth);
}

$auth = request($baseUrl, '/Users/AuthenticateByName', 'POST', [
    'Username' => $adminUser,
    'Pw' => $adminPassword,
], $clientAuth);
$token = trim((string) ($auth['AccessToken'] ?? ''));
if ($token === '') {
    fwrite(STDERR, "Jellyfin authentication succeeded without an access token.\n");
    exit(1);
}

$directory = dirname($tokenFile);
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create runtime token directory.');
}
if (file_put_contents($tokenFile, $token . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write Jellyfin runtime access token.');
}
@chmod($tokenFile, 0600);

echo "Jellyfin runtime test server bootstrapped successfully.\n";
echo "Token written to {$tokenFile}.\n";
