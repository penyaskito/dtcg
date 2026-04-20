<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

interface ValueFactory
{
    public function type(): Type;

    public function create(mixed $raw, string $pointer): Value;
}
