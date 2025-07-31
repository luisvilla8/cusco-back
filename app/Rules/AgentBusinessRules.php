<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Rules\AgentBusinessRules.php

namespace App\Rules;

use App\Models\Agent;

class AgentBusinessRules
{
 
    public static function canBeDeleted(Agent $agent): bool
    {
        // Aquí puedes agregar lógica específica si hay restricciones:
        // return !$agent->hasPendingTransactions() && !$agent->hasActiveContracts();
        return true;
    }

    /**
     * Verificar reglas de eliminación (throws exception)
     */
    public static function validateDeletion(Agent $agent): void
    {
        // Ejemplo de validaciones que podrías agregar:
        // if ($agent->hasPendingTransactions()) {
        //     throw new \InvalidArgumentException('No se puede eliminar un agente con transacciones pendientes');
        // }
        
        // if ($agent->hasActiveContracts()) {
        //     throw new \InvalidArgumentException('No se puede eliminar un agente con contratos activos');
        // }
    }

    /**
     * Verificar reglas de eliminación permanente
     */
    public static function validateForceDeletion(Agent $agent): void
    {
        // Ejemplo de validaciones para eliminación permanente:
        // if ($agent->hasHistoricalTransactions()) {
        //     throw new \InvalidArgumentException('No se puede eliminar permanentemente un agente con historial de transacciones');
        // }
    }

    /**
     * Validar datos de agente
     */
    public static function validateAgentData(array $data): void
    {
        // Validar que al menos tenga DNI o RUC
        if (empty($data['dni']) && empty($data['ruc'])) {
            throw new \InvalidArgumentException('El agente debe tener al menos DNI o RUC');
        }

        // Validar formato de DNI (8 dígitos)
        if (!empty($data['dni']) && !preg_match('/^\d{8}$/', $data['dni'])) {
            throw new \InvalidArgumentException('El DNI debe tener exactamente 8 dígitos');
        }

        // Validar formato de RUC (11 dígitos)
        if (!empty($data['ruc']) && !preg_match('/^\d{11}$/', $data['ruc'])) {
            throw new \InvalidArgumentException('El RUC debe tener exactamente 11 dígitos');
        }
    }
}