<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Serializer;

use Penyaskito\Dtcg\Reference\MaterializationException;
use Penyaskito\Dtcg\Reference\Materializer;
use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Path;
use Penyaskito\Dtcg\Tom\Token;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\ColorSpace;
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
use Penyaskito\Dtcg\Tom\Value\ShadowLayer;
use Penyaskito\Dtcg\Tom\Value\ShadowValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;
use Penyaskito\Dtcg\Tom\Value\TransitionValue;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;
use Penyaskito\Dtcg\Tom\Walker;

/**
 * @internal Reference implementation only — not intended for production use.
 *
 * Exists to prove the {@see Serializer} interface shape and to enable
 * end-to-end testing of the parse → resolve → serialize pipeline. It is
 * deliberately unopinionated and exposes no configuration beyond the CSS
 * selector: no prefixes, no theming, no naming strategies, no per-token
 * overrides, no handling of composite-value nuances beyond best-effort
 * fallbacks.
 *
 * Real consumers need all of the above and should ship their own
 * {@see Serializer} implementation.
 *
 * Do not depend on this class from third-party code. Its behaviour, output
 * format, and existence may change without notice.
 */
final class CssCustomPropertiesSerializer implements Serializer
{
    private const CSS_GENERIC_FAMILIES = [
        'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy',
        'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace',
        'ui-rounded', 'emoji', 'math', 'fangsong',
    ];

    public function __construct(
        private readonly string $selector = ':root',
    ) {
    }

    public function serialize(Document $document): string
    {
        $materializer = new Materializer(new Resolver($document));
        $lines = [];

        foreach (Walker::tokens($document) as $token) {
            // Per-token materialization: every reference (top-level $ref,
            // $value-root curly-brace alias, or property-level ref inside a
            // composite) is resolved here. If a token can't be fully
            // materialized — broken target, cycle, type mismatch — we skip
            // just that token rather than failing the whole document.
            try {
                $materialized = $materializer->materializeToken($token);
            } catch (MaterializationException) {
                continue;
            }

            $entries = $this->format($materialized->value);
            if ($entries === []) {
                continue;
            }

            $name = self::propertyName($token->path);
            foreach ($entries as $entry) {
                $lines[] = sprintf('  %s%s: %s;', $name, $entry['suffix'], $entry['value']);
            }
        }

        if ($lines === []) {
            return sprintf("%s {\n}\n", $this->selector);
        }

        return sprintf("%s {\n%s\n}\n", $this->selector, implode("\n", $lines));
    }

    /**
     * Format a value into one or more custom-property entries.
     *
     * Most value types produce a single entry with an empty suffix. Typography
     * is split across multiple sub-properties (one per field) because CSS has
     * no single property that carries fontFamily + fontSize + fontWeight +
     * letterSpacing + lineHeight — the `font:` shorthand excludes letter-spacing.
     *
     * @return list<array{suffix: string, value: string}>
     */
    private function format(Value $value): array
    {
        return match (true) {
            $value instanceof DimensionValue => [self::entry(self::formatDimension($value))],
            $value instanceof NumberValue => [self::entry(self::formatNumberValue($value))],
            $value instanceof DurationValue => [self::entry(self::formatDuration($value))],
            $value instanceof FontWeightValue => [self::entry(self::formatFontWeight($value))],
            $value instanceof CubicBezierValue => [self::entry(self::formatCubicBezier($value))],
            $value instanceof FontFamilyValue => [self::entry(self::formatFontFamily($value))],
            $value instanceof ColorValue => [self::entry(self::formatColor($value))],
            $value instanceof StrokeStyleValue => [self::entry(self::formatStrokeStyle($value))],
            $value instanceof BorderValue => [self::entry(self::formatBorder($value))],
            $value instanceof TransitionValue => [self::entry(self::formatTransition($value))],
            $value instanceof ShadowValue => [self::entry(self::formatShadow($value))],
            $value instanceof GradientValue => [self::entry(self::formatGradient($value))],
            $value instanceof TypographyValue => self::formatTypography($value),
            default => [],
        };
    }

    /** @return array{suffix: string, value: string} */
    private static function entry(string $value, string $suffix = ''): array
    {
        return ['suffix' => $suffix, 'value' => $value];
    }

