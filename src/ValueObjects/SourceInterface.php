<?php

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use PaymentSystem\Enum\SourceEnum;

interface SourceInterface extends JsonSerializable
{
    public function getType(): SourceEnum;
}