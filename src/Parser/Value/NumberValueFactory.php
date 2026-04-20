<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\NumberValue;

final class NumberValueFactory implements ValueFactory
{
    public function type(): Type
    {
        return Type::Number;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_int($raw) && !is_float($raw)) {
            throw ParseError::at($pointer, 'number $value must be a number');
        }

        return new NumberValue((float) $raw);
    }
}
