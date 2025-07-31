<?php

namespace App\Services;

use App\Models\ProductPriceDetail;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductPriceDetailService
{
    /**
     * Get product prices for all zones (devuelve precio base si no hay precio específico)
     */
    public function getProductPricesForAllZones(int $productId): array
    {
        try {
            $product = Product::findOrFail($productId);
            $zones = Zone::orderBy('name')->get();
            
            $result = [];
            
            foreach ($zones as $zone) {
                $priceDetail = ProductPriceDetail::where('product_id', $productId)
                    ->where('zone_id', $zone->id)
                    ->first();
                
                $price = $priceDetail ? $priceDetail->price : $product->price;
                $hasCustomPrice = !is_null($priceDetail);
                
                $result[] = [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'price' => $price,
                    'formatted_price' => 'S/ ' . number_format($price, 2),
                    'has_custom_price' => $hasCustomPrice,
                    'is_base_price' => !$hasCustomPrice,
                    'price_detail_id' => $priceDetail?->id,
                    'price_difference' => $price - $product->price,
                    'price_difference_percentage' => $product->price > 0 ? round((($price - $product->price) / $product->price) * 100, 2) : 0,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'zone_prices' => $result,
                    'total_zones' => count($result),
                    'zones_with_custom_prices' => collect($result)->where('has_custom_price', true)->count(),
                ],
                'message' => 'Product zone prices retrieved successfully'
            ];

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error getting product prices for all zones: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error retrieving product prices',
                'code' => 500
            ];
        }
    }

    /**
     * Get price for specific product and zone
     */
    public function getProductZonePrice(int $productId, int $zoneId): array
    {
        try {
            $product = Product::findOrFail($productId);
            $zone = Zone::findOrFail($zoneId);
            
            $priceDetail = ProductPriceDetail::where('product_id', $productId)
                ->where('zone_id', $zoneId)
                ->first();
            
            $price = $priceDetail ? $priceDetail->price : $product->price;
            $hasCustomPrice = !is_null($priceDetail);

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'zone' => [
                        'id' => $zone->id,
                        'name' => $zone->name,
                    ],
                    'price' => $price,
                    'formatted_price' => 'S/ ' . number_format($price, 2),
                    'has_custom_price' => $hasCustomPrice,
                    'is_base_price' => !$hasCustomPrice,
                    'price_detail_id' => $priceDetail?->id,
                    'price_difference' => $price - $product->price,
                    'price_difference_percentage' => $product->price > 0 ? round((($price - $product->price) / $product->price) * 100, 2) : 0,
                ],
                'message' => 'Product zone price retrieved successfully'
            ];

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Product or Zone not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error getting product zone price: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error retrieving product zone price',
                'code' => 500
            ];
        }
    }

    /**
     * Set or update price for specific product and zone
     */
    public function setProductZonePrice(int $productId, int $zoneId, float $price): array
    {
        try {
            DB::beginTransaction();

            // Verificar que producto y zona existen
            $product = Product::findOrFail($productId);
            $zone = Zone::findOrFail($zoneId);

            // Crear o actualizar el precio
            $priceDetail = ProductPriceDetail::updateOrCreate(
                [
                    'product_id' => $productId,
                    'zone_id' => $zoneId
                ],
                [
                    'price' => $price
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'data' => $priceDetail->load(['product', 'zone']),
                'message' => 'Price set successfully for zone: ' . $zone->name
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product or Zone not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error setting product zone price: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error setting product zone price: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Update price for specific product and zone
     */
    public function updateProductZonePrice(int $productId, int $zoneId, float $price): array
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $zone = Zone::findOrFail($zoneId);

            $priceDetail = ProductPriceDetail::where('product_id', $productId)
                ->where('zone_id', $zoneId)
                ->first();

            if (!$priceDetail) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No custom price found for this product and zone. Use POST to create a new price.',
                    'code' => 404
                ];
            }

            $priceDetail->update(['price' => $price]);

            DB::commit();

            return [
                'success' => true,
                'data' => $priceDetail->fresh(['product', 'zone']),
                'message' => 'Price updated successfully for zone: ' . $zone->name
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product or Zone not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating product zone price: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error updating product zone price: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Remove price for specific product and zone (hard delete)
     */
    public function removeProductZonePrice(int $productId, int $zoneId): array
    {
        try {
            DB::beginTransaction();

            $deleted = ProductPriceDetail::where('product_id', $productId)
                ->where('zone_id', $zoneId)
                ->forceDelete();

            DB::commit();

            if ($deleted) {
                return [
                    'success' => true,
                    'message' => 'Custom price removed successfully (reverted to base price)'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No custom price found for this product and zone',
                    'code' => 404
                ];
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error removing product zone price: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error removing product zone price: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Soft delete price for specific product and zone
     */
    public function softDeleteProductZonePrice(int $productId, int $zoneId): array
    {
        try {
            DB::beginTransaction();

            $priceDetail = ProductPriceDetail::where('product_id', $productId)
                ->where('zone_id', $zoneId)
                ->first();

            if (!$priceDetail) {
                return [
                    'success' => false,
                    'message' => 'No custom price found for this product and zone',
                    'code' => 404
                ];
            }

            $priceDetail->delete(); // Soft delete

            DB::commit();

            return [
                'success' => true,
                'message' => 'Custom price soft deleted successfully (will revert to base price)'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error soft deleting product zone price: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error soft deleting product zone price: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Get soft deleted prices for a product
     */
    public function getTrashedProductZonePrices(int $productId): array
    {
        try {
            $product = Product::findOrFail($productId);
            
            $trashedPrices = ProductPriceDetail::onlyTrashed()
                ->with('zone')
                ->where('product_id', $productId)
                ->orderBy('deleted_at', 'desc')
                ->get();

            $result = $trashedPrices->map(function ($priceDetail) {
                return [
                    'price_detail_id' => $priceDetail->id,
                    'zone_id' => $priceDetail->zone_id,
                    'zone_name' => $priceDetail->zone->name,
                    'price' => $priceDetail->price,
                    'formatted_price' => $priceDetail->formatted_price,
                    'deleted_at' => $priceDetail->deleted_at,
                ];
            });

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                    ],
                    'trashed_prices' => $result,
                    'trashed_count' => $result->count(),
                ],
                'message' => 'Trashed product zone prices retrieved successfully'
            ];

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error getting trashed product zone prices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error retrieving trashed product zone prices',
                'code' => 500
            ];
        }
    }

    /**
     * Restore soft deleted price for specific product and zone
     */
    public function restoreProductZonePrice(int $productId, int $zoneId): array
    {
        try {
            DB::beginTransaction();

            $priceDetail = ProductPriceDetail::onlyTrashed()
                ->where('product_id', $productId)
                ->where('zone_id', $zoneId)
                ->first();

            if (!$priceDetail) {
                return [
                    'success' => false,
                    'message' => 'No soft deleted price found for this product and zone',
                    'code' => 404
                ];
            }

            $priceDetail->restore();

            DB::commit();

            return [
                'success' => true,
                'data' => $priceDetail->fresh(['product', 'zone']),
                'message' => 'Custom price restored successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error restoring product zone price: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error restoring product zone price: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Force delete (permanently delete) price for specific product and zone
     */
    public function forceDeleteProductZonePrice(int $productId, int $zoneId): array
    {
        try {
            DB::beginTransaction();

            $priceDetail = ProductPriceDetail::onlyTrashed()
                ->where('product_id', $productId)
                ->where('zone_id', $zoneId)
                ->first();

            if (!$priceDetail) {
                return [
                    'success' => false,
                    'message' => 'No soft deleted price found for this product and zone',
                    'code' => 404
                ];
            }

            $priceDetail->forceDelete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Custom price permanently deleted'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error force deleting product zone price: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error permanently deleting product zone price: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Set prices for a product in multiple zones
     */
    public function setMultipleZonePrices(int $productId, array $zonePrices): array
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $updated = [];
            $errors = [];

            foreach ($zonePrices as $zonePriceData) {
                try {
                    $zoneId = $zonePriceData['zone_id'];
                    $price = $zonePriceData['price'];

                    // Verificar que la zona existe
                    $zone = Zone::findOrFail($zoneId);

                    $priceDetail = ProductPriceDetail::updateOrCreate(
                        [
                            'product_id' => $productId,
                            'zone_id' => $zoneId
                        ],
                        [
                            'price' => $price
                        ]
                    );

                    $updated[] = [
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'price' => $price,
                        'formatted_price' => 'S/ ' . number_format($price, 2),
                        'price_detail_id' => $priceDetail->id,
                    ];

                } catch (\Exception $e) {
                    $errors[] = [
                        'zone_id' => $zonePriceData['zone_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'updated_prices' => $updated,
                    'errors' => $errors,
                    'success_count' => count($updated),
                    'error_count' => count($errors),
                ],
                'message' => sprintf('Prices updated for %d zones successfully', count($updated))
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error setting multiple zone prices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error setting multiple zone prices: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Apply formula to create prices for a product in multiple zones
     */
    public function applyFormulaToProduct(int $productId, array $zoneIds, string $formulaType, float $formulaValue): array
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $zones = Zone::whereIn('id', $zoneIds)->get();

            if ($zones->count() !== count($zoneIds)) {
                return [
                    'success' => false,
                    'message' => 'One or more zones not found',
                    'code' => 404
                ];
            }

            $created = [];
            $updated = [];
            $errors = [];

            foreach ($zones as $zone) {
                try {
                    // Calcular precio según fórmula
                    $newPrice = $this->calculatePriceByFormula($product->price, $formulaType, $formulaValue);

                    if ($newPrice < 0) {
                        $errors[] = [
                            'zone_id' => $zone->id,
                            'zone_name' => $zone->name,
                            'error' => 'Calculated price is negative'
                        ];
                        continue;
                    }

                    // Verificar si ya existe
                    $existing = ProductPriceDetail::where('product_id', $productId)
                        ->where('zone_id', $zone->id)
                        ->first();

                    $priceDetail = ProductPriceDetail::updateOrCreate(
                        [
                            'product_id' => $productId,
                            'zone_id' => $zone->id
                        ],
                        [
                            'price' => $newPrice
                        ]
                    );

                    $result = [
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'price' => $newPrice,
                        'formatted_price' => 'S/ ' . number_format($newPrice, 2),
                        'price_detail_id' => $priceDetail->id,
                        'formula_applied' => "{$formulaType}: {$formulaValue}"
                    ];

                    if ($existing) {
                        $updated[] = $result;
                    } else {
                        $created[] = $result;
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'formula' => [
                        'type' => $formulaType,
                        'value' => $formulaValue,
                        'description' => $this->getFormulaDescription($formulaType, $formulaValue)
                    ],
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => $errors,
                    'created_count' => count($created),
                    'updated_count' => count($updated),
                    'error_count' => count($errors),
                    'total_processed' => count($zones),
                ],
                'message' => sprintf('Formula applied: %d created, %d updated, %d errors', count($created), count($updated), count($errors))
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error applying formula to product: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error applying formula: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Get all zone prices detailed (including zones without custom prices)
     */
    public function getAllZonePricesDetailed(int $productId): array
    {
        try {
            $product = Product::findOrFail($productId);
            $zones = Zone::orderBy('name')->get();
            
            $activePrices = ProductPriceDetail::where('product_id', $productId)
                ->with('zone')
                ->get()
                ->keyBy('zone_id');
                
            $trashedPrices = ProductPriceDetail::onlyTrashed()
                ->where('product_id', $productId)
                ->with('zone')
                ->get()
                ->keyBy('zone_id');
            
            $result = [];
            
            foreach ($zones as $zone) {
                $activePrice = $activePrices->get($zone->id);
                $trashedPrice = $trashedPrices->get($zone->id);
                
                $price = $activePrice ? $activePrice->price : $product->price;
                $hasCustomPrice = !is_null($activePrice);
                $hasTrashedPrice = !is_null($trashedPrice);
                
                $result[] = [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'zone_description' => $zone->description,
                    
                    // Precio actual
                    'current_price' => $price,
                    'formatted_current_price' => 'S/ ' . number_format($price, 2),
                    'has_custom_price' => $hasCustomPrice,
                    'is_base_price' => !$hasCustomPrice,
                    
                    // Detalles del precio personalizado
                    'price_detail_id' => $activePrice?->id,
                    'custom_price' => $activePrice?->price,
                    'formatted_custom_price' => $activePrice ? 'S/ ' . number_format($activePrice->price, 2) : null,
                    
                    // Precio eliminado
                    'has_trashed_price' => $hasTrashedPrice,
                    'trashed_price_detail_id' => $trashedPrice?->id,
                    'trashed_price' => $trashedPrice?->price,
                    'formatted_trashed_price' => $trashedPrice ? 'S/ ' . number_format($trashedPrice->price, 2) : null,
                    'trashed_at' => $trashedPrice?->deleted_at,
                    
                    // Comparaciones
                    'price_difference' => $price - $product->price,
                    'price_difference_percentage' => $product->price > 0 ? round((($price - $product->price) / $product->price) * 100, 2) : 0,
                    'is_higher_than_base' => $price > $product->price,
                    'is_lower_than_base' => $price < $product->price,
                    
                    // Timestamps
                    'created_at' => $activePrice?->created_at,
                    'updated_at' => $activePrice?->updated_at,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'zones' => $result,
                    'summary' => [
                        'total_zones' => count($result),
                        'zones_with_custom_prices' => collect($result)->where('has_custom_price', true)->count(),
                        'zones_with_base_price' => collect($result)->where('is_base_price', true)->count(),
                        'zones_with_trashed_prices' => collect($result)->where('has_trashed_price', true)->count(),
                        'zones_higher_than_base' => collect($result)->where('is_higher_than_base', true)->count(),
                        'zones_lower_than_base' => collect($result)->where('is_lower_than_base', true)->count(),
                    ]
                ],
                'message' => 'All zone prices retrieved successfully'
            ];

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error getting all zone prices detailed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error retrieving zone prices',
                'code' => 500
            ];
        }
    }

    /**
     * Update prices for multiple zones
     */
    public function updateMultipleZonePrices(int $productId, array $zonePrices): array
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $updated = [];
            $notFound = [];
            $errors = [];

            foreach ($zonePrices as $zonePriceData) {
                try {
                    $zoneId = $zonePriceData['zone_id'];
                    $price = $zonePriceData['price'];

                    // Verificar que la zona existe
                    $zone = Zone::find($zoneId);
                    if (!$zone) {
                        $notFound[] = [
                            'zone_id' => $zoneId,
                            'error' => 'Zone not found'
                        ];
                        continue;
                    }

                    // Buscar precio existente (solo actualizar, no crear)
                    $priceDetail = ProductPriceDetail::where('product_id', $productId)
                        ->where('zone_id', $zoneId)
                        ->first();

                    if (!$priceDetail) {
                        $notFound[] = [
                            'zone_id' => $zoneId,
                            'zone_name' => $zone->name,
                            'error' => 'No custom price exists for this zone. Use POST to create.'
                        ];
                        continue;
                    }

                    // Actualizar precio existente
                    $oldPrice = $priceDetail->price;
                    $priceDetail->update(['price' => $price]);

                    $updated[] = [
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'price_detail_id' => $priceDetail->id,
                        'old_price' => $oldPrice,
                        'new_price' => $price,
                        'formatted_old_price' => 'S/ ' . number_format($oldPrice, 2),
                        'formatted_new_price' => 'S/ ' . number_format($price, 2),
                        'price_change' => $price - $oldPrice,
                    ];

                } catch (\Exception $e) {
                    $errors[] = [
                        'zone_id' => $zonePriceData['zone_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'updated' => $updated,
                    'not_found' => $notFound,
                    'errors' => $errors,
                    'summary' => [
                        'updated_count' => count($updated),
                        'not_found_count' => count($notFound),
                        'error_count' => count($errors),
                        'total_processed' => count($zonePrices),
                    ]
                ],
                'message' => sprintf('Bulk update completed: %d updated, %d not found, %d errors', count($updated), count($notFound), count($errors))
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating multiple zone prices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error updating multiple zone prices: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Soft delete prices for multiple zones
     */
    public function softDeleteMultipleZonePrices(int $productId, array $zoneIds): array
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $deleted = [];
            $notFound = [];

            foreach ($zoneIds as $zoneId) {
                $zone = Zone::find($zoneId);
                if (!$zone) {
                    $notFound[] = [
                        'zone_id' => $zoneId,
                        'error' => 'Zone not found'
                    ];
                    continue;
                }

                $priceDetail = ProductPriceDetail::where('product_id', $productId)
                    ->where('zone_id', $zoneId)
                    ->first();

                if ($priceDetail) {
                    $deletedPrice = $priceDetail->price;
                    $priceDetail->delete(); // Soft delete
                    
                    $deleted[] = [
                        'zone_id' => $zoneId,
                        'zone_name' => $zone->name,
                        'price_detail_id' => $priceDetail->id,
                        'deleted_price' => $deletedPrice,
                        'formatted_deleted_price' => 'S/ ' . number_format($deletedPrice, 2),
                        'reverted_to_base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ];
                } else {
                    $notFound[] = [
                        'zone_id' => $zoneId,
                        'zone_name' => $zone->name,
                        'error' => 'No custom price found for this zone'
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'deleted' => $deleted,
                    'not_found' => $notFound,
                    'summary' => [
                        'deleted_count' => count($deleted),
                        'not_found_count' => count($notFound),
                        'total_processed' => count($zoneIds),
                    ]
                ],
                'message' => sprintf('Bulk soft delete completed: %d deleted, %d not found', count($deleted), count($notFound))
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error soft deleting multiple zone prices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error soft deleting multiple zone prices: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Hard delete (remove) prices for multiple zones - revert to base price
     */
    public function removeMultipleZonePrices(int $productId, array $zoneIds): array
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $removed = [];
            $notFound = [];

            foreach ($zoneIds as $zoneId) {
                $zone = Zone::find($zoneId);
                if (!$zone) {
                    $notFound[] = [
                        'zone_id' => $zoneId,
                        'error' => 'Zone not found'
                    ];
                    continue;
                }

                $deletedCount = ProductPriceDetail::where('product_id', $productId)
                    ->where('zone_id', $zoneId)
                    ->forceDelete(); // Hard delete

                if ($deletedCount > 0) {
                    $removed[] = [
                        'zone_id' => $zoneId,
                        'zone_name' => $zone->name,
                        'message' => 'Custom price permanently removed, reverted to base price',
                        'current_price' => $product->price,
                        'formatted_current_price' => $product->formatted_price,
                    ];
                } else {
                    $notFound[] = [
                        'zone_id' => $zoneId,
                        'zone_name' => $zone->name,
                        'error' => 'No custom price found for this zone'
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'removed' => $removed,
                    'not_found' => $notFound,
                    'summary' => [
                        'removed_count' => count($removed),
                        'not_found_count' => count($notFound),
                        'total_processed' => count($zoneIds),
                    ]
                ],
                'message' => sprintf('Bulk remove completed: %d removed, %d not found', count($removed), count($notFound))
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error removing multiple zone prices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error removing multiple zone prices: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Bulk soft delete prices for multiple zones
     */
    public function bulkSoftDeleteZonePrices(int $productId, array $zoneIds): array
    {
        return $this->softDeleteMultipleZonePrices($productId, $zoneIds);
    }

    /**
     * Bulk restore prices for multiple zones
     */
    public function bulkRestoreZonePrices(int $productId, array $zoneIds): array
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $restored = [];
            $notFound = [];

            foreach ($zoneIds as $zoneId) {
                $zone = Zone::find($zoneId);
                if (!$zone) {
                    $notFound[] = $zoneId;
                    continue;
                }

                $priceDetail = ProductPriceDetail::onlyTrashed()
                    ->where('product_id', $productId)
                    ->where('zone_id', $zoneId)
                    ->first();

                if ($priceDetail) {
                    $priceDetail->restore();
                    $restored[] = [
                        'zone_id' => $zoneId,
                        'zone_name' => $zone->name,
                        'price_detail_id' => $priceDetail->id,
                        'price' => $priceDetail->price,
                    ];
                } else {
                    $notFound[] = $zoneId;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                    ],
                    'restored' => $restored,
                    'not_found' => $notFound,
                    'restored_count' => count($restored),
                    'not_found_count' => count($notFound),
                ],
                'message' => sprintf('Bulk restore completed: %d restored, %d not found', count($restored), count($notFound))
            ];

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk restoring zone prices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error bulk restoring zone prices: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Get summary of zone prices
     */
    public function getZonePricesSummary(int $productId): array
    {
        try {
            $product = Product::findOrFail($productId);
            
            $totalZones = Zone::count();
            $activePrices = ProductPriceDetail::where('product_id', $productId)->count();
            $trashedPrices = ProductPriceDetail::onlyTrashed()->where('product_id', $productId)->count();
            $basePriceZones = $totalZones - $activePrices;
            
            $pricesHigherThanBase = ProductPriceDetail::where('product_id', $productId)
                ->where('price', '>', $product->price)
                ->count();
                
            $pricesLowerThanBase = ProductPriceDetail::where('product_id', $productId)
                ->where('price', '<', $product->price)
                ->count();
                
            $pricesEqualToBase = ProductPriceDetail::where('product_id', $productId)
                ->where('price', '=', $product->price)
                ->count();

            return [
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'description' => $product->description,
                        'base_price' => $product->price,
                        'formatted_base_price' => $product->formatted_price,
                    ],
                    'summary' => [
                        'total_zones' => $totalZones,
                        'zones_with_custom_prices' => $activePrices,
                        'zones_with_base_price' => $basePriceZones,
                        'zones_with_trashed_prices' => $trashedPrices,
                        'custom_prices_higher_than_base' => $pricesHigherThanBase,
                        'custom_prices_lower_than_base' => $pricesLowerThanBase,
                        'custom_prices_equal_to_base' => $pricesEqualToBase,
                    ],
                    'percentages' => [
                        'custom_price_coverage' => $totalZones > 0 ? round(($activePrices / $totalZones) * 100, 2) : 0,
                        'base_price_coverage' => $totalZones > 0 ? round(($basePriceZones / $totalZones) * 100, 2) : 0,
                    ]
                ],
                'message' => 'Zone prices summary retrieved successfully'
            ];

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Error getting zone prices summary: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error retrieving zone prices summary',
                'code' => 500
            ];
        }
    }

    /**
     * Calculate price using formula
     */
    private function calculatePriceByFormula(float $basePrice, string $formulaType, float $formulaValue): float
    {
        switch ($formulaType) {
            case 'percentage':
                // Ejemplo: 15% más = base * 1.15, 15% menos = base * 0.85
                return $basePrice * (1 + ($formulaValue / 100));
                
            case 'fixed_amount':
                // Ejemplo: +2.50 = base + 2.50, -1.00 = base - 1.00
                return $basePrice + $formulaValue;
                
            case 'fixed_price':
                // Precio fijo = formulaValue
                return $formulaValue;
                
            default:
                return $basePrice;
        }
    }

    /**
     * Get human-readable formula description
     */
    private function getFormulaDescription(string $formulaType, float $formulaValue): string
    {
        switch ($formulaType) {
            case 'percentage':
                $sign = $formulaValue >= 0 ? '+' : '';
                return "Base price {$sign}{$formulaValue}%";
                
            case 'fixed_amount':
                $sign = $formulaValue >= 0 ? '+' : '';
                return "Base price {$sign}S/ {$formulaValue}";
                
            case 'fixed_price':
                return "Fixed price S/ {$formulaValue}";
                
            default:
                return "Unknown formula";
        }
    }

    /**
     * Get all products with their prices for each zone (PIVOT style)
     */
    public function getAllProductsWithZonePrices(array $filters = []): array
    {
        try {
            // Filtros
            $includeDeleted = $filters['include_deleted'] ?? false;
            $onlyWithCustomPrices = $filters['only_with_custom_prices'] ?? false;
            $zoneIds = $filters['zone_ids'] ?? [];
            $productIds = $filters['product_ids'] ?? [];
            $categoryIds = $filters['category_ids'] ?? [];

            // Obtener todas las zonas (filtradas si se especifica)
            $zonesQuery = Zone::orderBy('name');
            if (!empty($zoneIds)) {
                $zonesQuery->whereIn('id', $zoneIds);
            }
            $zones = $zonesQuery->get();

            // Obtener productos
            $productsQuery = Product::with(['category', 'measureType']);
            
            if (!$includeDeleted) {
                $productsQuery->active();
            } else {
                $productsQuery->withTrashed();
            }

            if (!empty($productIds)) {
                $productsQuery->whereIn('id', $productIds);
            }

            if (!empty($categoryIds)) {
                $productsQuery->whereIn('product_category_id', $categoryIds);
            }

            if ($onlyWithCustomPrices) {
                $productsQuery->whereHas('priceDetails');
            }

            $products = $productsQuery->orderBy('description')->get();

            // Obtener todos los precios personalizados
            $priceDetailsQuery = ProductPriceDetail::with(['zone']);
            
            if (!$includeDeleted) {
                $priceDetailsQuery->active();
            } else {
                $priceDetailsQuery->withTrashed();
            }

            if (!empty($productIds)) {
                $priceDetailsQuery->whereIn('product_id', $productIds);
            }

            if (!empty($zoneIds)) {
                $priceDetailsQuery->whereIn('zone_id', $zoneIds);
            }

            $priceDetails = $priceDetailsQuery->get()
                ->groupBy('product_id')
                ->map(function ($productPrices) {
                    return $productPrices->keyBy('zone_id');
                });

            // Construir resultado tipo PIVOT
            $result = [];
            $zoneColumns = [];
            $statistics = [
                'total_products' => 0,
                'products_with_custom_prices' => 0,
                'total_custom_prices' => 0,
                'zones_coverage' => [],
            ];

            // Preparar columnas de zonas
            foreach ($zones as $zone) {
                $zoneColumns[$zone->id] = [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'zone_description' => $zone->description,
                ];
            }

            foreach ($products as $product) {
                $productPrices = $priceDetails->get($product->id, collect());
                $hasCustomPrices = $productPrices->isNotEmpty();
                
                $productRow = [
                    // Información del producto
                    'product_id' => $product->id,
                    'product_description' => $product->description,
                    'product_code' => $product->code,
                    'base_price' => $product->price,
                    'formatted_base_price' => $product->formatted_price,
                    'category_id' => $product->product_category_id,
                    'category_name' => $product->category?->name,
                    'measure_type_id' => $product->measure_type_id,
                    'measure_type_name' => $product->measureType?->name,
                    'has_custom_prices' => $hasCustomPrices,
                    'custom_prices_count' => $productPrices->count(),
                    'product_status' => $product->deleted_at ? 'deleted' : 'active',
                    
                    // Precios por zona (PIVOT)
                    'zone_prices' => [],
                    
                    // Estadísticas del producto
                    'price_statistics' => [
                        'min_price' => $product->price,
                        'max_price' => $product->price,
                        'avg_price' => $product->price,
                        'price_variance' => 0,
                        'zones_higher_than_base' => 0,
                        'zones_lower_than_base' => 0,
                    ]
                ];

                $zonePricesArray = [];
                $allPrices = [$product->price]; // Incluir precio base para estadísticas

                foreach ($zones as $zone) {
                    $priceDetail = $productPrices->get($zone->id);
                    $price = $priceDetail ? $priceDetail->price : $product->price;
                    $hasCustomPrice = !is_null($priceDetail);
                    
                    if ($hasCustomPrice) {
                        $allPrices[] = $priceDetail->price;
                    }

                    $zonePriceData = [
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name,
                        'price' => $price,
                        'formatted_price' => 'S/ ' . number_format($price, 2),
                        'has_custom_price' => $hasCustomPrice,
                        'is_base_price' => !$hasCustomPrice,
                        'price_detail_id' => $priceDetail?->id,
                        'price_difference' => $price - $product->price,
                        'price_difference_percentage' => $product->price > 0 ? round((($price - $product->price) / $product->price) * 100, 2) : 0,
                        'is_higher_than_base' => $price > $product->price,
                        'is_lower_than_base' => $price < $product->price,
                        'status' => $priceDetail?->deleted_at ? 'deleted' : ($hasCustomPrice ? 'custom' : 'base'),
                        'created_at' => $priceDetail?->created_at,
                        'updated_at' => $priceDetail?->updated_at,
                        'deleted_at' => $priceDetail?->deleted_at,
                    ];

                    $productRow['zone_prices'][$zone->id] = $zonePriceData;
                    $zonePricesArray[] = $zonePriceData;

                    // Contar para estadísticas
                    if ($price > $product->price) {
                        $productRow['price_statistics']['zones_higher_than_base']++;
                    } elseif ($price < $product->price) {
                        $productRow['price_statistics']['zones_lower_than_base']++;
                    }

                    // Estadísticas de cobertura por zona
                    if (!isset($statistics['zones_coverage'][$zone->id])) {
                        $statistics['zones_coverage'][$zone->id] = [
                            'zone_name' => $zone->name,
                            'products_with_custom_price' => 0,
                            'total_products' => 0,
                        ];
                    }
                    $statistics['zones_coverage'][$zone->id]['total_products']++;
                    if ($hasCustomPrice) {
                        $statistics['zones_coverage'][$zone->id]['products_with_custom_price']++;
                    }
                }

                // Calcular estadísticas de precios del producto
                if (count($allPrices) > 1) {
                    $productRow['price_statistics']['min_price'] = min($allPrices);
                    $productRow['price_statistics']['max_price'] = max($allPrices);
                    $productRow['price_statistics']['avg_price'] = round(array_sum($allPrices) / count($allPrices), 2);
                    
                    // Varianza
                    $mean = $productRow['price_statistics']['avg_price'];
                    $variance = array_sum(array_map(function($price) use ($mean) {
                        return pow($price - $mean, 2);
                    }, $allPrices)) / count($allPrices);
                    $productRow['price_statistics']['price_variance'] = round($variance, 2);
                }

                $result[] = $productRow;

                // Estadísticas generales
                $statistics['total_products']++;
                if ($hasCustomPrices) {
                    $statistics['products_with_custom_prices']++;
                    $statistics['total_custom_prices'] += $productPrices->count();
                }
            }

            // Calcular porcentajes de cobertura
            foreach ($statistics['zones_coverage'] as $zoneId => &$zoneCoverage) {
                $zoneCoverage['coverage_percentage'] = $zoneCoverage['total_products'] > 0 
                    ? round(($zoneCoverage['products_with_custom_price'] / $zoneCoverage['total_products']) * 100, 2)
                    : 0;
            }

            return [
                'success' => true,
                'data' => [
                    'products' => $result,
                    'zones' => $zoneColumns,
                    'statistics' => array_merge($statistics, [
                        'zones_count' => count($zones),
                        'custom_price_coverage_percentage' => $statistics['total_products'] > 0 
                            ? round(($statistics['products_with_custom_prices'] / $statistics['total_products']) * 100, 2)
                            : 0,
                    ]),
                    'filters_applied' => [
                        'include_deleted' => $includeDeleted,
                        'only_with_custom_prices' => $onlyWithCustomPrices,
                        'zone_ids' => $zoneIds,
                        'product_ids' => $productIds,
                        'category_ids' => $categoryIds,
                    ]
                ],
                'message' => 'All products with zone prices retrieved successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Error getting all products with zone prices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error retrieving products with zone prices: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Get products with zone prices in simplified PIVOT format (for exports)
     */
    public function getProductsZonePricesPivot(array $filters = []): array
    {
        try {
            $result = $this->getAllProductsWithZonePrices($filters);
            
            if (!$result['success']) {
                return $result;
            }

            $products = $result['data']['products'];
            $zones = $result['data']['zones'];

            // Crear formato simplificado tipo tabla
            $pivotData = [];
            $headers = ['Producto', 'Código', 'Precio Base', 'Categoría'];
            
            // Agregar columnas de zonas
            foreach ($zones as $zone) {
                $headers[] = $zone['zone_name'];
            }

            foreach ($products as $product) {
                $row = [
                    'Producto' => $product['product_description'],
                    'Código' => $product['product_code'],
                    'Precio Base' => $product['formatted_base_price'],
                    'Categoría' => $product['category_name'],
                ];

                // Agregar precios por zona
                foreach ($zones as $zoneId => $zone) {
                    $zonePrice = $product['zone_prices'][$zoneId] ?? null;
                    $row[$zone['zone_name']] = $zonePrice ? $zonePrice['formatted_price'] : $product['formatted_base_price'];
                }

                $pivotData[] = $row;
            }

            return [
                'success' => true,
                'data' => [
                    'headers' => $headers,
                    'rows' => $pivotData,
                    'statistics' => $result['data']['statistics'],
                ],
                'message' => 'Products zone prices pivot data retrieved successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Error getting products zone prices pivot: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error retrieving pivot data: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
}