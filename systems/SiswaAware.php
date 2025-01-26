<?php

namespace Perpus;

use Il4mb\Routing\Http\Request;
use Closure;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Middlewares\Middleware;
use Perpus\Cores\Database;
use Perpus\Cores\Encrypt;
use Throwable;

class SiswaAware implements Middleware
{
    function handle(Request $request, Closure $next): Response
    {

        $token = $request->getCookie('token-siswa', null);
        if (empty($token)) {
            if ($request->getMethod() == Method::GET) {
                return new Response(null, View::render('@siswa/login.twig'));
            } else if ($request->getMethod() == Method::POST) {
                return $this->handleLogin($request);
            }
        }
        return $next($request);
    }

    private function handleLogin(Request $request): Response
    {
        $response = new Response(null, ["status" => false, "message" => "Invalid credentials"], Code::UNAUTHORIZED);
        try {
            $nim    = $request->get("nim");
            $kataSandi = $request->get("password");

            if (!empty($nim) && !empty($kataSandi)) {

                $db = Database::getInstance();
                $users = $db->select(
                    "siswa",
                    ["kata_sandi", "nim"],
                    ["nim" => $nim]
                )["rows"];

                if (empty($users)) {
                    $response->setCode(Code::NOT_FOUND);
                    $response->setContent([
                        "status"  => false,
                        "message" => "Siswa tidak ditemukan!"
                    ]);
                    return $response;
                }

                if (!password_verify($kataSandi, $users[0]['kata_sandi'])) {
                    $response->setCode(Code::BAD_REQUEST);
                    $response->setContent([
                        "status"  => false,
                        "message" => "Kata sandi salah!"
                    ]);
                    return $response;
                }
                $nimSiswa = $users[0]["nim"];
                setcookie(name: "token-siswa", value: Encrypt::encrypt($nimSiswa), expires_or_options: time() + 60 * 60 + 30, path: "/");

                $response->setCode(COde::OK);
                $response->setContent([
                    "status"  => true,
                    "message" => "Masuk berhasil"
                ]);
            }
        } catch (Throwable $t) {
            $response->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            $response->setContent([
                "status" => false,
                "message" => $t->getMessage()
            ]);
        }
        return $response;
    }
}
