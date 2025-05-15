<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

use PaymentSystem\Contracts\TokenizableSourceInterface;
use PaymentSystem\Entities\BillingAddress;

readonly class TokenCreated
{
    public function __construct(public TokenizableSourceInterface $source, public ?BillingAddress $billingAddress = null)
    {
    }
}