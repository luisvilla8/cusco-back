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
        
        // ✅ ASIGNAR USUARIO SEGÚN ROL
        if ($user->hasRole('Vendedor')) {
            $data['user_id'] = $user->id;
            
            // ✅ VALIDACIÓN ADICIONAL: Verificar zona del vendedor
            $zoneId = $data['zone_id'] ?? null;
            if ($zoneId && !$user->hasZone($zoneId)) {
                return ResponseHelper::forbidden('No tienes permisos para operar en esta zona.');
            }
        } elseif (empty($data['user_id'])) {
            $data['user_id'] = $user->id;
        }

        $this->logInfo('Creando nueva transacción', [
            'data' => $data,
            'creator_id' => $user->id,
            'creator_role' => $user->getRoleName(),
            'zone_access_validated' => $user->hasRole('Vendedor') ? $user->hasZone($data['zone_id'] ?? null) : 'N/A'
        ]);

        try {
            $transaction = $this->transactionRepository->createWithDetailsAndPayments($data);
            
            // ✅ VERIFICAR SI SE CREÓ EL EGRESO PARA COMPRAS
            $egressCreated = false;
            if ($transaction->transactionType?->code === 'PURCHASE') {
                $egress = Egress::where('transaction_id', $transaction->id)->first();
                $egressCreated = $egress !== null;
            }
            
            $transactionDTO = TransactionMapper::modelToDTO($transaction);

            $this->logInfo('Transacción creada exitosamente', [
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->code,
                'creator_id' => $user->id,
                'total' => $transaction->total,
                'amount_paid' => $transaction->amount_paid,
                'details_count' => $transaction->transactionDetails->count(),
                'payments_count' => $transaction->transactionPayments->count(),
                'type' => $transaction->transactionType?->name,
                'egress_created' => $egressCreated
            ]);

            return ResponseHelper::created($transactionDTO, 'Transacción creada exitosamente');
            
        } catch (\InvalidArgumentException $e) {
            $this->logError('Error de validación al crear transacción', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data
            ]);
            return ResponseHelper::badRequest($e->getMessage());
            
        } catch (\Exception $e) {
            $this->logError('Error interno al crear transacción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'data' => $data
            ]);
            return ResponseHelper::internalServerError('Error interno al crear la transacción: ' . $e->getMessage());
        }
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

    public function cancelTransaction(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $transaction = $this->transactionRepository->findActiveWithDetails($id);
            
            if (!$transaction) {
                return ResponseHelper::notFound('Transacción no encontrada');
            }

            // ✅ ELIMINAR EGRESO SI ES COMPRA
            if ($this->isPurchaseTransaction($transaction)) {
                $this->deletePurchaseEgress($transaction);
            }

            $transaction->cancelTransaction();
            $transactionDTO = TransactionMapper::modelToDTO($transaction->fresh());

            $this->logInfo('Transaction cancelled', ['transaction_id' => $id]);

            return ResponseHelper::success($transactionDTO, 'Transacción anulada exitosamente');
        });
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
        $filters['include_returns'] = true; // Incluir devoluciones
        return $this->getAllTransactions($filters);
    }

    // ✅ MÉTODO PARA OBTENER DEVOLUCIONES DE UNA TRANSACCIÓN ESPECÍFICA
    public function getTransactionReturns(int $id): array
    {
        $transaction = $this->transactionRepository->findActiveWithDetails($id);
        
        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        // ✅ USAR EL MAPEO ANIDADO DESDE EL MAPPER
        $transactionDTO = TransactionMapper::modelToDTO($transaction);
        
        return ResponseHelper::success([
            'transaction_id' => $id,
            'transaction_code' => $transaction->code,
            'returns' => $transactionDTO->toArray()['returns'], // Solo las devoluciones
            'returns_count' => count($transactionDTO->toArray()['returns']),
            'total_returned_amount' => $transaction->getTotalReturnedAmount(),
            'remaining_returnable_amount' => $transaction->getRemainingReturnableAmount()
        ], 'Devoluciones obtenidas exitosamente');
    }

    public function createReturn(array $data): array
    {
        $user = Auth::user();
        
        // Validar que existe la transacción original
        $originalTransaction = $this->transactionRepository->findActiveWithDetails($data['relation_to']);
        
        if (!$originalTransaction) {
            return ResponseHelper::notFound('Transacción original no encontrada');
        }

        if (!$originalTransaction->canBeReturned()) {
            return ResponseHelper::badRequest('La transacción original no puede ser devuelta');
        }

        // ✅ CALCULAR MONTOS ANTES DE LA DEVOLUCIÓN
        $returnAmount = collect($data['details'])->sum(fn($detail) => $detail['price'] * $detail['quantity']);
        $debtInfoBefore = $originalTransaction->calculateDebtInfo();
        
        $this->logInfo('Procesando devolución - datos iniciales', [
            'original_transaction_id' => $originalTransaction->id,
            'return_amount' => $returnAmount,
            'debt_info_before' => $debtInfoBefore
        ]);

        // ✅ LÓGICA DE REEMBOLSO CORREGIDA
        $refundAmount = 0;
        $currentDebt = $debtInfoBefore['current_debt'];
        
        if ($currentDebt > 0.01) {
            // ✅ CASO: CLIENTE TIENE DEUDA
            if ($returnAmount > $currentDebt) {
                // La devolución cubre la deuda y sobra para reembolso
                $refundAmount = $returnAmount - $currentDebt;
            } else {
                // La devolución no cubre toda la deuda, no hay reembolso
                $refundAmount = 0;
            }
            
            $this->logInfo('Cliente con deuda', [
                'current_debt' => $currentDebt,
                'return_amount' => $returnAmount,
                'refund_amount' => $refundAmount,
                'logic' => $returnAmount > $currentDebt ? 'partial_refund_after_debt_coverage' : 'no_refund'
            ]);
        } else {
            // ✅ CASO: CLIENTE SIN DEUDA (YA PAGÓ TODO O MÁS)
            // Toda la devolución es reembolsable
            $refundAmount = $returnAmount;
            
            $this->logInfo('Cliente sin deuda - reembolso completo', [
                'current_debt' => $currentDebt,
                'return_amount' => $returnAmount,
                'refund_amount' => $refundAmount,
                'logic' => 'full_refund'
            ]);
        }

        // Asignar datos de la transacción original
        $data['user_id'] = $originalTransaction->user_id;
        $data['agent_id'] = $originalTransaction->agent_id;
        $data['zone_id'] = $originalTransaction->zone_id;
        $data['trip_id'] = $originalTransaction->trip_id;

        // Determinar el tipo de devolución
        $transactionTypeCode = $originalTransaction->isSale() ? 'RETURN_SALE' : 'RETURN_PURCHASE';
        $returnType = \App\Models\TransactionType::where('code', $transactionTypeCode)->first();
        
        if (!$returnType) {
            return ResponseHelper::badRequest("Tipo de transacción '{$transactionTypeCode}' no encontrado");
        }
        
        $data['transaction_type_id'] = $returnType->id;

        // ✅ CONFIGURAR MONTOS DE LA DEVOLUCIÓN CORRECTAMENTE
        $data['total'] = -$returnAmount; // ✅ NEGATIVO para devoluciones
        $data['amount_paid'] = 0; // ✅ SIEMPRE 0 PARA DEVOLUCIONES - NO NEGATIVO
        
        // ✅ STATUS PARA DEVOLUCIONES
        $data['delivery_status'] = 'RETURNED';
        $data['payment_status'] = 'PAID'; // Siempre PAID para devoluciones

        // ✅ CREAR PAGOS SOLO SI HAY REEMBOLSO REAL - CON MONTO POSITIVO
        if ($refundAmount > 0.01) {
            // ✅ VALIDAR QUE SE PROPORCIONÓ UN MÉTODO DE PAGO PARA EL REEMBOLSO
            if (empty($data['payments']) || empty($data['payments'][0]['payment_method_id'])) {
                return ResponseHelper::badRequest('Debe especificar un método de pago para el reembolso');
            }
            
            $data['payments'] = [
                [
                    'payment_method_id' => $data['payments'][0]['payment_method_id'],
                    'amount_paid' => $refundAmount, // ✅ POSITIVO = reembolso (dinero que entregamos)
                    'description' => "Reembolso por devolución - Transacción #{$originalTransaction->code}"
                ]
            ];
            
            $this->logInfo('Reembolso configurado correctamente', [
                'refund_amount' => $refundAmount,
                'payment_method_id' => $data['payments'][0]['payment_method_id'],
                'amount_paid_in_transaction' => $data['amount_paid'], // Siempre 0
                'amount_paid_in_payment' => $refundAmount // Positivo
            ]);
        } else {
            // Sin reembolso
            $data['payments'] = [];
            
            $this->logInfo('Sin reembolso configurado', [
                'refund_amount' => $refundAmount,
                'amount_paid_in_transaction' => $data['amount_paid'] // Siempre 0
            ]);
        }

        try {
            $returnTransaction = $this->transactionRepository->createWithDetailsAndPayments($data);
            
            // ✅ ACTUALIZAR PAYMENT_STATUS DE LA TRANSACCIÓN ORIGINAL
            $originalTransaction->updatePaymentStatusAutomatically();
            $originalTransaction->save();
            
            // ✅ OBTENER NUEVA INFORMACIÓN DE DEUDA
            $newDebtInfo = $originalTransaction->fresh()->calculateDebtInfo();
            
            $this->logInfo('Devolución creada exitosamente con amount_paid corregido', [
                'return_transaction_id' => $returnTransaction->id,
                'return_transaction_code' => $returnTransaction->code,
                'return_amount_paid' => $returnTransaction->amount_paid, // Debe ser 0
                'return_total' => $returnTransaction->total, // Debe ser negativo
                'refund_amount' => $refundAmount,
                'transaction_payments_count' => $returnTransaction->transactionPayments->count(),
                'transaction_payments_sum' => $returnTransaction->transactionPayments->sum('amount_paid'),
                'debt_info_after' => $newDebtInfo
            ]);

            $returnDTO = TransactionMapper::modelToDTO($returnTransaction);
            
            // ✅ MENSAJE DETALLADO SEGÚN EL RESULTADO
            if ($refundAmount > 0.01) {
                $message = "Devolución procesada. Reembolso: S/ " . number_format($refundAmount, 2);
                if ($newDebtInfo['current_debt'] > 0.01) {
                    $message .= ". Nueva deuda: S/ " . number_format($newDebtInfo['current_debt'], 2);
                } else {
                    $message .= ". Sin deuda pendiente.";
                }
            } else {
                $newDebt = $newDebtInfo['current_debt'];
                if ($newDebt > 0.01) {
                    $message = "Devolución procesada. No hay reembolso. Nueva deuda: S/ " . number_format($newDebt, 2);
                } else {
                    $message = "Devolución procesada. No hay reembolso. Sin deuda pendiente.";
                }
            }
            
            return ResponseHelper::created($returnDTO, $message);
            
        } catch (\Exception $e) {
            $this->logError('Error creando devolución', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            return ResponseHelper::internalServerError('Error interno al procesar la devolución');
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