    private static function formatDimension(DimensionValue $v): string
    {
        return (string) $v->value . $v->unit->value;
    }

    private static function formatNumberValue(NumberValue $v): string
    {
        return (string) $v->value;
    }

    private static function formatDuration(DurationValue $v): string
    {
        return (string) $v->value . $v->unit->value;
    }

    private static function formatBorder(BorderValue $v): string
    {
        // After materialization, all composite sub-fields are concrete primitives.
        \assert($v->color instanceof ColorValue);
        \assert($v->width instanceof DimensionValue);
        \assert($v->style instanceof StrokeStyleValue);

        // CSS `border` shorthand order: <width> <style> <color>
        return sprintf(
            '%s %s %s',
            self::formatDimension($v->width),
            self::formatStrokeStyle($v->style),
            self::formatColor($v->color),
        );
    }

    private static function formatTransition(TransitionValue $v): string
    {
        \assert($v->duration instanceof DurationValue);
        \assert($v->delay instanceof DurationValue);
        \assert($v->timingFunction instanceof CubicBezierValue);

        // CSS `transition` shorthand (without property): <duration> <timing-function> <delay>
        return sprintf(
            '%s %s %s',
            self::formatDuration($v->duration),
            self::formatCubicBezier($v->timingFunction),
            self::formatDuration($v->delay),
        );
    }

    private static function formatShadow(ShadowValue $v): string
    {
        // CSS `box-shadow`: comma-separated layers; each is
        // `[inset ]<x> <y> <blur> <spread> <color>`.
        return implode(', ', array_map(
            static fn (ShadowLayer $layer): string => self::formatShadowLayer($layer),
            $v->layers,
        ));
    }

    private static function formatShadowLayer(ShadowLayer $layer): string
    {
        \assert($layer->color instanceof ColorValue);
        \assert($layer->offsetX instanceof DimensionValue);
        \assert($layer->offsetY instanceof DimensionValue);
        \assert($layer->blur instanceof DimensionValue);
        \assert($layer->spread instanceof DimensionValue);
        \assert(is_bool($layer->inset));

        $parts = implode(' ', [
            self::formatDimension($layer->offsetX),
            self::formatDimension($layer->offsetY),
            self::formatDimension($layer->blur),
            self::formatDimension($layer->spread),
            self::formatColor($layer->color),
        ]);

        return $layer->inset ? 'inset ' . $parts : $parts;
    }

    private static function formatGradient(GradientValue $v): string
    {
        // DTCG gradient carries no direction; CSS `linear-gradient()` defaults
        // to `to bottom` when omitted. Positions (0-1) are emitted as percentages.
        $stops = array_map(
            static function (GradientStop $s): string {
                \assert($s->color instanceof ColorValue);
                \assert(is_float($s->position));

                return sprintf(
                    '%s %s%%',
                    self::formatColor($s->color),
                    (string) ($s->position * 100),
                );
            },
            $v->stops,
        );

        return 'linear-gradient(' . implode(', ', $stops) . ')';
    }

    /**
     * Typography is the one type that needs to emit more than one custom
     * property. We use the CSS `font:` shorthand for (weight, size/line-height,
     * family) and a sibling `-letter-spacing` property for letterSpacing, which
     * the `font:` shorthand doesn't cover. Consumers wire them up like:
     *
     *     .heading {
     *         font: var(--type-heading);
     *         letter-spacing: var(--type-heading-letter-spacing);
     *     }
     *
     * This is still a shortcut: real design-system output usually splits
     * typography across one property per field (font-family, font-size,
     * font-weight, letter-spacing, line-height) so each is overridable in
     * isolation. That structural choice is out of scope for a reference impl.
     *
     * @return list<array{suffix: string, value: string}>
     */
    private static function formatTypography(TypographyValue $v): array
    {
        \assert($v->fontFamily instanceof FontFamilyValue);
        \assert($v->fontSize instanceof DimensionValue);
        \assert($v->fontWeight instanceof FontWeightValue);
        \assert($v->letterSpacing instanceof DimensionValue);
        \assert($v->lineHeight instanceof NumberValue);

        $shorthand = sprintf(
            '%s %s/%s %s',
            self::formatFontWeight($v->fontWeight),
            self::formatDimension($v->fontSize),
            self::formatNumberValue($v->lineHeight),
            self::formatFontFamily($v->fontFamily),
        );

        return [
            self::entry($shorthand),
            self::entry(self::formatDimension($v->letterSpacing), '-letter-spacing'),
        ];
    }

