<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\ImageService.php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    private const PRODUCT_IMAGES_PATH = 'products';
    private const MAX_WIDTH = 800;
    private const MAX_HEIGHT = 600;
    private const THUMBNAIL_WIDTH = 200;
    private const THUMBNAIL_HEIGHT = 150;
    private const QUALITY = 85;

    private ImageManager $imageManager;

    public function __construct()
    {
        // ✅ V3: Crear ImageManager con driver
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Store product image and return the URL
     */
    public function storeProductImage(UploadedFile $file, ?string $productCode = null): array
    {
        // Generar nombre único
        $filename = $this->generateUniqueFilename($file, $productCode);
        
        // Rutas de almacenamiento
        $originalPath = self::PRODUCT_IMAGES_PATH . '/original/' . $filename;
        $resizedPath = self::PRODUCT_IMAGES_PATH . '/resized/' . $filename;
        $thumbnailPath = self::PRODUCT_IMAGES_PATH . '/thumbnails/' . $filename;

        // Crear directorios si no existen
        $this->ensureDirectoriesExist();

        // ✅ V3: Procesar imagen original
        $originalImage = $this->imageManager->read($file->getPathname());
        $this->storeImage($originalImage, $originalPath);

        // ✅ V3: Crear versión redimensionada
        $resizedImage = $this->imageManager->read($file->getPathname());
        $resizedImage->scale(width: self::MAX_WIDTH, height: self::MAX_HEIGHT);
        $this->storeImage($resizedImage, $resizedPath);

        // ✅ V3: Crear thumbnail
        $thumbnailImage = $this->imageManager->read($file->getPathname());
        $thumbnailImage->cover(self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
        $this->storeImage($thumbnailImage, $thumbnailPath);

        // ✅ GENERAR URLs COMPLETAS
        return [
            'original_url' => $this->getFullUrl($originalPath),
            'resized_url' => $this->getFullUrl($resizedPath),
            'thumbnail_url' => $this->getFullUrl($thumbnailPath),
            'filename' => $filename,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * ✅ MÉTODO PARA GENERAR URL COMPLETA
     */
    private function getFullUrl(string $path): string
    {
        $storageUrl = Storage::url($path);
        
        // Si ya tiene el dominio, devolverlo tal como está
        if (str_starts_with($storageUrl, 'http')) {
            return $storageUrl;
        }
        
        // Agregar el dominio base de la aplicación
        $appUrl = rtrim(config('app.url'), '/');
        return $appUrl . $storageUrl;
    }

    /**
     * Delete product image files
     */
    public function deleteProductImage(string $imageUrl): bool
    {
        if (!$imageUrl) return true;

        // Extraer filename de la URL (remover dominio si existe)
        $parsedUrl = parse_url($imageUrl);
        $path = $parsedUrl['path'] ?? $imageUrl;
        
        // Remover /storage/ del inicio si existe
        $path = preg_replace('#^/storage/#', '', $path);
        $filename = basename($path);
        
        if (!$filename) return false;

        // Rutas a eliminar
        $paths = [
            self::PRODUCT_IMAGES_PATH . '/original/' . $filename,
            self::PRODUCT_IMAGES_PATH . '/resized/' . $filename,
            self::PRODUCT_IMAGES_PATH . '/thumbnails/' . $filename,
        ];

        $deleted = true;
        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                $deleted = Storage::disk('public')->delete($path) && $deleted;
            }
        }

        return $deleted;
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file, ?string $productCode = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        
        if ($productCode) {
            $sanitizedCode = Str::slug($productCode);
            return "{$sanitizedCode}_{$timestamp}_{$random}.{$extension}";
        }
        
        return "product_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Store the image on the specified path (V3 compatible)
     */
    private function storeImage($image, string $path): void
    {
        // Ruta completa en storage/app/public
        $fullPath = storage_path('app/public/' . $path);
        
        // Crear directorio si no existe
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // ✅ V3: Guardar imagen con calidad optimizada
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                $image->toJpeg(self::QUALITY)->save($fullPath);
                break;
            case 'png':
                $image->toPng()->save($fullPath);
                break;
            case 'webp':
                $image->toWebp(self::QUALITY)->save($fullPath);
                break;
            case 'gif':
                $image->toGif()->save($fullPath);
                break;
            default:
                $image->toJpeg(self::QUALITY)->save($fullPath);
        }
    }

    /**
     * Ensure storage directories exist
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            self::PRODUCT_IMAGES_PATH . '/original',
            self::PRODUCT_IMAGES_PATH . '/resized',
            self::PRODUCT_IMAGES_PATH . '/thumbnails',
        ];

        foreach ($directories as $directory) {
            Storage::disk('public')->makeDirectory($directory);
        }
    }

    /**
     * Validate image dimensions
     */
    public function validateImageDimensions(UploadedFile $file, int $maxWidth = 2000, int $maxHeight = 2000): bool
    {
        try {
            $image = $this->imageManager->read($file->getPathname());
            return $image->width() <= $maxWidth && $image->height() <= $maxHeight;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get image info
     */
    public function getImageInfo(UploadedFile $file): array
    {
        try {
            $image = $this->imageManager->read($file->getPathname());
            
            return [
                'width' => $image->width(),
                'height' => $image->height(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
            ];
        } catch (\Exception $e) {
            return [
                'width' => 0,
                'height' => 0,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
            ];
        }
    }
}