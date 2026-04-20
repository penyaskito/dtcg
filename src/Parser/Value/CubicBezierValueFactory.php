<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\CubicBezierValue;

final class CubicBezierValueFactory implements ValueFactory
{
    public function type(): Type
    {
        return Type::CubicBezier;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || !array_is_list($raw) || count($raw) !== 4) {
            throw ParseError::at(
                $pointer,
                'cubicBezier $value must be a list of exactly 4 numbers',
            );
        }

        $coords = [];
        foreach ($raw as $i => $component) {
            if (!is_int($component) && !is_float($component)) {
                throw ParseError::at(
                    $pointer,
                    sprintf('cubicBezier $value[%d] must be a number', $i),
                );
            }
            $coords[] = (float) $component;
        }

        [$x1, $y1, $x2, $y2] = $coords;

        foreach ([0 => $x1, 2 => $x2] as $i => $x) {
            if ($x < 0 || $x > 1) {
                throw ParseError::at(
                    $pointer,
                    sprintf('cubicBezier $value[%d] (x coordinate) must be between 0 and 1', $i),
                );
            }
        }

        return new CubicBezierValue($x1, $y1, $x2, $y2);
    }
}
