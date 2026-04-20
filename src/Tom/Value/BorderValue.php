<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class BorderValue implements Value
{
    public function __construct(
        public ColorValue|ReferenceValue $color,
        public DimensionValue|ReferenceValue $width,
        public StrokeStyleValue|ReferenceValue $style,
    ) {
    }

    public function type(): Type
    {
        return Type::Border;
    }
}
