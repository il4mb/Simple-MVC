<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit36560a0564649b1d73da4ce45baf5ac6
{
    public static $prefixLengthsPsr4 = array (
        'I' => 
        array (
            'Il4mb\\BlockNode\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Il4mb\\BlockNode\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit36560a0564649b1d73da4ce45baf5ac6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit36560a0564649b1d73da4ce45baf5ac6::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit36560a0564649b1d73da4ce45baf5ac6::$classMap;

        }, null, ClassLoader::class);
    }
}
