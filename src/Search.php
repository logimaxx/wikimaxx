<?php

declare(strict_types=1);

namespace WikiMaxx;

final class Search
{
    private const MIN_QUERY_LEN = 2;
    private const DEFAULT_LIMIT = 50;
    private const SNIPPET_RADIUS = 70;

    public function __construct(
        private readonly ContentTree $tree,
    ) {
    }

    public static function minQueryLen(): int
    {
        return self::MIN_QUERY_LEN;
    }

    /**
     * Full-text search over Markdown under CONTENT_ROOT, filtered by ACL.
     *
     * @param list<string>|null $userRoles null = guest
     * @return list<array{title: string, href: string, path: string, snippet: string, score: int}>
     */
    public function query(string $q, ?array $userRoles, int $limit = self::DEFAULT_LIMIT): array
    {
        $q = trim($q);
        if (mb_strlen($q) < self::MIN_QUERY_LEN) {
            return [];
        }
        if ($limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }

        $root = $this->tree->root();
        $hits = [];
        $this->walk($root, '', $userRoles, $q, $hits);

        usort($hits, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            return strcasecmp($a['title'], $b['title']);
        });

        return array_slice($hits, 0, $limit);
    }

    /**
     * @param list<string>|null $userRoles
     * @param list<array{title: string, href: string, path: string, snippet: string, score: int}> $hits
     */
    private function walk(string $absDir, string $relDir, ?array $userRoles, string $q, array &$hits): void
    {
        $acl = Acl::resolve($this->tree->root(), $absDir);
        if (!Acl::canRead($acl, $userRoles)) {
            return;
        }

        foreach (scandir($absDir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')
                || $name === '_access.json' || $name === '_order.json') {
                continue;
            }
            $abs = $absDir . '/' . $name;
            $childRel = $relDir === '' ? $name : $relDir . '/' . $name;

            if (is_dir($abs)) {
                $this->walk($abs, $childRel, $userRoles, $q, $hits);
                continue;
            }

            if (!str_ends_with($name, '.md') || !is_file($abs)) {
                continue;
            }

            $hit = $this->matchFile($abs, $childRel, $q);
            if ($hit !== null) {
                $hits[] = $hit;
            }
        }
    }

    /**
     * @return array{title: string, href: string, path: string, snippet: string, score: int}|null
     */
    private function matchFile(string $absPath, string $relMd, string $q): ?array
    {
        $parsed = Markdown::parseFile($absPath);
        $title = $parsed['title'];
        $slug = basename($relMd, '.md');
        if ($title === null || $title === '') {
            $title = ContentTree::humanize($slug === 'index' ? basename(dirname($relMd)) ?: $slug : $slug);
        }

        $description = $parsed['description'] ?? '';
        $body = $parsed['body'];
        $plain = self::plainText($body);

        $score = 0;
        $snippetSource = $plain;
        $matchIn = null;

        if ($title !== '' && mb_stripos($title, $q) !== false) {
            $score += 100;
            $matchIn = 'title';
        }
        if (is_string($description) && $description !== '' && mb_stripos($description, $q) !== false) {
            $score += 50;
            if ($matchIn === null) {
                $matchIn = 'description';
                $snippetSource = $description;
            }
        }
        $bodyPos = mb_stripos($plain, $q);
        if ($bodyPos !== false) {
            $score += 10;
            if ($matchIn === null) {
                $matchIn = 'body';
            }
        }

        if ($score === 0) {
            return null;
        }

        if ($matchIn === 'title') {
            $snippet = is_string($description) && $description !== ''
                ? $description
                : self::snippetAround($plain, $q);
        } elseif ($matchIn === 'description') {
            $snippet = self::snippetAround((string) $description, $q);
        } else {
            $snippet = self::snippetAround($plain, $q);
        }

        return [
            'title' => $title,
            'href' => self::hrefFromRelMd($relMd),
            'path' => self::displayPath($relMd),
            'snippet' => $snippet,
            'score' => $score,
        ];
    }

    /** URL path matching ContentTree nav (index.md → folder trailing slash). */
    public static function hrefFromRelMd(string $relMd): string
    {
        $relMd = str_replace('\\', '/', $relMd);
        $relMd = ltrim($relMd, '/');
        if ($relMd === 'index.md') {
            return '/';
        }
        if (str_ends_with($relMd, '/index.md')) {
            $dir = substr($relMd, 0, -strlen('/index.md'));
            return '/' . $dir . '/';
        }
        if (str_ends_with($relMd, '.md')) {
            return '/' . substr($relMd, 0, -3);
        }
        return '/' . $relMd;
    }

    public static function displayPath(string $relMd): string
    {
        $href = self::hrefFromRelMd($relMd);
        return trim($href, '/') ?: '/';
    }

    public static function plainText(string $markdown): string
    {
        $s = $markdown;
        // Keep fenced/inline code text (unwrap), drop only fence markers
        $s = preg_replace('/```[\w]*\r?\n?([\s\S]*?)```/', ' $1 ', $s) ?? $s;
        $s = preg_replace('/`([^`]+)`/', '$1', $s) ?? $s;
        $s = preg_replace('/!\[[^\]]*\]\([^)]+\)/', ' ', $s) ?? $s;
        $s = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $s) ?? $s;
        $s = preg_replace('/^#{1,6}\s+/m', '', $s) ?? $s;
        $s = preg_replace('/[*_~]+/', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    public static function snippetAround(string $text, string $q, int $radius = self::SNIPPET_RADIUS): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return '';
        }
        $pos = mb_stripos($text, $q);
        if ($pos === false) {
            return mb_strlen($text) > $radius * 2
                ? mb_substr($text, 0, $radius * 2) . '…'
                : $text;
        }
        $start = max(0, $pos - $radius);
        $len = mb_strlen($q) + $radius * 2;
        $snippet = mb_substr($text, $start, $len);
        if ($start > 0) {
            $snippet = '…' . $snippet;
        }
        if ($start + $len < mb_strlen($text)) {
            $snippet .= '…';
        }
        return $snippet;
    }
}
