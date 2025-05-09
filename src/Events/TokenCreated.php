<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\Entities\BillingAddress;

readonly class TokenCreated
{
    public function __construct(public TokenizedSourceInterface $source, public ?BillingAddress $billingAddress = null)
    {
    }
}