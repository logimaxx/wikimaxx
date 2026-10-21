<?php
use WikiMaxx\View;
/** @var list<array> $nav */
/** @var string $base */
/** @var list<array{id: string, title: string, href: string, active: bool}> $librarySwitcher */
$prefix = ($base === '' || $base === '/') ? '' : $base;
$librarySwitcher = $librarySwitcher ?? [];

if ($librarySwitcher !== []) {
    echo '<ul class="lib-switch">';
    foreach ($librarySwitcher as $lib) {
        $cls = !empty($lib['active']) ? ' class="is-active"' : '';
        echo '<li><a' . $cls . ' href="' . View::e($prefix . $lib['href']) . '">'
            . View::e($lib['title']) . '</a></li>';
    }
    echo '</ul>';
}

$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($prefix !== '' && str_starts_with($current, $prefix)) {
    $current = substr($current, strlen($prefix)) ?: '/';
}
$current = '/' . trim(rawurldecode($current), '/');
if ($current === '//') {
    $current = '/';
}

$isActive = static function (string $href) use ($current): bool {
    $h = '/' . trim($href, '/');
    if ($h === '/') {
        return $current === '/';
    }
    return $current === $h || str_starts_with($current, $h . '/');
};

$renderNav = function (array $items, int $depth = 0) use (&$renderNav, $prefix, $isActive): void {
    if ($items === []) {
        return;
    }
    echo '<ul class="nav depth-' . $depth . '">';
    foreach ($items as $item) {
        $href = $prefix . $item['href'];
        $active = $isActive($item['href']);
        $hasChildren = !empty($item['children']);

        if ($hasChildren) {
            echo '<li class="nav-branch' . ($active ? ' is-active' : '') . '">';
            echo '<details' . ($active ? ' open' : '') . '>';
            echo '<summary class="nav-summary"><span class="nav-twist" aria-hidden="true"></span>'
                . View::e($item['title']) . '</summary>';
            $renderNav($item['children'], $depth + 1);
            echo '</details>';
            echo '</li>';
            continue;
        }

        echo '<li class="nav-leaf' . ($active ? ' is-active' : '') . '">';
        echo '<a href="' . View::e($href) . '">' . View::e($item['title']) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
};

$renderNav($nav);
?>
