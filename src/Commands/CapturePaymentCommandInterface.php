<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use PaymentSystem\TenderInterface;

interface CapturePaymentCommandInterface
{
    public ?string $amount { get; }

    public ?TenderInterface $tender { get; }
}