<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class FontWeightValue implements Value
{
    public function __construct(
        public float|FontWeightKeyword $weight,
    ) {
    }

    public function type(): Type
    {
        return Type::FontWeight;
    }

    public function isKeyword(): bool
    {
        return $this->weight instanceof FontWeightKeyword;
    }
}
