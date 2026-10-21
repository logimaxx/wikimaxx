<?php

declare(strict_types=1);

use WikiMaxx\Acl;
use WikiMaxx\AdminService;
use WikiMaxx\Auth;
use WikiMaxx\ContentTree;
use WikiMaxx\Csrf;
use WikiMaxx\Library;
use WikiMaxx\LibraryRegistry;
use WikiMaxx\Markdown;
use WikiMaxx\Search;
use WikiMaxx\View;

$config = require dirname(__DIR__) . '/bootstrap.php';
$auth = new Auth($config);
$registry = LibraryRegistry::load($config);
if ($registry->multi()) {
    $siteBrand = 'WikiMaxx';
} else {
    $rootTree = $registry->primary()->tree;
    $rootAcl = Acl::resolve($rootTree->root(), $rootTree->root());
    $siteBrand = (is_string($rootAcl['title'] ?? null) && $rootAcl['title'] !== '')
        ? $rootAcl['title']
        : 'WikiMaxx';
}
$view = new View(dirname(__DIR__) . '/templates', $siteBrand);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

$path = trim($uri, '/');
$uriHadTrailingSlash = str_ends_with($uri, '/') && $uri !== '/';
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

$redirect = static function (string $to) use ($base): never {
    $prefix = ($base === '' || $base === '/') ? '' : $base;
    header('Location: ' . $prefix . $to);
    exit;
};

$flashSet = static function (string $type, string $msg): void {
    $_SESSION['_flash'] = ['type' => $type, 'message' => $msg];
};

$user = $auth->user();
$roles = $auth->roles();
$csrf = Csrf::token($config);

$shell = static function (array $data, ?Library $current = null) use (
    &$flash,
    $user,
    $roles,
    $csrf,
    $base,
    $registry
): array {
    $inLibrary = $current !== null && $current->id !== '';
    $data['user'] = $user;
    $data['nav'] = shellNav($registry, $roles, $inLibrary ? $current : null);
    $data['librarySwitcher'] = librarySwitcher($registry, $roles, $inLibrary ? $current : null);
    $data['flash'] = $flash;
    $data['csrf'] = $csrf;
    $data['base'] = $base;
    $data['libraryId'] = $inLibrary ? $current->id : '';
    if ($inLibrary) {
        $data['siteBrand'] = $current->title;
    }
    return $data;
};

// ——— Auth routes ———
if ($path === 'login') {
    if ($method === 'POST') {
        if (!Csrf::validate($config, $_POST['_csrf'] ?? null)) {
            $flashSet('error', 'Sesiune invalidă.');
            $redirect('/login');
        }
        $ok = $auth->login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
        if (!$ok) {
            $flashSet('error', 'Utilizator sau parolă greșită.');
            $redirect('/login');
        }
        $redirect('/');
    }
    $view->render('login', [
        'title' => 'Autentificare',
        'user' => $user,
        'nav' => [],
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
    ]);
    exit;
}

if ($path === 'logout') {
    $auth->logout();
    $redirect('/');
}

// ——— Search ———
if ($path === 'search') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $tooShort = $q !== '' && mb_strlen($q) < Search::minQueryLen();
    $results = [];
    if ($q !== '' && !$tooShort) {
        $results = searchAll($registry, $roles, $q);
    }
    $view->render('search', $shell([
        'title' => $q !== '' ? ('Căutare: ' . $q) : 'Căutare',
        'searchQuery' => $q,
        'q' => $q,
        'results' => $results,
        'tooShort' => $tooShort,
        'minQueryLen' => Search::minQueryLen(),
    ]));
    exit;
}

