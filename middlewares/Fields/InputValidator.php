<?php

namespace Il4mb\Simvc\Middlewares\Fields;

use Closure;
use Exception;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Middlewares\Middleware;
use Throwable;

class InputValidator implements Middleware
{

    public function handle(Request $request, Closure $next): Response
    {

        try {
            
            $title     = $request->get("title");
            $publisher = $request->get("publisher");
            $author    = $request->get("author");
            $description = $request->get("description");

            if (empty($title) || strlen(trim($title)) < 3) {
                throw new Exception("Judul tidak boleh kosong", 400);
            }
            if (empty($publisher) || strlen(trim($publisher)) < 3) {
                throw new Exception("Penerbit tidak boleh kosong", 400);
            }
            if (empty($author) || strlen(trim($author)) < 3) {
                throw new Exception("Penulis tidak boleh kosong", 400);
            }
            if (empty($description) || strlen(trim($description)) < 3) {
                throw new Exception("Deskripsi tidak boleh kosong", 400);
            }

            return $next($request);
        } catch (Throwable $t) {
            return new Response([
                "status"  => false,
                "message" => $t->getMessage()
            ], Code::BAD_REQUEST, [
                "Content-Type" => "application/json"
            ]);
        }
    }
}
