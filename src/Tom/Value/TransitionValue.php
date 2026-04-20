<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class TransitionValue implements Value
{
    public function __construct(
        public DurationValue|ReferenceValue $duration,
        public DurationValue|ReferenceValue $delay,
        public CubicBezierValue|ReferenceValue $timingFunction,
    ) {
    }

    public function type(): Type
    {
        return Type::Transition;
    }
}
