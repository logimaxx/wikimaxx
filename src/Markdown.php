<?php

declare(strict_types=1);

namespace WikiMaxx;

use Parsedown;

final class Markdown
{
    /**
     * @return array{title: ?string, description: ?string, body: string, meta: array}
     */
    public static function parseFile(string $path): array
    {
        $raw = (string) file_get_contents($path);
        return self::parse($raw);
    }

    /**
     * @return array{title: ?string, description: ?string, body: string, meta: array}
     */
    public static function parse(string $raw): array
    {
        $meta = [];
        $body = $raw;

        if (preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $raw, $m)) {
            $meta = self::parseFrontmatter($m[1]);
            $body = $m[2];
        }

        $title = isset($meta['title']) && is_string($meta['title']) ? $meta['title'] : null;
        $description = isset($meta['description']) && is_string($meta['description'])
            ? $meta['description']
            : null;

        return [
            'title' => $title,
            'description' => $description,
            'body' => $body,
            'meta' => $meta,
        ];
    }

    public static function toHtml(string $body): string
    {
        $pd = new Parsedown();
        $pd->setSafeMode(true);
        return $pd->text($body);
    }

    /** @return array<string, mixed> */
    private static function parseFrontmatter(string $yaml): array
    {
        $meta = [];
        foreach (preg_split('/\r?\n/', $yaml) ?: [] as $line) {
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k === '' || str_starts_with($k, '#')) {
                continue;
            }
            // Skip nested / list / Astro-only complex keys
            if (str_starts_with($v, '[') || str_starts_with($v, '{') || $v === '|' || $v === '>') {
                continue;
            }
            $v = trim($v, "\"'");
            $meta[$k] = $v;
        }
        return $meta;
    }
}
