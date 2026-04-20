<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class NumberValue implements Value
{
    public function __construct(
        public float $value,
    ) {
    }

    public function type(): Type
    {
        return Type::Number;
    }
}
