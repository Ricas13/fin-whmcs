<?php

declare(strict_types=1);

$origin = rtrim((string) (getenv('CAPTAINFIN_TEST_EMBY_ORIGIN') ?: 'http://127.0.0.1:18097'), '/');
$adminUser = (string) (getenv('CAPTAINFIN_TEST_EMBY_ADMIN_USER') ?: 'captainfin-admin');
$adminPassword = (string) (getenv('CAPTAINFIN_TEST_EMBY_ADMIN_PASSWORD') ?: 'CaptainFin-Emby-Test-Admin-Only!');
$tokenFile = (string) (getenv('CAPTAINFIN_TEST_EMBY_TOKEN_FILE') ?: dirname(__DIR__) . '/.runtime/emby-token');
$baseUrlFile = (string) (getenv('CAPTAINFIN_TEST_EMBY_BASE_URL_FILE') ?: dirname(__DIR__) . '/.runtime/emby-base-url');
$clientAuthorization = 'Emby Client="CAPTAiNFiN Tests", Device="CI", DeviceId="captainfin-emby-bootstrap", Version="0.3.0"';

if (!extension_loaded('curl')) {
    fwrite(STDERR, "PHP cURL extension is required.\n");
    exit(1);
}

function embyRequest(
    string $baseUrl,
    string $path,
    string $method = 'GET',
    ?array $body = null,
    array $extraHeaders = [],
    array $okStatuses = [200, 204]
): array {
    $ch = curl_init(rtrim($baseUrl, '/') . $path);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialise cURL.');
    }

    $headers = array_merge(['Accept: application/json'], $extraHeaders);
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode Emby bootstrap request.');
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
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
        throw new RuntimeException('Emby bootstrap request failed: ' . $error);
    }
    if (!in_array($status, $okStatuses, true)) {
        throw new RuntimeException(sprintf('Emby bootstrap %s %s returned HTTP %d: %s', $method, $path, $status, substr($raw, 0, 500)));
    }

    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Emby bootstrap returned invalid JSON for ' . $path);
    }
    return $decoded;
}

$deadline = microtime(true) + 300;
$baseUrl = null;

// Emby installations commonly expose API routes both at the origin and below
// /emby depending on server/reverse-proxy configuration. Probe rather than
// hard-coding one shape so the runtime evidence also protects base-path support.
while (microtime(true) < $deadline && $baseUrl === null) {
    foreach ([$origin, $origin . '/emby'] as $candidate) {
        try {
            embyRequest($candidate, '/Startup/Configuration');
            $baseUrl = $candidate;
            break 2;
        } catch (Throwable) {
            // Server can answer its public page before the startup service is ready.
        }
    }
    usleep(500_000);
}

if ($baseUrl === null) {
    fwrite(STDERR, "Emby startup API did not become ready within the bootstrap deadline.\n");
    exit(1);
}

embyRequest($baseUrl, '/Startup/Configuration', 'POST', [
    'UICulture' => 'en-US',
    'MetadataCountryCode' => 'US',
    'PreferredMetadataLanguage' => 'en',
]);

// GET is intentional: the startup service owns the initial admin identity and
// this proves the default user exists before we mutate it. Current Emby also
// requires a non-empty Password during this update; omitting it causes the
// password provider to receive a null newPasswordHash.
embyRequest($baseUrl, '/Startup/User');
embyRequest($baseUrl, '/Startup/User', 'POST', [
    'Name' => $adminUser,
    'ConnectUserName' => null,
    'Password' => $adminPassword,
]);
embyRequest($baseUrl, '/Startup/RemoteAccess', 'POST', [
    'EnableRemoteAccess' => true,
    'EnableAutomaticPortMapping' => false,
]);
embyRequest($baseUrl, '/Startup/Complete', 'POST');

$auth = embyRequest(
    $baseUrl,
    '/Users/AuthenticateByName',
    'POST',
    ['Username' => $adminUser, 'Pw' => $adminPassword],
    ['X-Emby-Authorization: ' . $clientAuthorization]
);
$token = trim((string) ($auth['AccessToken'] ?? ''));
if ($token === '') {
    fwrite(STDERR, "Emby authentication succeeded without an access token.\n");
    exit(1);
}

$info = embyRequest($baseUrl, '/System/Info', 'GET', null, ['X-Emby-Token: ' . $token]);
$version = trim((string) ($info['Version'] ?? 'unknown'));

$directory = dirname($tokenFile);
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create Emby runtime token directory.');
}
if (file_put_contents($tokenFile, $token . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write Emby runtime access token.');
}
if (file_put_contents($baseUrlFile, $baseUrl . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write Emby runtime base URL.');
}
@chmod($tokenFile, 0600);
@chmod($baseUrlFile, 0600);

echo "Emby {$version} runtime test server bootstrapped successfully at {$baseUrl}.\n";
echo "Token written to {$tokenFile}.\n";
