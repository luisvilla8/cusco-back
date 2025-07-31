<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Exceptions\Handler.php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * MANEJO AUTOMÁTICO: Render para rutas API
     */
    public function render($request, Throwable $exception): Response
    {
        //  SOLO para rutas API - manejo automático
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * MANEJO CENTRALIZADO: Una sola función maneja todo
     */
    private function handleApiException(Request $request, Throwable $exception): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => 'Error interno del servidor',
        ];

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;

        switch (true) {
            case $exception instanceof \App\Exceptions\ApiAuthenticationException:
                $response['message'] = $exception->getMessage();
                $statusCode = Response::HTTP_UNAUTHORIZED;
                break;

            case $exception instanceof ValidationException:
                $response['message'] = 'Los datos proporcionados no son válidos';
                $response['errors'] = $exception->errors();
                $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
                break;

            case $exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException:
                $response['message'] = 'Recurso no encontrado';
                $statusCode = Response::HTTP_NOT_FOUND;
                break;

            case $exception instanceof \InvalidArgumentException:
                $response['message'] = $exception->getMessage();
                $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
                break;

            case $exception instanceof \Illuminate\Database\QueryException:
                $response['message'] = $this->handleDatabaseException($exception);
                $statusCode = Response::HTTP_CONFLICT;
                break;

            case $exception instanceof \Illuminate\Auth\AuthenticationException:
                $response['message'] = 'Token de acceso requerido. Por favor, inicia sesión.';
                $statusCode = Response::HTTP_UNAUTHORIZED;
                break;

            case $exception instanceof \Illuminate\Auth\Access\AuthorizationException:
                $response['message'] = 'No tienes permisos para acceder a este recurso';
                $statusCode = Response::HTTP_FORBIDDEN;
                break;

            case $exception instanceof \Laravel\Sanctum\Exceptions\MissingAbilityException:
                $response['message'] = 'El token no tiene los permisos necesarios para esta acción';
                $statusCode = Response::HTTP_FORBIDDEN;
                break;

            case $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException:
                $response['message'] = 'Ruta no encontrada';
                $statusCode = Response::HTTP_NOT_FOUND;
                break;

            case $exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException:
                $response['message'] = 'Método HTTP no permitido';
                $statusCode = Response::HTTP_METHOD_NOT_ALLOWED;
                break;

            case $exception instanceof \UnexpectedValueException:
                if (str_contains($exception->getMessage(), 'token')) {
                    $response['message'] = 'Token de acceso malformado o inválido';
                    $statusCode = Response::HTTP_UNAUTHORIZED;
                } else {
                    $response['message'] = config('app.debug') ? $exception->getMessage() : 'Error interno del servidor';
                }
                break;

            default:
                if (config('app.debug')) {
                    $response['message'] = $exception->getMessage();
                    $response['debug'] = [
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => collect($exception->getTrace())->take(5)->toArray()
                    ];
                }
                break;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Handle database specific exceptions
     */
    private function handleDatabaseException(\Illuminate\Database\QueryException $exception): string
    {
        if (str_contains($exception->getMessage(), 'Duplicate entry')) {
            return 'Ya existe un registro con estos datos';
        }

        if (str_contains($exception->getMessage(), 'foreign key constraint')) {
            return 'No se puede completar la operación debido a dependencias existentes';
        }

        return config('app.debug') ? $exception->getMessage() : 'Error en la base de datos';
    }

    /**
     */
    protected function unauthenticated($request, \Illuminate\Auth\AuthenticationException $exception)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Token de acceso requerido. Por favor, inicia sesión.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'success' => false,
            'message' => 'Acceso no autorizado',
        ], Response::HTTP_UNAUTHORIZED);
    }
}