    private static function formatFontWeight(FontWeightValue $v): string
    {
        if ($v->weight instanceof FontWeightKeyword) {
            return (string) match ($v->weight) {
                FontWeightKeyword::Thin, FontWeightKeyword::Hairline => 100,
                FontWeightKeyword::ExtraLight, FontWeightKeyword::UltraLight => 200,
                FontWeightKeyword::Light => 300,
                FontWeightKeyword::Normal, FontWeightKeyword::Regular => 400,
                FontWeightKeyword::Book => 450,
                FontWeightKeyword::Medium => 500,
                FontWeightKeyword::SemiBold, FontWeightKeyword::DemiBold => 600,
                FontWeightKeyword::Bold => 700,
                FontWeightKeyword::ExtraBold, FontWeightKeyword::UltraBold => 800,
                FontWeightKeyword::Black, FontWeightKeyword::Heavy => 900,
                FontWeightKeyword::ExtraBlack, FontWeightKeyword::UltraBlack => 950,
            };
        }

        return (string) $v->weight;
    }

    private static function formatCubicBezier(CubicBezierValue $v): string
    {
        return sprintf(
            'cubic-bezier(%s, %s, %s, %s)',
            (string) $v->x1,
            (string) $v->y1,
            (string) $v->x2,
            (string) $v->y2,
        );
    }

    private static function formatFontFamily(FontFamilyValue $v): string
    {
        $parts = array_map(static function (string $name): string {
            if (in_array($name, self::CSS_GENERIC_FAMILIES, true)) {
                return $name;
            }
            return '"' . addslashes($name) . '"';
        }, $v->families);

        return implode(', ', $parts);
    }

    private static function formatColor(ColorValue $color): string
    {
        if ($color->hex !== null && ($color->alpha === null || $color->alpha === 1.0)) {
            return $color->hex;
        }

        $components = array_map(
            static fn (?float $c): string => $c === null ? 'none' : (string) $c,
            $color->components,
        );

        if ($color->colorSpace === ColorSpace::Hsl || $color->colorSpace === ColorSpace::Hwb) {
            // indices 1 and 2 are percentages per the schema
            if (isset($components[1]) && $components[1] !== 'none') {
                $components[1] .= '%';
            }
            if (isset($components[2]) && $components[2] !== 'none') {
                $components[2] .= '%';
            }
        }

        $usesColorFunction = match ($color->colorSpace) {
            ColorSpace::Srgb,
            ColorSpace::SrgbLinear,
            ColorSpace::DisplayP3,
            ColorSpace::A98Rgb,
            ColorSpace::ProphotoRgb,
            ColorSpace::Rec2020,
            ColorSpace::XyzD65,
            ColorSpace::XyzD50 => true,
            default => false,
        };

        if ($usesColorFunction) {
            $body = $color->colorSpace->value . ' ' . implode(' ', $components);
            $fn = 'color';
        } else {
            $body = implode(' ', $components);
            $fn = $color->colorSpace->value;
        }

        if ($color->alpha !== null && $color->alpha !== 1.0) {
            $body .= ' / ' . (string) $color->alpha;
        }

        return sprintf('%s(%s)', $fn, $body);
    }

    private static function formatStrokeStyle(StrokeStyleValue $v): string
    {
        if ($v->keyword !== null) {
            return $v->keyword->value;
        }

        // Composite (dashArray + lineCap) has no single-property CSS equivalent;
        // fall back to the closest keyword so the custom property remains usable.
        // After materialization dashArray items are concrete DimensionValues, but
        // we don't try to encode them — CSS `border-style` accepts only keywords.
        return 'dashed';
    }

    private static function propertyName(Path $path): string
    {
        return '--' . implode('-', array_map(self::kebab(...), $path->segments));
    }

    private static function kebab(string $segment): string
    {
        $hyphenated = preg_replace('/([a-z\d])([A-Z])/', '$1-$2', $segment);
        $result = strtolower($hyphenated ?? $segment);

        return str_replace('_', '-', $result);
    }
}
