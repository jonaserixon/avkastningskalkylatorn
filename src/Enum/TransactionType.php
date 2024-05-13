<?php

namespace src\Enum;

enum TransactionType: string
{
    case BUY = 'buy';
    case SELL = 'sell';
    case DIVIDEND = 'dividend';
    case SHARE_TRANSFER = 'share_transfer';
    case OTHER = 'other';
}
