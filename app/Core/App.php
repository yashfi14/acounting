<?php

class App {
    protected $controller = 'DashboardController';
    protected $method = 'index';
    protected $params = [];

    public function __construct()
    {
        $url = $this->parseURL();

        // Cek Controller
        if(isset($url[0])) {
            $controllerName = ucfirst($url[0]) . 'Controller';
            if(file_exists('../app/Controllers/' . $controllerName . '.php')) {
                $this->controller = $controllerName;
                unset($url[0]);
            } else {
                $this->controller = 'ErrorController';
                $this->method = 'notFound';
                unset($url[0]);
            }
        }

        require_once '../app/Controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        // Cek Method
        if(isset($url[1])) {
            $methodName = $url[1];
            $reserved = ['model', 'view', '__construct', '__destruct', '__call', '__get', '__set'];
            if(!in_array(strtolower($methodName), $reserved, true) && is_callable([$this->controller, $methodName])) {
                $this->method = $methodName;
                unset($url[1]);
            } else {
                require_once '../app/Controllers/ErrorController.php';
                $this->controller = new ErrorController;
                $this->method = 'notFound';
                unset($url[1]);
            }
        }

        // Params
        if(!empty($url)) {
            $this->params = array_values($url);
        }

        // Jalankan controller & method, serta kirimkan params jika ada
        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    public function parseURL()
    {
        if(isset($_GET['url'])) {
            $url = rtrim($_GET['url'], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $url = explode('/', $url);
            return $url;
        }
    }
}
