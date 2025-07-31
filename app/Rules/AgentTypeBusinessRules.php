<?php

namespace App\Rules;

use App\Models\AgentType;

class AgentTypeBusinessRules
{
   
    public static function canBeDeleted(AgentType $agentType): bool
    {
        return !$agentType->hasAgents();
    }

    /**
     * Verificar reglas de eliminación (throws exception)
     */
    public static function validateDeletion(AgentType $agentType): void
    {
        if ($agentType->hasAgents()) {
            throw new \InvalidArgumentException('No se puede eliminar un tipo de agente que tiene agentes asignados');
        }
    }

    /**
     * Verificar reglas de eliminación permanente
     */
    public static function validateForceDeletion(AgentType $agentType): void
    {
        if ($agentType->hasAgentsInHistory()) {
            throw new \InvalidArgumentException('No se puede eliminar permanentemente un tipo de agente que tiene historial de agentes');
        }
    }
}