<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use WikiMaxx\Config;
use WikiMaxx\Auth;
use WikiMaxx\Roles;

$config = Config::load(__DIR__);
Roles::configure($config->rolesPath);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('academy_sess');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

$auth = new Auth($config);
$auth->ensureSchema();

return $config;
