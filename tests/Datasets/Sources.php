<?php

use PaymentSystem\ValueObjects\Cash;
use PaymentSystem\ValueObjects\Source;

dataset('source', function () {
    yield Source::wrap(new Cash());
});