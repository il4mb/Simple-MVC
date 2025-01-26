<?php

namespace Il4mb\Simvc\Systems;

use Il4mb\BlockNode\Node;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\ContentType;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Simvc\Systems\Cores\Error\Wrapper;

abstract class Cather
{

    protected Request $request;
    protected ?Response $response;
    private array $errors = [];
    function __construct()
    {
        $this->request = new Request();
        register_shutdown_function([$this, "__onProgramFinish"]);
        set_exception_handler([$this, "__exceptionHandler"]);
    }

    public function __onProgramFinish()
    {

        if (!empty($this->errors) && count($this->errors) > 0) {
            $error = $this->errors[count($this->errors) - 1];
            return $this->__render($error);
        }
    }

    public function __exceptionHandler($exception)
    {
        $this->errors[] = new Wrapper($exception);
    }

    function __render(Wrapper $error)
    {
        $request = $this->request;
        $response = $this->response ?? new Response(null, Code::INTERNAL_SERVER_ERROR);
        $response->setCode(Code::INTERNAL_SERVER_ERROR);

        if ($request->isAjax() || $request->isContent(ContentType::JSON)) {
            $response->setContentType("application/json");
            $response->setContent([
                "status" => false,
                "message" => $error->getMessage(),
                "error" => [
                    "title" => $error->getTitle(),
                    "message" => $error->getMessage(),
                    "file" => $error->getFile(),
                    "line" => $error->getLine(),
                    "tracers" => $error->getStackTrace(),
                    "snippet" => $error->getSnippet()
                ]
            ]);
        } else {
            $response->setContentType("text/html");
            $response->setContent(View::render("/systems/Cores/Error/view.twig", [
                "error" => [
                    "title" => $error->getTitle(),
                    "message" => $error->getMessage(),
                    "file" => $error->getFile(),
                    "line" => $error->getLine(),
                    "tracers" => $error->getStackTrace(),
                    "snippet" => $error->getSnippet()
                ]
            ]));
        }

        $content = $response->getContent();
        if ($content instanceof Node) {
            $response->setContent($content->render());
        }
        echo $response->send();
        exit(1);
    }
}