// ——— Admin ———
if (str_starts_with($path, 'admin')) {
    if (!$auth->canEditContent()) {
        http_response_code(401);
        $flashSet('error', 'Autentificare necesară.');
        $redirect('/login');
    }

    $adminPath = trim(substr($path, strlen('admin')), '/');

    if ($adminPath === 'users' || str_starts_with($adminPath, 'users/')) {
        if (!$auth->isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        handleUsers(
            $adminPath,
            $method,
            $auth,
            $config,
            $view,
            $user,
            shellNav($registry, $roles, null),
            $roles,
            $flash,
            $csrf,
            $base,
            $flashSet,
            $redirect
        );
        exit;
    }

    if ($adminPath === 'roles' || str_starts_with($adminPath, 'roles/')) {
        if (!$auth->isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        handleRoles(
            $method,
            $auth,
            $config,
            $view,
            $user,
            shellNav($registry, $roles, null),
            aclRoleIds($registry),
            $roles,
            $flash,
            $csrf,
            $base,
            $flashSet,
            $redirect
        );
        exit;
    }

    $active = null;
    if ($registry->multi()) {
        $requested = trim((string) ($_POST['lib'] ?? $_GET['lib'] ?? ''));
        $active = $requested === '' ? null : $registry->get($requested);
        if ($active === null) {
            if ($method === 'POST') {
                $flashSet('error', 'Bibliotecă invalidă.');
                $redirect('/admin');
            }
            if ($requested !== '') {
                $flash = ['type' => 'error', 'message' => 'Bibliotecă invalidă.'];
            }
            $choices = [];
            foreach ($registry->all() as $lib) {
                $choices[] = [
                    'id' => $lib->id,
                    'title' => $lib->title,
                    'href' => '/admin?lib=' . rawurlencode($lib->id),
                ];
            }
            $view->render('admin', $shell([
                'title' => 'Admin conținut',
                'chooseLibrary' => true,
                'libraryChoices' => $choices,
            ]));
            exit;
        }
        $tree = $active->tree;
    } else {
        $tree = $registry->primary()->tree;
    }
    $admin = new AdminService($tree, $auth);
    $libraryId = $active !== null ? $active->id : '';

    if ($method === 'POST') {
        if (!Csrf::validate($config, $_POST['_csrf'] ?? null)) {
            $flashSet('error', 'Sesiune invalidă.');
            $redirect($libraryId === '' ? '/admin' : '/admin?lib=' . rawurlencode($libraryId));
        }
        $action = (string) ($_POST['action'] ?? '');
        try {
            match ($action) {
                'create_folder' => $admin->createFolder((string) ($_POST['parent'] ?? ''), (string) ($_POST['name'] ?? '')),
                'create_page' => $admin->createPage(
                    (string) ($_POST['parent'] ?? ''),
                    (string) ($_POST['slug'] ?? ''),
                    (string) ($_POST['title'] ?? '')
                ),
                'save_page' => $admin->savePage((string) ($_POST['rel'] ?? ''), (string) ($_POST['body'] ?? '')),
                'save_access' => $admin->saveAccess((string) ($_POST['rel'] ?? ''), (string) ($_POST['json'] ?? '')),
                'save_order' => $admin->saveOrder(
                    (string) ($_POST['rel'] ?? ''),
                    array_values(array_filter((array) ($_POST['names'] ?? []), 'is_string'))
                ),
                'clear_order' => $admin->clearOrder((string) ($_POST['rel'] ?? '')),
                'delete' => $admin->deletePath((string) ($_POST['rel'] ?? '')),
                'upload' => $admin->uploadImage((string) ($_POST['parent'] ?? ''), $_FILES['file'] ?? []),
                default => throw new RuntimeException('Unknown action'),
            };
            $flashSet('ok', 'Salvat.');
        } catch (Throwable $e) {
            $flashSet('error', $e->getMessage());
        }
        $back = (string) ($_POST['redirect'] ?? '/admin');
        if (!str_starts_with($back, '/')) {
            $back = '/admin';
        }
        $redirect($back);
    }

    $editRel = (string) ($_GET['edit'] ?? '');
    $showAccess = array_key_exists('access', $_GET);
    $showOrder = array_key_exists('order', $_GET);
    $editContent = null;
    $accessContent = null;
    $orderContent = null;

    $browseRel = array_key_exists('path', $_GET)
        ? trim(str_replace('\\', '/', (string) $_GET['path']), '/')
        : null;

    // Legacy / shortcut: ?order=folder or ?access=folder without path=
    if ($browseRel === null && $showOrder) {
        $ov = trim(str_replace('\\', '/', (string) $_GET['order']), '/');
        if ($ov !== '' && $ov !== '1') {
            $browseRel = $ov;
        }
    }
    if ($browseRel === null && $showAccess) {
        $av = trim(str_replace('\\', '/', (string) $_GET['access']), '/');
        if ($av !== '' && $av !== '1') {
            $browseRel = $av;
        }
    }
    if ($browseRel === null && $editRel !== '') {
        $parent = dirname(str_replace('\\', '/', $editRel));
        $browseRel = ($parent === '.' || $parent === '/') ? '' : $parent;
    }
    if ($browseRel === null) {
        $browseRel = '';
    }

    $browseItems = [];
    $browseCrumbs = [['rel' => '', 'title' => 'Rădăcină']];
    try {
        $browseItems = $tree->browseLevel($browseRel);
        $browseCrumbs = $tree->browseCrumbs($browseRel);
    } catch (Throwable $e) {
        $browseRel = '';
        $browseItems = $tree->browseLevel('');
        $browseCrumbs = $tree->browseCrumbs('');
        $flash = ['type' => 'error', 'message' => $e->getMessage()];
    }

    $adminPathQ = static function (string $folder, array $extra = []) use ($libraryId): string {
        $q = array_merge(['path' => $folder], $extra);
        if ($libraryId !== '') {
            $q = ['lib' => $libraryId] + $q;
        }
        return '/admin?' . http_build_query($q);
    };

    if ($editRel !== '') {
        try {
            $editContent = ['rel' => $editRel, 'body' => $admin->readFile($editRel)];
        } catch (Throwable $e) {
            $flash = ['type' => 'error', 'message' => $e->getMessage()];
        }
    }
    if ($showAccess && $auth->isAdmin()) {
        $accessRel = $browseRel;
        $accessFile = $accessRel === '' ? '_access.json' : $accessRel . '/_access.json';
        $abs = $tree->root() . '/' . $accessFile;
        $accessContent = [
            'rel' => $accessRel,
            'json' => is_file($abs)
                ? (string) file_get_contents($abs)
                : "{\n  \"title\": \"\",\n  \"public\": false,\n  \"roles\": [\"colaborator\", \"admin\"]\n}\n",
        ];
    }
    if ($showOrder) {
        try {
            $orderContent = [
                'rel' => $browseRel,
                'items' => $tree->orderableChildren($browseRel),
                'manual' => ContentTree::loadOrder(
                    $browseRel === '' ? $tree->root() : $tree->root() . '/' . $browseRel
                ) !== null,
            ];
        } catch (Throwable $e) {
            $flash = ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    $browseParent = $browseRel === '' ? null : (dirname($browseRel) === '.' ? '' : dirname($browseRel));
    $current = ($active !== null && $active->id !== '') ? $active : null;

    $view->render('admin', $shell([
        'title' => 'Admin conținut',
        'chooseLibrary' => false,
        'browseRel' => $browseRel,
        'browseParent' => $browseParent,
        'browseItems' => $browseItems,
        'browseCrumbs' => $browseCrumbs,
        'adminPathQ' => $adminPathQ,
        'editContent' => $editContent,
        'accessContent' => $accessContent,
        'orderContent' => $orderContent,
        'isAdmin' => $auth->isAdmin(),
        'mount' => $tree->mount(),
        'libraryTitle' => $current !== null ? $current->title : '',
    ], $current));
    exit;
}

// ——— Catalog ———
if ($registry->multi() && $path === '') {
    $items = [];
    foreach ($registry->readable($roles) as $lib) {
        $items[] = ['title' => $lib->title, 'href' => '/' . $lib->id . '/'];
    }
    $view->render('catalog', $shell([
        'title' => 'Biblioteci',
        'libraries' => $items,
    ]));
    exit;
}

// ——— Content / assets ———
$active = null;
$contentPath = $path;
if ($registry->multi()) {
    $parts = explode('/', $path, 2);
    $active = $registry->get($parts[0]);
    if ($active === null) {
        http_response_code(404);
        $view->render('doc', $shell([
            'title' => 'Negăsit',
            'html' => '<p>Pagina nu există.</p>',
            'pageTitle' => '404',
            'breadcrumb' => [],
            'canEdit' => false,
            'editRel' => null,
            'orderRel' => null,
        ]));
        exit;
    }
    $contentPath = $parts[1] ?? '';
    $tree = $active->tree;
} else {
    $tree = $registry->primary()->tree;
}

$resolved = $tree->resolve($contentPath);
$current = ($active !== null && $active->id !== '') ? $active : null;
if ($resolved === null) {
    http_response_code(404);
    $view->render('doc', $shell([
        'title' => 'Negăsit',
        'html' => '<p>Pagina nu există.</p>',
        'pageTitle' => '404',
        'breadcrumb' => [],
        'canEdit' => false,
        'editRel' => null,
        'orderRel' => null,
    ], $current));
    exit;
}

// Canonical trailing slash on folder URLs so relative Markdown links resolve correctly.
if ($method === 'GET' && !$uriHadTrailingSlash) {
    $isLibraryRoot = $current !== null && $contentPath === '';
    $isFolderUrl = $isLibraryRoot
        || $resolved['type'] === 'dir'
        || ($resolved['type'] === 'file' && str_ends_with($resolved['rel'], '/index.md')
            && $contentPath === str_replace('\\', '/', dirname($resolved['rel'])));
    if ($isFolderUrl && ($contentPath !== '' || $isLibraryRoot)) {
        $mount = $current !== null ? $current->mount() : '';
        $redirect($mount . ($contentPath === '' ? '/' : '/' . $contentPath . '/'));
    }
}

if ($resolved['type'] === 'asset') {
    $acl = Acl::resolve($tree->root(), dirname($resolved['path']));
    if (!Acl::canRead($acl, $roles)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    $mime = mime_content_type($resolved['path']) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($resolved['path']));
    readfile($resolved['path']);
    exit;
}

$aclPath = $resolved['type'] === 'file' ? dirname($resolved['path']) : $resolved['path'];
$acl = Acl::resolve($tree->root(), $aclPath);
if (!Acl::canRead($acl, $roles)) {
    http_response_code($user ? 403 : 401);
    if (!$user) {
        $flashSet('error', 'Această zonă necesită autentificare.');
        $redirect('/login');
    }
    $view->render('doc', $shell([
        'title' => 'Acces interzis',
        'html' => '<p>Nu ai dreptul să vezi această pagină.</p>',
        'pageTitle' => '403',
        'breadcrumb' => [],
        'canEdit' => false,
        'editRel' => null,
        'orderRel' => null,
    ], $current));
    exit;
}

$breadcrumb = breadcrumbFromPath($tree, $contentPath, $current?->mount() ?? '', $current?->title ?? '');

if ($resolved['type'] === 'dir') {
    $listing = $tree->listChildren($resolved['path'], $resolved['rel'], $roles);
    $folderName = basename($resolved['path']) ?: 'WikiMaxx';
    $title = ContentTree::dirTitle($resolved['path'], $folderName);
    $html = '<h1>' . View::e($title) . '</h1><ul class="dir-list">';
    foreach ($listing as $item) {
        $html .= '<li><a href="' . View::e(($base === '' || $base === '/' ? '' : $base) . $item['href']) . '">'
            . View::e($item['title']) . '</a></li>';
    }
    $html .= '</ul>';
    $canEditHere = $auth->canEditContent() && Acl::canWrite($roles, $acl);
    $view->render('doc', $shell([
        'title' => $title,
        'html' => $html,
        'pageTitle' => $title,
        'breadcrumb' => $breadcrumb,
        'canEdit' => $canEditHere,
        'editRel' => null,
        'orderRel' => $canEditHere ? $resolved['rel'] : null,
    ], $current));
    exit;
}

$parsed = Markdown::parseFile($resolved['path']);
$pageTitle = $parsed['title'] ?? ContentTree::humanize(basename($resolved['path'], '.md'));
$html = Markdown::toHtml($parsed['body']);
$editRel = $resolved['rel'];
$canEditHere = $auth->canEditContent() && Acl::canWrite($roles, $acl);
$parentRel = dirname(str_replace('\\', '/', $editRel));
if ($parentRel === '.' || $parentRel === '/') {
    $parentRel = '';
}

$view->render('doc', $shell([
    'title' => $pageTitle,
    'html' => $html,
    'pageTitle' => $pageTitle,
    'breadcrumb' => $breadcrumb,
    'canEdit' => $canEditHere,
    'editRel' => $editRel,
    'orderRel' => $canEditHere ? $parentRel : null,
], $current));
exit;

/**
 * @param list<string>|null $roles
 * @return list<array{type: string, name: string, title: string, href: string}>
 */
function shellNav(LibraryRegistry $registry, ?array $roles, ?Library $current): array
{
    if ($current !== null) {
        return $current->tree->nav($roles);
    }
    if (!$registry->multi()) {
        return $registry->primary()->tree->nav($roles);
    }
    $nav = [];
    foreach ($registry->readable($roles) as $lib) {
        $nav[] = [
            'type' => 'file',
            'name' => $lib->id,
            'title' => $lib->title,
            'href' => '/' . $lib->id . '/',
        ];
    }
    return $nav;
}

/**
 * @param list<string>|null $roles
 * @return list<array{id: string, title: string, href: string, active: bool}>
 */
function librarySwitcher(LibraryRegistry $registry, ?array $roles, ?Library $current): array
{
    if (!$registry->multi() || $current === null) {
        return [];
    }
    $out = [];
    foreach ($registry->readable($roles) as $lib) {
        $out[] = [
            'id' => $lib->id,
            'title' => $lib->title,
            'href' => '/' . $lib->id . '/',
            'active' => $lib->id === $current->id,
        ];
    }
    return $out;
}

/**
 * @param list<string>|null $roles
 * @return list<array{title: string, href: string, path: string, snippet: string, score: int, library: string}>
 */
function searchAll(LibraryRegistry $registry, ?array $roles, string $q): array
{
    $libs = $registry->multi() ? $registry->readable($roles) : [$registry->primary()];
    $hits = [];
    foreach ($libs as $lib) {
        $label = $registry->multi() ? $lib->title : '';
        foreach ((new Search($lib->tree, $label))->query($q, $roles, 10000) as $hit) {
            $hits[] = $hit;
        }
    }
    usort($hits, static function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        return strcasecmp($a['title'], $b['title']);
    });
    return array_slice($hits, 0, 50);
}

/** @return list<string> */
function aclRoleIds(LibraryRegistry $registry): array
{
    $found = [];
    foreach ($registry->all() as $lib) {
        foreach (\WikiMaxx\Roles::scanAccessRoles($lib->tree->root()) as $id) {
            $found[$id] = $id;
        }
    }
    return array_values($found);
}

/**
 * @return list<array{title: string, href: string}>
 */
function breadcrumbFromPath(ContentTree $tree, string $path, string $mount = '', string $libraryTitle = ''): array
{
    $crumbs = [['title' => 'Acasă', 'href' => '/']];
    if ($mount !== '') {
        $crumbs[] = [
            'title' => $libraryTitle !== '' ? $libraryTitle : trim($mount, '/'),
            'href' => $mount . '/',
        ];
    }
    $parts = array_values(array_filter(explode('/', trim($path, '/'))));
    $acc = $mount;
    foreach ($parts as $p) {
        $acc .= '/' . $p;
        $rel = $mount === '' ? ltrim($acc, '/') : ltrim(substr($acc, strlen($mount)), '/');
        $crumbs[] = ['title' => $tree->titleForRel($rel), 'href' => $acc];
    }
    return $crumbs;
}

/**
 * @param callable(string): never $redirect
 * @param callable(string, string): void $flashSet
 * @param array{type: string, message: string}|null $flash
 * @param array{id: int, username: string, email: string, roles: list<string>}|null $user
 * @param list<array{type: string, name: string, title: string, href: string}> $nav
 * @param list<string>|null $roles
 */
function handleUsers(
    string $adminPath,
    string $method,
    Auth $auth,
    mixed $config,
    View $view,
    ?array $user,
    array $nav,
    ?array $roles,
    ?array $flash,
    string $csrf,
    string $base,
    callable $flashSet,
    callable $redirect,
): void {
    if ($method === 'POST') {
        if (!Csrf::validate($config, $_POST['_csrf'] ?? null)) {
            $flashSet('error', 'Sesiune invalidă.');
            $redirect('/admin/users');
        }
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $rolesIn = array_values(array_filter((array) ($_POST['roles'] ?? []), 'is_string'));
                $auth->createUser(
                    trim((string) ($_POST['username'] ?? '')),
                    trim((string) ($_POST['email'] ?? '')),
                    (string) ($_POST['password'] ?? ''),
                    $rolesIn
                );
            } elseif ($action === 'update') {
                $rolesIn = array_values(array_filter((array) ($_POST['roles'] ?? []), 'is_string'));
                $auth->updateUser(
                    (int) ($_POST['id'] ?? 0),
                    trim((string) ($_POST['email'] ?? '')),
                    $rolesIn,
                    (string) ($_POST['password'] ?? '') ?: null
                );
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id === ($user['id'] ?? 0)) {
                    throw new RuntimeException('Nu te poți șterge pe tine.');
                }
                $auth->deleteUser($id);
            }
            $flashSet('ok', 'Salvat.');
        } catch (Throwable $e) {
            $flashSet('error', $e->getMessage());
        }
        $redirect('/admin/users');
    }

    $view->render('users', [
        'title' => 'Utilizatori',
        'user' => $user,
        'nav' => $nav,
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'users' => $auth->allUsers(),
        'allRoles' => \WikiMaxx\Roles::catalog(),
    ]);
}

