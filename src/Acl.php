<?php

declare(strict_types=1);

namespace WikiMaxx;

final class Acl
{
    /** @return array{public: bool, roles: list<string>, title: ?string} */
    public static function defaultAcl(): array
    {
        return ['public' => true, 'roles' => [], 'title' => null];
    }

    /**
     * Nearest _access.json walking up from $dir toward $contentRoot (inclusive).
     *
     * @return array{public: bool, roles: list<string>, title: ?string}
     */
    public static function resolve(string $contentRoot, string $path): array
    {
        $contentRoot = realpath($contentRoot) ?: $contentRoot;
        $path = is_dir($path) ? $path : dirname($path);
        $path = realpath($path) ?: $path;

        $current = $path;
        while (true) {
            $file = $current . '/_access.json';
            if (is_file($file)) {
                return self::parseFile($file);
            }
            if ($current === $contentRoot || !str_starts_with($current, $contentRoot)) {
                break;
            }
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        return self::defaultAcl();
    }

    /** @return array{public: bool, roles: list<string>, title: ?string} */
    public static function parseFile(string $file): array
    {
        $raw = json_decode((string) file_get_contents($file), true);
        if (!is_array($raw)) {
            return self::defaultAcl();
        }
        $roles = [];
        if (isset($raw['roles']) && is_array($raw['roles'])) {
            foreach ($raw['roles'] as $r) {
                if (is_string($r) && $r !== '') {
                    $roles[] = $r;
                }
            }
        }
        $roles = Roles::normalize($roles);
        $public = array_key_exists('public', $raw)
            ? (bool) $raw['public']
            : ($roles === []);

        return [
            'public' => $public,
            'roles' => $roles,
            'title' => isset($raw['title']) && is_string($raw['title']) ? $raw['title'] : null,
        ];
    }

    /**
     * @param array{public: bool, roles: list<string>, title: ?string} $acl
     * @param list<string>|null $userRoles null = guest
     */
    public static function canRead(array $acl, ?array $userRoles): bool
    {
        if ($acl['public']) {
            return true;
        }
        if ($userRoles === null) {
            return false;
        }
        $userRoles = Roles::normalize($userRoles);
        if (in_array(Roles::ADMIN, $userRoles, true)) {
            return true;
        }
        foreach ($acl['roles'] as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<string>|null $userRoles
     */
    public static function canWrite(?array $userRoles, array $acl): bool
    {
        if ($userRoles === null) {
            return false;
        }
        $userRoles = Roles::normalize($userRoles);
        if (in_array(Roles::ADMIN, $userRoles, true)) {
            return true;
        }
        if (!Roles::hasWriteCapability($userRoles)) {
            return false;
        }
        return self::canRead($acl, $userRoles);
    }
}
