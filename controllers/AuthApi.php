<?php

namespace Il4mb\Simvc\Controllers;

use Exception;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Map\Route;
use Il4mb\Simvc\Systems\Cores\Database;
use Throwable;

class AuthApi
{

    #[Route(path: "/api/v1/auth/login", method: Method::POST)]
    function auth(Request $request, Response $response)
    {
        try {
            $response->setCode(Code::UNAUTHORIZED);
            $email    = $request->get("email");
            $password = $request->get("password");

            if (!empty($email) && !empty($password)) {

                $db = Database::getInstance();
                $users = $db->select(
                    "users",
                    ["id", "password", "email"],
                    ["email" => $email]
                )["rows"];

                if (empty($users)) 
                    throw new Exception("Email tidak ditemukan!", 400);
                if (!password_verify($password, $users[0]['password'])) 
                    throw new Exception("Kata sandi salah!", 400);
                
                $userId = $users[0]["id"];
                setcookie("token", md5($userId), time() + 60 * 60 + 30, "/");
                
                $response->setCode(Code::OK);
                return [
                    "status"  => true,
                    "message" => "Login berhasil"
                ];
            }
        } catch (Throwable $t) {
            $response->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }
}
