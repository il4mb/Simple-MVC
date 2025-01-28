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

            $title     = $request->get("judul");
            $description = $request->get("deskripsi");
            $publisher = $request->get("penerbit");
            $author    = $request->get("penulis");
            $tanggalTerbit = $request->get("tanggal_terbit");
            $link = $request->get("link");

            if (empty($title) || strlen(trim($title)) < 3) {
                throw new Exception("Judul tidak boleh kosong", 400);
            }
            if (empty($link) || !filter_var($link, FILTER_VALIDATE_URL)) {
                throw new Exception("Link tidak valid", 400);
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
            if (empty($tanggalTerbit)) {
                throw new Exception("Tanggal terbit tidak boleh kosong", 400);
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
