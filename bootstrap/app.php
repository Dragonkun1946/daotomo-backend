<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            return ($request->is('api/*') || $request->expectsJson()) ? null : '/html/Account.html';
        });
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhooks/*',
            'api/webhooks/*',
            'sepay/*',
            'api/sepay/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.',
                    ], 401);
                }
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null; // Let Laravel handle 422 standard responses
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $isDebug = (bool) config('app.debug', false);

                if ($status >= 500) {
                    \Illuminate\Support\Facades\Log::error('Unhandled API exception', [
                        'exception' => class_basename($e),
                        'message' => $e->getMessage(),
                        'path' => $request->path(),
                    ]);
                }

                $payload = [
                    'success' => false,
                    'message' => ($status >= 500 && !$isDebug)
                        ? 'Đã xảy ra lỗi hệ thống trên máy chủ. Vui lòng thử lại sau.'
                        : ($e->getMessage() ?: 'Đã xảy ra lỗi trên máy chủ.'),
                ];
                if ($isDebug) {
                    $payload['error_type'] = class_basename($e);
                }

                return response()->json($payload, $status);
            }
        });
    })->create();
