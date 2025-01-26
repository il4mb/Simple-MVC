<?php

namespace Il4mb\Simvc\Controllers;

use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Map\Route;
use Il4mb\Simvc\Systems\View;

class PublicController
{

    #[Route(path: "/", method: Method::GET)]
    function index()
    {
        return View::render('@guest/index.twig');
    }

    
}
