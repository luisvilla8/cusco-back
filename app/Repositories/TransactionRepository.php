<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use App\Models\Egress;

class TransactionRepository
{
    public function __construct(private Transaction $model) {}

    public function getAllActiveWithPagination(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['agent', 'user', 'zone', 'transactionType', 'trip']);

        // ✅ POR DEFECTO: EXCLUIR DEVOLUCIONES (SOLO MOSTRAR TRANSACCIONES ORIGINALES)
        if (!isset($filters['include_returns']) || $filters['include_returns'] === false) {
            $query->whereNull('relation_to'); // Solo transacciones originales
            
            Log::info('Excluding returns from transaction list', [
                'include_returns' => $filters['include_returns'] ?? false
            ]);
        } else {
            Log::info('Including returns in transaction list', [
                'include_returns' => $filters['include_returns']
            ]);
        }

        // ✅ FILTRO ESPECÍFICO PARA SOLO DEVOLUCIONES
        if (isset($filters['only_returns']) && $filters['only_returns'] === true) {
            $query->whereNotNull('relation_to'); // Solo devoluciones
            
            Log::info('Showing only returns');
        }

        // Apply other filters
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }

        if (!empty($filters['zone_id'])) {
            $query->where('zone_id', $filters['zone_id']);
        }

        if (!empty($filters['trip_id'])) {
            $query->where('trip_id', $filters['trip_id']);
        }

        // ✅ FILTRO DE TIPO DE TRANSACCIÓN MEJORADO
        if (!empty($filters['transaction_type'])) {
            if (is_array($filters['transaction_type'])) {
                // Array de tipos: ['SALE', 'PURCHASE'] o ['RETURN_SALE', 'RETURN_PURCHASE']
                $query->whereHas('transactionType', function ($q) use ($filters) {
                    $q->whereIn('code', $filters['transaction_type']);
                });
            } else {
                // Tipo individual
                if ($filters['transaction_type'] === 'SALE') {
                    $query->whereHas('transactionType', fn($q) => $q->where('code', 'SALE'));
                } elseif ($filters['transaction_type'] === 'PURCHASE') {
                    $query->whereHas('transactionType', fn($q) => $q->where('code', 'PURCHASE'));
                }
            }
        }

        // ✅ FILTROS DE STATUS MEJORADOS - SOPORTE PARA ARRAYS
        if (!empty($filters['delivery_status'])) {
            if (is_array($filters['delivery_status'])) {
                $query->whereIn('delivery_status', $filters['delivery_status']);
            } else {
                $query->where('delivery_status', $filters['delivery_status']);
            }
        }

        if (!empty($filters['payment_status'])) {
            if (is_array($filters['payment_status'])) {
                $query->whereIn('payment_status', $filters['payment_status']);
            } else {
                $query->where('payment_status', $filters['payment_status']);
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('code', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('description', 'LIKE', "%{$filters['search']}%")
                  ->orWhereHas('agent', fn($agent) => 
                      $agent->where('name', 'LIKE', "%{$filters['search']}%")
                  );
            });
        }

        // ✅ LOGGING PARA DEBUG
        Log::info('Transaction query filters applied', [
            'filters' => $filters,
            'include_returns' => $filters['include_returns'] ?? false,
            'only_returns' => $filters['only_returns'] ?? false,
            'has_relation_to_filter' => $query->toSql()
        ]);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findActiveWithDetails(int $id): ?Transaction
    {
        return $this->model->with([
            'agent',
            'user', 
            'zone',
            'transactionType',
            'trip',
            'transactionDetails.product.measureType',
            'transactionPayments.paymentMethod'
        ])->find($id);
    }

    

    public function createWithDetailsAndPayments(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            try {
                // ✅ VALIDACIONES SOLO PARA TRANSACCIONES ORIGINALES
                if (empty($data['relation_to'])) { // Solo validar si no es devolución
                    if (!empty($data['details'])) {
                        $zoneId = $data['zone_id'] ?? null;
                        
                        foreach ($data['details'] as $detail) {
                            $product = Product::active()->find($detail['product_id']);
                            if (!$product) {
                                throw new \InvalidArgumentException("El producto con ID {$detail['product_id']} no existe o está inactivo.");
                            }
                            
                            // ✅ OBTENER PRECIO SEGÚN LA ZONA
                            $currentPrice = $this->getProductPriceForZone($detail['product_id'], $zoneId);
                            $sentPrice = (float) $detail['price'];
                            
                            // ✅ COMPARAR PRECIOS SOLO PARA VENTAS/COMPRAS NUEVAS
                            if (abs($sentPrice - $currentPrice) > 0.01) {
                                $priceSource = $this->getPriceSourceDescription($detail['product_id'], $zoneId);
                                throw new \InvalidArgumentException(
                                    "El precio del producto '{$product->name}' ha cambiado. Precio actual: S/ {$currentPrice} ({$priceSource}), precio enviado: S/ {$sentPrice}"
                            );
                            }
                            
                            // ✅ VALIDAR STOCK SOLO PARA VENTAS
                            $transactionType = \App\Models\TransactionType::find($data['transaction_type_id']);
                            if ($transactionType && $transactionType->code === 'SALE' && !$product->hasStock($detail['quantity'])) {
                                throw new \InvalidArgumentException(
                                    "Stock insuficiente para {$product->name}. Stock disponible: {$product->stock}, cantidad solicitada: {$detail['quantity']}"
                                );
                            }
                        }
                    }
                }
                
                // ✅ CALCULAR TOTALES
                $total = $data['total'] ?? collect($data['details'])->sum(fn($detail) => $detail['price'] * $detail['quantity']);
                $amountPaid = $data['amount_paid'] ?? collect($data['payments'] ?? [])->sum('amount_paid');
                
                $data['total'] = $total;
                $data['amount_paid'] = $amountPaid;

                // ✅ CREAR TRANSACTION - NO INCLUIR relation_to SI NO EXISTE
                $transactionData = [
                    'agent_id' => $data['agent_id'],
                    'user_id' => $data['user_id'],
                    'zone_id' => $data['zone_id'],
                    'transaction_type_id' => $data['transaction_type_id'],
                    'date' => $data['date'],
                    'total' => $data['total'],
                    'amount_paid' => $data['amount_paid'],
                    'delivery_status' => $data['delivery_status'] ?? Transaction::DELIVERY_STATUS_PENDING,
                    'payment_status' => $data['payment_status'] ?? Transaction::PAYMENT_STATUS_PENDING,
                ];

                // ✅ AGREGAR CAMPOS OPCIONALES SOLO SI EXISTEN
                if (!empty($data['description'])) {
                    $transactionData['description'] = $data['description'];
                }

                if (!empty($data['trip_id'])) {
                    $transactionData['trip_id'] = $data['trip_id'];
                }

                // ✅ AGREGAR relation_to SOLO SI EXISTE (PARA DEVOLUCIONES)
                if (!empty($data['relation_to'])) {
                    $transactionData['relation_to'] = $data['relation_to'];
                }

                Log::info('Creating transaction with data', [
                    'transaction_data' => $transactionData,
                    'has_relation_to' => !empty($data['relation_to']),
                    'is_return' => !empty($data['relation_to'])
                ]);

                $transaction = $this->model->create($transactionData);

                // ✅ CREAR TRANSACTION DETAILS
                foreach ($data['details'] as $detailData) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $detailData['product_id'],
                        'price' => $detailData['price'],
                        'quantity' => $detailData['quantity']
                    ]);
                }

                // ✅ CREAR TRANSACTION PAYMENTS (SI HAY)
                if (!empty($data['payments'])) {
                    foreach ($data['payments'] as $paymentData) {
                        TransactionPayment::create([
                            'transaction_id' => $transaction->id,
                            'payment_method_id' => $paymentData['payment_method_id'],
                            'amount_paid' => $paymentData['amount_paid'],
                            'description' => $paymentData['description'] ?? null
                        ]);
                    }
                }

                // ✅ RECARGAR TRANSACCIÓN CON RELACIONES
                $transaction = $transaction->fresh()->load([
                    'agent', 'user', 'zone', 'transactionType', 'trip',
                    'transactionDetails.product.measureType', 
                    'transactionPayments.paymentMethod'
                ]);

                // ✅ CREAR EGRESO SOLO SI ES COMPRA ORIGINAL (NO DEVOLUCIÓN)
                if (empty($data['relation_to']) && $this->isPurchaseTransaction($transaction)) {
                    $this->createPurchaseEgress($transaction);
                }

                return $transaction;
                
            } catch (\Exception $e) {
                Log::error('Error creating transaction', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    // ✅ MÉTODO HELPER PARA VALIDAR SI ES VENTA
    private function isSaleTransaction(Transaction $transaction): bool
    {
        return $transaction->transactionType?->code === 'SALE';
    }

    // ✅ MÉTODO HELPER MEJORADO CON MÁS DEBUG
    private function isPurchaseTransaction(Transaction $transaction): bool
    {
        $transactionType = $transaction->transactionType;
        
        if (!$transactionType) {
            Log::error('Transaction type not found', [
                'transaction_id' => $transaction->id,
                'transaction_type_id' => $transaction->transaction_type_id
            ]);
            return false;
        }
        
        $code = strtoupper(trim($transactionType->code));
        $name = strtoupper(trim($transactionType->name));
        
        Log::info('Checking if transaction is purchase - DETAILED', [
            'transaction_id' => $transaction->id,
            'transaction_type_id' => $transaction->transaction_type_id,
            'transaction_type_code' => $code,
            'transaction_type_name' => $name,
            'raw_code' => $transactionType->code,
            'raw_name' => $transactionType->name
        ]);
        
        // ✅ VERIFICAR TANTO CODE COMO NAME
        $isPurchase = in_array($code, ['PURCHASE', 'COMPRA', 'BUY', 'PURCHASE_ORDER']) ||
                      in_array($name, ['COMPRA', 'PURCHASE']);
        
        Log::info('Purchase check result', [
            'transaction_id' => $transaction->id,
            'is_purchase' => $isPurchase,
            'code_check' => in_array($code, ['PURCHASE', 'COMPRA', 'BUY', 'PURCHASE_ORDER']),
            'name_check' => in_array($name, ['COMPRA', 'PURCHASE'])
        ]);
        
        return $isPurchase;
    }

    // ✅ MÉTODO HELPER CORREGIDO PARA CREAR EGRESO
    private function createPurchaseEgress(Transaction $transaction): void
    {
        Log::info('Checking if egress should be created', [
            'transaction_id' => $transaction->id,
            'transaction_type_code' => $transaction->transactionType?->code,
            'is_purchase' => $this->isPurchaseTransaction($transaction)
        ]);

        // ✅ VALIDAR QUE ES COMPRA Y VERIFICAR LÓGICA DE EGRESO
        if (!$this->isPurchaseTransaction($transaction)) {
            Log::info('Not a purchase transaction, skipping egress creation', [
                'transaction_id' => $transaction->id,
                'transaction_type' => $transaction->transactionType?->code
            ]);
            return;
        }

        // ✅ USAR amount_paid EN LUGAR DE total PARA EL EGRESO
        if ($transaction->amount_paid <= 0) {
            Log::info('Egress not created: zero or negative amount paid', [
                'transaction_id' => $transaction->id,
                'total' => $transaction->total,
                'amount_paid' => $transaction->amount_paid
            ]);
            return;
        }

        try {
            Log::info('=== STARTING EGRESS CREATION ===', [
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->code,
                'transaction_type_code' => $transaction->transactionType?->code,
                'transaction_type_name' => $transaction->transactionType?->name,
                'total' => $transaction->total,
                'amount_paid' => $transaction->amount_paid
            ]);

            // ✅ VERIFICAR QUE NO EXISTA YA UN EGRESO
            $existingEgress = Egress::where('transaction_id', $transaction->id)->first();
            if ($existingEgress) {
                Log::warning('Egress already exists for transaction', [
                    'transaction_id' => $transaction->id,
                    'existing_egress_id' => $existingEgress->id
                ]);
                return;
            }

            // ✅ PREPARAR DATOS DEL EGRESO - USAR amount_paid
            $egressData = [
                'name' => "Compra - {$transaction->code}",
                'description' => $transaction->description ? 
                    "Egreso automático por compra: {$transaction->description}" : 
                    "Egreso automático por compra #{$transaction->code}",
                'amount' => $transaction->amount_paid, // ✅ USAR amount_paid
                'date' => $transaction->date,
                'transaction_id' => $transaction->id,
            ];

            // ✅ AGREGAR RELACIONES OPCIONALES
            if ($transaction->agent_id) {
                $egressData['agent_id'] = $transaction->agent_id;
            }
            
            if ($transaction->zone_id) {
                $egressData['zone_id'] = $transaction->zone_id;
            }

            if ($transaction->trip_id) {
                $egressData['trip_id'] = $transaction->trip_id;
            }

            Log::info('Creating egress with data', [
                'egress_data' => $egressData
            ]);

            // ✅ CREAR EGRESO
            $egress = Egress::create($egressData);

            Log::info('=== EGRESS CREATED SUCCESSFULLY ===', [
                'transaction_id' => $transaction->id,
                'egress_id' => $egress->id,
                'egress_code' => $egress->code,
                'amount' => $egress->amount,
                'date' => $egress->date->format('Y-m-d')
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR CREATING PURCHASE EGRESS ===', [
                'transaction_id' => $transaction->id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            
            throw new \Exception("Error al crear egreso para compra: " . $e->getMessage());
        }
    }

    public function updateWithDetailsAndPayments(int $id, array $data): Transaction
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                $transaction = $this->findActiveWithDetails($id);
                
                if (!$transaction) {
                    throw new \Exception('Transacción no encontrada');
                }

                Log::info('Updating transaction', [
                    'transaction_id' => $id,
                    'current_data' => [
                        'agent_id' => $transaction->agent_id,
                        'zone_id' => $transaction->zone_id,
                        'description' => $transaction->description,
                        'date' => $transaction->date,
                        'total' => $transaction->total,
                        'amount_paid' => $transaction->amount_paid
                    ],
                    'new_data' => $data
                ]);

                // ✅ VALIDACIONES SOLO PARA CAMPOS QUE SE ESTÁN ACTUALIZANDO
                if (isset($data['details']) && !empty($data['details'])) {
                    $zoneId = $data['zone_id'] ?? $transaction->zone_id;
                    
                    foreach ($data['details'] as $detail) {
                        $product = Product::active()->find($detail['product_id']);
                        if (!$product) {
                            throw new \InvalidArgumentException("El producto con ID {$detail['product_id']} no existe o está inactivo.");
                        }
                        
                        // ✅ OBTENER PRECIO SEGÚN LA ZONA
                        $currentPrice = $this->getProductPriceForZone($detail['product_id'], $zoneId);
                        $sentPrice = (float) $detail['price'];
                        
                        // ✅ VALIDAR PRECIOS SOLO SI HAY DIFERENCIA
                        if (abs($sentPrice - $currentPrice) > 0.01) {
                            $priceSource = $this->getPriceSourceDescription($detail['product_id'], $zoneId);
                            Log::warning('Price mismatch detected', [
                                'product_id' => $detail['product_id'],
                                'product_name' => $product->name,
                                'current_price' => $currentPrice,
                                'sent_price' => $sentPrice,
                                'price_source' => $priceSource
                            ]);
                            
                            throw new \InvalidArgumentException(
                                "El precio del producto '{$product->name}' ha cambiado. Precio actual: S/ {$currentPrice} ({$priceSource}), precio enviado: S/ {$sentPrice}"
                            );
                        }
                        
                        // ✅ VALIDAR STOCK SOLO PARA VENTAS
                        if ($transaction->isSale() && !$product->hasStock($detail['quantity'])) {
                            throw new \InvalidArgumentException(
                                "Stock insuficiente para {$product->name}. Stock disponible: {$product->stock}, cantidad solicitada: {$detail['quantity']}"
                            );
                        }
                    }
                }

                // ✅ ACTUALIZAR DATOS BÁSICOS DE LA TRANSACCIÓN
                $updateData = [];
                
                if (isset($data['agent_id'])) {
                    $updateData['agent_id'] = $data['agent_id'];
                }
                
                if (isset($data['zone_id'])) {
                    $updateData['zone_id'] = $data['zone_id'];
                }
                
                if (isset($data['description'])) {
                    $updateData['description'] = $data['description'];
                }
                
                if (isset($data['date'])) {
                    $updateData['date'] = $data['date'];
                }

                // ✅ ACTUALIZAR LA TRANSACCIÓN BÁSICA SI HAY CAMBIOS
                if (!empty($updateData)) {
                    $transaction->update($updateData);
                    Log::info('Transaction basic data updated', [
                        'transaction_id' => $id,
                        'updated_fields' => array_keys($updateData)
                    ]);
                }

                // ✅ ACTUALIZAR DETALLES SI SE PROPORCIONAN - MÉTODO CORREGIDO
                if (isset($data['details']) && !empty($data['details'])) {
                    Log::info('Updating transaction details', [
                        'transaction_id' => $id,
                        'old_details_count' => $transaction->transactionDetails()->count(),
                        'new_details_count' => count($data['details'])
                    ]);

                    // ✅ ELIMINAR DETALLES EXISTENTES - FORZAR DELETE
                    $transaction->transactionDetails()->forceDelete();
                    
                    // ✅ ESPERAR UN MOMENTO PARA QUE SE PROCESE EL DELETE
                    usleep(100000); // 0.1 segundos
                    
                    // ✅ CREAR NUEVOS DETALLES CON VALIDACIÓN MEJORADA
                    $totalCalculated = 0;
                    foreach ($data['details'] as $index => $detailData) {
                        try {
                            // ✅ VERIFICAR QUE NO EXISTA UN DETALLE DUPLICADO
                            $existingDetail = TransactionDetail::where('transaction_id', $transaction->id)
                                ->where('product_id', $detailData['product_id'])
                                ->first();
                                
                            if ($existingDetail) {
                                Log::warning('Duplicate detail found, deleting before create', [
                                    'transaction_id' => $transaction->id,
                                    'product_id' => $detailData['product_id'],
                                    'existing_detail_id' => $existingDetail->id
                            ]);
                                
                                $existingDetail->forceDelete();
                            }
                            
                            $newDetail = TransactionDetail::create([
                                'transaction_id' => $transaction->id,
                                'product_id' => $detailData['product_id'],
                                'price' => $detailData['price'],
                                'quantity' => $detailData['quantity']
                            ]);
                            
                            $totalCalculated += $detailData['price'] * $detailData['quantity'];
                            
                            Log::info('Transaction detail created successfully', [
                                'detail_id' => $newDetail->id,
                                'transaction_id' => $transaction->id,
                                'product_id' => $detailData['product_id'],
                                'index' => $index
                            ]);
                            
                        } catch (\Exception $detailError) {
                            Log::error('Error creating transaction detail', [
                                'transaction_id' => $transaction->id,
                                'detail_index' => $index,
                                'detail_data' => $detailData,
                                'error' => $detailError->getMessage()
                            ]);
                            
                            throw new \Exception("Error al crear detalle #{$index}: " . $detailError->getMessage());
                        }
                    }

                    // ✅ ACTUALIZAR EL TOTAL CALCULADO
                    $transaction->update(['total' => $totalCalculated]);
                    
                    Log::info('Transaction details updated successfully', [
                        'transaction_id' => $id,
                        'new_total' => $totalCalculated,
                        'details_created' => count($data['details'])
                    ]);
                }

                // ✅ ACTUALIZAR PAGOS SI SE PROPORCIONAN - MÉTODO CORREGIDO
                if (isset($data['payments']) && is_array($data['payments'])) {
                    Log::info('Updating transaction payments', [
                        'transaction_id' => $id,
                        'old_payments_count' => $transaction->transactionPayments()->count(),
                        'new_payments_count' => count($data['payments'])
                    ]);

                    // ✅ ELIMINAR PAGOS EXISTENTES CON FORCE DELETE Y ESPERAR
                    $existingPayments = $transaction->transactionPayments()->get();
                    foreach ($existingPayments as $payment) {
                        Log::info('Force deleting payment', [
                            'payment_id' => $payment->id,
                            'payment_code' => $payment->code,
                            'transaction_id' => $id
                        ]);
                        $payment->forceDelete();
                    }
                    
                    // ✅ ESPERAR MÁS TIEMPO PARA QUE SE PROCESE EL DELETE
                    usleep(200000); // 0.2 segundos
                    
                    // ✅ VERIFICAR QUE NO QUEDEN PAGOS
                    $remainingPayments = $transaction->transactionPayments()->count();
                    if ($remainingPayments > 0) {
                        Log::warning('Some payments still exist after deletion', [
                            'transaction_id' => $id,
                            'remaining_count' => $remainingPayments
                        ]);
                        
                        // ✅ FORZAR ELIMINACIÓN DIRECTA EN DB
                        DB::table('transaction_payments')
                            ->where('transaction_id', $id)
                            ->delete();
                    }
                    
                    // ✅ CREAR NUEVOS PAGOS CON MANEJO DE ERRORES MEJORADO
                    $totalPaid = 0;
                    foreach ($data['payments'] as $index => $paymentData) {
                        try {
                            // ✅ GENERAR CÓDIGO ÚNICO MANUALMENTE SI ES NECESARIO
                            $attempts = 0;
                            $maxAttempts = 5;
                            $paymentCode = null;
                            
                            do {
                                $prefix = 'PAY';
                                $date = now()->format('dmy');
                                $dailyCount = TransactionPayment::whereDate('created_at', now())
                                    ->whereNull('deleted_at')
                                    ->count() + 1 + $attempts + $index;
                                $sequentialNumber = str_pad($dailyCount, 4, '0', STR_PAD_LEFT);
                                $paymentCode = "{$prefix}-{$date}-{$sequentialNumber}";
                                
                                $codeExists = TransactionPayment::where('code', $paymentCode)
                                    ->whereNull('deleted_at')
                                    ->exists();
                                    
                                $attempts++;
                            } while ($codeExists && $attempts < $maxAttempts);
                            
                            if ($codeExists) {
                                // ✅ ÚLTIMO RECURSO: AGREGAR TIMESTAMP
                                $paymentCode = "{$prefix}-{$date}-" . now()->format('His') . "-{$index}";
                            }
                            
                            Log::info('Creating payment with unique code', [
                                'transaction_id' => $id,
                                'payment_index' => $index,
                                'generated_code' => $paymentCode,
                                'attempts' => $attempts
                            ]);
                            
                            $newPayment = TransactionPayment::create([
                                'transaction_id' => $transaction->id,
                                'payment_method_id' => $paymentData['payment_method_id'],
                                'amount_paid' => $paymentData['amount_paid'],
                                'description' => $paymentData['description'] ?? null,
                                'code' => $paymentCode // ✅ ASIGNAR CÓDIGO MANUALMENTE
                            ]);
                            
                            $totalPaid += $paymentData['amount_paid'];
                            
                            Log::info('Transaction payment created successfully', [
                                'payment_id' => $newPayment->id,
                                'payment_code' => $newPayment->code,
                                'transaction_id' => $transaction->id,
                                'amount' => $paymentData['amount_paid'],
                                'index' => $index
                            ]);
                            
                        } catch (\Exception $paymentError) {
                            Log::error('Error creating transaction payment', [
                                'transaction_id' => $transaction->id,
                                'payment_index' => $index,
                                'payment_data' => $paymentData,
                                'error' => $paymentError->getMessage(),
                                'trace' => $paymentError->getTraceAsString()
                            ]);
                            
                            throw new \Exception("Error al crear pago #{$index}: " . $paymentError->getMessage());
                        }
                    }

                    // ✅ ACTUALIZAR EL AMOUNT_PAID CALCULADO
                    $transaction->update(['amount_paid' => $totalPaid]);
                    
                    Log::info('Transaction payments updated successfully', [
                        'transaction_id' => $id,
                        'new_amount_paid' => $totalPaid,
                        'payments_created' => count($data['payments'])
                    ]);
                } else {
                    // ✅ SI NO SE PROPORCIONAN PAGOS, RECALCULAR DESDE EXISTENTES
                    $totalPaid = $transaction->transactionPayments()->sum('amount_paid');
                    $transaction->update(['amount_paid' => $totalPaid]);
                    
                    Log::info('Transaction amount_paid recalculated from existing payments', [
                        'transaction_id' => $id,
                        'recalculated_amount_paid' => $totalPaid
                    ]);
                }

                // ✅ RECARGAR TRANSACCIÓN CON RELACIONES
                $updatedTransaction = $transaction->fresh()->load([
                    'agent', 'user', 'zone', 'transactionType', 'trip',
                    'transactionDetails.product.measureType', 
                    'transactionPayments.paymentMethod'
                ]);

                Log::info('Transaction updated successfully', [
                    'transaction_id' => $id,
                    'final_total' => $updatedTransaction->total,
                    'final_amount_paid' => $updatedTransaction->amount_paid,
                    'payment_status' => $updatedTransaction->payment_status,
                    'details_count' => $updatedTransaction->transactionDetails->count(),
                    'payments_count' => $updatedTransaction->transactionPayments->count()
                ]);

                return $updatedTransaction;
                
            } catch (\InvalidArgumentException $e) {
                Log::error('Validation error updating transaction', [
                    'transaction_id' => $id,
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
                throw $e;
                
            } catch (\Exception $e) {
                Log::error('Error updating transaction', [
                    'transaction_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $data
                ]);
                throw new \Exception("Error al actualizar la transacción: " . $e->getMessage());
            }
        });
    }

    public function addPayment(int $id, array $paymentData): Transaction
    {
        $transaction = $this->findActiveWithDetails($id);
        
        if (!$transaction) {
            throw new \Exception('Transaction not found');
        }

        // ✅ VALIDAR QUE PUEDE RECIBIR EL PAGO
        if (!$transaction->canReceivePayment()) {
            throw new \Exception('Transaction cannot receive payments in its current state');
        }

        // ✅ CREAR NUEVO PAGO EN DB TRANSACTION
        DB::transaction(function () use ($transaction, $paymentData) {
            // ✅ CREAR EL PAGO PRIMERO
            TransactionPayment::create([
                'transaction_id' => $transaction->id,
                'payment_method_id' => $paymentData['payment_method_id'],
                'amount_paid' => $paymentData['amount_paid'],
                'description' => $paymentData['notes'] ?? null
            ]);

            // ✅ CALCULAR TOTAL PAGADO DESPUÉS DE CREAR EL PAGO
            $totalPaid = $transaction->transactionPayments()->sum('amount_paid');
            
            // ✅ ACTUALIZAR EL amount_paid CON EL TOTAL CALCULADO
            $transaction->update(['amount_paid' => $totalPaid]);
            
            // ✅ EL PAYMENT STATUS SE ACTUALIZA AUTOMÁTICAMENTE EN EL BOOT DEL MODELO
            Log::info('Payment added successfully', [
                'transaction_id' => $transaction->id,
                'new_payment_amount' => $paymentData['amount_paid'],
                'total_paid' => $totalPaid,
                'remaining_amount' => $transaction->total - $totalPaid,
                'payment_status' => $transaction->fresh()->payment_status
            ]);
        });

        return $transaction->fresh()->load([
            'agent', 'user', 'zone', 'transactionType', 'trip',
            'transactionDetails.product.measureType', 
            'transactionPayments.paymentMethod'
        ]);
    }

    public function getForDropdown(): Collection
    {
        return $this->model->with(['agent:id,name'])
            ->select('id', 'code', 'date', 'total', 'delivery_status', 'agent_id')
            ->whereNotNull('delivery_status') // ✅ ASEGURAR QUE TENGA STATUS
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($transaction) {
                // ✅ ASEGURAR QUE DELIVERY_STATUS NUNCA SEA NULL
                if (is_null($transaction->delivery_status)) {
                    $transaction->delivery_status = 'PENDING';
                }
                return $transaction;
            });
    }

    public function delete(int $id): bool
    {
        $transaction = $this->model->find($id);
        return $transaction ? $transaction->delete() : false;
    }

    public function forceDelete(int $id): bool
    {
        $transaction = $this->model->withTrashed()->find($id);
        return $transaction ? $transaction->forceDelete() : false;
    }

    // ✅ NUEVO MÉTODO PRIVADO: Obtener precio del producto según la zona
    private function getProductPriceForZone(int $productId, ?int $zoneId): float
    {
        if (!$zoneId) {
            // Si no hay zona, usar precio base
            $product = Product::find($productId);
            return $product ? (float) $product->price : 0;
        }
        
        // 1. Buscar precio específico para la zona
        $zonePriceDetail = \App\Models\ProductPriceDetail::active()
            ->where('product_id', $productId)
            ->where('zone_id', $zoneId)
            ->first();
        
        if ($zonePriceDetail) {
            Log::info("Using zone-specific price", [
                'product_id' => $productId,
                'zone_id' => $zoneId,
                'zone_price' => $zonePriceDetail->price
            ]);
            return (float) $zonePriceDetail->price;
        }
        
        // 2. Si no hay precio para la zona, usar precio base del producto
        $product = Product::find($productId);
        $basePrice = $product ? (float) $product->price : 0;
        
        Log::info("Using base product price", [
            'product_id' => $productId,
            'zone_id' => $zoneId,
            'base_price' => $basePrice,
            'reason' => 'No zone-specific price found'
        ]);
        
        return $basePrice;
    }

    // ✅ NUEVO MÉTODO PRIVADO: Obtener descripción de la fuente del precio
    private function getPriceSourceDescription(int $productId, ?int $zoneId): string
    {
        if (!$zoneId) {
            return "precio base";
        }
        
        $zonePriceDetail = \App\Models\ProductPriceDetail::active()
            ->where('product_id', $productId)
            ->where('zone_id', $zoneId)
            ->first();
        
        if ($zonePriceDetail) {
            $zone = \App\Models\Zone::find($zoneId);
            return "precio para zona {$zone?->name}";
        }
        
        return "precio base";
    }
}