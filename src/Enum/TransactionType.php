<?php

namespace src\Enum;

enum TransactionType: string
{
    case BUY = 'buy';
    case SELL = 'sell';
    case DIVIDEND = 'dividend';
    case FEE = 'fee';
    case TAX = 'tax';
    case INTEREST = 'interest';
    case SHARE_TRANSFER = 'share_transfer';
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case OTHER = 'other';
}
