<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class TypographyValue implements Value
{
    public function __construct(
        public FontFamilyValue|ReferenceValue $fontFamily,
        public DimensionValue|ReferenceValue $fontSize,
        public FontWeightValue|ReferenceValue $fontWeight,
        public DimensionValue|ReferenceValue $letterSpacing,
        public NumberValue|ReferenceValue $lineHeight,
    ) {
    }

    public function type(): Type
    {
        return Type::Typography;
    }
}
