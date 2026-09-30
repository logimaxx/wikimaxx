<?php

declare(strict_types=1);

use WikiMaxx\Acl;
use WikiMaxx\AdminService;
use WikiMaxx\Auth;
use WikiMaxx\ContentTree;
use WikiMaxx\Csrf;
use WikiMaxx\Markdown;
use WikiMaxx\Search;
use WikiMaxx\View;

$config = require dirname(__DIR__) . '/bootstrap.php';
$auth = new Auth($config);
$tree = new ContentTree($config->contentRoot);
$rootAcl = Acl::resolve($tree->root(), $tree->root());
$siteBrand = (is_string($rootAcl['title'] ?? null) && $rootAcl['title'] !== '')
    ? $rootAcl['title']
    : 'WikiMaxx';
$view = new View(dirname(__DIR__) . '/templates', $siteBrand);
$admin = new AdminService($tree, $auth);

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
        $results = (new Search($tree))->query($q, $roles);
    }
    $view->render('search', [
        'title' => $q !== '' ? ('Căutare: ' . $q) : 'Căutare',
        'user' => $user,
        'nav' => $tree->nav($roles),
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'searchQuery' => $q,
        'q' => $q,
        'results' => $results,
        'tooShort' => $tooShort,
        'minQueryLen' => Search::minQueryLen(),
    ]);
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
        handleUsers($adminPath, $method, $auth, $config, $view, $user, $tree, $roles, $flash, $csrf, $base, $flashSet, $redirect);
        exit;
    }

    if ($adminPath === 'roles' || str_starts_with($adminPath, 'roles/')) {
        if (!$auth->isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        handleRoles($method, $auth, $config, $view, $user, $tree, $roles, $flash, $csrf, $base, $flashSet, $redirect);
        exit;
    }

    if ($method === 'POST') {
        if (!Csrf::validate($config, $_POST['_csrf'] ?? null)) {
            $flashSet('error', 'Sesiune invalidă.');
            $redirect('/admin');
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

    $adminPathQ = static function (string $path, array $extra = []) : string {
        $q = array_merge(['path' => $path], $extra);
        // keep empty path in query for stable redirects
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

    $view->render('admin', [
        'title' => 'Admin conținut',
        'user' => $user,
        'nav' => $tree->nav($roles),
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'browseRel' => $browseRel,
        'browseParent' => $browseParent,
        'browseItems' => $browseItems,
        'browseCrumbs' => $browseCrumbs,
        'adminPathQ' => $adminPathQ,
        'editContent' => $editContent,
        'accessContent' => $accessContent,
        'orderContent' => $orderContent,
        'isAdmin' => $auth->isAdmin(),
    ]);
    exit;
}

// ——— Content / assets ———
$resolved = $tree->resolve($path);
if ($resolved === null) {
    http_response_code(404);
    $view->render('doc', [
        'title' => 'Negăsit',
        'user' => $user,
        'nav' => $tree->nav($roles),
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'html' => '<p>Pagina nu există.</p>',
        'pageTitle' => '404',
        'breadcrumb' => [],
        'canEdit' => false,
        'editRel' => null,
        'orderRel' => null,
    ]);
    exit;
}

// Canonical trailing slash on folder URLs so relative Markdown links resolve correctly.
if ($method === 'GET' && $path !== '' && !$uriHadTrailingSlash) {
    $isFolderUrl = $resolved['type'] === 'dir'
        || ($resolved['type'] === 'file' && str_ends_with($resolved['rel'], '/index.md')
            && $path === str_replace('\\', '/', dirname($resolved['rel'])));
    if ($isFolderUrl) {
        $redirect('/' . $path . '/');
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
    $view->render('doc', [
        'title' => 'Acces interzis',
        'user' => $user,
        'nav' => $tree->nav($roles),
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'html' => '<p>Nu ai dreptul să vezi această pagină.</p>',
        'pageTitle' => '403',
        'breadcrumb' => [],
        'canEdit' => false,
        'editRel' => null,
        'orderRel' => null,
    ]);
    exit;
}

$nav = $tree->nav($roles);
$breadcrumb = breadcrumbFromPath($tree, $path);

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
    $view->render('doc', [
        'title' => $title,
        'user' => $user,
        'nav' => $nav,
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'html' => $html,
        'pageTitle' => $title,
        'breadcrumb' => $breadcrumb,
        'canEdit' => $canEditHere,
        'editRel' => null,
        'orderRel' => $canEditHere ? $resolved['rel'] : null,
    ]);
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

$view->render('doc', [
    'title' => $pageTitle,
    'user' => $user,
    'nav' => $nav,
    'flash' => $flash,
    'csrf' => $csrf,
    'base' => $base,
    'html' => $html,
    'pageTitle' => $pageTitle,
    'breadcrumb' => $breadcrumb,
    'canEdit' => $canEditHere,
    'editRel' => $editRel,
    'orderRel' => $canEditHere ? $parentRel : null,
]);
exit;
/**
 * @return list<array{title: string, href: string}>
 */
function breadcrumbFromPath(ContentTree $tree, string $path): array
{
    $parts = array_values(array_filter(explode('/', trim($path, '/'))));
    $crumbs = [['title' => 'Acasă', 'href' => '/']];
    $acc = '';
    foreach ($parts as $p) {
        $acc .= '/' . $p;
        $crumbs[] = ['title' => $tree->titleForRel(ltrim($acc, '/')), 'href' => $acc];
    }
    return $crumbs;
}

/**
 * @param callable(string): never $redirect
 * @param callable(string, string): void $flashSet
 * @param array{type: string, message: string}|null $flash
 * @param array{id: int, username: string, email: string, roles: list<string>}|null $user
 * @param list<string>|null $roles
 */
function handleUsers(
    string $adminPath,
    string $method,
    Auth $auth,
    mixed $config,
    View $view,
    ?array $user,
    ContentTree $tree,
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
        'nav' => $tree->nav($roles),
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
 * @param list<string>|null $roles
 */
function handleRoles(
    string $method,
    Auth $auth,
    mixed $config,
    View $view,
    ?array $user,
    ContentTree $tree,
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
                    \WikiMaxx\Roles::scanAccessRoles($tree->root())
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
        'nav' => $tree->nav($roles),
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'catalog' => \WikiMaxx\Roles::catalog(),
        'usedUsers' => $auth->usedRoleIds(),
        'usedAcl' => \WikiMaxx\Roles::scanAccessRoles($tree->root()),
    ]);
}
