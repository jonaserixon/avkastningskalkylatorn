<?php

namespace src\Enum;

// TODO: implementera enums ordentligt.

enum TransactionType: string
{
    case BUY = 'buy';
    case SELL = 'sell';
    case DIVIDEND = 'dividend';
    case FEE = 'fee';
    case TAX = 'tax';
    case FOREIGN_WITHHOLDING_TAX = 'foreign_withholding_tax'; // Utländsk källskatt
    case RETURNED_FOREIGN_WITHHOLDING_TAX = 'returned_foreign_withholding_tax'; // Återbetald utländsk källskatt
    case INTEREST = 'interest';
    case SHARE_TRANSFER = 'share_transfer';
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case OTHER = 'other';
    case SHARE_LOAN_PAYOUT = 'share_loan_payout';
}
