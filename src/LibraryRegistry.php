<?php

declare(strict_types=1);

namespace WikiMaxx;

final class LibraryRegistry
{
    /** @var list<string> */
    public const RESERVED = ['login', 'logout', 'search', 'admin', 'assets'];

    /**
     * @param list<Library> $libraries
     */
    private function __construct(
        private readonly bool $multi,
        private readonly array $libraries,
    ) {
    }

    public static function load(Config $config): self
    {
        $path = $config->librariesPath;
        if ($path === '' || !is_file($path)) {
            $tree = new ContentTree($config->contentRoot);
            return new self(false, [new Library('', self::rootTitle($tree), $tree)]);
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new \RuntimeException('LIBRARIES_PATH must be a JSON array: ' . $path);
        }

        $libs = [];
        $seen = [];
        foreach ($raw as $i => $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Library entry ' . $i . ' is not an object');
            }
            $id = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
            if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $id)) {
                throw new \RuntimeException('Invalid library id: ' . $id);
            }
            if (in_array($id, self::RESERVED, true)) {
                throw new \RuntimeException('Reserved library id: ' . $id);
            }
            if (isset($seen[$id])) {
                throw new \RuntimeException('Duplicate library id: ' . $id);
            }
            $seen[$id] = true;

            $rel = isset($row['path']) && is_string($row['path']) ? trim($row['path']) : '';
            if ($rel === '') {
                throw new \RuntimeException('Library path missing: ' . $id);
            }
            $abs = Config::resolvePath($config->root, $rel);
            if (!is_dir($abs)) {
                throw new \RuntimeException('Library path is not a directory: ' . $id . ' (' . $abs . ')');
            }

            $tree = new ContentTree($abs, '/' . $id, false);
            $title = isset($row['title']) && is_string($row['title']) ? trim($row['title']) : '';
            if ($title === '') {
                $title = self::rootTitle($tree);
            }
            if ($title === '') {
                $title = ContentTree::humanize($id);
            }
            $libs[] = new Library($id, $title, $tree);
        }

        return new self(true, $libs);
    }

    public function multi(): bool
    {
        return $this->multi;
    }

    /** @return list<Library> */
    public function all(): array
    {
        return $this->libraries;
    }

    public function get(string $id): ?Library
    {
        foreach ($this->libraries as $lib) {
            if ($lib->id === $id) {
                return $lib;
            }
        }
        return null;
    }

    public function primary(): Library
    {
        if ($this->libraries === []) {
            throw new \RuntimeException('No libraries configured');
        }
        return $this->libraries[0];
    }

    /**
     * Libraries whose root ACL allows this reader.
     *
     * @param list<string>|null $userRoles null = guest
     * @return list<Library>
     */
    public function readable(?array $userRoles): array
    {
        $out = [];
        foreach ($this->libraries as $lib) {
            $root = $lib->tree->root();
            if (Acl::canRead(Acl::resolve($root, $root), $userRoles)) {
                $out[] = $lib;
            }
        }
        return $out;
    }

    private static function rootTitle(ContentTree $tree): string
    {
        $access = $tree->root() . '/_access.json';
        if (!is_file($access)) {
            return '';
        }
        return Acl::parseFile($access)['title'] ?? '';
    }
}
