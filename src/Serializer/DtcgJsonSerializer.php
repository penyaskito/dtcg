<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Serializer;

use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Metadata;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\CubicBezierValue;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\DurationValue;
use Penyaskito\Dtcg\Tom\Value\FontFamilyValue;
use Penyaskito\Dtcg\Tom\Value\FontWeightKeyword;
use Penyaskito\Dtcg\Tom\Value\FontWeightValue;
use Penyaskito\Dtcg\Tom\Value\GradientStop;
use Penyaskito\Dtcg\Tom\Value\GradientValue;
use Penyaskito\Dtcg\Tom\Value\NumberValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\ShadowLayer;
use Penyaskito\Dtcg\Tom\Value\ShadowValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;
use Penyaskito\Dtcg\Tom\Value\TransitionValue;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;
use Penyaskito\Dtcg\Tom\ValueToken;

/**
 * Serializes a TOM back into a DTCG-conforming JSON string.
 *
 * The output is structurally faithful at the TOM level: parse → serialize →
 * parse is a fixed point (equivalent TOM). It is not guaranteed to be
 * byte-identical to the original input, because several DTCG idioms have
 * multiple valid JSON shapes (e.g. a one-element fontFamily can be a string
 * or a single-item list; a one-layer shadow can be an object or a one-item
 * list). The serializer picks a canonical form for each.
 */
final class DtcgJsonSerializer implements Serializer
{
    public function __construct(
        private readonly int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) {
    }

    public function serialize(Document $document): string
    {
        $array = $this->groupToArray($document->root, null);

        return json_encode($array, $this->jsonFlags | JSON_THROW_ON_ERROR) . "\n";
    }

