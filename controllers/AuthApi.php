<?php

namespace Il4mb\Simvc\Controllers;

use Exception;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Map\Route;
use Il4mb\Simvc\Systems\Cores\Database;
use Il4mb\Simvc\Systems\Cores\Encrypt;
use Throwable;

class AuthApi
{

    #[Route(path: "/api/auth/login", method: Method::POST)]
    function auth(Request $request, Response $response)
    {
        try {
            $response->setCode(Code::UNAUTHORIZED);
            $email    = $request->get("email");
            $password = $request->get("password");
            if (!empty($email) && !empty($password)) {
                $db = Database::getInstance();
                $users = $db->select("users", ["id", "password", "email"], ["email" => $email])["rows"];
                if (empty($users)) throw new Exception("Email tidak ditemukan!", 400);
                if (!password_verify($password, $users[0]['password'])) throw new Exception("Kata sandi salah!", 400);
                $userId = $users[0]["id"];
                setcookie("token", Encrypt::encrypt($userId), time() + 60 * 60 + 120, "/");
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

    #[Route(path: "/api/auth/logout", method: Method::POST)]
    function logout()
    {
        setcookie("token", "", 0, "/");
        return [
            "status"  => true,
            "message" => "Logout berhasil"
        ];
    }

    #[Route(path: "/api/auth/register", method: Method::POST)]
    function register(Request $req, Response $res)
    {
        try {
            $email    = trim($req->get("email") ?? "");
            $password = trim($req->get("password") ?? "");
            $name     = trim($req->get("name") ?? "");
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Email tidak valid!", 400);
            if (empty($email) || empty($password) || empty($name)) throw new Exception("Semua field harus diisi!", 400);
            if (strlen($password) < 6) throw new Exception("Password harus minimal 6 karakter!", 400);

            $db = Database::getInstance();
            $existingUser = $db->select(table: "users", conditions: ["email" => $email]);
            if (!empty($existingUser["rows"])) throw new Exception("Email sudah terdaftar!", 409);
            $db->insert(
                table: "users",
                params: [
                    "email"    => $email,
                    "password" => password_hash($password, PASSWORD_DEFAULT),
                    "nama"     => $name
                ]
            );
            $res->setCode(201);
            return [
                "status"  => true,
                "message" => "Register berhasil"
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }
}
