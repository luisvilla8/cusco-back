<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\TransactionTypeService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\TransactionTypeMapper;
use App\Repositories\TransactionTypeRepository;

class TransactionTypeService
{
    public function __construct(
        private TransactionTypeRepository $transactionTypeRepository
    ) {}

    public function getAllTransactionTypes(array $filters = []): array
    {
        $paginatedTransactionTypes = $this->transactionTypeRepository->getAllActiveWithPagination($filters);
        $mapped = TransactionTypeMapper::paginatedToDTOs($paginatedTransactionTypes);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedTransactionTypes,
            'Tipos de transacción obtenidos exitosamente'
        );
    }

    public function getTransactionType(int $id): array
    {
        $transactionType = $this->transactionTypeRepository->findActive($id);

        if (!$transactionType) {
            return ResponseHelper::notFound('Tipo de transacción no encontrado');
        }

        $transactionTypeDTO = TransactionTypeMapper::modelToDTO($transactionType);
        return ResponseHelper::success($transactionTypeDTO->toArray(), 'Tipo de transacción obtenido exitosamente');
    }

    public function getTransactionTypesList(): array
    {
        $transactionTypes = $this->transactionTypeRepository->getActiveForDropdown();
        $dropdownData = TransactionTypeMapper::collectionToDropdownDTOs($transactionTypes);

        return ResponseHelper::success($dropdownData, 'Lista de tipos de transacción obtenida exitosamente');
    }
}