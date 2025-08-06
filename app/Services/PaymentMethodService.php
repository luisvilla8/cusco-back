<?php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\PaymentMethodMapper;
use App\Repositories\PaymentMethodRepository;

class PaymentMethodService
{
    public function __construct(
        private PaymentMethodRepository $paymentMethodRepository
    ) {}

    /**
     * Obtener todos los métodos de pago con paginación
     */
    public function getAllPaymentMethods(array $filters = []): array
    {
        $paginatedPaymentMethods = $this->paymentMethodRepository->getAllActiveWithPagination($filters);
        $mapped = PaymentMethodMapper::paginatedToDTOs($paginatedPaymentMethods);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedPaymentMethods,
            'Métodos de pago obtenidos exitosamente'
        );
    }

    /**
     * Obtener un método de pago específico
     */
    public function getPaymentMethod(int $id): array
    {
        $paymentMethod = $this->paymentMethodRepository->findActive($id);

        if (!$paymentMethod) {
            return ResponseHelper::notFound('Método de pago no encontrado');
        }

        $paymentMethodDTO = PaymentMethodMapper::modelToDTO($paymentMethod);
        return ResponseHelper::success($paymentMethodDTO->toArray(), 'Método de pago obtenido exitosamente');
    }

    /**
     * Obtener lista de métodos de pago para dropdowns
     */
    public function getPaymentMethodsList(): array
    {
        $paymentMethods = $this->paymentMethodRepository->getActiveForDropdown();
        $dropdownData = PaymentMethodMapper::collectionToDropdownDTOs($paymentMethods);

        return ResponseHelper::success($dropdownData, 'Lista de métodos de pago obtenida exitosamente');
    }
}