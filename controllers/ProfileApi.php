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
use Symfony\Component\Filesystem\Path;
use Throwable;

class ProfileApi
{

    private function getBukuImage($kode)
    {
        $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
        $port     = $_SERVER['SERVER_PORT'];
        $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);

        $offset = dirname($_SERVER['SCRIPT_NAME'] ?? "");
        $root = preg_replace("/\\\\/m", "/", realpath(__DIR__ . "/../"));
        $path = preg_replace("/\\\\|\\\\\/\\\\\//m", "/", $root . "/uploads/" . intval($kode) . ".webp");
        if (file_exists($path)) {
            return Path::join($hostLink, $offset,  str_replace($root, "", $path)) .  "?v=" . filemtime(filename: $path);
        }
        return null;
    }

    private function getProfileImage($kode)
    {
        $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
        $port     = $_SERVER['SERVER_PORT'];
        $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);

        $offset = dirname($_SERVER['SCRIPT_NAME'] ?? "");
        $root = preg_replace("/\\\\/m", "/", realpath(__DIR__ . "/../"));
        $path = preg_replace("/\\\\|\\\\\/\\\\\//m", "/", $root . "/uploads/profile/$kode.webp");

        if (file_exists($path)) {
            return Path::join($hostLink, $offset,  str_replace($root, "", $path)) .  "?v=" . filemtime(filename: $path);
        }
        return null;
    }


    #[Route(path: "/api/v1/profile", method: Method::GET, middlewares: [AuthMiddleware::class])]
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
            if (empty($fetch["rows"])) {
                throw new Exception("User tidak ditemukan!", 404);
            }
            $row = $fetch["rows"][0];
            $row['photo'] = $this->getProfileImage($row['id']);

            return [
                "status" => true,
                "message" => "Profile",
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

    #[Route(path: "/api/v1/profile/books", method: Method::GET, middlewares: [AuthMiddleware::class])]
    function books(Request $req, Response $res)
    {
        try {

            $uid   = $req->get("uid");
            $page  = $req->get("page") ?? 0;
            $size  = $req->get("size") ?? 10;
  
            $db = Database::getInstance();
            $list_buku = $db->select(
                table: "daftar_buku B",
                conditions: [
                    "B.user_id" => $uid
                ],
                limit: $size,
                offset: $page * $size,
                count: true,
                order: "B.post_date DESC"
            );
            
            $rows = array_map(function ($item) {
                $item['image'] = $this->getBukuImage($item['id']);
                $item['attachments'] = array_map(function ($path) {
                    $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
                    $port     = $_SERVER['SERVER_PORT'];
                    $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);
                    return Path::join($hostLink, $path);
                }, json_decode($item['attachments']) ?? []);
                return $item;
            }, $list_buku["rows"]);

            return [
                "status" => true,
                "message" => "Daftar buku",
                "data" => [
                    "rows" => $rows,
                    "size" => $list_buku["size"],
                    "total_pages" => ceil($list_buku["size"] / 25)
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

    #[Route(path: "/api/v1/profile", method: Method::PUT, middlewares: [AuthMiddleware::class])]
    function update(Request $req, Response $res)
    {
        try {
            $uid = $req->get("uid");
            $nama = $req->get("nama");
            $bio = $req->get("bio");
            $jenis_kelamin = $req->get("jenis_kelamin");
            $image = $req->getFile("image");

            if (empty($nama) || strlen($nama) <= 3) {
                throw new Exception("Nama harus diisi dan minimal 3 karakter!", 400);
            }
            if (!in_array(intval($jenis_kelamin), [1, 2])) {
                throw new Exception("Jenis kelamin tidak valid!", 400);
            }
            $db = Database::getInstance();

            $db->update(
                table: "users",
                params: [
                    "nama" => $nama,
                    "bio" => $bio,
                    "jenis_kelamin" => $jenis_kelamin
                ],
                conditions: ["id" => $uid]
            );

            if (!empty($image)) {
                $temp = $image['tmp_name'];
                $target = realpath(__DIR__ . "/../uploads") . "/profile/" . $uid . ".webp";
                if (!file_exists(__DIR__ . "/../uploads/profile/")) {
                    mkdir(__DIR__ . "/../uploads/profile/");
                }
                if (is_uploaded_file($temp)) {
                    move_uploaded_file($temp, $target);
                } else {
                    rename($temp, $target);
                }
            }
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
