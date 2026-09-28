<?php

declare(strict_types=1);

namespace WikiMaxx;

final class AdminService
{
    public function __construct(
        private readonly ContentTree $tree,
        private readonly Auth $auth,
    ) {
    }

    public function createFolder(string $relParent, string $name): string
    {
        $name = $this->sanitizeName($name);
        $rel = trim($relParent, '/') === '' ? $name : trim($relParent, '/') . '/' . $name;
        $abs = $this->tree->absoluteFromRel($rel);
        if ($abs === null) {
            throw new \InvalidArgumentException('Invalid path');
        }
        if (is_dir($abs)) {
            throw new \RuntimeException('Folder already exists');
        }
        if (!mkdir($abs, 0755, true) && !is_dir($abs)) {
            throw new \RuntimeException('Could not create folder');
        }
        return $rel;
    }

    public function createPage(string $relParent, string $slug, string $title = ''): string
    {
        $slug = $this->sanitizeName($slug);
        $rel = trim($relParent, '/') === '' ? $slug . '.md' : trim($relParent, '/') . '/' . $slug . '.md';
        $abs = $this->tree->absoluteFromRel($rel);
        if ($abs === null) {
            throw new \InvalidArgumentException('Invalid path');
        }
        if (is_file($abs)) {
            throw new \RuntimeException('Page already exists');
        }
        $dir = dirname($abs);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $t = $title !== '' ? $title : ContentTree::humanize($slug);
        $body = "---\ntitle: " . $t . "\n---\n\n# " . $t . "\n\n";
        if (file_put_contents($abs, $body) === false) {
            throw new \RuntimeException('Could not write page');
        }
        return $rel;
    }

    public function savePage(string $rel, string $raw): void
    {
        $abs = $this->requireWritableFile($rel);
        if (!str_ends_with($abs, '.md')) {
            throw new \InvalidArgumentException('Not a markdown file');
        }
        if (file_put_contents($abs, $raw) === false) {
            throw new \RuntimeException('Could not save');
        }
    }

    public function saveAccess(string $relDir, string $json): void
    {
        if (!$this->auth->isAdmin()) {
            throw new \RuntimeException('Only admin can edit ACL');
        }
        $rel = trim($relDir, '/');
        $accessRel = $rel === '' ? '_access.json' : $rel . '/_access.json';
        $abs = $this->tree->absoluteFromRel($accessRel);
        if ($abs === null) {
            // allow creating at root
            $root = $this->tree->root();
            $abs = $rel === '' ? $root . '/_access.json' : $root . '/' . $rel . '/_access.json';
            $parent = dirname($abs);
            $parentReal = realpath($parent);
            if ($parentReal === false || !str_starts_with($parentReal, $root)) {
                throw new \InvalidArgumentException('Invalid path');
            }
            $abs = $parentReal . '/_access.json';
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid JSON');
        }
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($pretty === false || file_put_contents($abs, $pretty . "\n") === false) {
            throw new \RuntimeException('Could not save ACL');
        }
    }

    public function readFile(string $rel): string
    {
        $abs = $this->requireReadableRel($rel);
        return (string) file_get_contents($abs);
    }

    /** @return string relative path of stored file */
    public function uploadImage(string $relDir, array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed');
        }
        $orig = (string) ($file['name'] ?? 'image');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
            throw new \InvalidArgumentException('Unsupported image type');
        }
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($orig, PATHINFO_FILENAME)) ?: 'image';
        $name = $base . '.' . $ext;
        $rel = trim($relDir, '/') === '' ? $name : trim($relDir, '/') . '/' . $name;
        $abs = $this->tree->absoluteFromRel($rel);
        if ($abs === null) {
            throw new \InvalidArgumentException('Invalid path');
        }
        $dir = dirname($abs);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!move_uploaded_file((string) $file['tmp_name'], $abs)) {
            throw new \RuntimeException('Could not store upload');
        }
        return $rel;
    }

    public function deletePath(string $rel): void
    {
        $abs = $this->requireWritableFile($rel);
        if (is_dir($abs)) {
            throw new \InvalidArgumentException('Use empty folder delete carefully — only files supported');
        }
        if (!unlink($abs)) {
            throw new \RuntimeException('Could not delete');
        }
    }

    private function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '-.');
        if ($name === '' || $name === '_access.json' || str_contains($name, '..')) {
            throw new \InvalidArgumentException('Invalid name');
        }
        return $name;
    }

    private function requireReadableRel(string $rel): string
    {
        $root = $this->tree->root();
        $rel = str_replace('\\', '/', trim($rel, '/'));
        if ($rel === '' || str_contains($rel, '..')) {
            throw new \InvalidArgumentException('Invalid path');
        }
        $abs = realpath($root . '/' . $rel);
        if ($abs === false || !str_starts_with($abs, $root) || !is_file($abs)) {
            throw new \InvalidArgumentException('File not found');
        }
        $acl = Acl::resolve($root, dirname($abs));
        if (!Acl::canWrite($this->auth->roles(), $acl) && !$this->auth->isAdmin()) {
            throw new \RuntimeException('Forbidden');
        }
        return $abs;
    }

    private function requireWritableFile(string $rel): string
    {
        return $this->requireReadableRel($rel);
    }
}