    /** @return array<string, mixed> */
    private function groupToArray(Group $group, ?Type $inheritedType): array
    {
        $out = [];
        if ($group->extendsFrom !== null) {
            $out['$extends'] = $group->extendsFrom->original();
        }
        if ($group->defaultType !== null) {
            $out['$type'] = $group->defaultType->value;
        }
        $this->writeMetadata($out, $group->metadata);

        $childInheritedType = $group->defaultType ?? $inheritedType;
        foreach ($group->children as $name => $child) {
            // Skip children that were materialized from `$extends` — they'll
            // be re-inherited when the output is reparsed.
            if (array_key_exists($name, $group->inheritedFrom)) {
                continue;
            }
            if ($child instanceof Group) {
                $out[$name] = $this->groupToArray($child, $childInheritedType);
            } elseif ($child instanceof ValueToken) {
                $out[$name] = $this->valueTokenToArray($child, $childInheritedType);
            } elseif ($child instanceof ReferenceToken) {
                $out[$name] = $this->referenceTokenToArray($child, $childInheritedType);
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function valueTokenToArray(ValueToken $token, ?Type $inheritedType): array
    {
        $out = [];
        if ($token->type !== $inheritedType) {
            $out['$type'] = $token->type->value;
        }
        $out['$value'] = $this->valueToJson($token->value);
        $this->writeMetadata($out, $token->metadata);

        return $out;
    }

    /** @return array<string, mixed> */
    private function referenceTokenToArray(ReferenceToken $token, ?Type $inheritedType): array
    {
        $out = [];
        if ($token->declaredType !== null && $token->declaredType !== $inheritedType) {
            $out['$type'] = $token->declaredType->value;
        }
        $out['$ref'] = $token->reference->original();
        $this->writeMetadata($out, $token->metadata);

        return $out;
    }

    /** @param array<string, mixed> $out */
    private function writeMetadata(array &$out, Metadata $metadata): void
    {
        if ($metadata->description !== null) {
            $out['$description'] = $metadata->description;
        }
        if ($metadata->extensions !== []) {
            $out['$extensions'] = $metadata->extensions;
        }
        if ($metadata->deprecated !== null) {
            $out['$deprecated'] = $metadata->deprecated;
        }
    }

    private function valueToJson(Value $value): mixed
    {
        return match (true) {
            $value instanceof ReferenceValue => $this->referenceValueToJson($value),
            $value instanceof DimensionValue => $this->dimensionToJson($value),
            $value instanceof NumberValue => $value->value,
            $value instanceof DurationValue => $this->durationToJson($value),
            $value instanceof FontWeightValue => $this->fontWeightToJson($value),
            $value instanceof CubicBezierValue => $this->cubicBezierToJson($value),
            $value instanceof FontFamilyValue => $this->fontFamilyToJson($value),
            $value instanceof ColorValue => $this->colorToJson($value),
            $value instanceof StrokeStyleValue => $this->strokeStyleToJson($value),
            $value instanceof BorderValue => [
                'color' => $this->colorToJson($value->color),
                'width' => $this->dimensionToJson($value->width),
                'style' => $this->strokeStyleToJson($value->style),
            ],
            $value instanceof TransitionValue => [
                'duration' => $this->durationToJson($value->duration),
                'delay' => $this->durationToJson($value->delay),
                'timingFunction' => $this->cubicBezierToJson($value->timingFunction),
            ],
            $value instanceof ShadowValue => $this->shadowToJson($value),
            $value instanceof GradientValue => array_map(
                fn (GradientStop $s): array => [
                    'color' => $this->colorToJson($s->color),
                    'position' => $s->position instanceof ReferenceValue
                        ? $this->referenceValueToJson($s->position)
                        : $s->position,
                ],
                $value->stops,
            ),
            $value instanceof TypographyValue => [
                'fontFamily' => $this->fontFamilyToJson($value->fontFamily),
                'fontSize' => $this->dimensionToJson($value->fontSize),
                'fontWeight' => $this->fontWeightToJson($value->fontWeight),
                'letterSpacing' => $this->dimensionToJson($value->letterSpacing),
                'lineHeight' => $value->lineHeight instanceof ReferenceValue
                    ? $this->referenceValueToJson($value->lineHeight)
                    : $value->lineHeight->value,
            ],
            default => throw new \LogicException('unexpected Value type: ' . $value::class),
        };
    }

    /**
     * Emit a {@see ReferenceValue} in the JSON form that matches its source
     * syntax: curly-brace references emit as a string, JSON-pointer
     * references as an object `{"$ref": "..."}`.
     *
     * @return string|array<string, string>
     */
    private function referenceValueToJson(ReferenceValue $v): string|array
    {
        return $v->reference instanceof CurlyBraceReference
            ? $v->reference->original()
            : ['$ref' => $v->reference->original()];
    }

    /** @return string|array<string, mixed> */
    private function dimensionToJson(DimensionValue|ReferenceValue $v): string|array
    {
        return match (true) {
            $v instanceof ReferenceValue => $this->referenceValueToJson($v),
            default => ['value' => $v->value, 'unit' => $v->unit->value],
        };
    }

    /** @return string|array<string, mixed> */
    private function durationToJson(DurationValue|ReferenceValue $v): string|array
    {
        return match (true) {
            $v instanceof ReferenceValue => $this->referenceValueToJson($v),
            default => ['value' => $v->value, 'unit' => $v->unit->value],
        };
    }

    /** @return string|array<string|int, mixed> */
    private function fontFamilyToJson(FontFamilyValue|ReferenceValue $v): string|array
    {
        return match (true) {
            $v instanceof ReferenceValue => $this->referenceValueToJson($v),
            count($v->families) === 1 => $v->families[0],
            default => $v->families,
        };
    }

    /** @return string|array<string, string>|float */
    private function fontWeightToJson(FontWeightValue|ReferenceValue $v): string|array|float
    {
        return match (true) {
            $v instanceof ReferenceValue => $this->referenceValueToJson($v),
            $v->weight instanceof FontWeightKeyword => $v->weight->value,
            default => $v->weight,
        };
    }

    /** @return string|array<array-key, mixed> */
    private function cubicBezierToJson(CubicBezierValue|ReferenceValue $v): string|array
    {
        return match (true) {
            $v instanceof ReferenceValue => $this->referenceValueToJson($v),
            default => [$v->x1, $v->y1, $v->x2, $v->y2],
        };
    }

    /** @return string|array<string, mixed> */
    private function colorToJson(ColorValue|ReferenceValue $color): string|array
    {
        if ($color instanceof ReferenceValue) {
            return $this->referenceValueToJson($color);
        }
        $out = [
            'colorSpace' => $color->colorSpace->value,
            'components' => array_map(
                static fn (?float $c): string|float => $c === null ? 'none' : $c,
                $color->components,
            ),
        ];
        if ($color->alpha !== null) {
            $out['alpha'] = $color->alpha;
        }
        if ($color->hex !== null) {
            $out['hex'] = $color->hex;
        }

        return $out;
    }

    /** @return string|array<string, mixed> */
    private function strokeStyleToJson(StrokeStyleValue|ReferenceValue $value): string|array
    {
        return match (true) {
            $value instanceof ReferenceValue => $this->referenceValueToJson($value),
            $value->keyword !== null => $value->keyword->value,
            default => $this->strokeStyleCompositeToJson($value),
        };
    }

    /** @return array<string, mixed> */
    private function strokeStyleCompositeToJson(StrokeStyleValue $value): array
    {
        \assert($value->dashArray !== null && $value->lineCap !== null);

        return [
            'dashArray' => array_map(
                fn (DimensionValue|ReferenceValue $d): string|array => $this->dimensionToJson($d),
                $value->dashArray,
            ),
            'lineCap' => $value->lineCap->value,
        ];
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private function shadowToJson(ShadowValue $value): array
    {
        $layers = array_map(fn (ShadowLayer $l): array => $this->shadowLayerToJson($l), $value->layers);

        return $value->isSingleLayer() ? $layers[0] : $layers;
    }

    /** @return array<string, mixed> */
    private function shadowLayerToJson(ShadowLayer $layer): array
    {
        $out = [
            'color' => $this->colorToJson($layer->color),
            'offsetX' => $this->dimensionToJson($layer->offsetX),
            'offsetY' => $this->dimensionToJson($layer->offsetY),
            'blur' => $this->dimensionToJson($layer->blur),
            'spread' => $this->dimensionToJson($layer->spread),
        ];
        $inset = match (true) {
            $layer->inset instanceof ReferenceValue => $this->referenceValueToJson($layer->inset),
            $layer->inset === true => true,
            default => null,
        };
        if ($inset !== null) {
            $out['inset'] = $inset;
        }

        return $out;
    }
}
