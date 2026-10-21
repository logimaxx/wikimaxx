<?php

declare(strict_types=1);

namespace WikiMaxx;

final class Library
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ContentTree $tree,
    ) {
    }

    /** URL prefix, e.g. `/maxxops`. Empty when this tree is the whole site. */
    public function mount(): string
    {
        return $this->id === '' ? '' : '/' . $this->id;
    }
}
