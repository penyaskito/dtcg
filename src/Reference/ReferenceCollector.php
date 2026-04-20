<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\Token;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\GradientValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\ShadowValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;
use Penyaskito\Dtcg\Tom\Value\TransitionValue;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;
use Penyaskito\Dtcg\Tom\ValueToken;

/**
 * Yields every {@see Reference} contained in a {@see Token} or {@see Value},
 * including composite sub-fields.
 *
 * Single source of truth for "this thing has these references" — used by
 * semantic rules (to detect broken targets / cycles) and by serializers /
 * materializers (to know whether resolution is needed).
 */
final class ReferenceCollector
{
    /** @return iterable<Reference> */
    public static function inToken(Token $token): iterable
    {
        if ($token instanceof ReferenceToken) {
            yield $token->reference;

            return;
        }
        if ($token instanceof ValueToken) {
            yield from self::inValue($token->value);
        }
    }

    /** @return iterable<Reference> */
    public static function inValue(Value $value): iterable
    {
        if ($value instanceof ReferenceValue) {
            yield $value->reference;

            return;
        }
        if ($value instanceof BorderValue) {
            yield from self::yieldIfRef($value->color);
            yield from self::yieldIfRef($value->width);
            yield from self::yieldIfRef($value->style);
            if ($value->style instanceof StrokeStyleValue) {
                yield from self::inValue($value->style);
            }

            return;
        }
        if ($value instanceof TransitionValue) {
            yield from self::yieldIfRef($value->duration);
            yield from self::yieldIfRef($value->delay);
            yield from self::yieldIfRef($value->timingFunction);

            return;
        }
        if ($value instanceof ShadowValue) {
            foreach ($value->layers as $layer) {
                yield from self::yieldIfRef($layer->color);
                yield from self::yieldIfRef($layer->offsetX);
                yield from self::yieldIfRef($layer->offsetY);
                yield from self::yieldIfRef($layer->blur);
                yield from self::yieldIfRef($layer->spread);
                if ($layer->inset instanceof ReferenceValue) {
                    yield $layer->inset->reference;
                }
            }

            return;
        }
        if ($value instanceof GradientValue) {
            foreach ($value->stops as $stop) {
                yield from self::yieldIfRef($stop->color);
                if ($stop->position instanceof ReferenceValue) {
                    yield $stop->position->reference;
                }
            }

            return;
        }
        if ($value instanceof TypographyValue) {
            yield from self::yieldIfRef($value->fontFamily);
            yield from self::yieldIfRef($value->fontSize);
            yield from self::yieldIfRef($value->fontWeight);
            yield from self::yieldIfRef($value->letterSpacing);
            yield from self::yieldIfRef($value->lineHeight);

            return;
        }
        if ($value instanceof StrokeStyleValue && $value->dashArray !== null) {
            foreach ($value->dashArray as $dim) {
                yield from self::yieldIfRef($dim);
            }
        }
    }

    public static function valueContainsReference(Value $value): bool
    {
        foreach (self::inValue($value) as $_) {
            return true;
        }

        return false;
    }

    /** @return iterable<Reference> */
    private static function yieldIfRef(mixed $field): iterable
    {
        if ($field instanceof ReferenceValue) {
            yield $field->reference;
        }
    }
}
