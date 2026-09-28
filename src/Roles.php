<?php

declare(strict_types=1);

namespace WikiMaxx;

/**
 * Role catalog in roles.json (soft-FK vs users / _access.json).
 *
 * Special ids (still in catalog):
 * - admin: bypass read/write + user/ACL admin
 * - editor: write capability on zones the user can already read
 */
final class Roles
{
    public const ADMIN = 'admin';
    public const COLABORATOR = 'colaborator';
    public const PARTENER = 'partener';
    public const EDITOR = 'editor';

    /** @deprecated treated as colaborator */
    public const LEGACY_ECHIPA = 'echipa';

    private static ?string $path = null;

    /** @var list<array{id:string,label:string,builtin:bool,capability:?string}>|null */
    private static ?array $cache = null;

    public static function configure(string $path): void
    {
        self::$path = $path;
        self::$cache = null;
        self::ensureSchema();
    }

    public static function path(): string
    {
        if (self::$path === null) {
            throw new \RuntimeException('Roles catalog not configured');
        }
        return self::$path;
    }

    public static function ensureSchema(): void
    {
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_file($path)) {
            self::writeStore(['roles' => self::builtinDefaults()]);
            return;
        }
        // Ensure builtins exist (merge missing only)
        $store = self::readStore();
        $byId = [];
        foreach ($store['roles'] as $r) {
            $byId[$r['id']] = $r;
        }
        $changed = false;
        foreach (self::builtinDefaults() as $b) {
            if (!isset($byId[$b['id']])) {
                $store['roles'][] = $b;
                $changed = true;
            }
        }
        if ($changed) {
            self::writeStore($store);
        }
    }

    /**
     * @return list<array{id:string,label:string,builtin:bool,capability:?string}>
     */
    public static function builtinDefaults(): array
    {
        return [
            ['id' => self::ADMIN, 'label' => 'Admin', 'builtin' => true, 'capability' => null],
            ['id' => self::COLABORATOR, 'label' => 'Colaborator', 'builtin' => true, 'capability' => null],
            ['id' => self::PARTENER, 'label' => 'Partener', 'builtin' => true, 'capability' => null],
            ['id' => self::EDITOR, 'label' => 'Editor', 'builtin' => true, 'capability' => 'write'],
        ];
    }

    /**
     * Role ids selectable in admin / ACL.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(static fn ($r) => $r['id'], self::catalog());
    }

    /**
     * @return list<array{id:string,label:string,builtin:bool,capability:?string}>
     */
    public static function catalog(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = self::readStore()['roles'];
        return self::$cache;
    }

    public static function label(string $id): string
    {
        foreach (self::catalog() as $r) {
            if ($r['id'] === $id) {
                return $r['label'];
            }
        }
        return $id;
    }

    public static function exists(string $id): bool
    {
        return in_array($id, self::all(), true);
    }

    /**
     * @param list<string> $userRoles
     */
    public static function hasWriteCapability(array $userRoles): bool
    {
        $userRoles = self::normalize($userRoles);
        if (in_array(self::ADMIN, $userRoles, true)) {
            return true;
        }
        $write = [];
        foreach (self::catalog() as $r) {
            if ($r['capability'] === 'write') {
                $write[$r['id']] = true;
            }
        }
        foreach ($userRoles as $id) {
            if (isset($write[$id])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public static function normalize(array $roles): array
    {
        $known = array_flip(self::all());
        $out = [];
        foreach ($roles as $r) {
            if (!is_string($r) || $r === '') {
                continue;
            }
            if ($r === self::LEGACY_ECHIPA) {
                $r = self::COLABORATOR;
            }
            if (!isset($known[$r])) {
                continue; // soft-FK: drop orphans at read time
            }
            $out[] = $r;
        }
        return array_values(array_unique($out));
    }

    /**
     * Reject unknown role ids (write-path soft FK).
     *
     * @param list<string> $roles
     */
    public static function assertKnown(array $roles): void
    {
        $known = array_flip(self::all());
        $bad = [];
        foreach ($roles as $r) {
            if (!is_string($r) || $r === '') {
                continue;
            }
            $id = $r === self::LEGACY_ECHIPA ? self::COLABORATOR : $r;
            if (!isset($known[$id])) {
                $bad[] = $r;
            }
        }
        if ($bad !== []) {
            throw new \InvalidArgumentException('Unknown role(s): ' . implode(', ', array_unique($bad)));
        }
    }

    public static function create(string $id, string $label, ?string $capability = null): void
    {
        $id = self::sanitizeId($id);
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Label required');
        }
        if (self::exists($id)) {
            throw new \RuntimeException('Role already exists');
        }
        if ($capability !== null && $capability !== 'write') {
            throw new \InvalidArgumentException('capability must be null or write');
        }
        $store = self::readStore();
        $store['roles'][] = [
            'id' => $id,
            'label' => $label,
            'builtin' => false,
            'capability' => $capability,
        ];
        self::writeStore($store);
    }

    public static function update(string $id, string $label, ?string $capability = null): void
    {
        $id = trim($id);
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Label required');
        }
        $store = self::readStore();
        $found = false;
        foreach ($store['roles'] as &$r) {
            if ($r['id'] !== $id) {
                continue;
            }
            $found = true;
            $r['label'] = $label;
            // Builtin capability is fixed (admin/editor semantics)
            if (!$r['builtin']) {
                if ($capability !== null && $capability !== 'write') {
                    throw new \InvalidArgumentException('capability must be null or write');
                }
                $r['capability'] = $capability;
            }
            break;
        }
        unset($r);
        if (!$found) {
            throw new \RuntimeException('Role not found');
        }
        self::writeStore($store);
    }

    /**
     * @param list<string> $usedByUsers role ids currently on users
     * @param list<string> $usedInAcl role ids found in _access.json files
     */
    public static function delete(string $id, array $usedByUsers, array $usedInAcl): void
    {
        $id = trim($id);
        $store = self::readStore();
        $entry = null;
        foreach ($store['roles'] as $r) {
            if ($r['id'] === $id) {
                $entry = $r;
                break;
            }
        }
        if ($entry === null) {
            throw new \RuntimeException('Role not found');
        }
        if ($entry['builtin']) {
            throw new \RuntimeException('Cannot delete builtin role');
        }
        if (in_array($id, $usedByUsers, true)) {
            throw new \RuntimeException('Role is assigned to one or more users');
        }
        if (in_array($id, $usedInAcl, true)) {
            throw new \RuntimeException('Role is referenced in _access.json');
        }
        $store['roles'] = array_values(array_filter(
            $store['roles'],
            static fn ($r) => $r['id'] !== $id
        ));
        self::writeStore($store);
    }

    /**
     * Collect role ids referenced by any _access.json under content root.
     *
     * @return list<string>
     */
    public static function scanAccessRoles(string $contentRoot): array
    {
        $contentRoot = realpath($contentRoot) ?: $contentRoot;
        if (!is_dir($contentRoot)) {
            return [];
        }
        $found = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contentRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getFilename() !== '_access.json') {
                continue;
            }
            $raw = json_decode((string) file_get_contents($file->getPathname()), true);
            if (!is_array($raw) || !isset($raw['roles']) || !is_array($raw['roles'])) {
                continue;
            }
            foreach ($raw['roles'] as $r) {
                if (is_string($r) && $r !== '') {
                    $found[$r] = true;
                }
            }
        }
        return array_keys($found);
    }

    private static function sanitizeId(string $id): string
    {
        $id = strtolower(trim($id));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $id)) {
            throw new \InvalidArgumentException('Role id: start with letter, then a-z 0-9 _ -');
        }
        if ($id === self::LEGACY_ECHIPA) {
            throw new \InvalidArgumentException('Reserved id');
        }
        return $id;
    }

    /** @return array{roles: list<array{id:string,label:string,builtin:bool,capability:?string}>} */
    private static function readStore(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return ['roles' => self::builtinDefaults()];
        }
        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || !isset($raw['roles']) || !is_array($raw['roles'])) {
            return ['roles' => self::builtinDefaults()];
        }
        $roles = [];
        foreach ($raw['roles'] as $r) {
            if (!is_array($r) || !isset($r['id']) || !is_string($r['id']) || $r['id'] === '') {
                continue;
            }
            $cap = $r['capability'] ?? null;
            $roles[] = [
                'id' => $r['id'],
                'label' => isset($r['label']) && is_string($r['label']) && $r['label'] !== ''
                    ? $r['label']
                    : $r['id'],
                'builtin' => (bool) ($r['builtin'] ?? false),
                'capability' => is_string($cap) && $cap !== '' ? $cap : null,
            ];
        }
        return ['roles' => $roles !== [] ? $roles : self::builtinDefaults()];
    }

    /** @param array{roles: list<array{id:string,label:string,builtin:bool,capability:?string}>} $store */
    private static function writeStore(array $store): void
    {
        $json = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(self::path(), $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Could not write roles store');
        }
        self::$cache = null;
    }
}
