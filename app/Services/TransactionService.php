<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\TransactionService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\TransactionMapper;
use App\Models\Egress;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(
        private TransactionRepository $transactionRepository
    ) {}

    public function getAllTransactions(array $filters = []): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('Vendedor')) {
            $filters['user_id'] = $user->id;
        }
        
        if (!isset($filters['include_returns'])) {
            $filters['include_returns'] = false;
        }

        $paginatedTransactions = $this->transactionRepository->getAllActiveWithPagination($filters);
        $mapped = TransactionMapper::paginatedToDTOs($paginatedTransactions);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedTransactions,
            'Transacciones obtenidas exitosamente'
        );
    }

    private function validateUserAccessToTransaction($transaction, $user): ?array
    {
        if ($user->hasRole('Vendedor') && $transaction->user_id !== $user->id) {
            return ResponseHelper::forbidden('Solo puedes acceder a tus propias transacciones');
        }
        
        return null;
    }

    public function getTransaction(int $id): array
    {
        $user = Auth::user();
        
        $transaction = $this->transactionRepository->findActiveWithDetails($id);

        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        $accessValidation = $this->validateUserAccessToTransaction($transaction, $user);
        if ($accessValidation) {
            return $accessValidation;
        }

        $transactionDTO = TransactionMapper::modelToDTO($transaction);
        return ResponseHelper::success($transactionDTO, 'Transacción obtenida exitosamente');
    }

    public function createTransaction(array $data): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('Vendedor')) {
            $data['user_id'] = $user->id;
            
            $zoneId = $data['zone_id'] ?? null;
            if ($zoneId && !$user->hasZone($zoneId)) {
                return ResponseHelper::forbidden('No tienes permisos para operar en esta zona.');
            }
        } elseif (empty($data['user_id'])) {
            $data['user_id'] = $user->id;
        }

        try {
            $this->validateTransactionDataForCreation($data);
            
            $transaction = $this->transactionRepository->createWithDetailsAndPayments($data);
            
            $transactionDTO = TransactionMapper::modelToDTO($transaction);

            return ResponseHelper::created($transactionDTO, 'Transacción creada exitosamente');
            
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
            
        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error interno al crear la transacción: ' . $e->getMessage());
        }
    }

    private function validateTransactionDataForCreation(array $data): void
    {
        if (empty($data['details'])) {
            throw new \InvalidArgumentException('La transacción debe tener al menos un detalle de producto');
        }
        
        if (empty($data['transaction_type_id'])) {
            throw new \InvalidArgumentException('Debe especificar el tipo de transacción');
        }
        
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
    }

    public function updateTransaction(int $id, array $data): array
    {
        $user = Auth::user();

        return DB::transaction(function () use ($id, $data, $user) {
            try {
                $transaction = $this->transactionRepository->findActiveWithDetails($id);

                if (!$transaction) {
                    return ResponseHelper::notFound('Transacción no encontrada');
                }

                $accessValidation = $this->validateUserAccessToTransaction($transaction, $user);
                if ($accessValidation) {
                    return $accessValidation;
                }

                if (!$this->canBeEdited($transaction)) {
                    return ResponseHelper::badRequest('Esta transacción no puede ser editada en su estado actual');
                }

                $updatedTransaction = $this->transactionRepository->updateWithDetailsAndPayments($id, $data);
                $transactionDTO = TransactionMapper::modelToDTO($updatedTransaction);

                return ResponseHelper::success($transactionDTO, 'Transacción actualizada exitosamente');
                
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::badRequest($e->getMessage());
                
            } catch (\Exception $e) {
                return ResponseHelper::internalServerError('Error interno al actualizar la transacción: ' . $e->getMessage());
            }
        });
    }

    public function markAsDelivered(int $id): array
    {
        return $this->updateTransactionStatus($id, 'markAsDelivered', 'Transacción marcada como entregada');
    }

    public function cancelTransaction(int $id): array
    {
        $user = Auth::user();
        
        return DB::transaction(function () use ($id, $user) {
            try {
                $transaction = $this->transactionRepository->findActiveWithDetails($id);
                
                if (!$transaction) {
                    return ResponseHelper::notFound('Transacción no encontrada');
                }

                $accessValidation = $this->validateUserAccessToTransaction($transaction, $user);
                if ($accessValidation) {
                    return $accessValidation;
                }

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

                $transaction->cancelTransaction();

                $cancelledTransaction = $transaction->fresh();
                $transactionDTO = TransactionMapper::modelToDTO($cancelledTransaction);

                $message = $this->getUnifiedCancellationMessage($transactionInfo);

                return ResponseHelper::success($transactionDTO, $message);
                
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::badRequest($e->getMessage());
                
            } catch (\Exception $e) {
                return ResponseHelper::internalServerError('Error interno al cancelar: ' . $e->getMessage());
            }
        });
    }

    private function getUnifiedCancellationMessage(array $transactionInfo): string
    {
        if ($transactionInfo['is_return']) {
            $baseMessage = "Devolución {$transactionInfo['code']} anulada exitosamente.";
            $baseMessage .= " Los cambios de stock y reembolsos han sido revertidos.";
            return $baseMessage;
        } else {
            $baseMessage = "Transacción {$transactionInfo['code']} cancelada exitosamente.";
            
            if ($transactionInfo['has_active_returns']) {
                $count = $transactionInfo['active_returns_count'];
                $baseMessage .= " Se cancelaron {$count} devolución(es) relacionada(s) automáticamente.";
            }
            
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

            $user = Auth::user();
            $accessValidation = $this->validateUserAccessToTransaction($transaction, $user);
            if ($accessValidation) {
                return $accessValidation;
            }

            if (!$transaction->canReceivePayment()) {
                return ResponseHelper::badRequest('Esta transacción no puede recibir pagos en su estado actual');
            }

            $paymentAmount = (float) $paymentData['amount_paid'];
            $currentDebt = $transaction->getCurrentDebt();

            if ($currentDebt <= 0.01) {
                $formattedSurplus = 'S/ ' . number_format($transaction->getPaymentSurplus(), 2);
                return ResponseHelper::badRequest(
                    "Esta transacción ya está completamente pagada. " .
                    ($transaction->getPaymentSurplus() > 0 ? "Tiene un excedente de {$formattedSurplus}." : "")
                );
            }

            if ($paymentAmount > $currentDebt) {
                $formattedDebt = 'S/ ' . number_format($currentDebt, 2);
                $formattedRequested = 'S/ ' . number_format($paymentAmount, 2);
                $formattedTotal = 'S/ ' . number_format($transaction->total, 2);
                $formattedPaid = 'S/ ' . number_format($transaction->amount_paid, 2);
                
                return ResponseHelper::badRequest(
                    "El monto del pago ({$formattedRequested}) excede la deuda actual ({$formattedDebt}). " .
                    "Total: {$formattedTotal}, pagado: {$formattedPaid}. " .
                    "Deuda restante: {$formattedDebt}."
                );
            }

            if ($paymentAmount <= 0) {
                return ResponseHelper::badRequest('El monto del pago debe ser mayor a cero.');
            }

            $paymentMethod = \App\Models\PaymentMethod::active()->find($paymentData['payment_method_id']);
            if (!$paymentMethod) {
                return ResponseHelper::badRequest('El método de pago seleccionado no está disponible.');
            }

            try {
                $updatedTransaction = $this->transactionRepository->addPayment($id, $paymentData);
                
                $transactionDTO = TransactionMapper::modelToDTO($updatedTransaction);
                
                $newCurrentDebt = $updatedTransaction->getCurrentDebt();

                $message = 'Pago agregado exitosamente. ';
                $message .= 'Monto pagado: S/ ' . number_format($paymentAmount, 2) . '. ';
                
                if ($newCurrentDebt > 0.01) {
                    $message .= 'Deuda restante: S/ ' . number_format($newCurrentDebt, 2) . '.';
                } else {
                    $message .= '¡Transacción completamente pagada!';
                    
                    if ($updatedTransaction->getPaymentSurplus() > 0) {
                        $formattedSurplus = 'S/ ' . number_format($updatedTransaction->getPaymentSurplus(), 2);
                        $message .= " Excedente: {$formattedSurplus}.";
                    }
                }

                return ResponseHelper::success($transactionDTO, $message);
                
            } catch (\Exception $e) {
                return ResponseHelper::internalServerError('Error interno al agregar el pago: ' . $e->getMessage());
            }
        });
    }

    public function getSales(array $filters = []): array
    {
        $filters['transaction_type'] = 'SALE';
        $filters['delivery_status'] = ['DELIVERED', 'RETURNED'];
        return $this->getAllTransactions($filters);
    }

    public function getPurchases(array $filters = []): array
    {
        $filters['transaction_type'] = 'PURCHASE';
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

    public function getReturns(array $filters = []): array
    {
        $filters['transaction_type'] = ['RETURN_SALE', 'RETURN_PURCHASE'];
        $filters['include_returns'] = true;
        
        if (!isset($filters['delivery_status'])) {
            $filters['delivery_status'] = ['PENDING', 'DELIVERED', 'RETURNED'];
        }
        
        if (!isset($filters['payment_status'])) {
            $filters['payment_status'] = ['PENDING', 'PARTIAL', 'PAID'];
        }
        
        return $this->getAllTransactions($filters);
    }

    public function getTransactionReturns(int $id): array
    {
        $transaction = $this->transactionRepository->findActiveWithDetails($id);
        
        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        $transactionDTO = TransactionMapper::modelToDTO($transaction);
        
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
            'returns' => $transactionDTO->toArray()['returns'],
            'returns_count' => count($transactionDTO->toArray()['returns']),
            'cancelled_returns_count' => $cancelledReturns->count(),
            'total_returned_amount' => $transaction->getTotalReturnedAmount(),
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
            $originalTransaction = $this->transactionRepository->findActiveWithDetails($data['relation_to']);
            
            if (!$originalTransaction) {
                return ResponseHelper::notFound('Transacción original no encontrada');
            }

            if (!$originalTransaction->canBeReturned()) {
                return ResponseHelper::badRequest('La transacción original no puede ser devuelta');
            }

            $returnAmount = collect($data['details'])->sum(fn($detail) => $detail['price'] * $detail['quantity']);
            $debtInfoBefore = $originalTransaction->calculateDebtInfo();
            $currentDebtBefore = $debtInfoBefore['current_debt'];

            $refundAmount = 0;
            $debtCompensation = 0;
            
            if ($currentDebtBefore > 0.01) {
                $debtCompensation = min($returnAmount, $currentDebtBefore);
                
                if ($returnAmount > $currentDebtBefore) {
                    $refundAmount = $returnAmount - $currentDebtBefore;
                } else {
                    $refundAmount = 0;
                }
            } else {
                $refundAmount = $returnAmount;
                $debtCompensation = 0;
            }

            if ($refundAmount > 0.01) {
                if (empty($data['payments']) || empty($data['payments'][0]['payment_method_id'])) {
                    return ResponseHelper::badRequest('Debe especificar un método de pago para el reembolso de S/ ' . number_format($refundAmount, 2));
                }
            }

            $transactionTypeCode = $originalTransaction->isSale() ? 'RETURN_SALE' : 'RETURN_PURCHASE';
            $returnType = \App\Models\TransactionType::where('code', $transactionTypeCode)->first();
            
            if (!$returnType) {
                return ResponseHelper::badRequest("Tipo de transacción '{$transactionTypeCode}' no encontrado");
            }

            $returnData = [
                'user_id' => $originalTransaction->user_id,
                'agent_id' => $originalTransaction->agent_id,
                'zone_id' => $originalTransaction->zone_id,
                'trip_id' => $originalTransaction->trip_id,
                'transaction_type_id' => $returnType->id,
                'relation_to' => $data['relation_to'],
                'date' => $data['date'],
                'description' => $data['description'] ?? "Devolución de {$originalTransaction->code}",
                'total' => -$returnAmount,
                'amount_paid' => 0,
                'delivery_status' => 'RETURNED',
                'payment_status' => 'PAID',
                'details' => $data['details'],
                'payments' => []
            ];

            if ($refundAmount > 0.01) {
                $returnData['payments'] = [
                    [
                        'payment_method_id' => $data['payments'][0]['payment_method_id'],
                        'amount_paid' => $refundAmount,
                        'description' => "Reembolso por devolución - {$originalTransaction->code}"
                    ]
                ];
            }

            $returnTransaction = $this->transactionRepository->createWithDetailsAndPayments($returnData);

            $actualRefundCreated = $returnTransaction->transactionPayments->sum('amount_paid');

            $originalTransaction->updatePaymentStatusAutomatically();
            $originalTransaction->save();
            
            $debtInfoAfter = $originalTransaction->fresh()->calculateDebtInfo();
            $currentDebtAfter = $debtInfoAfter['current_debt'];
            
            $actualDebtReduction = max(0, $currentDebtBefore - $currentDebtAfter);

            $returnDTO = TransactionMapper::modelToDTO($returnTransaction);

            $message = "Devolución procesada exitosamente.";
            
            if ($actualRefundCreated > 0.01) {
                $message .= " Reembolso: S/ " . number_format($actualRefundCreated, 2) . ".";
            } else {
                $message .= " No hay reembolso.";
            }
            
            if ($actualDebtReduction > 0.01) {
                $message .= " Deuda reducida en: S/ " . number_format($actualDebtReduction, 2) . ".";
            }
            
            if ($currentDebtAfter > 0.01) {
                $message .= " Deuda restante: S/ " . number_format($currentDebtAfter, 2) . ".";
            }

            return ResponseHelper::created($returnDTO, $message);
            
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
            
        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error interno al procesar la devolución: ' . $e->getMessage());
        }
    }

    private function updateTransactionStatus(int $id, string $method, string $message): array
    {
        $transaction = $this->transactionRepository->findActiveWithDetails($id);
        
        if (!$transaction) {
            return ResponseHelper::notFound('Transacción no encontrada');
        }

        $transaction->{$method}();
        $transactionDTO = TransactionMapper::modelToDTO($transaction->fresh());

        return ResponseHelper::success($transactionDTO, $message);
    }

    private function canBeEdited($transaction): bool
    {
        return $transaction->isDeliveryPending() && !$transaction->isDeliveryCancelled();
    }

    private function isPurchaseTransaction($transaction): bool
    {
        return $transaction->transactionType?->code === 'PURCHASE';
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
            
        } catch (\Exception $e) {
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
    }

    private function deletePurchaseEgress($transaction): void
    {
        $egress = \App\Models\Egress::where('transaction_id', $transaction->id)->first();
        
        if ($egress) {
            $egress->delete();
        }
    }

    private function getReturnSaleTypeId(): int
    {
        return \App\Models\TransactionType::where('code', 'RETURN_SALE')->firstOrFail()->id;
    }

    private function getReturnPurchaseTypeId(): int
    {
        return \App\Models\TransactionType::where('code', 'RETURN_PURCHASE')->firstOrFail()->id;
    }
}