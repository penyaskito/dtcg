<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\DimensionUnit;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;

final class DimensionValueFactory implements ValueFactory
{
    public function type(): Type
    {
        return Type::Dimension;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'dimension $value must be an object');
        }

        if (!array_key_exists('value', $raw) || !is_numeric($raw['value'])) {
            throw ParseError::at($pointer, 'dimension $value.value must be a number');
        }

        if (!array_key_exists('unit', $raw) || !is_string($raw['unit'])) {
            throw ParseError::at($pointer, 'dimension $value.unit must be a string');
        }

        $unit = DimensionUnit::tryFrom($raw['unit']);
        if ($unit === null) {
            throw ParseError::at(
                $pointer,
                sprintf("invalid dimension unit '%s' (expected 'px' or 'rem')", $raw['unit']),
            );
        }

        return new DimensionValue((float) $raw['value'], $unit);
    }
}
