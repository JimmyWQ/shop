<?php

namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\View;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpException && $e->getStatusCode() === 404) {
            return response(View::fetch('error/404'), 404);
        }

        return parent::render($request, $e);
    }
}
