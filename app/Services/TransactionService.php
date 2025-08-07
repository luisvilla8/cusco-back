<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\TransactionService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\TransactionMapper;
use App\Models\Egress;
use App\Repositories\TransactionRepository;
use App\Traits\LoggingTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    use LoggingTrait;

    public function __construct(
        private TransactionRepository $transactionRepository
    ) {}

    public function getAllTransactions(array $filters = []): array
    {
        $user = Auth::user();
        
        // ✅ VENDEDORES: Solo ven sus transacciones
        if ($user->hasRole('Vendedor')) {
            $filters['user_id'] = $user->id;
        }
        
        // ✅ POR DEFECTO, SOLO MOSTRAR TRANSACCIONES ORIGINALES (NO DEVOLUCIONES)
        if (!isset($filters['include_returns'])) {
            $filters['include_returns'] = false;
        }
        
        $this->logInfo('Fetching transactions with filters', [
            'filters' => $filters, 
            'user_role' => $user->getRoleName(),
            'include_returns' => $filters['include_returns']
        ]);

        $paginatedTransactions = $this->transactionRepository->getAllActiveWithPagination($filters);
        $mapped = TransactionMapper::paginatedToDTOs($paginatedTransactions);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedTransactions,
            'Transacciones obtenidas exitosamente'
        );
    }

    public function getTransaction(int $id): array
    {
        $user = Auth::user();
        
        $this->logInfo('Fetching transaction', ['transaction_id' => $id, 'user_id' => $user->id]);

        $transaction = $this->transactionRepository->findActiveWithDetails($id);

        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        // ✅ VENDEDORES: Solo pueden ver sus transacciones
        if ($user->hasRole('Vendedor') && $transaction->user_id !== $user->id) {
            return ResponseHelper::forbidden('No tienes acceso a esta transacción');
        }

        $transactionDTO = TransactionMapper::modelToDTO($transaction);
        return ResponseHelper::success($transactionDTO, 'Transacción obtenida exitosamente');
    }

    public function createTransaction(array $data): array
    {
        $user = Auth::user();
        
        // ✅ LOGGING INICIAL MÁS DETALLADO
        $this->logInfo('=== INICIANDO CREACIÓN DE TRANSACCIÓN ===', [
            'user_id' => $user->id,
            'user_role' => $user->getRoleName(),
            'raw_data' => $data,
            'details_count' => count($data['details'] ?? []),
            'payments_count' => count($data['payments'] ?? [])
        ]);
        
        // ✅ ASIGNAR USUARIO SEGÚN ROL
        if ($user->hasRole('Vendedor')) {
            $data['user_id'] = $user->id;
            
            // ✅ VALIDACIÓN ADICIONAL: Verificar zona del vendedor
            $zoneId = $data['zone_id'] ?? null;
            if ($zoneId && !$user->hasZone($zoneId)) {
                $this->logError('Vendedor sin permisos en zona', [
                    'user_id' => $user->id,
                    'zone_id' => $zoneId
                ]);
                return ResponseHelper::forbidden('No tienes permisos para operar en esta zona.');
            }
        } elseif (empty($data['user_id'])) {
            $data['user_id'] = $user->id;
        }

        $this->logInfo('Datos preparados para creación', [
            'final_user_id' => $data['user_id'],
            'zone_validated' => $user->hasRole('Vendedor') ? $user->hasZone($data['zone_id'] ?? null) : 'N/A',
            'transaction_type_id' => $data['transaction_type_id'] ?? null
        ]);

        try {
            // ✅ VALIDAR DATOS ANTES DE CREAR
            $this->validateTransactionDataForCreation($data);
            
            $transaction = $this->transactionRepository->createWithDetailsAndPayments($data);
            
            // ✅ VERIFICAR SI SE CREÓ EL EGRESO PARA COMPRAS
            $egressCreated = false;
            if ($transaction->transactionType?->code === 'PURCHASE') {
                $egress = Egress::where('transaction_id', $transaction->id)->first();
                $egressCreated = $egress !== null;
            }
            
            $transactionDTO = TransactionMapper::modelToDTO($transaction);

            $this->logInfo('=== TRANSACCIÓN CREADA EXITOSAMENTE ===', [
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->code,
                'creator_id' => $user->id,
                'total' => $transaction->total,
                'amount_paid' => $transaction->amount_paid,
                'details_count' => $transaction->transactionDetails->count(),
                'payments_count' => $transaction->transactionPayments->count(),
                'type' => $transaction->transactionType?->name,
                'egress_created' => $egressCreated,
                'delivery_status' => $transaction->delivery_status,
                'payment_status' => $transaction->payment_status
            ]);

            return ResponseHelper::created($transactionDTO, 'Transacción creada exitosamente');
            
        } catch (\InvalidArgumentException $e) {
            $this->logError('=== ERROR DE VALIDACIÓN ===', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data
            ]);
            return ResponseHelper::badRequest($e->getMessage());
            
        } catch (\Exception $e) {
            $this->logError('=== ERROR INTERNO ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return ResponseHelper::internalServerError('Error interno al crear la transacción: ' . $e->getMessage());
        }
    }

    // ✅ NUEVO MÉTODO: VALIDAR DATOS ANTES DE CREAR
    private function validateTransactionDataForCreation(array $data): void
    {
        // Validar que existan detalles
        if (empty($data['details'])) {
            throw new \InvalidArgumentException('La transacción debe tener al menos un detalle de producto');
        }
        
        // Validar que exista tipo de transacción
        if (empty($data['transaction_type_id'])) {
            throw new \InvalidArgumentException('Debe especificar el tipo de transacción');
        }
        
        // Validar que los productos existan
        foreach ($data['details'] as $index => $detail) {
            $productId = $detail['product_id'] ?? null;
            if (!$productId) {
                throw new \InvalidArgumentException("Detalle {$index}: product_id es requerido");
            }
            
            $product = \App\Models\Product::active()->find($productId);
            if (!$product) {
                throw new \InvalidArgumentException("Detalle {$index}: El producto ID {$productId} no existe o está inactivo");
            }
            
            $quantity = $detail['quantity'] ?? 0;
            if ($quantity <= 0) {
                throw new \InvalidArgumentException("Detalle {$index}: La cantidad debe ser mayor a 0");
            }
            
            $price = $detail['price'] ?? 0;
            if ($price <= 0) {
                throw new \InvalidArgumentException("Detalle {$index}: El precio debe ser mayor a 0");
            }
        }
        
        $this->logInfo('Validación de datos completada exitosamente', [
            'details_validated' => count($data['details']),
            'payments_count' => count($data['payments'] ?? [])
        ]);
    }

    public function updateTransaction(int $id, array $data): array
    {
        $user = Auth::user();

        $this->logInfo('Starting transaction update', [
            'transaction_id' => $id, 
            'data' => $data, 
            'user_id' => $user->id,
            'user_role' => $user->getRoleName()
        ]);

        return DB::transaction(function () use ($id, $data, $user) {
            try {
                $transaction = $this->transactionRepository->findActiveWithDetails($id);

                if (!$transaction) {
                    return ResponseHelper::notFound('Transacción no encontrada');
                }

                // ✅ VENDEDORES: Solo pueden editar sus transacciones
                if ($user->hasRole('Vendedor') && $transaction->user_id !== $user->id) {
                    return ResponseHelper::forbidden('No tienes acceso a esta transacción');
                }

                // ✅ VALIDAR SI PUEDE EDITARSE
                if (!$this->canBeEdited($transaction)) {
                    return ResponseHelper::badRequest('Esta transacción no puede ser editada en su estado actual');
                }

                $this->logInfo('Transaction validation passed, proceeding with update', [
                    'transaction_id' => $id,
                    'can_be_edited' => true,
                    'current_status' => [
                        'delivery_status' => $transaction->delivery_status,
                        'payment_status' => $transaction->payment_status
                    ]
                ]);

                $updatedTransaction = $this->transactionRepository->updateWithDetailsAndPayments($id, $data);
                $transactionDTO = TransactionMapper::modelToDTO($updatedTransaction);

                $this->logInfo('Transaction updated successfully', [
                    'transaction_id' => $id,
                    'updated_total' => $updatedTransaction->total,
                    'updated_amount_paid' => $updatedTransaction->amount_paid,
                    'new_payment_status' => $updatedTransaction->payment_status
                ]);

                return ResponseHelper::success($transactionDTO, 'Transacción actualizada exitosamente');
                
            } catch (\InvalidArgumentException $e) {
                $this->logError('Validation error in transaction update', [
                    'transaction_id' => $id,
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
                return ResponseHelper::badRequest($e->getMessage());
                
            } catch (\Exception $e) {
                $this->logError('Internal error in transaction update', [
                    'transaction_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $data
                ]);
                return ResponseHelper::internalServerError('Error interno al actualizar la transacción: ' . $e->getMessage());
            }
        });
    }

    public function markAsDelivered(int $id): array
    {
        return $this->updateTransactionStatus($id, 'markAsDelivered', 'Transacción marcada como entregada');
    }

    public function markAsReturned(int $id): array
    {
        return $this->updateTransactionStatus($id, 'markAsReturned', 'Transacción marcada como devuelta');
    }

    // ✅ MÉTODO ACTUALIZADO: CANCELACIÓN UNIFICADA
    public function cancelTransaction(int $id): array
    {
        $user = Auth::user();
        
        return DB::transaction(function () use ($id, $user) {
            try {
                $transaction = $this->transactionRepository->findActiveWithDetails($id);
                
                if (!$transaction) {
                    return ResponseHelper::notFound('Transacción no encontrada');
                }

                // ✅ VALIDAR PERMISOS
                if ($user->hasRole('Vendedor') && $transaction->user_id !== $user->id) {
                    return ResponseHelper::forbidden('No tienes acceso a esta transacción');
                }

                // ✅ OBTENER INFORMACIÓN ANTES DE CANCELAR
                $transactionInfo = [
                    'id' => $transaction->id,
                    'code' => $transaction->code,
                    'type' => $transaction->transactionType?->name,
                    'type_code' => $transaction->transactionType?->code,
                    'delivery_status' => $transaction->delivery_status,
                    'payment_status' => $transaction->payment_status,
                    'is_return' => $transaction->isReturn(),
                    'is_original' => $transaction->isOriginalTransaction(),
                    'has_active_returns' => $transaction->hasActiveReturns(),
                    'active_returns_count' => $transaction->getActiveReturns()->count()
                ];

                $this->logInfo('Starting unified transaction cancellation', [
                    'transaction_info' => $transactionInfo,
                    'user_id' => $user->id
                ]);

                // ✅ CANCELAR LA TRANSACCIÓN (CON CASCADA AUTOMÁTICA)
                $transaction->cancelTransaction();

                // ✅ RECARGAR TRANSACCIÓN
                $cancelledTransaction = $transaction->fresh();
                $transactionDTO = TransactionMapper::modelToDTO($cancelledTransaction);

                // ✅ MENSAJE SEGÚN TIPO Y EFECTOS
                $message = $this->getUnifiedCancellationMessage($transactionInfo);

                return ResponseHelper::success($transactionDTO, $message);
                
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::badRequest($e->getMessage());
                
            } catch (\Exception $e) {
                $this->logError('Error in unified cancellation', [
                    'transaction_id' => $id,
                    'error' => $e->getMessage()
                ]);
                return ResponseHelper::internalServerError('Error interno al cancelar: ' . $e->getMessage());
            }
        });
    }

    // ✅ NUEVO MÉTODO: MENSAJE UNIFICADO DE CANCELACIÓN
    private function getUnifiedCancellationMessage(array $transactionInfo): string
    {
        if ($transactionInfo['is_return']) {
            $baseMessage = "Devolución {$transactionInfo['code']} anulada exitosamente.";
            $baseMessage .= " Los cambios de stock y reembolsos han sido revertidos.";
            return $baseMessage;
        } else {
            // Transacción original
            $baseMessage = "Transacción {$transactionInfo['code']} cancelada exitosamente.";
            
            // Mencionar devoluciones canceladas en cascada
            if ($transactionInfo['has_active_returns']) {
                $count = $transactionInfo['active_returns_count'];
                $baseMessage .= " Se cancelaron {$count} devolución(es) relacionada(s) automáticamente.";
            }
            
            // Mencionar efectos en stock
            switch ($transactionInfo['type_code']) {
                case 'SALE':
                    if ($transactionInfo['delivery_status'] === 'DELIVERED') {
                        $baseMessage .= " El stock ha sido restaurado.";
                    } else {
                        $baseMessage .= " El stock reservado ha sido liberado.";
                    }
                    break;
                    
                case 'PURCHASE':
                    if ($transactionInfo['delivery_status'] === 'DELIVERED') {
                        $baseMessage .= " El stock ha sido ajustado y el egreso eliminado.";
                    } else {
                        $baseMessage .= " El egreso programado ha sido eliminado.";
                    }
                    break;
            }
            
            return $baseMessage;
        }
    }

    public function addPayment(int $id, array $paymentData): array
    {
        return DB::transaction(function () use ($id, $paymentData) {
            $transaction = $this->transactionRepository->findActiveWithDetails($id);
            
            if (!$transaction) {
                return ResponseHelper::notFound('Transacción no encontrada');
            }

            // ✅ VALIDAR PERMISOS DE USUARIO
            $user = Auth::user();
            if ($user->hasRole('Vendedor') && $transaction->user_id !== $user->id) {
                return ResponseHelper::forbidden('No tienes acceso a esta transacción');
            }

            // ✅ VALIDAR ESTADO DE LA TRANSACCIÓN
            if (!$transaction->canReceivePayment()) {
                return ResponseHelper::badRequest('Esta transacción no puede recibir pagos en su estado actual');
            }

            // ✅ VALIDAR MONTO DEL PAGO
            $remainingAmount = $transaction->getRemainingAmount();
            $paymentAmount = (float) $paymentData['amount_paid'];

            if ($paymentAmount > $remainingAmount) {
                return ResponseHelper::badRequest(
                    "El monto del pago (S/ " . number_format($paymentAmount, 2) . 
                    ") excede el monto pendiente (S/ " . number_format($remainingAmount, 2) . ")"
                );
            }

            // ✅ AGREGAR EL PAGO
            $updatedTransaction = $this->transactionRepository->addPayment($id, $paymentData);
            
            // ✅ CREAR EGRESO ADICIONAL SOLO SI ES COMPRA Y CON FECHA CORRECTA
            if ($this->isPurchaseTransaction($updatedTransaction)) {
                try {
                    $this->createAdditionalPurchaseEgressSafe($updatedTransaction, $paymentAmount);
                } catch (\Exception $e) {
                    $this->logError('Error creating additional egress for payment', [
                        'transaction_id' => $id,
                        'payment_amount' => $paymentAmount,
                        'error' => $e->getMessage()
                    ]);
                    
                    // ✅ NO FALLAR TODO EL PROCESO POR UN ERROR EN EL EGRESO
                    $this->logWarning('Continuing without creating egress due to validation error');
                }
            }
            
            $transactionDTO = TransactionMapper::modelToDTO($updatedTransaction);

            $this->logInfo('Payment added to transaction', [
                'transaction_id' => $id,
                'amount_paid' => $paymentAmount,
                'remaining_amount' => $updatedTransaction->getRemainingAmount(),
                'total_paid_now' => $updatedTransaction->amount_paid,
                'payment_status' => $updatedTransaction->payment_status
            ]);

            return ResponseHelper::success($transactionDTO, 'Pago agregado exitosamente');
        });
    }

    // ✅ MÉTODOS ESPECIALIZADOS CORREGIDOS
    public function getSales(array $filters = []): array
    {
        $filters['transaction_type'] = 'SALE';
        // ✅ SOLO VENTAS ENTREGADAS O DEVUELTAS
        $filters['delivery_status'] = ['DELIVERED', 'RETURNED'];
        return $this->getAllTransactions($filters);
    }

    public function getPurchases(array $filters = []): array
    {
        $filters['transaction_type'] = 'PURCHASE';
        // ✅ SOLO COMPRAS ENTREGADAS O DEVUELTAS (RECIBIDAS)
        $filters['delivery_status'] = ['DELIVERED', 'RETURNED'];
        return $this->getAllTransactions($filters);
    }

    public function getPendingDelivery(array $filters = []): array
    {
        $filters['delivery_status'] = 'PENDING';
        return $this->getAllTransactions($filters);
    }

    public function getPendingPayment(array $filters = []): array
    {
        $filters['payment_status'] = ['PENDING', 'PARTIAL'];
        return $this->getAllTransactions($filters);
    }

    public function getCompleted(array $filters = []): array
    {
        $filters['delivery_status'] = 'DELIVERED';
        $filters['payment_status'] = 'PAID';
        return $this->getAllTransactions($filters);
    }

    // ✅ NUEVO MÉTODO PARA DEVOLUCIONES
    public function getReturns(array $filters = []): array
    {
        $filters['transaction_type'] = ['RETURN_SALE', 'RETURN_PURCHASE'];
        $filters['include_returns'] = true;
        
        // ✅ EXCLUIR DEVOLUCIONES CANCELADAS
        if (!isset($filters['delivery_status'])) {
            $filters['delivery_status'] = ['PENDING', 'DELIVERED', 'RETURNED'];
        }
        
        if (!isset($filters['payment_status'])) {
            $filters['payment_status'] = ['PENDING', 'PARTIAL', 'PAID'];
        }
        
        return $this->getAllTransactions($filters);
    }

    // ✅ MÉTODO ACTUALIZADO PARA OBTENER DEVOLUCIONES DE UNA TRANSACCIÓN ESPECÍFICA
    public function getTransactionReturns(int $id): array
    {
        $transaction = $this->transactionRepository->findActiveWithDetails($id);
        
        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        // ✅ USAR EL MAPEO ANIDADO DESDE EL MAPPER (YA EXCLUYE CANCELADAS)
        $transactionDTO = TransactionMapper::modelToDTO($transaction);
        
        // ✅ OBTENER ESTADÍSTICAS SOLO DE DEVOLUCIONES ACTIVAS
        $activeReturns = $transaction->returns()
            ->whereNot('delivery_status', 'CANCELLED')
            ->whereNot('payment_status', 'CANCELLED')
            ->get();
        
        $cancelledReturns = $transaction->returns()
            ->where(function ($q) {
                $q->where('delivery_status', 'CANCELLED')
                  ->orWhere('payment_status', 'CANCELLED');
            })
            ->get();
        
        return ResponseHelper::success([
            'transaction_id' => $id,
            'transaction_code' => $transaction->code,
            'returns' => $transactionDTO->toArray()['returns'], // Solo las activas
            'returns_count' => count($transactionDTO->toArray()['returns']),
            'cancelled_returns_count' => $cancelledReturns->count(),
            'total_returned_amount' => $transaction->getTotalReturnedAmount(), // Ya excluye canceladas
            'remaining_returnable_amount' => $transaction->getRemainingReturnableAmount(),
            'statistics' => [
                'active_returns' => $activeReturns->count(),
                'cancelled_returns' => $cancelledReturns->count(),
                'total_returns_ever_created' => $transaction->returns()->count()
            ]
        ], 'Devoluciones obtenidas exitosamente');
    }

    public function createReturn(array $data): array
    {
        $user = Auth::user();
        
        try {
            $this->logInfo('=== INICIO CREACIÓN DE DEVOLUCIÓN ===', [
                'data_received' => $data,
                'user_id' => $user->id
            ]);
            
            // Validar que existe la transacción original
            $originalTransaction = $this->transactionRepository->findActiveWithDetails($data['relation_to']);
            
            if (!$originalTransaction) {
                $this->logError('Transacción original no encontrada', [
                    'relation_to' => $data['relation_to']
                ]);
                return ResponseHelper::notFound('Transacción original no encontrada');
            }

            if (!$originalTransaction->canBeReturned()) {
                return ResponseHelper::badRequest('La transacción original no puede ser devuelta');
            }

            // ✅ CALCULAR MONTOS Y REEMBOLSO CORRECTAMENTE
            $returnAmount = collect($data['details'])->sum(fn($detail) => $detail['price'] * $detail['quantity']);
            $debtInfoBefore = $originalTransaction->calculateDebtInfo();
            $currentDebtBefore = $debtInfoBefore['current_debt'];
            
            $this->logInfo('Cálculos de devolución - ANTES', [
                'return_amount' => $returnAmount,
                'current_debt_before' => $currentDebtBefore,
                'original_total' => $originalTransaction->total,
                'original_amount_paid' => $originalTransaction->amount_paid,
                'debt_info_before' => $debtInfoBefore
            ]);

            // ✅ LÓGICA DE REEMBOLSO CORREGIDA
            $refundAmount = 0;
            $debtCompensation = 0; // ✅ NUEVA VARIABLE PARA TRACKING REAL
            
            if ($currentDebtBefore > 0.01) {
                // ✅ HAY DEUDA: El monto devuelto compensa deuda primero
                $debtCompensation = min($returnAmount, $currentDebtBefore); // ✅ REAL COMPENSATION
                
                if ($returnAmount > $currentDebtBefore) {
                    // ✅ EL MONTO DEVUELTO ES MAYOR QUE LA DEUDA
                    $refundAmount = $returnAmount - $currentDebtBefore; // Lo que sobra se reembolsa
                    $this->logInfo('Devolución mayor que deuda', [
                        'debt_compensation' => $debtCompensation,
                        'refund_amount' => $refundAmount
                    ]);
                } else {
                    // ✅ EL MONTO DEVUELTO ES MENOR O IGUAL QUE LA DEUDA
                    $refundAmount = 0; // Todo compensa deuda, no hay reembolso
                    $this->logInfo('Devolución compensa deuda parcial/total', [
                        'debt_compensation' => $debtCompensation,
                        'refund_amount' => 0
                    ]);
                }
            } else {
                // ✅ NO HAY DEUDA: Todo se reembolsa
                $refundAmount = $returnAmount;
                $debtCompensation = 0; // ✅ NO HAY DEUDA QUE COMPENSAR
                $this->logInfo('Sin deuda, reembolso total', [
                    'refund_amount' => $refundAmount,
                    'debt_compensation' => $debtCompensation
                ]);
            }

            // ✅ VALIDAR MÉTODO DE PAGO PARA REEMBOLSO
            if ($refundAmount > 0.01) {
                if (empty($data['payments']) || empty($data['payments'][0]['payment_method_id'])) {
                    return ResponseHelper::badRequest('Debe especificar un método de pago para el reembolso de S/ ' . number_format($refundAmount, 2));
                }
            }

            // ✅ OBTENER TIPO DE DEVOLUCIÓN
            $transactionTypeCode = $originalTransaction->isSale() ? 'RETURN_SALE' : 'RETURN_PURCHASE';
            $returnType = \App\Models\TransactionType::where('code', $transactionTypeCode)->first();
            
            if (!$returnType) {
                return ResponseHelper::badRequest("Tipo de transacción '{$transactionTypeCode}' no encontrado");
            }

            // ✅ PREPARAR DATOS DE LA DEVOLUCIÓN
            $returnData = [
                'user_id' => $originalTransaction->user_id,
                'agent_id' => $originalTransaction->agent_id,
                'zone_id' => $originalTransaction->zone_id,
                'trip_id' => $originalTransaction->trip_id,
                'transaction_type_id' => $returnType->id,
                'relation_to' => $data['relation_to'],
                'date' => $data['date'],
                'description' => $data['description'] ?? "Devolución de {$originalTransaction->code}",
                'total' => -$returnAmount, // ✅ NEGATIVO para devoluciones
                'amount_paid' => 0, // ✅ SIEMPRE 0 PARA DEVOLUCIONES
                'delivery_status' => 'RETURNED', // ✅ YA PROCESADA
                'payment_status' => 'PAID', // ✅ YA PAGADA (REEMBOLSADA)
                'details' => $data['details'],
                'payments' => []
            ];

            // ✅ AGREGAR PAGO DE REEMBOLSO SI ES NECESARIO
            if ($refundAmount > 0.01) {
                $returnData['payments'] = [
                    [
                        'payment_method_id' => $data['payments'][0]['payment_method_id'],
                        'amount_paid' => $refundAmount,
                        'description' => "Reembolso por devolución - {$originalTransaction->code}"
                    ]
                ];
                
                $this->logInfo('Pago de reembolso agregado', [
                    'refund_amount' => $refundAmount,
                    'payment_method_id' => $data['payments'][0]['payment_method_id']
                ]);
            }

            $this->logInfo('Creando devolución con datos finales', [
                'return_data_summary' => [
                    'total' => $returnData['total'],
                    'amount_paid' => $returnData['amount_paid'],
                    'refund_amount' => $refundAmount,
                    'debt_compensation' => $debtCompensation, // ✅ AGREGAR AL LOG
                    'payments_count' => count($returnData['payments'])
                ]
            ]);

            // ✅ CREAR LA DEVOLUCIÓN
            $returnTransaction = $this->transactionRepository->createWithDetailsAndPayments($returnData);

            // ✅ VERIFICAR REEMBOLSO CREADO
            $actualRefundCreated = $returnTransaction->transactionPayments->sum('amount_paid');
            
            $this->logInfo('Devolución creada, verificando reembolso', [
                'return_transaction_id' => $returnTransaction->id,
                'return_transaction_code' => $returnTransaction->code,
                'expected_refund' => $refundAmount,
                'actual_refund_created' => $actualRefundCreated,
                'payments_created' => $returnTransaction->transactionPayments->count()
            ]);

            // ✅ ACTUALIZAR TRANSACCIÓN ORIGINAL Y OBTENER NUEVA DEUDA
            $originalTransaction->updatePaymentStatusAutomatically();
            $originalTransaction->save();
            
            // ✅ VERIFICAR NUEVA DEUDA DE LA TRANSACCIÓN ORIGINAL
            $debtInfoAfter = $originalTransaction->fresh()->calculateDebtInfo();
            $currentDebtAfter = $debtInfoAfter['current_debt'];
            
            // ✅ CALCULAR REDUCCIÓN REAL DE DEUDA
            $actualDebtReduction = max(0, $currentDebtBefore - $currentDebtAfter);
            
            $this->logInfo('Transacción original actualizada', [
                'original_payment_status' => $originalTransaction->payment_status,
                'debt_before' => $currentDebtBefore,
                'debt_after' => $currentDebtAfter,
                'actual_debt_reduction' => $actualDebtReduction,
                'debt_info_after' => $debtInfoAfter
            ]);

            // ✅ MAPEAR A DTO
            $returnDTO = TransactionMapper::modelToDTO($returnTransaction);

            // ✅ GENERAR MENSAJE INFORMATIVO CORREGIDO
            $message = "Devolución procesada exitosamente.";
            
            if ($actualRefundCreated > 0.01) {
                $message .= " Reembolso: S/ " . number_format($actualRefundCreated, 2) . ".";
            } else {
                $message .= " No hay reembolso.";
            }
            
            // ✅ USAR LA REDUCCIÓN REAL DE DEUDA CALCULADA
            if ($actualDebtReduction > 0.01) {
                $message .= " Deuda reducida en: S/ " . number_format($actualDebtReduction, 2) . ".";
            }
            
            // ✅ AGREGAR INFO ADICIONAL SI QUEDA DEUDA
            if ($currentDebtAfter > 0.01) {
                $message .= " Deuda restante: S/ " . number_format($currentDebtAfter, 2) . ".";
            }

            $this->logInfo('=== DEVOLUCIÓN COMPLETADA ===', [
                'return_transaction_id' => $returnTransaction->id,
                'original_debt_before' => $currentDebtBefore,
                'original_debt_after' => $currentDebtAfter,
                'actual_debt_reduction' => $actualDebtReduction,
                'refund_created' => $actualRefundCreated,
                'message' => $message
            ]);

            return ResponseHelper::created($returnDTO, $message);
            
        } catch (\InvalidArgumentException $e) {
            $this->logError('=== ERROR DE VALIDACIÓN EN DEVOLUCIÓN ===', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return ResponseHelper::badRequest($e->getMessage());
            
        } catch (\Exception $e) {
            $this->logError('=== ERROR INTERNO EN DEVOLUCIÓN ===', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            return ResponseHelper::internalServerError('Error interno al procesar la devolución: ' . $e->getMessage());
        }
    }

    // ✅ MÉTODOS PRIVADOS DE NEGOCIO
    private function updateTransactionStatus(int $id, string $method, string $message): array
    {
        $transaction = $this->transactionRepository->findActiveWithDetails($id);
        
        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        $transaction->{$method}();
        $transactionDTO = TransactionMapper::modelToDTO($transaction->fresh());

        $this->logInfo("Transaction status updated", ['transaction_id' => $id, 'method' => $method]);

        return ResponseHelper::success($transactionDTO, $message);
    }

    private function canBeEdited($transaction): bool
    {
        // ✅ No se puede editar si está entregada o cancelada
        return $transaction->isDeliveryPending() && !$transaction->isDeliveryCancelled();
    }

    private function isPurchaseTransaction($transaction): bool
    {
        return $transaction->transactionType?->code === 'PURCHASE';
    }

    public function deleteTransaction(int $id): array
    {
        $user = Auth::user();
        
        $transaction = $this->transactionRepository->findActiveWithDetails($id);
        
        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        // ✅ VENDEDORES: Solo pueden eliminar sus transacciones
        if ($user->hasRole('Vendedor') && $transaction->user_id !== $user->id) {
            return ResponseHelper::forbidden('No tienes acceso a esta transacción');
        }

        // ✅ ELIMINAR EGRESO SI ES COMPRA
        if ($this->isPurchaseTransaction($transaction)) {
            $this->deletePurchaseEgress($transaction);
        }

        $this->transactionRepository->delete($id);
        
        $this->logInfo('Transaction deleted', ['transaction_id' => $id]);
        
        return ResponseHelper::success(null, 'Transacción eliminada exitosamente');
    }

    public function forceDeleteTransaction(int $id): array
    {
        $user = Auth::user();
        
        if (!$user->hasAnyRole(['Administrador', 'Super Admin'])) {
            return ResponseHelper::forbidden('No tienes permisos para eliminar permanentemente');
        }

        $this->transactionRepository->forceDelete($id);
        
        $this->logInfo('Transaction force deleted', ['transaction_id' => $id]);
        
        return ResponseHelper::success(null, 'Transacción eliminada permanentemente');
    }

    public function getTransactionsList(): array
    {
        $transactions = $this->transactionRepository->getForDropdown();
        $mapped = TransactionMapper::collectionToDropdownDTOs($transactions);
        
        return ResponseHelper::success($mapped, 'Lista de transacciones obtenida exitosamente');
    }

    public function getTransactionDependencies(int $id): array
    {
        $transaction = $this->transactionRepository->findActiveWithDetails($id);
        
        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        $dependencies = [
            'details_count' => $transaction->transactionDetails->count(),
            'payments_count' => $transaction->transactionPayments->count(),
            'can_be_deleted' => $transaction->isDeliveryPending(),
            'returns_count' => $transaction->returns()->count(),
            'related_egresses' => $transaction->trip_id ? [$transaction->trip_id] : []
        ];

        return ResponseHelper::success($dependencies, 'Dependencias obtenidas exitosamente');
    }


    // ✅ MÉTODOS PRIVADOS DE EGRESO
    private function createAdditionalPurchaseEgressSafe($transaction, float $additionalAmount): void
    {
        if ($additionalAmount <= 0) return;

        try {
            $egress = new \App\Models\Egress([
                'name' => "Pago Adicional - {$transaction->code}",
                'description' => "Pago adicional por compra: {$transaction->description}",
                'amount' => $additionalAmount,
                'date' => min($transaction->date, now()->toDateString()),
                'agent_id' => $transaction->agent_id,
                'zone_id' => $transaction->zone_id,
                'trip_id' => $transaction->trip_id,
                'transaction_id' => $transaction->id,
            ]);
            
            $egress->save();
            
            $this->logInfo('Additional purchase egress created safely', [
                'transaction_id' => $transaction->id,
                'egress_id' => $egress->id,
                'additional_amount' => $additionalAmount,
                'egress_date' => $egress->date,
                'transaction_date' => $transaction->date
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Failed to create additional purchase egress', [
                'transaction_id' => $transaction->id,
                'amount' => $additionalAmount,
                'error' => $e->getMessage(),
                'transaction_date' => $transaction->date,
                'current_date' => now()->toDateString()
            ]);
            
            throw $e;
        }
    }

    private function createPurchaseEgress($transaction): void
    {
        if ($transaction->total <= 0) return;

        $egress = new \App\Models\Egress([
            'name' => "Compra - {$transaction->code}",
            'description' => "Egreso automático por compra: {$transaction->description}",
            'amount' => $transaction->total,
            'date' => $transaction->date,
            'agent_id' => $transaction->agent_id,
            'zone_id' => $transaction->zone_id,
            'transaction_id' => $transaction->id,
        ]);
        
        $egress->save();
        
        $this->logInfo('Purchase egress created', [
            'transaction_id' => $transaction->id,
            'egress_id' => $egress->id,
            'amount' => $egress->amount
        ]);
    }

    private function deletePurchaseEgress($transaction): void
    {
        $egress = \App\Models\Egress::where('transaction_id', $transaction->id)->first();
        
        if ($egress) {
            $egress->delete();
            $this->logInfo('Purchase egress deleted', ['transaction_id' => $transaction->id]);
        }
    }

    // ✅ MÉTODOS HELPERS PARA TIPOS DE TRANSACCIÓN
    private function getReturnSaleTypeId(): int
    {
        return \App\Models\TransactionType::where('code', 'RETURN_SALE')->firstOrFail()->id;
    }

    private function getReturnPurchaseTypeId(): int
    {
        return \App\Models\TransactionType::where('code', 'RETURN_PURCHASE')->firstOrFail()->id;
    }
}