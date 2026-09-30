<?php

namespace App\Core;

class App
{
    protected $controller = 'DashboardController';
    protected $method = 'index';
    protected $params = [];

    public function __construct()
    {
    }

    public function run()
    {
        $url = $this->parseUrl();

        if (isset($url[0])) {
            // Strictly allow only a-zA-Z0-9_- for controller names
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $url[0])) {
                $controllerName = ucfirst($url[0]) . 'Controller';
                if (file_exists(APP_DIR . '/Controllers/' . $controllerName . '.php')) {
                    $this->controller = $controllerName;
                    unset($url[0]);
                } else {
                    $this->controller = 'ErrorController';
                    $this->method = 'notFound';
                }
            } else {
                $this->controller = 'ErrorController';
                $this->method = 'notFound';
            }
        }

        $controllerClass = 'App\\Controllers\\' . $this->controller;
        $this->controller = new $controllerClass;

        if (isset($url[1])) {
            // Strictly allow only a-zA-Z0-9_- for method names
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $url[1])) {
                $candidateMethod = $url[1];
                $camelMethod = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $candidateMethod))));
                if (method_exists($this->controller, $candidateMethod)) {
                    $this->method = $candidateMethod;
                    unset($url[1]);
                } elseif (method_exists($this->controller, $camelMethod)) {
                    $this->method = $camelMethod;
                    unset($url[1]);
                } else {
                    // Method specified but not found: strict 404
                    $this->controller = new \App\Controllers\ErrorController();
                    $this->method = 'notFound';
                }
            } else {
                // Invalid characters in method name: strict 404
                $this->controller = new \App\Controllers\ErrorController();
                $this->method = 'notFound';
            }
        }

        $this->params = $url ? array_values($url) : [];

        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    public function parseUrl()
    {
        if (isset($_GET['url'])) {
            $url = rtrim($_GET['url'], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            return explode('/', $url);
        }
        return [];
    }
}
