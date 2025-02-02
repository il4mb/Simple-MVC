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
use Il4mb\Simvc\Systems\Helper;
use Symfony\Component\Filesystem\Path;
use Throwable;

class DefaultApi
{

    private readonly string $uploadPath;

    function __construct()
    {
        $this->uploadPath = Path::canonicalize(__DIR__ . "/../uploads/");
        if (!file_exists($this->uploadPath)) {
            mkdir($this->uploadPath);
        }
    }

    #[Route(path: "/api/transactions", method: Method::GET)]
    function index(Request $req, Response $res)
    {
        try {

            $page = $req->get("page") ?? 0;
            $size = $req->get("size") ?? 100;
            $owner = ($req->get("owner") ?? 0);
            $owner = $owner == '1' || $owner == "true";


            $db = Database::getInstance();
            $queryParams = [
                "table" => "transactions A",
                "columns" => [
                    "A.*",
                    "JSON_ARRAYAGG(
                        DISTINCT IF(I.id IS NOT NULL,
                        JSON_OBJECT(
                            'id', I.id,
                            'name', I.name,
                            'price', I.price
                        ), NULL)
                    ) as items",
                ],
                "limit" => $size,
                "offset" => $page * $size,
                "group" => [
                    "A.id"
                ],
                "order" => "A.post_date DESC",
                "join" => [
                    "items I" => [
                        "I.transaction_id" => "A.id"
                    ]
                ],
                "conditions" => [
                    "A.post_date >" => date("Y-m-d", strtotime("-1 days"))
                ]
            ];

            $result = $db->select(...$queryParams);
            $rows = array_map(function ($item) {
                $item['items'] = array_values(array_filter(json_decode($item['items'] ?? "", true)));
                $item['subtotal'] = 0;
                foreach ($item['items'] as $i) {
                    $item['subtotal'] += intval($i['price']);
                }
                return $item;
            }, $result["rows"]);

            return [
                "status" => true,
                "message" => "Berhasil fetch data",
                "data" => [
                    "rows" => $rows,
                ]
            ];
        } catch (Throwable $t) {
            error_log($t->getMessage());
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status" => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/transactions/histories", method: Method::GET)]
    function histories(Request $req, Response $res)
    {
        try {

            $page = $req->get("page") ?? 0;
            $size = $req->get("size") ?? 100;
            $owner = ($req->get("owner") ?? 0);
            $owner = $owner == '1' || $owner == "true";


            $db = Database::getInstance();
            $queryParams = [
                "table" => "transactions A",
                "columns" => [
                    "A.*",
                    "JSON_ARRAYAGG(
                        DISTINCT IF(I.id IS NOT NULL,
                        JSON_OBJECT(
                            'id', I.id,
                            'name', I.name,
                            'price', I.price
                        ), NULL)
                    ) as items",
                ],
                "limit" => $size,
                "offset" => $page * $size,
                "group" => [
                    "A.id"
                ],
                "order" => "A.post_date DESC",
                "join" => [
                    "items I" => [
                        "I.transaction_id" => "A.id"
                    ]
                ],
                "conditions" => [
                    "A.post_date <" => date("Y-m-d", strtotime("-1 days"))
                ]
            ];

            $result = $db->select(...$queryParams);
            $rows = array_map(function ($item) {
                $item['items'] = array_values(array_filter(json_decode($item['items'] ?? "", true)));
                $item['subtotal'] = 0;
                foreach ($item['items'] as $i) {
                    $item['subtotal'] += intval($i['price']);
                }
                return $item;
            }, $result["rows"]);

            return [
                "status" => true,
                "message" => "Berhasil fetch data",
                "data" => [
                    "rows" => $rows,
                ]
            ];
        } catch (Throwable $t) {
            error_log($t->getMessage());
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status" => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/transactions/{docId}", method: Method::GET, middlewares: [AuthMiddleware::class])]
    function detail(Request $req, Response $res, $docId)
    {
        try {

            $db = Database::getInstance();
            $fetch = $db->select(
                table: "transactions A",
                columns: [
                    "A.*",
                    "JSON_ARRAYAGG(
                        DISTINCT IF(I.id IS NOT NULL,
                        JSON_OBJECT(
                            'id', I.id,
                            'name', I.name,
                            'price', I.price
                        ), NULL)
                    ) as items",
                ],
                conditions: [
                    "A.id" => $docId
                ],
                join: [
                    "items I" => [
                        "I.transaction_id" => "A.id"
                    ]
                ],
                group: [
                    "A.id"
                ]
            );
            if (empty($fetch["rows"])) {
                throw new Exception("Resep tidak ditemukan!", 404);
            }
            $row = $fetch["rows"][0];
            $row['items'] = array_filter(json_decode($row['items'] ?? "", true));
            $row['subtotal'] = 0;
            foreach ($row['items'] as $i) {
                $row['subtotal'] += intval($i['price']);
            }

            return [
                "status" => true,
                "message" => "Detail transaction",
                "data" => $row
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status" => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/transactions", method: Method::POST, middlewares: [AuthMiddleware::class])]
    function tambah(Request $req, Response $res)
    {
        try {

            $deskripsi = $req->get("deskripsi");
            $items = $req->get("items");

            $db = Database::getInstance();
            $db->beginTransaction();
            if ($db->insert(table: "transactions", params: ["description" => $deskripsi])) {
                $id = intval($db->lastInsertId());
                if (!is_array($items)) {
                    throw new Exception("Gagal mohon coba lagi!", 500);
                }

                foreach ($items as $item) {
                    $db->insert("items", [
                        "transaction_id" => $id,
                        "name" => $item['name'],
                        "price" => intval($item['price'])
                    ]);
                }

                $res->setCode(Code::CREATED);
                $db->commit();

                return [
                    "status" => true,
                    "message" => "Transaction berhasil ditambahkan"
                ];
            }
            throw new Exception("Gagal mohon coba lagi!", 500);
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status" => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/transactions/{docId}", method: Method::PUT, middlewares: [AuthMiddleware::class, InputValidator::class])]
    function update(Request $req, Response $res, $docId)
    {
        try {

            $deskripsi = $req->get("deskripsi");
            $docId = intval($docId);

            $db = Database::getInstance();
            $db->beginTransaction();
            if ($db->update(table: "transactions", params: ["description" => $deskripsi], conditions: ["id" => $docId])) {

                if ($items = $req->get("items")) {
                    $db->delete(
                        table: "items",
                        conditions: [
                            "transaction_id" => $docId
                        ]
                    );
                    foreach ($items as $item) {
                        $db->insert("items", [
                            "transaction_id" => $docId,
                            "name" => $item['name'],
                            "price" => intval($item['price'])
                        ]);
                    }
                }

                $res->setCode(Code::CREATED);
                $db->commit();
                return [
                    "status" => true,
                    "message" => "Resep berhasil diperbarui"
                ];
            }

            throw new Exception("Gagal mohon coba lagi!", 500);
        } catch (Throwable $t) {

            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status" => false,
                "message" => $t->getMessage()
            ];
        }
    }

    #[Route(path: "/api/transactions/{docId}", method: Method::DELETE, middlewares: [AuthMiddleware::class])]
    function hapus(Response $res, $docId)
    {
        try {
            $docId = intval($docId);

            $db = Database::getInstance();
            $fetch = $db->select(
                table: "transactions",
                conditions: ["id" => $docId]
            );
            if (empty($fetch["rows"]))
                throw new Exception("transactions tidak ditemukan!", 404);

            return [
                "status" => true,
                "message" => "Hapus berhasil"
            ];
        } catch (Throwable $t) {
            $res->setCode(Code::fromCode(intval($t->getCode())) ?? 500);
            return [
                "status" => false,
                "message" => $t->getMessage()
            ];
        }
    }

}