/**
 * @param callable(string): never $redirect
 * @param callable(string, string): void $flashSet
 * @param array{type: string, message: string}|null $flash
 * @param array{id: int, username: string, email: string, roles: list<string>}|null $user
 * @param list<array{type: string, name: string, title: string, href: string}> $nav
 * @param list<string> $usedAcl
 * @param list<string>|null $roles
 */
function handleRoles(
    string $method,
    Auth $auth,
    mixed $config,
    View $view,
    ?array $user,
    array $nav,
    array $usedAcl,
    ?array $roles,
    ?array $flash,
    string $csrf,
    string $base,
    callable $flashSet,
    callable $redirect,
): void {
    if ($method === 'POST') {
        if (!Csrf::validate($config, $_POST['_csrf'] ?? null)) {
            $flashSet('error', 'Sesiune invalidă.');
            $redirect('/admin/roles');
        }
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $cap = trim((string) ($_POST['capability'] ?? ''));
                \WikiMaxx\Roles::create(
                    (string) ($_POST['id'] ?? ''),
                    (string) ($_POST['label'] ?? ''),
                    $cap === 'write' ? 'write' : null
                );
            } elseif ($action === 'update') {
                $cap = trim((string) ($_POST['capability'] ?? ''));
                \WikiMaxx\Roles::update(
                    (string) ($_POST['id'] ?? ''),
                    (string) ($_POST['label'] ?? ''),
                    $cap === 'write' ? 'write' : null
                );
            } elseif ($action === 'delete') {
                \WikiMaxx\Roles::delete(
                    (string) ($_POST['id'] ?? ''),
                    $auth->usedRoleIds(),
                    $usedAcl
                );
            }
            $flashSet('ok', 'Salvat.');
        } catch (Throwable $e) {
            $flashSet('error', $e->getMessage());
        }
        $redirect('/admin/roles');
    }

    $view->render('roles', [
        'title' => 'Roluri',
        'user' => $user,
        'nav' => $nav,
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'catalog' => \WikiMaxx\Roles::catalog(),
        'usedUsers' => $auth->usedRoleIds(),
        'usedAcl' => $usedAcl,
    ]);
}
