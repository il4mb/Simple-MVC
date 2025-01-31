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
use Il4mb\Simvc\Systems\Cores\Encrypt;
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

    private function getLinkablePath($file)
    {
        $root = preg_replace("/\\\\/m", "/", $_SERVER['DOCUMENT_ROOT']);
        return Path::join(str_replace($root, "", preg_replace("/\\\\/m", "/", $file)));
    }

    #[Route(path: "/api/" . self::VERSION . "/books", method: Method::GET)]
    function index(Request $req, Response $res)
    {
        try {

            $query = $req->getQuery("query");
            $page  = $req->get("page") ?? 0;
            $size  = $req->get("size") ?? 100;
            $userId = Encrypt::decrypt($req->getCookie("token") ?? "");
            if (empty($userId)) {
                throw new Exception("Token tidak valid!", 401);
            }

            $db = Database::getInstance();
            if (!empty($query)) {
                $list_buku = $db->select(
                    table: "daftar_buku B",
                    columns: [
                        "B.*",
                        "CASE 
                            WHEN F.id IS NOT NULL THEN TRUE 
                            ELSE FALSE 
                        END AS is_fav"
                    ],
                    join: [
                        "fav_buku F" => [
                            "B.id" => "F.buku_id",
                            "F.user_id" => $userId
                        ]
                    ],
                    conditions: [
                        "B.judul LIKE" => "%$query%",
                    ],
                    limit: $size,
                    offset: $page * $size,
                    count: true,
                    order: "B.post_date DESC"
                );
            } else {
                $list_buku = $db->select(
                    table: "daftar_buku A",
                    columns: [
                        "A.*",
                        "CASE 
                            WHEN F.id IS NOT NULL THEN TRUE 
                            ELSE FALSE 
                        END AS is_fav"
                    ],
                    join: [
                        "fav_buku F" => [
                            "A.id" => "F.buku_id",
                            "F.user_id" => $userId
                        ]
                    ],
                    limit: $size,
                    offset: $page * $size,
                    count: true,
                    order: "A.post_date DESC"
                );
            }

            $rows = array_map(function ($item) {
                $item['image'] = $this->getBukuImage($item['id']);
                $item['is_fav'] = $item['is_fav'] == true;
                $item['attachments'] = array_map(function ($path) {
                    $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
                    $port     = $_SERVER['SERVER_PORT'];
                    $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);
                    return Path::join($hostLink, $path);
                }, json_decode($item['attachments'], true) ?? []);
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


    #[Route(
        path: "/api/" . self::VERSION . "/books",
        method: Method::POST,
        middlewares: [
            AuthMiddleware::class,
            InputValidator::class
        ]
    )]
    function tambah(Request $req, Response $res)
    {
        try {

            $uid       = $req->get("uid");
            $judul     = $req->get("judul");
            $deskripsi = $req->get("deskripsi");
            $topik     = $req->get("topik");
            $link      = $req->get("link");
            $attachments = $req->getFile("attachments");
            $image     = $req->getFile("gambar");

            $db = Database::getInstance();
            if ($db->insert(
                table: "daftar_buku",
                params: [
                    "judul" => $judul,
                    "topik" => $topik,
                    "deskripsi" => $deskripsi,
                    "user_id" => $uid,
                    "link" => $link
                ]
            )) {

                $id = $db->lastInsertId();

                if (!empty($attachments)) {
                    $targetPath = realpath($this->uploadPath) . "/attachments/$id";
                    if (!file_exists($targetPath)) {
                        mkdir($targetPath, 0777, true);
                    }

                    $linkPaths = [];
                    foreach ($attachments as $file) {

                        if ($file['error']) {
                            throw new Exception($file['error']);
                        }
                        $temp = $file['tmp_name'] ?? false;
                        if (!$temp) throw new Exception("Gagal mengupload file $file[name]!");
                        $target = $targetPath . "/$file[name]";
                        if (is_uploaded_file($temp)) {
                            move_uploaded_file($temp, $target);
                        } else {
                            rename($temp, $target);
                        }

                        chmod($target, 0777);
                        $linkPaths[] = $this->getLinkablePath($target);
                    }
                    $db->update(
                        table: "daftar_buku",
                        params: [
                            "attachments" => json_encode($linkPaths)
                        ],
                        conditions: [
                            "id" => $id
                        ]
                    );
                }

                if (!empty($image)) {
                    $temp = $image['tmp_name'] ?? false;
                    if (!$temp) throw new Exception("Gagal mengupload file $file[name]!");
                    $target = realpath($this->uploadPath) . "/$id.webp";
                    if (is_uploaded_file($temp)) {
                        move_uploaded_file($temp, $target);
                    } else {
                        rename($temp, $target);
                    }
                    chmod($target, 0777);
                }

                $res->setCode(Code::CREATED);
                return [
                    "status"  => true,
                    "message" => "Upload berhasil",
                    "data" => [
                        "id" => $id,
                        "image" => $this->getBukuImage($id),
                        "judul" => $judul,
                        "deskripsi" => $deskripsi,

                    ]
                ];
            }

            throw new Exception("Upload gagal", 500);
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }


    #[Route(path: "/api/" . self::VERSION . "/books/{docId}", method: Method::GET)]
    function detail(Response $res, $docId)
    {
        try {

            $db = Database::getInstance();
            $fetch = $db->select(
                table: "daftar_buku B",
                columns: [
                    "B.*",
                    "CASE 
                        WHEN F.id IS NOT NULL THEN TRUE 
                        ELSE FALSE 
                    END AS is_fav",
                    "JSON_OBJECT(
                        'name', U.nama,
                        'email', U.email
                    ) as user"
                ],
                conditions: [
                    "B.id" => $docId
                ],
                join: [
                    "fav_buku F" => [
                        "F.buku_id" => "B.id"
                    ],
                    "users U" => [
                        "B.user_id" => "U.id"
                    ]

                ]
            );
            if (empty($fetch["rows"])) {
                throw new Exception("Buku tidak ditemukan!", 404);
            }
            $row = $fetch["rows"][0];
            $row['image'] = $this->getBukuImage($row['id']);
            $row['is_fav'] = $row['is_fav'] == true;
            $row['user'] = json_decode($row['user'], true);
            $row['attachments'] = array_map(function ($path) {
                $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
                $port     = $_SERVER['SERVER_PORT'];
                $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);
                return Path::join($hostLink, $path);
            }, json_decode($row['attachments'], true) ?? []);

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
        path: "/api/" . self::VERSION . "/books/{docId}",
        method: Method::PUT,
        middlewares: [
            AuthMiddleware::class,
            InputValidator::class
        ]
    )]
    function update(Request $req, Response $res, $docId)
    {
        try {

            $uid       = $req->get("uid");
            $judul     = $req->get("judul");
            $topik     = $req->get("topik");
            $deskripsi = $req->get("deskripsi");
            $image     = $req->getFile("gambar");
            $link      = $req->get("link");
            $currentsAttachment = array_values(
                array_map(
                    fn($link) => parse_url($link, PHP_URL_PATH),
                    json_decode($req->get("currents_attachments") ?? "[]", true) ?? []
                )
            );
            $attachments = $req->getFile("attachments");

            if (!empty($attachments)) {
                $targetPath = realpath($this->uploadPath) . "/attachments/$docId";
                if (!file_exists($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                foreach ($attachments as $file) {

                    if ($file['error']) {
                        throw new Exception($file['error']);
                    }
                    $temp = $file['tmp_name'] ?? false;
                    if (!$temp) throw new Exception("Gagal mengupload file $file[name]!");
                    $target = $targetPath . "/$file[name]";
                    if (is_uploaded_file($temp)) {
                        move_uploaded_file($temp, $target);
                    } else {
                        rename($temp, $target);
                    }

                    chmod($target, 0777);
                    $currentsAttachment[] = $this->getLinkablePath($target);
                }
            }

            $db = Database::getInstance();
            $db->update(
                table: "daftar_buku",
                params: [
                    "judul" => $judul,
                    "topik" => $topik,
                    "deskripsi" => $deskripsi,
                    "user_id" => $uid,
                    "link" => $link,
                    "attachments" => json_encode($currentsAttachment)
                ],
                conditions: [
                    "id" => $docId,
                    "user_id" => $uid
                ]
            );

            if (!empty($image)) {
                $temp = $image['tmp_name'] ?? false;
                if (!$temp) throw new Exception("Gagal mengupload file $file[name]!");
                $target = realpath($this->uploadPath) . "/$docId.webp";
                if (!file_exists($this->uploadPath)) {
                    mkdir($this->uploadPath);
                }
                if (is_uploaded_file($temp)) {
                    move_uploaded_file($temp, $target);
                } else {
                    rename($temp, $target);
                }
                chmod($target, 0777);
            }

            $buku = $db->select('daftar_buku', ['*'], ['id' => $docId])['rows'][0];
            $buku['image'] = $this->getBukuImage($buku['id']);
            $buku['attachments'] = array_map(function ($path) {
                $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
                $port     = $_SERVER['SERVER_PORT'];
                $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);
                return Path::join($hostLink, $path);
            }, json_decode($buku['attachments'], true) ?? []);

            return [
                "status"  => true,
                "message" => "Update berhasil",
                "data"    => $buku
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
        path: "/api/" . self::VERSION . "/books/{docId}",
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
            $attachmentDir = realpath($this->uploadPath) . "/attachments/$docId";
            if (is_dir($attachmentDir)) {
                foreach (scandir($attachmentDir) as $file) {
                    if (file_exists($attachmentDir . "/" . $file))
                        unlink($attachmentDir . "/" . $file);
                }
                unlink($attachmentDir);
            }
            $image = realpath($this->uploadPath) . "/$docId.webp";
            if (file_exists($image))
                unlink($image);

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

    #[Route(path: "/api/" . self::VERSION . "/favs", method: Method::GET, middlewares: [AuthMiddleware::class])]
    function favs(Request $req, Response $res)
    {
        try {
            $userId = Encrypt::decrypt($req->getCookie("token") ?? "");
            if (empty($userId)) {
                throw new Exception("Token tidak valid!", 401);
            }

            $db = Database::getInstance();
            $favs = $db->select(
                table: "fav_buku F",
                columns: ["B.*"],
                conditions: [
                    "F.user_id" => $userId,
                    "B.id IS NOT NULL"
                ],
                join: [
                    "daftar_buku B" => [
                        "F.buku_id" => "B.id"
                    ]
                ],
                order: "F.post_date DESC"
            );

            $rows = array_map(function ($item) {
                $item['image'] = $this->getBukuImage($item['id']);
                $item['is_fav'] = true;
                $item['attachments'] = array_map(function ($path) {
                    $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
                    $port     = $_SERVER['SERVER_PORT'];
                    $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);
                    return Path::join($hostLink, $path);
                }, json_decode($item['attachments'], true) ?? []);
                return $item;
            }, $favs["rows"]);
            $favs['rows'] = $rows;

            return [
                "status" => true,
                "message" => "Daftar buku favorit",
                "data" => $favs
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }


    #[Route(path: "/api/" . self::VERSION . "/favs/{docId}", method: Method::POST, middlewares: [AuthMiddleware::class])]
    function addFav(Request $req, Response $res, $docId)
    {
        try {

            $userId = Encrypt::decrypt($req->getCookie("token") ?? "");
            if (empty($userId)) {
                throw new Exception("Token tidak valid!", 401);
            }

            $db = Database::getInstance();
            $findFav = $db->select(
                table: "fav_buku",
                conditions: [
                    "user_id" => $userId,
                    "buku_id" => $docId
                ]
            );
            if (!empty($findFav["rows"])) {
                throw new Exception("Buku sudah favorit!", 409);
            }

            $findBook = $db->select(
                table: "daftar_buku",
                conditions: [
                    "id" => $docId
                ]
            );
            if (empty($findBook["rows"])) {
                throw new Exception("Buku tidak ditemukan!", 40);
            }

            $db->insert(
                table: "fav_buku",
                params: [
                    "user_id" => $userId,
                    "buku_id" => $docId
                ]
            );
            return [
                "status"  => true,
                "message" => "Tambah favorit berhasil"
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status"  => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/" . self::VERSION . "/favs/{docId}", method: Method::DELETE, middlewares: [AuthMiddleware::class])]
    function removeFav(Request $req, Response $res, $docId)
    {
        try {
            $userId = Encrypt::decrypt($req->getCookie("token") ?? "");
            if (empty($userId)) {
                throw new Exception("Token tidak valid!", 401);
            }
            $db = Database::getInstance();
            $db->delete(
                table: "fav_buku",
                conditions: [
                    "user_id" => $userId,
                    "buku_id" => $docId
                ]
            );
            return [
                "status"  => true,
                "message" => "Hapus favorit berhasil"
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
