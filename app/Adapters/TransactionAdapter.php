<?php

namespace App\Adapters;

use Illuminate\Http\Request;

class TransactionAdapter
{
    public static function createTransactionPaymentPayload(int $transactionId, int $amountPaid, int $agentId)
    {
      return Request::create('/', 'POST', [
          'transaction_id' => $transactionId,
          'amount_paid' => $amountPaid,
          'agent_id' => $agentId,
      ]);
    }
}
