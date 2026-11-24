<?php

declare(strict_types=1);

namespace WikiMaxx;

/** Shared text folding for search and heading ids. Folds are one codepoint to one codepoint. */
final class Text
{
    /** @var array<string, string> */
    private const FOLD = [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ç' => 'c', 'ñ' => 'n', 'ø' => 'o', 'ł' => 'l', 'đ' => 'd',
    ];

    public static function fold(string $s): string
    {
        return strtr(mb_strtolower($s), self::FOLD);
    }

    public static function slug(string $s): string
    {
        $s = self::fold($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if (strlen($s) > 80) {
            $s = trim(substr($s, 0, 80), '-');
        }
        return $s !== '' ? $s : 'sectiune';
    }
}
