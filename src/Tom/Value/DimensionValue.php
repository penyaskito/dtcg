<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class DimensionValue implements Value
{
    public function __construct(
        public float $value,
        public DimensionUnit $unit,
    ) {
    }

    public function type(): Type
    {
        return Type::Dimension;
    }
}
