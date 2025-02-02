<?php

namespace Il4mb\Simvc\Systems;

use Exception;
use Symfony\Component\Filesystem\Path;

class Helper
{

    static function uploadFile($path, $file)
    {

        $name = $file['name'] ?? "undefine name!";
        $target = Path::join(Path::canonicalize(realpath(__DIR__ . "/../")), "uploads", $path);
        $dirname = dirname($target);
        if (!file_exists($dirname)) mkdir($dirname, 0777, true);

        $temp = $file['tmp_name'] ?? false;
        if (!$temp) throw new Exception("Gagal mengupload file $name !");

        if (is_uploaded_file($temp)) {
            move_uploaded_file($temp, $target);
        } else {
            rename($temp, $target);
        }
        chmod($target, 0777);
    }

    static function getUploadFullpath($path)
    {
        return Path::join(Path::canonicalize(realpath(__DIR__ . "/../")), "uploads", $path);
    }

    static function getUploadAsUrl($path): string
    {
        $serverRoot = preg_replace("/\\\\/m", "/", $_SERVER["DOCUMENT_ROOT"] ?? "");
        $fullPath = self::getUploadFullpath($path);
        $relativePath = str_replace($serverRoot, "", $fullPath);
        $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
        $port     = $_SERVER['SERVER_PORT'];
        $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);
        return Path::join($hostLink, $relativePath);
    }

    static function getUploadAsUrlIfExist($path): ?string
    {
        $serverRoot = preg_replace("/\\\\/m", "/", $_SERVER["DOCUMENT_ROOT"] ?? "");
        $fullPath = self::getUploadFullpath($path);
        if (file_exists($fullPath)) {
            $version = filectime($fullPath);
            $relativePath = str_replace($serverRoot, "", $fullPath);
            $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
            $port     = $_SERVER['SERVER_PORT'];
            $hostLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . (empty($port) || $port === '80' ? '' : ':' . $port);
            return Path::join($hostLink, $relativePath) . "?v=" . $version;
        }
        return null;
    }

    static function removeUpload($path)
    {
        $fullPath = self::getUploadFullpath($path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
