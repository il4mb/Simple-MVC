<?php

namespace Il4mb\Simvc\Systems\Cores;

use Symfony\Component\Filesystem\Path;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ViewExtension extends AbstractExtension
{

    protected string $offset;
    public function __construct()
    {   
        $scriptName = $_SERVER["SCRIPT_NAME"];
        $this->offset = dirname($scriptName);
    }

    public function getFilters()
    {
        return [
            new TwigFilter('assets', [$this, 'assets']),
        ];
    }


    function assets($path)
    {
        return Path::join($this->offset, $path);
       
    }
}
