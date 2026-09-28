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
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')
                || $name === '_access.json' || $name === '_order.json') {
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
                    'href' => '/' . $childRel . '/',
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

        return self::applyOrder(array_merge($dirs, $files), self::loadOrder($absDir));
    }

    /**
     * One-level children for directory index pages (ACL-filtered, same order as nav).
     *
     * @param list<string>|null $userRoles
     * @return list<array{title: string, href: string}>
     */
    public function listChildren(string $absDir, string $relDir, ?array $userRoles): array
    {
        $acl = Acl::resolve($this->root(), $absDir);
        if (!Acl::canRead($acl, $userRoles)) {
            return [];
        }

        $dirs = [];
        $files = [];
        foreach (scandir($absDir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')
                || $name === '_access.json' || $name === '_order.json') {
                continue;
            }
            $childAbs = $absDir . '/' . $name;
            $childRel = $relDir === '' ? $name : $relDir . '/' . $name;
            if (is_dir($childAbs)) {
                $childAcl = Acl::resolve($this->root(), $childAbs);
                if (!Acl::canRead($childAcl, $userRoles)) {
                    continue;
                }
                $dirs[] = [
                    'type' => 'dir',
                    'name' => $name,
                    'title' => self::dirTitle($childAbs, $name),
                    'href' => '/' . $childRel . '/',
                ];
            } elseif (str_ends_with($name, '.md') && $name !== 'index.md') {
                $meta = Markdown::parseFile($childAbs);
                $slug = substr($name, 0, -3);
                $files[] = [
                    'type' => 'file',
                    'name' => $slug,
                    'title' => $meta['title'] ?? self::humanize($slug),
                    'href' => '/' . ($relDir === '' ? $slug : $relDir . '/' . $slug),
                ];
            }
        }

        $out = [];
        foreach (self::applyOrder(array_merge($dirs, $files), self::loadOrder($absDir)) as $item) {
            $out[] = ['title' => $item['title'], 'href' => $item['href']];
        }
        return $out;
    }

    /**
     * Orderable children for admin (ignores ACL filter — editors already have write).
     *
     * @return list<array{type: string, name: string, title: string}>
     */
    public function orderableChildren(string $relDir): array
    {
        $relDir = trim(str_replace('\\', '/', $relDir), '/');
        $absDir = $relDir === '' ? $this->root() : ($this->root() . '/' . $relDir);
        $real = realpath($absDir);
        if ($real === false || !is_dir($real) || !str_starts_with($real, $this->root())) {
            throw new \InvalidArgumentException('Invalid folder');
        }

        $dirs = [];
        $files = [];
        foreach (scandir($real) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')
                || $name === '_access.json' || $name === '_order.json') {
                continue;
            }
            $abs = $real . '/' . $name;
            if (is_dir($abs)) {
                $dirs[] = [
                    'type' => 'dir',
                    'name' => $name,
                    'title' => self::dirTitle($abs, $name),
                ];
            } elseif (str_ends_with($name, '.md') && $name !== 'index.md') {
                $slug = substr($name, 0, -3);
                $meta = Markdown::parseFile($abs);
                $files[] = [
                    'type' => 'file',
                    'name' => $slug,
                    'title' => $meta['title'] ?? self::humanize($slug),
                ];
            }
        }

        return self::applyOrder(array_merge($dirs, $files), self::loadOrder($real));
    }

    /** @return list<string>|null */
    public static function loadOrder(string $absDir): ?array
    {
        $file = rtrim($absDir, '/') . '/_order.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = json_decode((string) file_get_contents($file), true);
        if (!is_array($raw)) {
            return null;
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * Apply manual order when present; otherwise dirs A–Z then files A–Z (by title).
     *
     * @param list<array{type: string, name: string, title: string, ...}> $items
     * @param list<string>|null $order
     * @return list<array{type: string, name: string, title: string, ...}>
     */
    public static function applyOrder(array $items, ?array $order): array
    {
        if ($order === null) {
            $dirs = [];
            $files = [];
            foreach ($items as $item) {
                if (($item['type'] ?? '') === 'dir') {
                    $dirs[] = $item;
                } else {
                    $files[] = $item;
                }
            }
            usort($dirs, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
            usort($files, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
            return array_values(array_merge($dirs, $files));
        }

        $rank = [];
        foreach ($order as $i => $name) {
            if (!array_key_exists($name, $rank)) {
                $rank[$name] = $i;
            }
        }
        usort($items, static function ($a, $b) use ($rank) {
            $ra = $rank[$a['name']] ?? PHP_INT_MAX;
            $rb = $rank[$b['name']] ?? PHP_INT_MAX;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            $ta = ($a['type'] ?? '') === 'dir' ? 0 : 1;
            $tb = ($b['type'] ?? '') === 'dir' ? 0 : 1;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            return strcasecmp($a['title'], $b['title']);
        });
        return array_values($items);
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
     * One-level listing for the admin folder browser.
     *
     * @return list<array{type: 'dir'|'md'|'asset', name: string, title: string, rel: string}>
     */
    public function browseLevel(string $relDir): array
    {
        $relDir = trim(str_replace('\\', '/', $relDir), '/');
        $absDir = $relDir === '' ? $this->root() : ($this->root() . '/' . $relDir);
        $real = realpath($absDir);
        if ($real === false || !is_dir($real) || !str_starts_with($real, $this->root())) {
            throw new \InvalidArgumentException('Invalid folder');
        }

        $dirs = [];
        $mds = [];
        $assets = [];
        foreach (scandir($real) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')
                || $name === '_access.json' || $name === '_order.json') {
                continue;
            }
            $abs = $real . '/' . $name;
            $childRel = $relDir === '' ? $name : $relDir . '/' . $name;
            if (is_dir($abs)) {
                $dirs[] = [
                    'type' => 'dir',
                    'name' => $name,
                    'title' => self::dirTitle($abs, $name),
                    'rel' => $childRel,
                ];
            } elseif (str_ends_with($name, '.md')) {
                $meta = Markdown::parseFile($abs);
                $mds[] = [
                    'type' => 'md',
                    'name' => $name,
                    'title' => $meta['title'] ?? self::humanize(substr($name, 0, -3)),
                    'rel' => $childRel,
                ];
            } elseif (preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $name)) {
                $assets[] = [
                    'type' => 'asset',
                    'name' => $name,
                    'title' => $name,
                    'rel' => $childRel,
                ];
            }
        }

        $orderedCore = [];
        foreach ($dirs as $item) {
            $orderedCore[] = [
                'type' => 'dir',
                'name' => $item['name'],
                'title' => $item['title'],
                '_item' => $item,
            ];
        }
        foreach ($mds as $item) {
            $orderedCore[] = [
                'type' => 'file',
                'name' => substr($item['name'], 0, -3),
                'title' => $item['title'],
                '_item' => $item,
            ];
        }
        $sorted = [];
        foreach (self::applyOrder($orderedCore, self::loadOrder($real)) as $row) {
            $sorted[] = $row['_item'];
        }
        usort($assets, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return array_values(array_merge($sorted, $assets));
    }

    /**
     * Breadcrumb segments for admin browse path (root first).
     *
     * @return list<array{rel: string, title: string}>
     */
    public function browseCrumbs(string $relDir): array
    {
        $relDir = trim(str_replace('\\', '/', $relDir), '/');
        $crumbs = [['rel' => '', 'title' => 'Rădăcină']];
        if ($relDir === '') {
            return $crumbs;
        }
        $acc = '';
        foreach (explode('/', $relDir) as $part) {
            $acc = $acc === '' ? $part : $acc . '/' . $part;
            $crumbs[] = ['rel' => $acc, 'title' => $this->titleForRel($acc)];
        }
        return $crumbs;
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
