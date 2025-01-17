<?php

declare(strict_types=1);

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use RuntimeException;

readonly class State implements JsonSerializable
{
    private string $name;

    public function __construct(private string $state, Country $country = null)
    {
        if ($country === null) {
            return;
        }

        $states = require dirname(__DIR__) . '/Resources/states.php';

        isset($states[(string)$country]) || throw new RuntimeException('Invalid state');

        $this->name = $states[(string)$country][$this->state];
    }

    public static function all(Country $country = null): array
    {
        $values = array_filter(
            (require dirname(__DIR__) . '/Resources/states.php'),
            fn(string $code) => $country === null || $code === (string)$country,
            ARRAY_FILTER_USE_KEY,
        );

        $result = [];

        foreach ($values as $country => $states) {
            array_push(
                $result,
                ...
                array_map(fn(string $state) => new State($state, new Country($country)), array_keys($states))
            );
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->state;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}