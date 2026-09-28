<?php

declare(strict_types=1);

/**
 * Seed the first admin user.
 * Usage: php bin/seed-admin.php [username] [password]
 */

$root = dirname(__DIR__);
$config = require $root . '/bootstrap.php';

use WikiMaxx\Auth;

$auth = new Auth($config);

$username = $argv[1] ?? $config->adminUser;
$password = $argv[2] ?? $config->adminPassword;

if ($password === '') {
    fwrite(STDERR, "Set ADMIN_PASSWORD in .env or pass: php bin/seed-admin.php <user> <password>\n");
    exit(1);
}

$existing = $auth->findIdByUsername($username);
if ($existing !== null) {
    $auth->updateUser($existing, $config->adminEmail, ['admin'], $password);
    echo "Updated user '{$username}' with admin role.\n";
    exit(0);
}

$auth->createUser($username, $config->adminEmail, $password, ['admin']);
echo "Created admin user '{$username}'.\n";
