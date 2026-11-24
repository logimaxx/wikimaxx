<?php

declare(strict_types=1);

namespace WikiMaxx;

use Highlight\Highlighter;

final class Markdown
{
    /** @var array<string, string> */
    private const LANG_ALIAS = [
        'js' => 'javascript',
        'ts' => 'typescript',
        'tsx' => 'typescript',
        'jsx' => 'javascript',
        'sh' => 'bash',
        'shell' => 'bash',
        'zsh' => 'bash',
        'console' => 'bash',
        'yml' => 'yaml',
        'md' => 'markdown',
        'py' => 'python',
        'rb' => 'ruby',
        'html' => 'xml',
        'htm' => 'xml',
        'svg' => 'xml',
        'c++' => 'cpp',
        'c#' => 'csharp',
        'f#' => 'fsharp',
    ];

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
        return self::render($body)['html'];
    }

    /**
     * @return array{html: string, toc: list<array{level: int, id: string, text: string}>}
     */
    public static function render(string $body): array
    {
        $parser = new DocParser();
        $html = $parser->text($body);
        $toc = [];
        $html = self::decorateHeadings($html, $toc);
        $html = self::highlightCode($html);
        return ['html' => $html, 'toc' => $toc];
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

    /**
     * @param list<array{level: int, id: string, text: string}> $toc
     */
    private static function decorateHeadings(string $html, array &$toc): string
    {
        $used = [];
        $out = preg_replace_callback(
            '#<h([1-6])>(.*?)</h\1>#s',
            static function (array $m) use (&$used, &$toc): string {
                $level = (int) $m[1];
                $inner = $m[2];
                $plain = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
                $base = Text::slug($plain);
                $id = $base;
                $n = 2;
                while (isset($used[$id])) {
                    $id = $base . '-' . $n;
                    $n++;
                }
                $used[$id] = true;
                if ($level >= 2 && $level <= 4 && $plain !== '') {
                    $toc[] = ['level' => $level, 'id' => $id, 'text' => $plain];
                }
                $anchor = '<a class="heading-anchor" href="#' . $id . '" aria-hidden="true" tabindex="-1">#</a>';
                return '<h' . $level . ' id="' . $id . '">' . $anchor . $inner . '</h' . $level . '>';
            },
            $html
        );
        return $out ?? $html;
    }

    private static function highlightCode(string $html): string
    {
        if (!str_contains($html, '<pre>')) {
            return $html;
        }
        $hl = new Highlighter();
        $out = preg_replace_callback(
            '#<pre><code(?: class="language-([^"]*)")?>(.*?)</code></pre>#s',
            static function (array $m) use ($hl): string {
                $rawLang = strtolower(html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $code = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($rawLang === '' || $rawLang === 'text' || $rawLang === 'txt' || $rawLang === 'plaintext') {
                    return $m[0];
                }
                $lang = self::LANG_ALIAS[$rawLang] ?? $rawLang;
                if (!preg_match('/^[a-z0-9_+#-]+$/', $lang)) {
                    return $m[0];
                }
                try {
                    $highlighted = $hl->highlight($lang, $code);
                } catch (\Throwable) {
                    return $m[0];
                }
                $class = htmlspecialchars($lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<pre><code class="hljs language-' . $class . '">' . $highlighted->value . '</code></pre>';
            },
            $html
        );
        return $out ?? $html;
    }
}
