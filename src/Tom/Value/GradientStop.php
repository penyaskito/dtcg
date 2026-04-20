<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

final readonly class GradientStop
{
    public function __construct(
        public ColorValue|ReferenceValue $color,
        public float|ReferenceValue $position,
    ) {
    }
}
