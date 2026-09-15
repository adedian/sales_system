<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View tidak ditemukan: {$view}");
        }

        $content = self::capture($viewFile, $data);

        if ($layout === null) {
            echo $content;

            return;
        }

        $layoutFile = __DIR__ . '/../Views/' . $layout . '.php';
        echo self::capture($layoutFile, array_merge($data, ['content' => $content]));
    }

    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;

        return ob_get_clean();
    }
}
