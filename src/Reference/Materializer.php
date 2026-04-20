<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\Token;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\CubicBezierValue;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\DurationValue;
use Penyaskito\Dtcg\Tom\Value\FontFamilyValue;
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
 * Resolves every reference in a {@see Document} or single value, producing
 * a fully concrete result with no remaining {@see ReferenceValue} or
 * {@see ReferenceToken} occurrences.
 *
 * Strict — throws {@see MaterializationException} when a reference cannot
 * be resolved (broken target, cycle, type mismatch). Consumers wanting
 * best-effort behaviour can call per-token via {@see materializeToken()}
 * and catch the exception to skip individual tokens.
 *
 * Note: property-level JSON Pointer references (segments starting with
 * `$`, used by `shadow.inset` and a few other places per the schema) are
 * not yet supported by the underlying {@see Resolver}. Materialization of
 * a value whose sub-field reference uses a property-level pointer will
 * throw with the resolver's own "property-level references not yet
 * supported" message.
 */
final class Materializer
{
    public function __construct(
        private readonly Resolver $resolver,
    ) {
    }

    /**
     * Materialize an entire document. Strict — throws on first failure.
     */
    public function materialize(Document $document): Document
    {
        return new Document(
            $document->specVersion,
            $this->materializeGroup($document->root),
            $document->sourceUri,
        );
    }

    /**
     * Materialize a single token. Returns a {@see ValueToken} with
     * fully-concrete value. ReferenceTokens are converted into their
     * resolved ValueToken form.
     *
     * @throws MaterializationException
     */
    public function materializeToken(Token $token): ValueToken
    {
        if ($token instanceof ReferenceToken) {
            return $this->materializeReferenceToken($token);
        }
        if ($token instanceof ValueToken) {
            return $this->materializeValueToken($token);
        }

        throw new MaterializationException('unsupported token subclass: ' . $token::class);
    }

    /**
     * Materialize a value. Returns a fully-concrete value with no
     * ReferenceValue anywhere in its tree.
     *
     * @throws MaterializationException
     */
    public function materializeValue(Value $value): Value
    {
        if ($value instanceof ReferenceValue) {
            $resolved = $this->resolveValueRef($value);

            return $this->materializeValue($resolved);
        }

        return match (true) {
            $value instanceof BorderValue => $this->materializeBorder($value),
            $value instanceof TransitionValue => $this->materializeTransition($value),
            $value instanceof ShadowValue => $this->materializeShadow($value),
            $value instanceof GradientValue => $this->materializeGradient($value),
            $value instanceof TypographyValue => $this->materializeTypography($value),
            $value instanceof StrokeStyleValue => $this->materializeStrokeStyle($value),
            default => $value,
        };
    }

    private function materializeGroup(Group $group): Group
    {
        $newChildren = [];
        foreach ($group->children as $name => $child) {
            $newChildren[$name] = $child instanceof Group
                ? $this->materializeGroup($child)
                : $this->materializeToken($child);
        }

        return new Group(
            name: $group->name,
            path: $group->path,
            defaultType: $group->defaultType,
            metadata: $group->metadata,
            sourceMap: $group->sourceMap,
            children: $newChildren,
            extendsFrom: $group->extendsFrom,
            inheritedFrom: $group->inheritedFrom,
        );
    }

    private function materializeReferenceToken(ReferenceToken $token): ValueToken
    {
        try {
            $target = $this->resolver->resolveChain($token->reference);
        } catch (UnresolvableReferenceException | CyclicReferenceException $e) {
            throw new MaterializationException(
                sprintf("cannot materialize ref token '%s': %s", $token->path->toString(), $e->getMessage()),
                0,
                $e,
            );
        }
        if (!$target instanceof ValueToken) {
            throw new MaterializationException(sprintf(
                "ref token '%s' resolves to a group, not a value token",
                $token->path->toString(),
            ));
        }

        $resolvedValue = $this->materializeValue($target->value);
        $type = $token->declaredType ?? $target->type;

        return new ValueToken(
            name: $token->name,
            path: $token->path,
            type: $type,
            value: $resolvedValue,
            metadata: $token->metadata,
            sourceMap: $token->sourceMap,
        );
    }

    private function materializeValueToken(ValueToken $token): ValueToken
    {
        $resolved = $this->materializeValue($token->value);
        if ($resolved === $token->value) {
            return $token;
        }

        return new ValueToken(
            name: $token->name,
            path: $token->path,
            type: $token->type,
            value: $resolved,
            metadata: $token->metadata,
            sourceMap: $token->sourceMap,
        );
    }

