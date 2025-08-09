<?php

namespace App\Repositories;

use App\Models\ProductPriceDetail;
use App\Models\Zone;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductPriceDetailRepository
{
    public function __construct(
        private ProductPriceDetail $model
    ) {}

    public function findActiveWithRelations(int $id): ?ProductPriceDetail
    {
        return $this->model->active()
            ->with(['product', 'zone'])
            ->find($id);
    }

    public function getAllActiveWithPagination(array $filters, ?array $allowedZoneIds = null): LengthAwarePaginator
    {
        $query = $this->model->active()->with(['product', 'zone']);

        //  FILTRO POR ZONAS PERMITIDAS (para vendedores)
        if ($allowedZoneIds !== null) {
            $query->whereIn('zone_id', $allowedZoneIds);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($productQuery) use ($search) {
                      $productQuery->where('name', 'LIKE', "%{$search}%")
                                  ->orWhere('code', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('zone', function ($zoneQuery) use ($search) {
                      $zoneQuery->where('name', 'LIKE', "%{$search}%")
                               ->orWhere('code', 'LIKE', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['zone_id'])) {
            $query->where('zone_id', $filters['zone_id']);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        //  ORDENAMIENTO POR RELACIONES
        switch ($sortBy) {
            case 'product_name':
                $query->join('products', 'product_price_details.product_id', '=', 'products.id')
                      ->orderBy('products.name', $sortOrder)
                      ->select('product_price_details.*');
                break;
            case 'zone_name':
                $query->join('zones', 'product_price_details.zone_id', '=', 'zones.id')
                      ->orderBy('zones.name', $sortOrder)
                      ->select('product_price_details.*');
                break;
            default:
                $query->orderBy($sortBy, $sortOrder);
                break;
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getActiveForDropdown(?array $allowedZoneIds = null): Collection
    {
        $query = $this->model->active()
            ->with(['product', 'zone'])
            ->orderBy('id', 'desc');

        if ($allowedZoneIds !== null) {
            $query->whereIn('zone_id', $allowedZoneIds);
        }

        return $query->get();
    }

    /**
     *  CREAR PRECIOS MASIVOS - RESTAURAR SOFT DELETED
     */
    public function createMassivePrices(int $productId, array $prices): array
    {
        return DB::transaction(function () use ($productId, $prices) {
            Log::info('Creating massive prices (with restore check)', [
                'product_id' => $productId,
                'prices_count' => count($prices)
            ]);

            $product = Product::findOrFail($productId);
            $allZones = Zone::active()->get();
            
            $results = [
                'created' => [],
                'skipped' => [],
                'errors' => [],
            ];

            //  PROCESAR PRECIOS ESPECIFICADOS
            $specifiedZoneIds = [];
            foreach ($prices as $priceData) {
                try {
                    $zoneId = $priceData['zone_id'];
                    $specifiedZoneIds[] = $zoneId;

                    //  BUSCAR INCLUYENDO SOFT DELETED
                    $existing = $this->model->withTrashed()
                                           ->where('product_id', $productId)
                                           ->where('zone_id', $zoneId)
                                           ->first();

                    if ($existing) {
                        if ($existing->trashed()) {
                            //  RESTAURAR Y ACTUALIZAR
                            $existing->restore();
                            $existing->update(['price' => $priceData['price']]);
                            $results['created'][] = $existing->fresh(['product', 'zone']);
                            
                            Log::info('Restored and updated soft deleted price', [
                                'id' => $existing->id,
                                'product_id' => $productId,
                                'zone_id' => $zoneId,
                                'new_price' => $priceData['price']
                            ]);
                        } else {
                            //  YA EXISTE ACTIVO: Omitir
                            $results['skipped'][] = [
                                'zone_id' => $zoneId,
                                'zone_name' => Zone::find($zoneId)?->name ?? 'Zona desconocida',
                                'existing_price' => $existing->price,
                                'requested_price' => $priceData['price'],
                                'reason' => 'Ya existe precio activo para esta zona'
                            ];
                        }
                    } else {
                        //  NO EXISTE: Crear nuevo
                        $newPrice = $this->model->create([
                            'product_id' => $productId,
                            'zone_id' => $zoneId,
                            'price' => $priceData['price'],
                        ]);
                        
                        $results['created'][] = $newPrice->load(['product', 'zone']);
                        
                        Log::info('Created new price', [
                            'id' => $newPrice->id,
                            'product_id' => $productId,
                            'zone_id' => $zoneId,
                            'price' => $priceData['price']
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'zone_id' => $priceData['zone_id'],
                        'price' => $priceData['price'],
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Error creating/restoring price', [
                        'product_id' => $productId,
                        'zone_id' => $priceData['zone_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            //  CREAR/RESTAURAR PRECIOS POR DEFECTO PARA ZONAS FALTANTES
            $missingZones = $allZones->whereNotIn('id', $specifiedZoneIds);

            foreach ($missingZones as $zone) {
                try {
                    $existing = $this->model->withTrashed()
                                           ->where('product_id', $productId)
                                           ->where('zone_id', $zone->id)
                                           ->first();

                    if ($existing) {
                        if ($existing->trashed()) {
                            //  RESTAURAR CON PRECIO POR DEFECTO
                            $existing->restore();
                            $existing->update(['price' => $product->price]);
                            $results['created'][] = $existing->fresh(['product', 'zone']);
                            
                            Log::info('Restored soft deleted price with default', [
                                'id' => $existing->id,
                                'product_id' => $productId,
                                'zone_id' => $zone->id,
                                'price' => $product->price
                            ]);
                        } else {
                            //  YA EXISTE ACTIVO: Omitir
                            $results['skipped'][] = [
                                'zone_id' => $zone->id,
                                'zone_name' => $zone->name,
                                'existing_price' => $existing->price,
                                'reason' => 'Ya existe precio activo para esta zona'
                            ];
                        }
                    } else {
                        //  NO EXISTE: Crear nuevo
                        $newPrice = $this->model->create([
                            'product_id' => $productId,
                            'zone_id' => $zone->id,
                            'price' => $product->price,
                        ]);
                        
                        $results['created'][] = $newPrice->load(['product', 'zone']);
                        
                        Log::info('Created default price for missing zone', [
                            'id' => $newPrice->id,
                            'product_id' => $productId,
                            'zone_id' => $zone->id,
                            'price' => $product->price
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'zone_id' => $zone->id,
                        'price' => $product->price,
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info('Massive prices creation completed', [
                'product_id' => $productId,
                'created_count' => count($results['created']),
                'skipped_count' => count($results['skipped']),
                'errors_count' => count($results['errors'])
            ]);

            return $results;
        });
    }

    /**
     *  NUEVO: ACTUALIZAR PRECIOS MASIVOS
     */
    public function updateMassivePrices(int $productId, array $prices): array
    {
        return DB::transaction(function () use ($productId, $prices) {
            Log::info('Updating massive prices', [
                'product_id' => $productId,
                'prices_count' => count($prices)
            ]);

            $results = [
                'updated' => [],
                'not_found' => [],
                'errors' => [],
            ];

            foreach ($prices as $priceData) {
                try {
                    $existing = $this->model->where('product_id', $productId)
                                           ->where('zone_id', $priceData['zone_id'])
                                           ->first();

                    if ($existing) {
                        $existing->update(['price' => $priceData['price']]);
                        $results['updated'][] = $existing->fresh(['product', 'zone']);
                        
                        Log::info('Updated existing price', [
                            'id' => $existing->id,
                            'product_id' => $productId,
                            'zone_id' => $priceData['zone_id'],
                            'old_price' => $existing->getOriginal('price'),
                            'new_price' => $priceData['price']
                        ]);
                    } else {
                        $results['not_found'][] = [
                            'zone_id' => $priceData['zone_id'],
                            'zone_name' => Zone::find($priceData['zone_id'])?->name ?? 'Zona desconocida',
                            'requested_price' => $priceData['price'],
                            'reason' => 'No existe precio para esta zona'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'zone_id' => $priceData['zone_id'],
                        'price' => $priceData['price'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $results;
        });
    }

    /**
     *  OBTENER PRECIOS POR ZONAS DE UN PRODUCTO
     */
    public function getProductZonePrices(int $productId, ?array $allowedZoneIds = null): array
    {
        $product = Product::with('productPriceDetails.zone')->findOrFail($productId);
        
        $allZones = Zone::active()->get();
        if ($allowedZoneIds !== null) {
            $allZones = $allZones->whereIn('id', $allowedZoneIds);
        }

        $existingPrices = $product->productPriceDetails->keyBy('zone_id');
        
        $zonePrices = [];
        $missingZones = [];

        foreach ($allZones as $zone) {
            $existingPrice = $existingPrices->get($zone->id);
            
            if ($existingPrice) {
                $zonePrices[] = [
                    'id' => $existingPrice->id,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'zone_code' => $zone->code,
                    'price' => (float) $existingPrice->price,
                    'is_default' => abs($existingPrice->price - $product->price) < 0.01,
                    'difference' => $existingPrice->price - $product->price,
                ];
            } else {
                $missingZones[] = [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'zone_code' => $zone->code,
                    'suggested_price' => (float) $product->price,
                ];
            }
        }

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_code' => $product->code,
            'product_default_price' => (float) $product->price,
            'zone_prices' => $zonePrices,
            'missing_zones' => $missingZones,
            'total_zones' => $allZones->count(),
            'configured_zones' => count($zonePrices),
        ];
    }

    /**
     *  NUEVO: OBTENER PRECIOS AGRUPADOS POR PRODUCTO CON PAGINACIÓN
     */
    public function getAllActiveGroupedByProduct(array $filters, ?array $allowedZoneIds = null): LengthAwarePaginator
    {
        //  OBTENER PRODUCTOS QUE TIENEN PRECIOS (CON FILTROS)
        $productsQuery = Product::active()
            ->whereHas('productPriceDetails', function ($query) use ($allowedZoneIds) {
                if ($allowedZoneIds !== null) {
                    $query->whereIn('zone_id', $allowedZoneIds);
                }
            });

        //  APLICAR FILTROS DE BÚSQUEDA
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['product_id'])) {
            $productsQuery->where('id', $filters['product_id']);
        }

        //  ORDENAMIENTO
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $productsQuery->orderBy($sortBy, $sortOrder);

        //  PAGINAR PRODUCTOS
        $paginatedProducts = $productsQuery->paginate($filters['per_page'] ?? 15);

        //  CARGAR PRECIOS PARA CADA PRODUCTO PAGINADO
        $productIds = $paginatedProducts->pluck('id')->toArray();
        
        $allZones = Zone::active()->get();
        if ($allowedZoneIds !== null) {
            $allZones = $allZones->whereIn('id', $allowedZoneIds);
        }

        //  OBTENER TODOS LOS PRECIOS DE LOS PRODUCTOS PAGINADOS
        $priceDetails = $this->model->active()
            ->whereIn('product_id', $productIds)
            ->when($allowedZoneIds, function ($query, $zoneIds) {
                return $query->whereIn('zone_id', $zoneIds);
            })
            ->with(['zone'])
            ->get()
            ->groupBy('product_id');

        //  TRANSFORMAR DATOS
        $transformedItems = $paginatedProducts->map(function ($product) use ($priceDetails, $allZones, $filters) {
            $productPrices = $priceDetails->get($product->id, collect());
            
            return $this->buildProductZonePricesData($product, $productPrices, $allZones, $filters);
        });

        //  CREAR NUEVA INSTANCIA DE PAGINACIÓN CON DATOS TRANSFORMADOS
        $transformedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $transformedItems,
            $paginatedProducts->total(),
            $paginatedProducts->perPage(),
            $paginatedProducts->currentPage(),
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );

        return $transformedPaginator;
    }

    /**
     *  CONSTRUIR DATOS DE PRODUCTO CON PRECIOS POR ZONA
     */
    private function buildProductZonePricesData($product, $productPrices, $allZones, $filters = []): array
    {
        $existingPrices = $productPrices->keyBy('zone_id');
        $zonePrices = [];

        foreach ($allZones as $zone) {
            $existingPrice = $existingPrices->get($zone->id);
            
            //  APLICAR FILTRO POR ZONA SI EXISTE
            if (!empty($filters['zone_id']) && $zone->id != $filters['zone_id']) {
                continue;
            }

            if ($existingPrice) {
                $zonePrices[] = [
                    'id' => $existingPrice->id,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'zone_code' => $zone->code,
                    'price' => (float) $existingPrice->price,
                    'is_default' => abs($existingPrice->price - $product->price) < 0.01,
                    'difference' => $existingPrice->price - $product->price,
                    'created_at' => $existingPrice->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $existingPrice->updated_at?->format('Y-m-d H:i:s'),
                ];
            } else {
                //  ZONA SIN PRECIO CONFIGURADO
                $zonePrices[] = [
                    'id' => null,
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'zone_code' => $zone->code,
                    'price' => null,
                    'is_default' => null,
                    'difference' => null,
                    'created_at' => null,
                    'updated_at' => null,
                    'needs_configuration' => true,
                ];
            }
        }

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'default_price' => (float) $product->price,
            ],
            'zone_prices' => $zonePrices,
        ];
    }

    public function create(array $data): ProductPriceDetail
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?ProductPriceDetail
    {
        $priceDetail = $this->findActiveWithRelations($id);
        if (!$priceDetail) {
            return null;
        }

        $priceDetail->update($data);
        return $priceDetail->fresh(['product', 'zone']);
    }

    public function delete(int $id): bool
    {
        $priceDetail = $this->findActiveWithRelations($id);
        if (!$priceDetail) {
            return false;
        }

        return $priceDetail->delete();
    }

    public function forceDelete(int $id): bool
    {
        $priceDetail = $this->model->withTrashed()->find($id);
        if (!$priceDetail) {
            return false;
        }

        return $priceDetail->forceDelete();
    }

    /**
     *  OBTENER PRECIOS POR PRODUCTO ID
     */
    public function getByProductId(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->where('product_id', $productId)
                          ->whereNull('deleted_at')
                          ->with(['zone'])
                          ->get();
    }

    /**
     *  LIMPIAR TODOS LOS PRECIOS DE UN PRODUCTO
     */
    public function clearProductPrices(int $productId): int
    {
        Log::info('Clearing all prices for product', ['product_id' => $productId]);

        $deletedCount = $this->model->where('product_id', $productId)
                                    ->whereNull('deleted_at')
                                    ->delete(); // Soft delete

        Log::info('Product prices cleared', [
            'product_id' => $productId,
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }
}