<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class CubicBezierValue implements Value
{
    public function __construct(
        public float $x1,
        public float $y1,
        public float $x2,
        public float $y2,
    ) {
    }

    public function type(): Type
    {
        return Type::CubicBezier;
    }

    /** @return array{float, float, float, float} */
    public function toTuple(): array
    {
        return [$this->x1, $this->y1, $this->x2, $this->y2];
    }
}
