<?php

namespace src\DataStructure;

class TransactionGroup
{
    /** @var Transaction[] */
    public array $buy = [];

    /** @var Transaction[] */
    public array $sell = [];

    /** @var Transaction[] */
    public array $dividend = [];

    /** @var Transaction[] */
    public array $interest = [];

    /** @var Transaction[] */
    public array $share_split = [];

    /** @var Transaction[] */
    public array $share_transfer = [];

    /** @var Transaction[] */
    public array $deposit = [];

    /** @var Transaction[] */
    public array $withdrawal = [];

    /** @var Transaction[] */
    public array $tax = [];

    /** @var Transaction[] */
    public array $other = [];

    /** @var Transaction[] */
    public array $foreign_withholding_tax = [];

    /** @var Transaction[] */
    public array $returned_foreign_withholding_tax = [];

    /** @var Transaction[] */
    public array $fee = [];

    /** @var Transaction[] */
    public array $share_loan_payout = [];
}
