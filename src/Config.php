<?php

declare(strict_types=1);

namespace WikiMaxx;

final class Config
{
    public function __construct(
        public readonly string $root,
        public readonly string $contentRoot,
        public readonly string $sqlitePath,
        public readonly string $rolesPath,
        public readonly string $sessionSecret,
        public readonly string $adminUser,
        public readonly string $adminPassword,
        public readonly string $adminEmail,
        public readonly string $librariesPath = '',
    ) {
    }

    /** Absolute path. Relative paths are from the application root. */
    public static function resolvePath(string $root, string $path): string
    {
        if ($path === '') {
            return $root;
        }
        if ($path[0] === '/' || (strlen($path) > 1 && $path[1] === ':')) {
            return $path;
        }
        return $root . '/' . $path;
    }

    public static function load(string $root): self
    {
        // Prefer .env; fall back to dotenv (Hestia git-deploy never ships .env* into releases)
        $envFile = $root . '/.env';
        if (!is_file($envFile) && is_file($root . '/dotenv')) {
            $envFile = $root . '/dotenv';
        }
        $vars = [];
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\"'");
            }
        }

        $librariesRaw = trim($vars['LIBRARIES_PATH'] ?? '');

        return new self(
            root: $root,
            contentRoot: self::resolvePath($root, $vars['CONTENT_ROOT'] ?? 'content'),
            sqlitePath: self::resolvePath($root, $vars['USERS_PATH'] ?? $vars['SQLITE_PATH'] ?? 'data/users.json'),
            rolesPath: self::resolvePath($root, $vars['ROLES_PATH'] ?? 'data/roles.json'),
            sessionSecret: $vars['SESSION_SECRET'] ?? 'dev-insecure-secret',
            adminUser: $vars['ADMIN_USER'] ?? 'admin',
            adminPassword: $vars['ADMIN_PASSWORD'] ?? '',
            adminEmail: $vars['ADMIN_EMAIL'] ?? 'admin@example.com',
            librariesPath: $librariesRaw === '' ? '' : self::resolvePath($root, $librariesRaw),
        );
    }
}
