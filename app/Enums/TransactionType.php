<?php

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Buy = 'buy';
    case Sell = 'sell';

    public function isCashMovement(): bool
    {
        return $this === self::Deposit || $this === self::Withdrawal;
    }

    public function isInstrumentMovement(): bool
    {
        return $this === self::Buy || $this === self::Sell;
    }
}
