<?php

declare(strict_types=1);

namespace WikiMaxx;

final class View
{
    public function __construct(
        private readonly string $templatesDir,
        private readonly string $siteBrand = 'WikiMaxx',
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): void
    {
        $data['siteBrand'] ??= $this->siteBrand;
        extract($data, EXTR_SKIP);
        $templatesDir = $this->templatesDir;
        $content = $this->renderPartial($template, $data);
        require $this->templatesDir . '/layout.php';
    }

    /** @param array<string, mixed> $data */
    public function renderPartial(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->templatesDir . '/' . $template . '.php';
        return (string) ob_get_clean();
    }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
