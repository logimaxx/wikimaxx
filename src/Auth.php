<?php

declare(strict_types=1);

namespace WikiMaxx;

/**
 * Local WikiMaxx users stored as JSON (no DB extension required).
 * Path comes from Config::$sqlitePath for backwards-compatible env name,
 * or any path ending in .json / .sqlite — always written as JSON.
 */
final class Auth
{
    public function __construct(private readonly Config $config)
    {
    }

    private function path(): string
    {
        $p = $this->config->sqlitePath;
        if (str_ends_with($p, '.sqlite') || str_ends_with($p, '.db')) {
            $p = preg_replace('/\.(sqlite|db)$/', '.json', $p) ?? ($p . '.json');
        }
        if (!str_ends_with($p, '.json')) {
            $p .= '.json';
        }
        return $p;
    }

    public function ensureSchema(): void
    {
        $path = $this->path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_file($path)) {
            $this->writeStore(['next_id' => 1, 'users' => []]);
        }
    }

    /** @return array{next_id: int, users: list<array>} */
    private function readStore(): array
    {
        $this->ensureSchema();
        $raw = json_decode((string) file_get_contents($this->path()), true);
        if (!is_array($raw)) {
            return ['next_id' => 1, 'users' => []];
        }
        return [
            'next_id' => (int) ($raw['next_id'] ?? 1),
            'users' => array_values($raw['users'] ?? []),
        ];
    }

    /** @param array{next_id: int, users: list<array>} $store */
    private function writeStore(array $store): void
    {
        $json = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($this->path(), $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Could not write users store');
        }
    }

    /** @return array{id: int, username: string, email: string, roles: list<string>}|null */
    public function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        return $this->findById((int) $id);
    }

    /** @return list<string>|null */
    public function roles(): ?array
    {
        $u = $this->user();
        return $u === null ? null : $u['roles'];
    }

    public function login(string $username, string $password): bool
    {
        foreach ($this->readStore()['users'] as $row) {
            if (($row['username'] ?? '') !== $username) {
                continue;
            }
            if (!password_verify($password, (string) ($row['password_hash'] ?? ''))) {
                return false;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['id'];
            return true;
        }
        return false;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    /** @return array{id: int, username: string, email: string, roles: list<string>}|null */
    public function findById(int $id): ?array
    {
        foreach ($this->readStore()['users'] as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $this->hydrate($row);
            }
        }
        return null;
    }

    /** @return list<array{id: int, username: string, email: string, roles: list<string>}> */
    public function allUsers(): array
    {
        $users = array_map(fn ($r) => $this->hydrate($r), $this->readStore()['users']);
        usort($users, static fn ($a, $b) => strcasecmp($a['username'], $b['username']));
        return $users;
    }

    /**
     * @param list<string> $roles
     */
    public function createUser(string $username, string $email, string $password, array $roles): int
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new \InvalidArgumentException('Username and password required');
        }
        $store = $this->readStore();
        foreach ($store['users'] as $row) {
            if (($row['username'] ?? '') === $username) {
                throw new \RuntimeException('Username already exists');
            }
        }
        $id = $store['next_id'];
        $store['next_id'] = $id + 1;
        $store['users'][] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'roles' => Roles::normalize($roles),
            'created_at' => gmdate('c'),
        ];
        $this->writeStore($store);
        return $id;
    }

    /**
     * @param list<string> $roles
     */
    public function updateUser(int $id, string $email, array $roles, ?string $newPassword = null): void
    {
        $store = $this->readStore();
        $found = false;
        foreach ($store['users'] as &$row) {
            if ((int) ($row['id'] ?? 0) !== $id) {
                continue;
            }
            $found = true;
            $row['email'] = $email;
            $row['roles'] = Roles::normalize($roles);
            if ($newPassword !== null && $newPassword !== '') {
                $row['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            break;
        }
        unset($row);
        if (!$found) {
            throw new \RuntimeException('User not found');
        }
        $this->writeStore($store);
    }

    public function deleteUser(int $id): void
    {
        $store = $this->readStore();
        $store['users'] = array_values(array_filter(
            $store['users'],
            static fn ($r) => (int) ($r['id'] ?? 0) !== $id
        ));
        $this->writeStore($store);
    }

    public function countUsers(): int
    {
        return count($this->readStore()['users']);
    }

    public function findIdByUsername(string $username): ?int
    {
        foreach ($this->readStore()['users'] as $row) {
            if (($row['username'] ?? '') === $username) {
                return (int) $row['id'];
            }
        }
        return null;
    }

    public function hasRole(string $role): bool
    {
        $roles = $this->roles();
        $role = Roles::normalize([$role])[0] ?? $role;
        return $roles !== null && (in_array(Roles::ADMIN, $roles, true) || in_array($role, $roles, true));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Roles::ADMIN);
    }

    public function canEditContent(): bool
    {
        $roles = $this->roles();
        return $roles !== null && (
            in_array(Roles::ADMIN, $roles, true) || in_array(Roles::EDITOR, $roles, true)
        );
    }

    /** @param array $row */
    private function hydrate(array $row): array
    {
        $roles = $row['roles'] ?? [];
        if (!is_array($roles)) {
            $roles = [];
        }
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'email' => (string) ($row['email'] ?? ''),
            'roles' => Roles::normalize(array_values(array_filter($roles, 'is_string'))),
        ];
    }
}
