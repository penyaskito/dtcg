<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class ShadowValue implements Value
{
    /** @var list<ShadowLayer> */
    public array $layers;

    /** @param list<ShadowLayer> $layers non-empty */
    public function __construct(array $layers)
    {
        \assert($layers !== []);
        $this->layers = $layers;
    }

    public function type(): Type
    {
        return Type::Shadow;
    }

    public function isSingleLayer(): bool
    {
        return count($this->layers) === 1;
    }
}
