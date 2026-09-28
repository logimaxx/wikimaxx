<?php

declare(strict_types=1);

use WikiMaxx\Acl;
use WikiMaxx\AdminService;
use WikiMaxx\Auth;
use WikiMaxx\ContentTree;
use WikiMaxx\Csrf;
use WikiMaxx\Markdown;
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
    $editAccess = (string) ($_GET['access'] ?? '');
    $editContent = null;
    $accessContent = null;
    if ($editRel !== '') {
        try {
            $editContent = ['rel' => $editRel, 'body' => $admin->readFile($editRel)];
        } catch (Throwable $e) {
            $flash = ['type' => 'error', 'message' => $e->getMessage()];
        }
    }
    if ($editAccess !== '' && $auth->isAdmin()) {
        $accessRel = trim($editAccess, '/') === ''
            ? '_access.json'
            : trim($editAccess, '/') . '/_access.json';
        $abs = $tree->root() . '/' . $accessRel;
        $accessContent = [
            'rel' => trim($editAccess, '/'),
            'json' => is_file($abs)
                ? (string) file_get_contents($abs)
                : "{\n  \"title\": \"\",\n  \"public\": false,\n  \"roles\": [\"colaborator\", \"admin\"]\n}\n",
        ];
    }

    $view->render('admin', [
        'title' => 'Admin conținut',
        'user' => $user,
        'nav' => $tree->nav($roles),
        'flash' => $flash,
        'csrf' => $csrf,
        'base' => $base,
        'flat' => $tree->flatList(),
        'editContent' => $editContent,
        'accessContent' => $accessContent,
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
    ]);
    exit;
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
    ]);
    exit;
}

$nav = $tree->nav($roles);
$breadcrumb = breadcrumbFromPath($tree, $path);

if ($resolved['type'] === 'dir') {
    $listing = listReadableChildren($tree, $resolved['path'], $resolved['rel'], $roles);
    $folderName = basename($resolved['path']) ?: 'WikiMaxx';
    $title = ContentTree::dirTitle($resolved['path'], $folderName);
    $html = '<h1>' . View::e($title) . '</h1><ul class="dir-list">';
    foreach ($listing as $item) {
        $html .= '<li><a href="' . View::e(($base === '' || $base === '/' ? '' : $base) . $item['href']) . '">'
            . View::e($item['title']) . '</a></li>';
    }
    $html .= '</ul>';
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
        'canEdit' => $auth->canEditContent() && Acl::canWrite($roles, $acl),
        'editRel' => null,
    ]);
    exit;
}

$parsed = Markdown::parseFile($resolved['path']);
$pageTitle = $parsed['title'] ?? ContentTree::humanize(basename($resolved['path'], '.md'));
$html = Markdown::toHtml($parsed['body']);
$editRel = $resolved['rel'];

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
    'canEdit' => $auth->canEditContent() && Acl::canWrite($roles, $acl),
    'editRel' => $editRel,
]);

/**
 * @param list<string>|null $roles
 * @return list<array{title: string, href: string}>
 */
function listReadableChildren(ContentTree $tree, string $abs, string $rel, ?array $roles): array
{
    $items = [];
    foreach (scandir($abs) ?: [] as $name) {
        if ($name === '.' || $name === '..' || str_starts_with($name, '.') || $name === '_access.json') {
            continue;
        }
        $childAbs = $abs . '/' . $name;
        $childRel = $rel === '' ? $name : $rel . '/' . $name;
        if (is_dir($childAbs)) {
            $childAcl = Acl::resolve($tree->root(), $childAbs);
            if (!Acl::canRead($childAcl, $roles)) {
                continue;
            }
            $items[] = [
                'title' => ContentTree::dirTitle($childAbs, $name),
                'href' => '/' . $childRel,
            ];
        } elseif (str_ends_with($name, '.md') && $name !== 'index.md') {
            $meta = Markdown::parseFile($childAbs);
            $slug = substr($name, 0, -3);
            $items[] = [
                'title' => $meta['title'] ?? ContentTree::humanize($slug),
                'href' => '/' . ($rel === '' ? $slug : $rel . '/' . $slug),
            ];
        }
    }
    usort($items, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
    return $items;
}

/** @return list<array{title: string, href: string}> */
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
        'allRoles' => \WikiMaxx\Roles::all(),
    ]);
}
