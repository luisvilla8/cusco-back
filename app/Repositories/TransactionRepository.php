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

        // ✅ EXCLUIR TRANSACCIONES CANCELADAS Y SOFT DELETED SIEMPRE
        $query->whereNot('delivery_status', 'CANCELLED')
            ->whereNot('payment_status', 'CANCELLED')
            ->whereNull('deleted_at'); // ✅ EXCLUIR SOFT DELETED

        // ✅ POR DEFECTO: EXCLUIR DEVOLUCIONES (SOLO MOSTRAR TRANSACCIONES ORIGINALES)
        if (!isset($filters['include_returns']) || $filters['include_returns'] === false) {
            $query->whereNull('relation_to'); // Solo transacciones originales
        } else {
            // ✅ SI SE INCLUYEN DEVOLUCIONES, EXCLUIR LAS CANCELADAS Y SOFT DELETED
            $query->where(function ($q) {
                $q->whereNull('relation_to') // Transacciones originales
                    ->orWhere(function ($returnQuery) {
                        $returnQuery->whereNotNull('relation_to') // Es devolución
                            ->whereNot('delivery_status', 'CANCELLED') // No cancelada
                            ->whereNot('payment_status', 'CANCELLED')
                            ->whereNull('deleted_at'); // ✅ No soft deleted
                    });
            });
        }

        // ✅ FILTRO ESPECÍFICO PARA SOLO DEVOLUCIONES ACTIVAS
        if (isset($filters['only_returns']) && $filters['only_returns'] === true) {
            $query->whereNotNull('relation_to') // Solo devoluciones
                ->whereNot('delivery_status', 'CANCELLED') // No canceladas
                ->whereNot('payment_status', 'CANCELLED')
                ->whereNull('deleted_at'); // ✅ No soft deleted
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
                    ->orWhereHas(
                        'agent',
                        fn($agent) =>
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
                // ✅ VALIDACIONES PARA TRANSACCIONES ORIGINALES
                if (empty($data['relation_to'])) {
                    if (!empty($data['details'])) {
                        $transactionType = \App\Models\TransactionType::find($data['transaction_type_id']);
                        
                        foreach ($data['details'] as $detail) {
                            $product = Product::active()->find($detail['product_id']);
                            if (!$product) {
                                throw new \InvalidArgumentException("El producto con ID {$detail['product_id']} no existe o está inactivo.");
                            }
                            
                            // ✅ VALIDAR STOCK DISPONIBLE SOLO PARA VENTAS
                            if ($transactionType && $transactionType->code === 'SALE') {
                                if (!$product->hasSufficientStock($detail['quantity'])) {
                                    $availableStock = $product->getAvailableStock();
                                    throw new \InvalidArgumentException(
                                        "Stock disponible insuficiente para {$product->name}. " .
                                        "Stock total: {$product->stock}, reservado: {$product->reserved_stock}, " .
                                        "disponible: {$availableStock}, cantidad solicitada: {$detail['quantity']}"
                                    );
                                }
                            }
                            
                            if ($detail['quantity'] <= 0) {
                                throw new \InvalidArgumentException("La cantidad debe ser mayor a 0 para el producto {$product->name}");
                            }
                            
                            if ($detail['price'] <= 0) {
                                throw new \InvalidArgumentException("El precio debe ser mayor a 0 para el producto {$product->name}");
                            }
                        }
                    }
                }

                // ✅ CALCULAR TOTALES
                $total = $data['total'] ?? collect($data['details'])->sum(fn($detail) => $detail['price'] * $detail['quantity']);
                $amountPaid = $data['amount_paid'] ?? collect($data['payments'] ?? [])->sum('amount_paid');

                // ✅ CREAR DATOS DE LA TRANSACCIÓN
                $transactionData = [
                    'agent_id' => $data['agent_id'],
                    'user_id' => $data['user_id'],
                    'zone_id' => $data['zone_id'],
                    'transaction_type_id' => $data['transaction_type_id'],
                    'date' => $data['date'],
                    'total' => $total,
                    'amount_paid' => $amountPaid,
                    'delivery_status' => $data['delivery_status'] ?? Transaction::DELIVERY_STATUS_PENDING,
                    'payment_status' => $data['payment_status'] ?? Transaction::PAYMENT_STATUS_PENDING,
                ];

                // ✅ AGREGAR CAMPOS OPCIONALES
                if (!empty($data['description'])) {
                    $transactionData['description'] = $data['description'];
                }
                if (!empty($data['trip_id'])) {
                    $transactionData['trip_id'] = $data['trip_id'];
                }
                if (!empty($data['relation_to'])) {
                    $transactionData['relation_to'] = $data['relation_to'];
                }

                // CREAR TRANSACCIÓN (LAS REGLAS DE STOCK SE APLICAN EN BOOT)
                $transaction = $this->model->create($transactionData);

                // CREAR DETALLES PRIMERO
                foreach ($data['details'] as $detailData) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $detailData['product_id'],
                        'price' => $detailData['price'],
                        'quantity' => $detailData['quantity']
                    ]);
                }

                // CREAR PAGOS
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

                // RECARGAR CON DETALLES PARA QUE LAS REGLAS DE STOCK FUNCIONEN
                $transaction = $transaction->fresh()->load([
                    'transactionDetails.product',
                    'transactionType',
                    'agent',
                    'user',
                    'zone',
                    'trip',
                    'transactionPayments.paymentMethod'
                ]);

                // APLICAR REGLAS DE STOCK MANUALMENTE SI NO SE APLICARON EN BOOT
                if ($transaction->transactionDetails->count() > 0) {
                    $transaction->applyStockRulesOnCreate();
                }

                // CREAR EGRESO PARA COMPRAS
                if ($this->isPurchaseTransaction($transaction)) {
                    $this->createPurchaseEgress($transaction);
                }

                return $transaction->load([
                    'agent',
                    'user',
                    'zone',
                    'transactionType',
                    'trip',
                    'transactionDetails.product.measureType',
                    'transactionPayments.paymentMethod'
                ]);
            } catch (\Exception $e) {
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

                // ✅ ACTUALIZAR DETALLES CON REGLAS CORREGIDAS
                if (isset($data['details']) && !empty($data['details'])) {
                    $oldDetails = [];
                    
                    // ✅ OBTENER DETALLES ANTERIORES AGRUPADOS POR PRODUCTO
                    if ($transaction->isSale() && $transaction->isDeliveryPending()) {
                        foreach ($transaction->transactionDetails as $oldDetail) {
                            $productId = $oldDetail->product_id;
                            $oldDetails[$productId] = ($oldDetails[$productId] ?? 0) + $oldDetail->quantity;
                        }
                    }
                    
                    // ✅ VALIDAR NUEVOS DETALLES CONSIDERANDO LIBERACIÓN DE RESERVAS
                    foreach ($data['details'] as $detailData) {
                        $productId = $detailData['product_id'];
                        $newQuantity = $detailData['quantity'];
                        
                        $product = Product::active()->find($productId);
                        if (!$product) {
                            throw new \InvalidArgumentException("El producto con ID {$productId} no existe o está inactivo.");
                        }
                        
                        // ✅ VALIDAR STOCK DISPONIBLE PARA VENTAS CONSIDERANDO LIBERACIÓN
                        if ($transaction->isSale() && $transaction->isDeliveryPending()) {
                            $oldQuantityForProduct = $oldDetails[$productId] ?? 0;
                            
                            // ✅ CALCULAR STOCK DISPONIBLE DESPUÉS DE LIBERAR LA RESERVA ACTUAL
                            $currentReservedStock = $product->reserved_stock;
                            $availableStockAfterRelease = $product->stock - ($currentReservedStock - $oldQuantityForProduct);
                            
                            if ($availableStockAfterRelease < $newQuantity) {
                                throw new \InvalidArgumentException(
                                    "Stock disponible insuficiente para {$product->name}. " .
                                    "Stock total: {$product->stock}, reservado actual: {$currentReservedStock}, " .
                                    "cantidad actual: {$oldQuantityForProduct}, " .
                                    "disponible después de liberar: {$availableStockAfterRelease}, " .
                                    "cantidad solicitada: {$newQuantity}"
                                );
                            }
                        }
                    }
                    
                    // ✅ ELIMINAR DETALLES EXISTENTES
                    $transaction->transactionDetails()->forceDelete();
                    
                    // ✅ CREAR NUEVOS DETALLES
                    $newDetails = [];
                    $totalCalculated = 0;
                    
                    foreach ($data['details'] as $detailData) {
                        TransactionDetail::create([
                            'transaction_id' => $transaction->id,
                            'product_id' => $detailData['product_id'],
                            'price' => $detailData['price'],
                            'quantity' => $detailData['quantity']
                        ]);
                        
                        $newDetails[] = [
                            'product_id' => $detailData['product_id'],
                            'quantity' => $detailData['quantity']
                        ];
                        
                        $totalCalculated += $detailData['price'] * $detailData['quantity'];
                    }

                    // ✅ ACTUALIZAR EL TOTAL CALCULADO
                    $transaction->update(['total' => $totalCalculated]);

                    // ✅ APLICAR REGLAS DE ACTUALIZACIÓN PARA VENTAS PENDIENTES
                    if ($transaction->isSale() && $transaction->isDeliveryPending()) {
                        $transaction = $transaction->fresh(['transactionDetails.product']);
                        
                        $oldDetailsFormatted = [];
                        foreach ($oldDetails as $productId => $quantity) {
                            $oldDetailsFormatted[] = [
                                'product_id' => $productId,
                                'quantity' => $quantity
                            ];
                        }
                        
                        $transaction->updateSaleReservations($oldDetailsFormatted, $newDetails);
                    }
                }

                // ✅ ACTUALIZAR PAGOS SI SE PROPORCIONAN
                if (isset($data['payments'])) {
                    $transaction->transactionPayments()->forceDelete();

                    $totalPaid = 0;
                    foreach ($data['payments'] as $paymentData) {
                        TransactionPayment::create([
                            'transaction_id' => $transaction->id,
                            'payment_method_id' => $paymentData['payment_method_id'],
                            'amount_paid' => $paymentData['amount_paid'],
                            'description' => $paymentData['description'] ?? null
                        ]);

                        $totalPaid += $paymentData['amount_paid'];
                    }

                    $transaction->update(['amount_paid' => $totalPaid]);
                }

                return $transaction->fresh()->load([
                    'agent',
                    'user',
                    'zone',
                    'transactionType',
                    'trip',
                    'transactionDetails.product.measureType',
                    'transactionPayments.paymentMethod'
                ]);
            } catch (\Exception $e) {
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
            'agent',
            'user',
            'zone',
            'transactionType',
            'trip',
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
