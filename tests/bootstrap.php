<?php

declare(strict_types=1);

if (!defined('WHMCS')) {
    define('WHMCS', true);
}

$composer = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
}

if (!class_exists('WHMCS\\Database\\Capsule', false)
    && class_exists('Illuminate\\Database\\Capsule\\Manager')) {
    class_alias('Illuminate\\Database\\Capsule\\Manager', 'WHMCS\\Database\\Capsule');
}

require_once dirname(__DIR__) . '/modules/addons/captainfin/lib/autoload.php';
