<?php

declare(strict_types=1);

namespace MailPanel\Support;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function render(string $template, array $data = []): string
    {
        $path = $this->basePath . '/' . ltrim($template, '/');

        if (!is_file($path)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}
