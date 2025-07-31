<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateNumericId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ✅ VALIDAR: Todos los parámetros que terminan en 'id'
        $routeParameters = $request->route()->parameters();
        
        foreach ($routeParameters as $key => $value) {
            // Validar parámetros que terminan en 'id' o son específicamente 'id'
            if ($key === 'id' || str_ends_with($key, '_id')) {
                if (!$this->isValidId($value)) {
                    return response()->json([
                        'success' => false,
                        'message' => "El parámetro '{$key}' debe ser un número entero positivo.",
                        'errors' => [
                            $key => ["El valor '{$value}' no es un ID válido."]
                        ]
                    ], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        return $next($request);
    }

    /**
     * ✅ VALIDACIÓN: Verificar que sea un ID válido
     */
    private function isValidId($value): bool
    {
        // Debe ser numérico y entero positivo
        return is_numeric($value) && 
               ctype_digit(strval($value)) && 
               intval($value) > 0;
    }
}