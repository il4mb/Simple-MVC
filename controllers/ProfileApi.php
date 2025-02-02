<?php

namespace Il4mb\Simvc\Controllers;

use Exception;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Map\Route;
use Il4mb\Simvc\Middlewares\Auth\AuthMiddleware;
use Il4mb\Simvc\Systems\Cores\Database;
use Il4mb\Simvc\Systems\Helper;
use Throwable;

class ProfileApi
{

    #[Route(path: "/api/profile", method: Method::GET, middlewares: [AuthMiddleware::class])]
    function index(Request $req, Response $res)
    {
        try {
            $uid = $req->get("uid");
            $db = Database::getInstance();
            $fetch = $db->select(
                table: "users",
                columns: ["id", "nama", "bio", "email", "jenis_kelamin"],
                conditions: ["id" => $uid]
            );
            if (empty($fetch["rows"])) throw new Exception("User tidak ditemukan!", 404);
            $row = $fetch["rows"][0];
            $row['photo'] = Helper::getUploadAsUrlIfExist("profile/" . $row['id'] . ".webp");
            return [
                "status" => true,
                "message" => "Berhasil fetch profile",
                "data" => $row
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/profile/books", method: Method::GET, middlewares: [AuthMiddleware::class])]
    function books(Request $req, Response $res)
    {
        try {

            $uid   = $req->get("uid");
            $page  = $req->get("page") ?? 0;
            $size  = $req->get("size") ?? 100;

            $db = Database::getInstance();
            $result = $db->select(
                table: "reseps A",
                conditions: [
                    "A.user_id" => $uid
                ],
                limit: $size,
                offset: $page * $size,
                count: true,
                order: "B.post_date DESC"
            );

            $rows = array_map(function ($item) {
                $item['image'] = Helper::getUploadAsUrlIfExist("$item[id].webp");
                return $item;
            }, $result["rows"]);

            return [
                "status" => true,
                "message" => "Daftar buku",
                "data" => [
                    "rows" => $rows
                ]
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/profile", method: Method::PUT, middlewares: [AuthMiddleware::class])]
    function update(Request $req, Response $res)
    {
        try {

            $uid = $req->get("uid");
            $nama = $req->get("nama");
            $bio = $req->get("bio");
            $jenis_kelamin = $req->get("jenis_kelamin");
            $image = $req->getFile("image");

            if (empty($nama) || strlen($nama) <= 3) throw new Exception("Nama harus diisi dan minimal 3 karakter!", 400);
            if (!in_array(intval($jenis_kelamin), [1, 2])) throw new Exception("Jenis kelamin tidak valid!", 400);

            $db = Database::getInstance();
            $db->beginTransaction();
            $db->update(
                table: "users",
                params: [
                    "nama" => $nama,
                    "bio" => $bio,
                    "jenis_kelamin" => $jenis_kelamin
                ],
                conditions: ["id" => $uid]
            );

            Helper::uploadFile("profile/" . $uid . ".webp", $image);
            $db->commit();
            $res->setCode(Code::CREATED);
            return [
                "status"  => true,
                "message" => "Update berhasil"
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
