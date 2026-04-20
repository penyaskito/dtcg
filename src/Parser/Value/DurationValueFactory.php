<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\DurationUnit;
use Penyaskito\Dtcg\Tom\Value\DurationValue;

final class DurationValueFactory implements ValueFactory
{
    public function type(): Type
    {
        return Type::Duration;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'duration $value must be an object');
        }

        if (!array_key_exists('value', $raw) || (!is_int($raw['value']) && !is_float($raw['value']))) {
            throw ParseError::at($pointer, 'duration $value.value must be a number');
        }

        if (!array_key_exists('unit', $raw) || !is_string($raw['unit'])) {
            throw ParseError::at($pointer, 'duration $value.unit must be a string');
        }

        $unit = DurationUnit::tryFrom($raw['unit']);
        if ($unit === null) {
            throw ParseError::at(
                $pointer,
                sprintf("invalid duration unit '%s' (expected 'ms' or 's')", $raw['unit']),
            );
        }

        return new DurationValue((float) $raw['value'], $unit);
    }
}
