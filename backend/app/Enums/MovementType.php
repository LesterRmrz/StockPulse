<?php

namespace App\Enums;

/**
 * Why a row was added to the stock_movements ledger.
 */
enum MovementType: string
{
    case Receipt = 'receipt';           // + stock arrived from a purchase order
    case Sale = 'sale';                 // - stock left for a customer order
    case Return = 'return';             // + a customer sent stock back
    case Adjustment = 'adjustment';     // +/- manual correction (damage, recount)
    case TransferOut = 'transfer_out';  // - stock left for another warehouse
    case TransferIn = 'transfer_in';    // + stock arrived from another warehouse
}
