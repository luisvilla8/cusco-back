<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Rules\PaymentMethodBusinessRules.php

namespace App\Rules;

use App\Models\PaymentMethod;

class PaymentMethodBusinessRules
{
    /**
     * Verificar si un método de pago puede ser eliminado
     */
    public static function canBeDeleted(PaymentMethod $paymentMethod): bool
    {
        return !$paymentMethod->hasPayments() 
            && !self::isProtectedPaymentMethod($paymentMethod);
    }

    /**
     * Verificar si es un método de pago protegido
     */
    public static function isProtectedPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return in_array($paymentMethod->code, ['CASH', 'CARD']) 
            || $paymentMethod->id === 1;
    }

    /**
     * Validar eliminación lógica
     */
    public static function validateDeletion(PaymentMethod $paymentMethod): void
    {
        if ($paymentMethod->hasPayments()) {
            throw new \InvalidArgumentException('No se puede eliminar un método de pago que tiene pagos asociados');
        }
        
        if (self::isProtectedPaymentMethod($paymentMethod)) {
            throw new \InvalidArgumentException('No se puede eliminar este método de pago del sistema');
        }
    }

    /**
     * Validar eliminación física
     */
    public static function validateForceDeletion(PaymentMethod $paymentMethod): void
    {
        if ($paymentMethod->hasPaymentsInHistory()) {
            throw new \InvalidArgumentException('No se puede eliminar permanentemente un método de pago que tiene historial de pagos');
        }
    }
}