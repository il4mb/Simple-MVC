<?php

namespace Il4mb\Simvc\Systems;

use Il4mb\BlockNode\Node;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Router;

class App extends Cather
{

    protected Router $router;

    function __construct(array $controllers = [])
    {
        parent::__construct();

        $this->router = new Router([], [
            "throwOnDuplicatePath" => false
        ]);
        foreach ($controllers as $controller) {
            $this->router->addRoute($controller);
        }
    }

    /**
     * Load Controller
     * @param string| object $data
     */
    function loadController(string| object $data)
    {

        if (is_string($data)) {
            if (is_file($data)) {
                $className = "\\Il4mb\\Simvc\\Controllers\\" . basename($data, '.php');
                if (class_exists($className)) {
                    $this->router->addRoute(new $className);
                }
            } else if (is_dir($data)) {
                foreach (glob($data . '/*.php', GLOB_BRACE) as $file) {
                    $this->loadController($file);
                }
            }
        } else if (is_object($data)) {
            $this->router->addRoute($data);
        }
    }

    /**
     * App Render
     * @return string
     */
    function render(): string
    {
        $this->response = $this->router->dispatch($this->request);
        $content = $this->response->getContent();
        if ($content instanceof Node) {
            $this->response->setContent($content->render());
        }
        if (empty($content) && $this->response->getCode() == Code::NOT_FOUND && $this->request->isAjax()) {
            $this->response->setCode(404);
            $this->response->setContent([
                "status" => false,
                "message" => "Halaman tidak ditemukan!"
            ]);
        }
        return $this->response->send();
    }
}
