<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class GradientValue implements Value
{
    /** @var list<GradientStop> */
    public array $stops;

    /** @param list<GradientStop> $stops non-empty */
    public function __construct(array $stops)
    {
        \assert($stops !== []);
        $this->stops = $stops;
    }

    public function type(): Type
    {
        return Type::Gradient;
    }
}
