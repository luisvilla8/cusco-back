<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\FileService.php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Upload an image file
     */
    public function uploadImage(UploadedFile $file, string $directory = 'products'): array
    {
        try {
            // Generar nombre único para el archivo
            $fileName = $this->generateUniqueFileName($file);
            
            // Crear directorio si no existe
            $fullPath = $directory . '/' . date('Y/m');
            
            // Guardar archivo
            $path = $file->storeAs($fullPath, $fileName, 'public');
            
            if (!$path) {
                return [
                    'success' => false,
                    'message' => 'Error al guardar el archivo',
                    'path' => null
                ];
            }

            return [
                'success' => true,
                'message' => 'Archivo guardado exitosamente',
                'path' => $path,
                'url' => asset('storage/' . $path),
                'filename' => $fileName
            ];

        } catch (\Exception $e) {
            Log::error('Error uploading image: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno al subir archivo',
                'path' => null
            ];
        }
    }

    /**
     * Delete an image file
     */
    public function deleteImage(string $path): bool
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->delete($path);
            }
            return true; // Si no existe, consideramos que está "eliminado"
        } catch (\Exception $e) {
            Log::error('Error deleting image: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Limpiar nombre original
        $cleanName = Str::slug($originalName);
        
        // Generar nombre único
        $uniqueId = uniqid();
        $timestamp = time();
        
        return "{$cleanName}_{$timestamp}_{$uniqueId}.{$extension}";
    }

    /**
     * Get file URL from path
     */
    public function getFileUrl(string $path): string
    {
        return asset('storage/' . $path);
    }

    /**
     * Validate image dimensions (opcional)
     */
    public function validateImageDimensions(UploadedFile $file, int $maxWidth = 1920, int $maxHeight = 1080): array
    {
        try {
            $imageSize = getimagesize($file->getPathname());
            
            if (!$imageSize) {
                return [
                    'valid' => false,
                    'message' => 'No se pudo obtener las dimensiones de la imagen'
                ];
            }

            $width = $imageSize[0];
            $height = $imageSize[1];

            if ($width > $maxWidth || $height > $maxHeight) {
                return [
                    'valid' => false,
                    'message' => "La imagen excede las dimensiones permitidas ({$maxWidth}x{$maxHeight}px). Actual: {$width}x{$height}px"
                ];
            }

            return [
                'valid' => true,
                'width' => $width,
                'height' => $height
            ];

        } catch (\Exception $e) {
            Log::error('Error validating image dimensions: ' . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Error al validar dimensiones de la imagen'
            ];
        }
    }
}