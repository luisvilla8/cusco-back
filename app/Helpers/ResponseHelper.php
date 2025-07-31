<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Helpers\ResponseHelper.php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResponseHelper
{
  
    public static function success($data = null, string $message = '', int $code = Response::HTTP_OK): array
    {
        $response = [
            'success' => true,
            'message' => $message,
            'code' => $code
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }


    public static function error(string $message, int $code = Response::HTTP_BAD_REQUEST, array $errors = []): array
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

   
    public static function created($data, string $message = 'Recurso creado exitosamente'): array
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }


    public static function notFound(string $message = 'Recurso no encontrado'): array
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

 
    public static function validationError(string $message, array $errors = []): array
    {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

  
    public static function unauthorized(string $message = 'No autorizado'): array
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

 
    public static function forbidden(string $message = 'Sin permisos'): array
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    public static function conflict(string $message = 'Conflicto de datos'): array
    {
        return self::error($message, Response::HTTP_CONFLICT);
    }

  
    public static function paginated($data, $paginator, string $message = 'Datos obtenidos exitosamente'): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'code' => Response::HTTP_OK,
            'meta' => [
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ]
            ]
        ];
    }

    // =====================================
    //  MÉTODOS PARA CONTROLLERS
    // =====================================

   
    public static function toJsonResponse(array $result): JsonResponse
    {
        $statusCode = $result['code'] ?? Response::HTTP_OK;

        // Remove 'code' from response as it's used for HTTP status
        $responseData = $result;
        unset($responseData['code']);

        return response()->json($responseData, $statusCode);
    }

    /**
     * Success JsonResponse (for direct controller use)
     */
    public static function successJson($data = null, string $message = '', int $code = Response::HTTP_OK): JsonResponse
    {
        return self::toJsonResponse(self::success($data, $message, $code));
    }

    /**
     *  Error JsonResponse (for direct controller use)
     */
    public static function errorJson(string $message, int $code = Response::HTTP_BAD_REQUEST, array $errors = []): JsonResponse
    {
        return self::toJsonResponse(self::error($message, $code, $errors));
    }
}