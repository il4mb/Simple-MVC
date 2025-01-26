<?php

namespace Il4mb\Simvc\Controllers;

use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Map\Route;
use Il4mb\Simvc\Middlewares\Auth\AuthMiddleware;
use Il4mb\Simvc\Systems\View;


class AdminController
{

    #[Route(path: "/admin", method: Method::GET, middlewares: [AuthMiddleware::class])]
    function index()
    {
        return View::render('@admin/index.twig');
    }
}
