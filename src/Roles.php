<?php

declare(strict_types=1);

namespace WikiMaxx;

/**
 * Audience roles: guest (no account), colaborator, partener, admin.
 * Capability role: editor (write content they can read).
 */
final class Roles
{
    public const ADMIN = 'admin';
    public const COLABORATOR = 'colaborator';
    public const PARTENER = 'partener';
    public const EDITOR = 'editor';

    /** @deprecated treated as colaborator */
    public const LEGACY_ECHIPA = 'echipa';

    /**
     * Roles selectable in /admin/users.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ADMIN, self::COLABORATOR, self::PARTENER, self::EDITOR];
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public static function normalize(array $roles): array
    {
        $out = [];
        foreach ($roles as $r) {
            if (!is_string($r) || $r === '') {
                continue;
            }
            if ($r === self::LEGACY_ECHIPA) {
                $r = self::COLABORATOR;
            }
            $out[] = $r;
        }
        return array_values(array_unique($out));
    }
}
