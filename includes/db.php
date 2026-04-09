<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/security/EnvLoader.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!function_exists('db_env_bool')) {
    function db_env_bool(string $key, bool $default = false): bool
    {
        $value = EnvLoader::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }
}

if (!function_exists('db_env_int')) {
    function db_env_int(string $key, int $default): int
    {
        $value = EnvLoader::get($key);
        return is_numeric($value) ? (int) $value : $default;
    }
}

if (!function_exists('db_resolve_ssl_file')) {
    function db_resolve_ssl_file(string $pathKey, string $base64Key, string $suffix): ?string
    {
        $path = EnvLoader::get($pathKey);
        if (is_string($path) && $path !== '') {
            return $path;
        }

        $base64Value = EnvLoader::get($base64Key);
        if (!is_string($base64Value) || $base64Value === '') {
            return null;
        }

        $decoded = base64_decode($base64Value, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        $hash = sha1($decoded);
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pluggedin-db-' . $suffix . '-' . $hash . '.pem';

        if (!is_file($target)) {
            file_put_contents($target, $decoded, LOCK_EX);
            @chmod($target, 0600);
        }

        return $target;
    }
}

$dbHost = EnvLoader::get('DB_HOST', '127.0.0.1');
$dbPort = db_env_int('DB_PORT', 3307);
$dbName = EnvLoader::get('DB_NAME', 'pluggedin_itdbadm');
$dbUser = EnvLoader::get('DB_USER', 'root');
$dbPassword = EnvLoader::get('DB_PASSWORD', '');
$dbSocket = EnvLoader::get('DB_SOCKET', null);
$dbCharset = EnvLoader::get('DB_CHARSET', 'utf8mb4');
$dbConnectTimeout = db_env_int('DB_CONNECT_TIMEOUT', 10);
$dbSslCa = db_resolve_ssl_file('DB_SSL_CA', 'DB_SSL_CA_BASE64', 'ca');
$dbSslCert = db_resolve_ssl_file('DB_SSL_CERT', 'DB_SSL_CERT_BASE64', 'cert');
$dbSslKey = db_resolve_ssl_file('DB_SSL_KEY', 'DB_SSL_KEY_BASE64', 'key');
$dbSslCipher = EnvLoader::get('DB_SSL_CIPHER', null);
$dbVerifyServerCert = db_env_bool('DB_SSL_VERIFY_SERVER_CERT', true);

$conn = mysqli_init();

if ($conn === false) {
    throw new RuntimeException('Unable to initialize the database connection.');
}

mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, $dbConnectTimeout);

$clientFlags = 0;
if ($dbSslCa !== null || $dbSslCert !== null || $dbSslKey !== null) {
    $conn->ssl_set($dbSslKey, $dbSslCert, $dbSslCa, null, $dbSslCipher);
    $clientFlags |= MYSQLI_CLIENT_SSL;

    if (!$dbVerifyServerCert && defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
        $clientFlags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
    }
}

$conn->real_connect(
    $dbHost,
    $dbUser,
    $dbPassword,
    $dbName,
    $dbPort,
    $dbSocket ?: null,
    $clientFlags
);

if (!$conn->set_charset($dbCharset)) {
    throw new RuntimeException('Unable to set the database connection charset.');
}

$conn->set_charset('utf8mb4');
?>
