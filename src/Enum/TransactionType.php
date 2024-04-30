<?php

enum TransactionType: string {
    case BUY = 'buy';
    case SELL = 'sell';
    case DIVIDEND = 'dividend';
    case OTHER = 'other';
}