    private function resolveValueRef(ReferenceValue $rv): Value
    {
        try {
            $target = $this->resolver->resolveChain($rv->reference);
        } catch (UnresolvableReferenceException | CyclicReferenceException $e) {
            throw new MaterializationException(
                sprintf("cannot resolve reference '%s': %s", $rv->reference->original(), $e->getMessage()),
                0,
                $e,
            );
        }
        if (!$target instanceof ValueToken) {
            throw new MaterializationException(sprintf(
                "reference '%s' resolves to a group, not a value",
                $rv->reference->original(),
            ));
        }

        return $target->value;
    }

    private function materializeBorder(BorderValue $v): BorderValue
    {
        return new BorderValue(
            $this->materializeAs($v->color, ColorValue::class),
            $this->materializeAs($v->width, DimensionValue::class),
            $this->materializeAs($v->style, StrokeStyleValue::class),
        );
    }

    private function materializeTransition(TransitionValue $v): TransitionValue
    {
        return new TransitionValue(
            $this->materializeAs($v->duration, DurationValue::class),
            $this->materializeAs($v->delay, DurationValue::class),
            $this->materializeAs($v->timingFunction, CubicBezierValue::class),
        );
    }

    private function materializeShadow(ShadowValue $v): ShadowValue
    {
        return new ShadowValue(array_map(
            fn (ShadowLayer $l): ShadowLayer => $this->materializeShadowLayer($l),
            $v->layers,
        ));
    }

    private function materializeShadowLayer(ShadowLayer $layer): ShadowLayer
    {
        // `inset` can be `bool|ReferenceValue`. The reference form is a
        // property-level JSON Pointer (e.g. `#/.../$value/inset`) — these
        // require property-level pointer resolution, not yet supported by
        // Resolver. We surface that limitation here directly so the error
        // mentions `inset` rather than coming through a more generic path.
        $inset = $layer->inset;
        if ($inset instanceof ReferenceValue) {
            throw new MaterializationException(
                'shadow.inset references use property-level JSON Pointers, which are not yet supported',
            );
        }

        return new ShadowLayer(
            $this->materializeAs($layer->color, ColorValue::class),
            $this->materializeAs($layer->offsetX, DimensionValue::class),
            $this->materializeAs($layer->offsetY, DimensionValue::class),
            $this->materializeAs($layer->blur, DimensionValue::class),
            $this->materializeAs($layer->spread, DimensionValue::class),
            $inset,
        );
    }

    private function materializeGradient(GradientValue $v): GradientValue
    {
        return new GradientValue(array_map(
            fn (GradientStop $s): GradientStop => new GradientStop(
                $this->materializeAs($s->color, ColorValue::class),
                $s->position instanceof ReferenceValue
                    ? $this->resolveToFloat($s->position)
                    : $s->position,
            ),
            $v->stops,
        ));
    }

    private function materializeTypography(TypographyValue $v): TypographyValue
    {
        return new TypographyValue(
            $this->materializeAs($v->fontFamily, FontFamilyValue::class),
            $this->materializeAs($v->fontSize, DimensionValue::class),
            $this->materializeAs($v->fontWeight, FontWeightValue::class),
            $this->materializeAs($v->letterSpacing, DimensionValue::class),
            $this->materializeAs($v->lineHeight, NumberValue::class),
        );
    }

    private function materializeStrokeStyle(StrokeStyleValue $v): StrokeStyleValue
    {
        if ($v->keyword !== null) {
            return $v;
        }

        \assert($v->dashArray !== null && $v->lineCap !== null);

        $dashArray = array_map(
            fn (DimensionValue|ReferenceValue $d): DimensionValue
                => $this->materializeAs($d, DimensionValue::class),
            $v->dashArray,
        );

        return StrokeStyleValue::composite($dashArray, $v->lineCap);
    }

    /**
     * @template T of Value
     * @param class-string<T> $expectedClass
     * @return T
     */
    private function materializeAs(Value $field, string $expectedClass): Value
    {
        $resolved = $this->materializeValue($field);
        if (!$resolved instanceof $expectedClass) {
            throw new MaterializationException(sprintf(
                'expected a value of type %s, resolved to %s',
                $expectedClass,
                $resolved::class,
            ));
        }

        return $resolved;
    }

    private function resolveToFloat(ReferenceValue $rv): float
    {
        $value = $this->materializeValue($this->resolveValueRef($rv));
        if (!$value instanceof NumberValue) {
            throw new MaterializationException(sprintf(
                "reference '%s' should resolve to a number, got %s",
                $rv->reference->original(),
                $value::class,
            ));
        }

        return $value->value;
    }
}
