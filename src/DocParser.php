<?php

declare(strict_types=1);

namespace WikiMaxx;

/** Parsedown plus GitHub-style callouts: `> [!NOTE]`. */
final class DocParser extends \Parsedown
{
    public function __construct()
    {
        $this->setSafeMode(true);
    }

    /**
     * @param array<string, mixed> $Line
     * @return array<string, mixed>|null
     */
    protected function blockQuote($Line)
    {
        $block = parent::blockQuote($Line);
        if ($block === null) {
            return null;
        }
        $lines = $block['element']['handler']['argument'] ?? null;
        if (!is_array($lines) || !isset($lines[0]) || !is_string($lines[0])) {
            return $block;
        }
        if (!preg_match('/^\[!(NOTE|TIP|WARNING|IMPORTANT|CAUTION)\][ \t]*(.*)$/i', $lines[0], $m)) {
            return $block;
        }
        $type = strtolower($m[1]);
        $block['element']['attributes']['class'] = 'callout callout-' . $type;
        if ($m[2] === '') {
            array_shift($lines);
        } else {
            $lines[0] = $m[2];
        }
        $block['element']['handler']['argument'] = $lines;
        return $block;
    }
}
