<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\BaseService.php

namespace App\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseService
{
    /**
     * Handle exceptions and return standardized response with detailed error mapping
     */
    protected function handleException(\Exception $exception): array
    {
        // Log the full exception details
        Log::error('Service Exception in ' . static::class, [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $this->getErrorContext()
        ]);

        // Map specific exceptions to appropriate responses
        return match (true) {
            $exception instanceof ModelNotFoundException => $this->notFoundResponse(
                $exception->getMessage() ?: 'Recurso no encontrado'
            ),
            $exception instanceof ValidationException => $this->validationErrorResponse(
                'Error de validación',
                $exception->errors()
            ),
            $exception instanceof \InvalidArgumentException => $this->validationErrorResponse(
                $exception->getMessage() ?: 'Argumentos inválidos'
            ),
            $exception instanceof \UnauthorizedHttpException => $this->unauthorizedResponse(
                $exception->getMessage() ?: 'No autorizado'
            ),
            $exception instanceof \AccessDeniedHttpException => $this->forbiddenResponse(
                $exception->getMessage() ?: 'Acceso denegado'
            ),
            default => $this->internalServerErrorResponse(
                config('app.debug') ? $exception->getMessage() : 'Error interno del servidor'
            )
        };
    }

    /**
     * Get additional context for error logging
     */
    protected function getErrorContext(): array
    {
        return [
            'service' => static::class,
            'user_id' => auth()->id() ?? null,
            'ip' => request()->ip() ?? null,
            'user_agent' => request()->userAgent() ?? null,
            'url' => request()->fullUrl() ?? null,
            'method' => request()->method() ?? null,
        ];
    }

    /**
     * Success response format with flexible data handling
     */
    protected function successResponse($data, string $message = '', int $code = Response::HTTP_OK, array $meta = []): array
    {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'code' => $code
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Error response format with optional errors details
     */
    protected function errorResponse(string $message, int $code = Response::HTTP_BAD_REQUEST, array $errors = []): array
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    /**
     * Created response format (for POST operations)
     */
    protected function createdResponse($data, string $message = 'Recurso creado exitosamente', array $meta = []): array
    {
        return $this->successResponse($data, $message, Response::HTTP_CREATED, $meta);
    }

    /**
     * Not found response format
     */
    protected function notFoundResponse(string $message = 'Recurso no encontrado'): array
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Validation error response format with errors details
     */
    protected function validationErrorResponse(string $message = 'Error de validación', array $errors = []): array
    {
        return $this->errorResponse($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Unauthorized response format
     */
    protected function unauthorizedResponse(string $message = 'No autorizado'): array
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Forbidden response format
     */
    protected function forbiddenResponse(string $message = 'Acceso denegado'): array
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Conflict response format (for business logic conflicts)
     */
    protected function conflictResponse(string $message = 'Conflicto en la operación'): array
    {
        return $this->errorResponse($message, Response::HTTP_CONFLICT);
    }

    /**
     * Internal server error response format
     */
    protected function internalServerErrorResponse(string $message = 'Error interno del servidor'): array
    {
        return $this->errorResponse($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * No content response format (for DELETE operations)
     */
    protected function noContentResponse(string $message = 'Operación completada'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'code' => Response::HTTP_NO_CONTENT
        ];
    }

    /**
     * Paginated response format with standardized meta information
     */
    protected function paginatedResponse($data, $paginator, string $message = 'Datos obtenidos exitosamente'): array
    {
        return $this->successResponse(
            $data,
            $message,
            Response::HTTP_OK,
            [
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                    'path' => $paginator->path(),
                ]
            ]
        );
    }

    /**
     * Validate business rules and throw exception if validation fails
     */
    protected function validateBusinessRule(bool $condition, string $message, int $code = Response::HTTP_UNPROCESSABLE_ENTITY): void
    {
        if (!$condition) {
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * Log info message with context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge($context, $this->getErrorContext()));
    }

    /**
     * Log warning message with context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge($context, $this->getErrorContext()));
    }

    /**
     * Log error message with context
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge($context, $this->getErrorContext()));
    }
}