<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BaseController extends Controller
{
    /**
     * Success response method with flexible data handling
     */
    public function sendResponse($result, string $message = '', int $code = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Error response method with optional error details
     */
    public function sendError(string $error, array $errorMessages = [], int $code = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    /**
     * Created response method (201)
     */
    public function sendCreated($result, string $message = 'Recurso creado exitosamente'): JsonResponse
    {
        return $this->sendResponse($result, $message, Response::HTTP_CREATED);
    }

    /**
     * Updated response method (200)
     */
    public function sendUpdated($result, string $message = 'Recurso actualizado exitosamente'): JsonResponse
    {
        return $this->sendResponse($result, $message, Response::HTTP_OK);
    }

    /**
     * Deleted response method (200) - with data
     */
    public function sendDeleted($result, string $message = 'Recurso eliminado exitosamente'): JsonResponse
    {
        return $this->sendResponse($result, $message, Response::HTTP_OK);
    }

    /**
     * No content response method (204) - without data
     */
    public function sendNoContent(string $message = 'Operación completada'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Not found response method (404)
     */
    public function sendNotFound(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return $this->sendError($message, [], Response::HTTP_NOT_FOUND);
    }

    /**
     * Validation error response method (422)
     */
    public function sendValidationError(string $message = 'Error de validación', array $errors = []): JsonResponse
    {
        return $this->sendError($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Unauthorized response method (401)
     */
    public function sendUnauthorized(string $message = 'No autorizado'): JsonResponse
    {
        return $this->sendError($message, [], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Forbidden response method (403)
     */
    public function sendForbidden(string $message = 'Acceso denegado'): JsonResponse
    {
        return $this->sendError($message, [], Response::HTTP_FORBIDDEN);
    }

    /**
     * Conflict response method (409) - for business logic conflicts
     */
    public function sendConflict(string $message = 'Conflicto en la operación'): JsonResponse
    {
        return $this->sendError($message, [], Response::HTTP_CONFLICT);
    }

    /**
     * Internal server error response method (500)
     */
    public function sendInternalError(string $message = 'Error interno del servidor'): JsonResponse
    {
        return $this->sendError($message, [], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Paginated response method with standardized format
     */
    public function sendPaginatedResponse($data, $paginator, string $message = 'Datos obtenidos exitosamente'): JsonResponse
    {
        return $this->sendResponse(
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
                    'links' => [
                        'first' => $paginator->url(1),
                        'last' => $paginator->url($paginator->lastPage()),
                        'prev' => $paginator->previousPageUrl(),
                        'next' => $paginator->nextPageUrl(),
                    ]
                ]
            ]
        );
    }

    /**
     * Handle service response and convert to appropriate HTTP response
     */
    public function handleServiceResponse(array $serviceResponse): JsonResponse
    {
        if (!$serviceResponse['success']) {
            return $this->sendError(
                $serviceResponse['message'],
                $serviceResponse['errors'] ?? [],
                $serviceResponse['code']
            );
        }

        $meta = [];
        if (isset($serviceResponse['meta'])) {
            $meta = $serviceResponse['meta'];
        }

        return $this->sendResponse(
            $serviceResponse['data'],
            $serviceResponse['message'],
            $serviceResponse['code'],
            $meta
        );
    }

    /**
     * Extract filters from request for services
     */
    protected function extractFilters(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'id'),
            'sort_order' => $request->input('sort_order', 'asc'),
            'per_page' => min($request->input('per_page', 15), 100), // Max 100 items
            'page' => $request->input('page', 1),
            'status' => $request->input('status'),
            'created_from' => $request->input('created_from'),
            'created_to' => $request->input('created_to'),
        ];
    }

    /**
     * Validate pagination parameters
     */
    protected function validatePaginationParams(Request $request): void
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort_by' => 'string|max:50',
            'sort_order' => 'in:asc,desc',
        ]);
    }
}