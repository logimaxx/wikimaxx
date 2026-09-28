<?php

declare(strict_types=1);

/**
 * One-shot: copy seed (or another source tree) into CONTENT_ROOT and write ACL files.
 *
 * Usage:
 *   php bin/migrate-content.php [sourceDir]
 * Default source: seed/
 *
 * For production sites, point CONTENT_ROOT at your content repo and skip this,
 * or pass that repo path as sourceDir once.
 */

$root = dirname(__DIR__);
$config = require $root . '/bootstrap.php';

$source = $argv[1] ?? null;
if ($source === null) {
    foreach ([$root . '/seed'] as $candidate) {
        if (is_dir($candidate)) {
            $source = $candidate;
            break;
        }
    }
}

if ($source === null || !is_dir($source)) {
    fwrite(STDERR, "Source docs directory not found. Pass path: php bin/migrate-content.php /path/to/docs\n");
    exit(1);
}

$dest = $config->contentRoot;
if (!is_dir($dest)) {
    mkdir($dest, 0755, true);
}

$copied = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, ['md', 'mdx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'json'], true)) {
        continue;
    }

    $rel = substr($file->getPathname(), strlen(rtrim($source, '/')) + 1);
    $rel = preg_replace('/\.mdx$/i', '.md', $rel) ?? $rel;
    $out = $dest . '/' . $rel;
    $outdir = dirname($out);
    if (!is_dir($outdir)) {
        mkdir($outdir, 0755, true);
    }

    if (in_array($ext, ['md', 'mdx'], true)) {
        $raw = (string) file_get_contents($file->getPathname());
        $raw = stripFrontmatterNoise($raw);
        file_put_contents($out, $raw);
    } else {
        copy($file->getPathname(), $out);
    }
    $copied++;
}

// Root ACL if missing
if (!is_file($dest . '/_access.json')) {
    writeJson($dest . '/_access.json', [
        'title' => 'WikiMaxx',
        'public' => true,
        'roles' => [],
    ]);
}

// Demo private zone
$internal = $dest . '/internal';
if (is_dir($internal) && !is_file($internal . '/_access.json')) {
    writeJson($internal . '/_access.json', [
        'title' => 'Internal',
        'public' => false,
        'roles' => ['colaborator', 'admin'],
    ]);
}

if (!is_file($dest . '/index.md')) {
    $home = <<<'MD'
---
title: WikiMaxx
description: Flat-file wiki demo
---

# WikiMaxx

Welcome. Point `CONTENT_ROOT` at your own Markdown tree for production.

- [Getting started](/getting-started)
- [Internal (login)](/internal)
MD;
    file_put_contents($dest . '/index.md', $home);
}

echo "Migrated {$copied} files into {$dest}\n";

function writeJson(string $path, array $data): void
{
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

function stripFrontmatterNoise(string $raw): string
{
    if (!preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $raw, $m)) {
        return preg_replace('/^import\s+.+$/m', '', $raw) ?? $raw;
    }
    $fmLines = [];
    foreach (preg_split('/\r?\n/', $m[1]) ?: [] as $line) {
        if (preg_match('/^(title|description)\s*:/', $line)) {
            $fmLines[] = $line;
        }
    }
    $body = $m[2];
    $body = preg_replace('/^import\s+.+$/m', '', $body) ?? $body;
    $body = preg_replace('/\n{3,}/', "\n\n", $body) ?? $body;
    $fm = $fmLines !== [] ? "---\n" . implode("\n", $fmLines) . "\n---\n\n" : '';
    return $fm . ltrim($body);
}
