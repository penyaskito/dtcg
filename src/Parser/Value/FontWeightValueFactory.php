<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\FontWeightKeyword;
use Penyaskito\Dtcg\Tom\Value\FontWeightValue;

final class FontWeightValueFactory implements ValueFactory
{
    public function type(): Type
    {
        return Type::FontWeight;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (is_int($raw) || is_float($raw)) {
            $numeric = (float) $raw;
            if ($numeric < 1 || $numeric > 1000) {
                throw ParseError::at(
                    $pointer,
                    sprintf('fontWeight numeric value must be between 1 and 1000, got %s', $numeric),
                );
            }

            return new FontWeightValue($numeric);
        }

        if (is_string($raw)) {
            $keyword = FontWeightKeyword::tryFrom($raw);
            if ($keyword === null) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown fontWeight keyword '%s'", $raw),
                );
            }

            return new FontWeightValue($keyword);
        }

        throw ParseError::at(
            $pointer,
            'fontWeight $value must be a number (1-1000) or a recognised keyword string',
        );
    }
}
