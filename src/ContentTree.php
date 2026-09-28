<?php

declare(strict_types=1);

namespace WikiMaxx;

final class ContentTree
{
    public function __construct(
        private readonly string $contentRoot,
    ) {
    }

    public function root(): string
    {
        if (!is_dir($this->contentRoot)) {
            mkdir($this->contentRoot, 0755, true);
        }
        $real = realpath($this->contentRoot);
        if ($real === false) {
            throw new \RuntimeException('CONTENT_ROOT is not readable: ' . $this->contentRoot);
        }
        return $real;
    }

    /**
     * Resolve a URL path to an absolute filesystem path under content root, or null.
     * Returns directory path, .md file path, or null if missing/invalid.
     */
    public function resolve(string $urlPath): ?array
    {
        $root = $this->root();
        $rel = trim($urlPath, '/');
        if ($rel === '' || $rel === 'index') {
            $indexMd = $root . '/index.md';
            if (is_file($indexMd)) {
                return ['type' => 'file', 'path' => $indexMd, 'rel' => 'index.md'];
            }
            return ['type' => 'dir', 'path' => $root, 'rel' => ''];
        }

        if (str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }

        $candidate = $root . '/' . $rel;
        $realFile = realpath($candidate . '.md');
        if ($realFile !== false && is_file($realFile) && str_starts_with($realFile, $root)) {
            return [
                'type' => 'file',
                'path' => $realFile,
                'rel' => ltrim(substr($realFile, strlen($root)), '/'),
            ];
        }

        $realDir = realpath($candidate);
        if ($realDir !== false && is_dir($realDir) && str_starts_with($realDir, $root)) {
            $index = $realDir . '/index.md';
            if (is_file($index)) {
                return [
                    'type' => 'file',
                    'path' => $index,
                    'rel' => ltrim(substr($index, strlen($root)), '/'),
                ];
            }
            return [
                'type' => 'dir',
                'path' => $realDir,
                'rel' => ltrim(substr($realDir, strlen($root)), '/'),
            ];
        }

        // Asset under content (images)
        $asset = realpath($candidate);
        if ($asset !== false && is_file($asset) && str_starts_with($asset, $root)) {
            return [
                'type' => 'asset',
                'path' => $asset,
                'rel' => ltrim(substr($asset, strlen($root)), '/'),
            ];
        }

        return null;
    }

    /**
     * Build nav tree filtered by ACL.
     *
     * @param list<string>|null $userRoles
     * @return list<array{type: string, name: string, title: string, href: string, children?: list}>
     */
    public function nav(?array $userRoles): array
    {
        return $this->scanDir($this->root(), '', $userRoles);
    }

    /**
     * @param list<string>|null $userRoles
     * @return list<array{type: string, name: string, title: string, href: string, children?: list}>
     */
    private function scanDir(string $absDir, string $relDir, ?array $userRoles): array
    {
        $acl = Acl::resolve($this->root(), $absDir);
        if (!Acl::canRead($acl, $userRoles)) {
            return [];
        }

        $entries = scandir($absDir) ?: [];
        $dirs = [];
        $files = [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.') || $name === '_access.json') {
                continue;
            }
            $abs = $absDir . '/' . $name;
            $childRel = $relDir === '' ? $name : $relDir . '/' . $name;

            if (is_dir($abs)) {
                $childAcl = Acl::resolve($this->root(), $abs);
                if (!Acl::canRead($childAcl, $userRoles)) {
                    continue;
                }
                $children = $this->scanDir($abs, $childRel, $userRoles);
                $dirs[] = [
                    'type' => 'dir',
                    'name' => $name,
                    'title' => self::dirTitle($abs, $name),
                    'href' => '/' . $childRel,
                    'children' => $children,
                ];
                continue;
            }

            if (!str_ends_with($name, '.md')) {
                continue;
            }
            if ($name === 'index.md') {
                // folder/root index is the dir itself (or home), not a nav leaf
                continue;
            }
            $meta = Markdown::parseFile($abs);
            $slug = substr($name, 0, -3);
            $hrefRel = $relDir === '' ? $slug : $relDir . '/' . $slug;
            if ($slug === 'index' && $relDir === '') {
                $hrefRel = '';
            }
            $files[] = [
                'type' => 'file',
                'name' => $slug,
                'title' => $meta['title'] ?? self::humanize($slug),
                'href' => '/' . ltrim($hrefRel, '/'),
            ];
        }

        usort($dirs, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
        usort($files, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));

        return array_merge($dirs, $files);
    }

    public static function humanize(string $slug): string
    {
        $s = str_replace(['-', '_'], ' ', $slug);
        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Display title for a content directory: index.md frontmatter, then local
     * _access.json title (not inherited), then humanized folder name.
     */
    public static function dirTitle(string $absDir, string $folderName): string
    {
        $index = $absDir . '/index.md';
        if (is_file($index)) {
            $meta = Markdown::parseFile($index);
            if ($meta['title'] !== null && $meta['title'] !== '') {
                return $meta['title'];
            }
        }
        $access = $absDir . '/_access.json';
        if (is_file($access)) {
            $local = Acl::parseFile($access);
            if ($local['title'] !== null && $local['title'] !== '') {
                return $local['title'];
            }
        }
        return self::humanize($folderName);
    }

    /**
     * Resolve a relative URL path segment chain to a display title (for breadcrumbs / listings).
     */
    public function titleForRel(string $rel): string
    {
        $rel = trim(str_replace('\\', '/', $rel), '/');
        if ($rel === '') {
            return self::dirTitle($this->root(), 'WikiMaxx');
        }
        $abs = $this->root() . '/' . $rel;
        if (is_dir($abs)) {
            return self::dirTitle($abs, basename($rel));
        }
        $md = $abs . '.md';
        if (is_file($md)) {
            $meta = Markdown::parseFile($md);
            return $meta['title'] ?? self::humanize(basename($rel));
        }
        return self::humanize(basename($rel));
    }

    /**
     * Flat list of all writable paths for admin tree.
     *
     * @return list<array{rel: string, type: string}>
     */
    public function flatList(): array
    {
        $out = [];
        $this->walkFlat($this->root(), '', $out);
        return $out;
    }

    /** @param list<array{rel: string, type: string}> $out */
    private function walkFlat(string $absDir, string $relDir, array &$out): void
    {
        $entries = scandir($absDir) ?: [];
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }
            $abs = $absDir . '/' . $name;
            $childRel = $relDir === '' ? $name : $relDir . '/' . $name;
            if (is_dir($abs)) {
                $out[] = ['rel' => $childRel, 'type' => 'dir'];
                $this->walkFlat($abs, $childRel, $out);
            } elseif (str_ends_with($name, '.md') || $name === '_access.json') {
                $out[] = ['rel' => $childRel, 'type' => 'file'];
            } elseif (preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $name)) {
                $out[] = ['rel' => $childRel, 'type' => 'asset'];
            }
        }
    }

    public function absoluteFromRel(string $rel): ?string
    {
        $root = $this->root();
        $rel = str_replace('\\', '/', $rel);
        $rel = trim($rel, '/');
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }
        $candidate = $root . '/' . $rel;
        $parent = dirname($candidate);
        if (!is_dir($parent)) {
            return null;
        }
        $parentReal = realpath($parent);
        if ($parentReal === false || !str_starts_with($parentReal, $root)) {
            return null;
        }
        return $parentReal . '/' . basename($candidate);
    }
}
