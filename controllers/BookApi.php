<?php

namespace Il4mb\Simvc\Controllers;

use Exception;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Map\Route;
use Il4mb\Simvc\Middlewares\Auth\AuthMiddleware;
use Il4mb\Simvc\Middlewares\Fields\InputValidator;
use Il4mb\Simvc\Systems\Cores\Database;
use Symfony\Component\Filesystem\Path;
use Throwable;

class BookApi
{

    private const VERSION = "v1";
    private readonly string $uploadPath;

    function __construct()
    {
        $this->uploadPath = Path::canonicalize(__DIR__ . "/../uploads/");
        if (!file_exists($this->uploadPath)) {
            mkdir($this->uploadPath);
        }
    }


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


    #[Route(path: "/api/" . self::VERSION . "/list", method: Method::GET)]
    function index(Request $req, Response $res)
    {
        try {

            $query = $req->getQuery("query");
            $page  = $req->get("page") ?? 0;
            $size  = $req->get("size") ?? 10;

            $db = Database::getInstance();
            if (!empty($query)) {
                $list_buku = $db->select(
                    table: "daftar_buku",
                    conditions: [
                        "judul LIKE" => "%$query%",
                    ],
                    limit: $size,
                    offset: $page * $size,
                    count: true,
                    order: "post_date DESC"
                );
            } else {
                $list_buku = $db->select(
                    table: "daftar_buku",
                    limit: $size,
                    offset: $page * $size,
                    count: true,
                    order: "post_date DESC"
                );
            }

            $rows = $list_buku["rows"];
            foreach ($rows as $key => $row) {
                $row['image'] = $this->getBukuImage($row['id']);
                $rows[$key] = $row;
            }

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


    #[Route(
        path: "/api/" . self::VERSION . "/doc",
        method: Method::POST,
        middlewares: [
            AuthMiddleware::class,
            InputValidator::class
        ]
    )]
    function tambah(Request $req, Response $res)
    {
        try {
            $title     = $req->get("title");
            $publisher = $req->get("publisher");
            $author    = $req->get("author");
            $description = $req->get("description");
            $image = $req->getFile("image");

            return [
                "status"  => true,
                "message" => "Upload berhasil",
                "data" => []
            ];

            $db = Database::getInstance();
            $db->insert(
                table: "daftar_buku",
                params: [
                    "judul" => $title,
                    "penerbit" => $publisher,
                    "penulis" => $author,
                    "deskripsi" => $description,
                ]
            );

            $id = $db->lastInsertId();
            if (!empty($image)) {
                $temp = $image['tmp_name'];
                $target = realpath($this->uploadPath) . "/$id.webp";
                if (!file_exists($this->uploadPath)) {
                    mkdir($this->uploadPath);
                }
                if (is_uploaded_file($temp)) {
                    move_uploaded_file($temp, $target);
                } else {
                    rename($temp, $target);
                }
            }

            return [
                "status"  => true,
                "message" => "Upload berhasil"
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }


    #[Route(path: "/api/" . self::VERSION . "/doc/{docId}", method: Method::GET)]
    function detail(Response $res, $docId)
    {
        try {

            $db = Database::getInstance();
            $fetch = $db->select(
                table: "daftar_buku",
                conditions: [
                    "id" => $docId
                ]
            );
            if (empty($fetch["rows"])) {
                throw new Exception("Buku tidak ditemukan!", 404);
            }
            $row = $fetch["rows"][0];
            $row['image'] = $this->getBukuImage($row['kode']);

            return [
                "status" => true,
                "message" => "Detail buku",
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


    #[Route(
        path: "/api/" . self::VERSION . "/doc/{docId}",
        method: Method::PUT,
        middlewares: [
            AuthMiddleware::class,
            InputValidator::class
        ]
    )]
    function update(Request $req, Response $res, $docId)
    {
        try {

            $title       = $req->get("title");
            $publisher   = $req->get("publisher");
            $author      = $req->get("author");
            $description = $req->get("description");
            $image       = $req->getFile("image");

            $db = Database::getInstance();
            $db->update(
                table: "daftar_buku",
                params: [
                    "judul" => $title,
                    "penerbit" => $publisher,
                    "penulis" => $author,
                    "deskripsi" => $description,
                ],
                conditions: [
                    "id" => $docId
                ]
            );

            if (!empty($image)) {
                $temp = $image['tmp_name'];
                $target = realpath($this->uploadPath) . "/$docId.webp";
                if (!file_exists($this->uploadPath)) {
                    mkdir($this->uploadPath);
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

    #[Route(
        path: "/api/" . self::VERSION . "/doc/{docId}",
        method: Method::DELETE,
        middlewares: [
            AuthMiddleware::class
        ]
    )]
    function hapus(Response $res, $docId)
    {
        try {

            $db = Database::getInstance();
            $fetch = $db->select(
                table: "daftar_buku",
                conditions: [
                    "id" => $docId
                ]
            );
            if (empty($fetch["rows"])) {
                throw new Exception("Buku tidak ditemukan!", 404);
            }

            $db->delete(
                table: "daftar_buku",
                conditions: [
                    "id" => $docId
                ]
            );
            return [
                "status"  => true,
                "message" => "Hapus berhasil"
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
