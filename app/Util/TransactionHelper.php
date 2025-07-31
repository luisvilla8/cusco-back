<?php

namespace App\Util;

use Illuminate\Support\Collection;
use App\Util\Constants\TransactionsConstants;

class TransactionHelper
{
  public static function calculateTotalTransactionAmountByItems(Collection $items)
  {
    return $items->sum(function ($item) {
        return $item['finalPrice'];
    });
  }

  public static function calculateTotalSumByField(Collection $items, string $field, $quantityField = 'quantity')
  {
    return $items->sum(function ($item) use ($field, $quantityField) {
        return $item[$field] * $item[$quantityField];
    });
  }

  public static function filterTransactionsByType(Collection $transactions, $transactionTypeId)
  {
    $filteredTransactions = $transactions->filter(function ($transaction) use ($transactionTypeId) {
      return $transaction['transaction_type_id'] === (int)$transactionTypeId;
    });

    return $filteredTransactions->values();
  }

  public static function getProductsQuantityByTransactionType(Collection $products, $transactionType)
  {
    return $products->map(function ($product) use ($transactionType) {
      $productQuantity = $product['quantity'];
      $productQuantityByTransactionType = $transactionType === TransactionsConstants::TRANSACTION_TYPE_PURCHASE ? $productQuantity : -abs($productQuantity);
      return array_merge($product, ['quantity' => $productQuantityByTransactionType]);
    });
  }

  public static function hasTransactionDetailsChanged(Collection $oldTransactionDetails, Collection $newTransactionDetails)
  {
    // only compare cost, price and quantity
    // $oldTransactionDetails = $oldTransactionDetails->map(function ($detail) {
    //   return [
    //     'cost' => $detail['cost'],
    //     'price' => $detail['price'],
    //     'quantity' => $detail['quantity'],
    //   ];
    // });

    // $newTransactionDetails = $newTransactionDetails->map(function ($detail) {
    //   return [
    //     'cost' => $detail['cost'],
    //     'price' => $detail['price'],
    //     'quantity' => $detail['quantity'],
    //   ];
    // });

  }
}

