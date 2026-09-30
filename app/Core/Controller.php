<?php

namespace App\Core;

class Controller
{
    public function view($view, $data = [], $layout = 'main')
    {
        // Extract data to variables
        extract($data);

        // Capture view content
        ob_start();
        $viewFile = APP_DIR . '/Views/' . $view . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            die("View $view not found!");
        }
        $content = ob_get_clean();

        // Include layout
        if ($layout) {
            $layoutFile = APP_DIR . '/Views/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }

    public function redirect($url)
    {
        header('Location: ' . BASE_PATH . '/' . ltrim($url, '/'));
        exit;
    }
}
