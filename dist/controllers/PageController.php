<?php

namespace Il4mb\Simvc\Controllers;

use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Map\Route;
use Il4mb\Simvc\Systems\View;

class PageController
{

    #[Route(Method::GET, "/")]
    function index($path)
    {
        return View::render("base.twig");
    }

    #[Route(Method::GET, "/{path}")]
    function page($path)
    {

        return View::render("base.twig");
    }
}
