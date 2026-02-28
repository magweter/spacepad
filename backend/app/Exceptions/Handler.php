<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Render an exception into an HTTP response.
     * @throws Throwable
     */
    public function render($request, Throwable $e): Response|JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        // Log exceptions with context (skip 4xx noise; avoid leaking details in prod)
        if (
            $this->shouldReport($e) &&
            !($e instanceof NotFoundHttpException) &&
            !($e instanceof ValidationException) &&
            !($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500)
        ) {
            $logLevel = $e instanceof AuthenticationException ? 'warning' : 'error';

            $context = [
                'exception' => get_class($e),
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
            ];

            if (config('app.debug')) {
                $context += [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'ip' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 200),
                    'trace' => substr($e->getTraceAsString(), 0, 1000),
                ];
            }

            logger()->{$logLevel}('Unhandled exception', $context);
        }

        if ($request->expectsJson()) {
            $status = 500;
            $message = 'Server Error';
            $errors = config('app.debug') ? $e->getMessage() : null;

            if ($e instanceof ValidationException) {
                $status = 422;
                $message = 'Validation Error';
                $errors = $e->errors();
            }

            if ($e instanceof AuthenticationException) {
                $status = 401;
                $message = 'Unauthenticated';
            }

            if ($e instanceof NotFoundHttpException) {
                $status = 404;
                $message = 'Resource not found';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $status);
        }

        return parent::render($request, $e);
    }
}
