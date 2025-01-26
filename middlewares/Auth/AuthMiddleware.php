<?php

namespace Il4mb\Simvc\Middlewares\Auth;

use Closure;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Middlewares\Middleware;
use Il4mb\Simvc\Systems\View;

class AuthMiddleware  implements Middleware
{

    function handle(Request $request, Closure $next): Response
    {

        $token = $request->getCookie('token');
        if (empty($token)) {

            if ($request->method == Method::GET) {
                if ($request->isAjax()) {
                    return new Response([
                        "status" => false,
                        "message" => "Invalid credentials"
                    ], Code::UNAUTHORIZED);
                }
                return new Response(View::render('@admin/login.twig'));
            }
            return new Response([
                "status" => false,
                "message" => "Invalid credentials"
            ], Code::UNAUTHORIZED);
        }
        return $next($request);
    }
}
