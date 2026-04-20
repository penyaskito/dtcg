<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

final readonly class ShadowLayer
{
    public function __construct(
        public ColorValue|ReferenceValue $color,
        public DimensionValue|ReferenceValue $offsetX,
        public DimensionValue|ReferenceValue $offsetY,
        public DimensionValue|ReferenceValue $blur,
        public DimensionValue|ReferenceValue $spread,
        public bool|ReferenceValue $inset = false,
    ) {
    }
}
