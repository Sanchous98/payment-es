<?php

namespace PaymentSystem\Gateway;

interface GatewayTokenInterface
{
    public function getRawResponse(): mixed;

    public function isValid(): bool;
}