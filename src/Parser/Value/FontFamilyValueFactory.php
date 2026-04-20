<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\FontFamilyValue;

final class FontFamilyValueFactory implements ValueFactory
{
    public function type(): Type
    {
        return Type::FontFamily;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (is_string($raw)) {
            if ($raw === '') {
                throw ParseError::at($pointer, 'fontFamily string must not be empty');
            }

            return new FontFamilyValue([$raw]);
        }

        if (is_array($raw) && array_is_list($raw) && $raw !== []) {
            $families = [];
            foreach ($raw as $i => $name) {
                if (!is_string($name) || $name === '') {
                    throw ParseError::at(
                        $pointer,
                        sprintf('fontFamily $value[%d] must be a non-empty string', $i),
                    );
                }
                $families[] = $name;
            }

            return new FontFamilyValue($families);
        }

        throw ParseError::at(
            $pointer,
            'fontFamily $value must be a non-empty string or a non-empty list of strings',
        );
    }
}
