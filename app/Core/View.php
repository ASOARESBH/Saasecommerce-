<?php
namespace App\Core;

class View {
    public static function render(string $view, array $data = [], string $layout = 'app'): void {
        extract($data);
        $viewPath = __DIR__ . "/../Views/{$view}.php";

        if (!file_exists($viewPath)) {
            throw new \Exception("View nao encontrada: {$view}");
        }

        ob_start();
        try {
            require $viewPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $content = ob_get_clean();

        $headerPath = __DIR__ . "/../Views/layout/{$layout}_header.php";
        $footerPath = __DIR__ . "/../Views/layout/{$layout}_footer.php";

        if (file_exists($headerPath)) require $headerPath;
        echo $content;
        if (file_exists($footerPath)) require $footerPath;
    }
}
