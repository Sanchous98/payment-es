<?php

use PaymentSystem\ValueObjects\SourceInterface;

class Cash implements SourceInterface {}

dataset('source', function () {
    yield new Cash();
});