<?php

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use PaymentSystem\Enum\ECICodesEnum;
use PaymentSystem\Enum\SupportedVersionsEnum;
use PaymentSystem\Enum\ThreeDSStatusEnum;

readonly class ThreeDSResult implements JsonSerializable
{
    public function __construct(
        public ThreeDSStatusEnum $status,
        public string $authenticationValue,
        public ECICodesEnum $eci,
        public string $dsTransactionId,
        public string $acsTransactionId,
        public ?string $cardToken,
        public SupportedVersionsEnum $version,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status->value,
            'authenticationValue' => $this->authenticationValue,
            'eci' => $this->eci->value,
            'dsTransactionId' => $this->dsTransactionId,
            'acsTransactionId' => $this->acsTransactionId,
            'cardToken' => $this->cardToken,
            'version' => $this->version->value,
        ];
    }
}
