<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Serializer;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Serializer\CssCustomPropertiesSerializer;
use PHPUnit\Framework\TestCase;

final class CssCustomPropertiesSerializerTest extends TestCase
{
    public function testEmitsBasicDimensionAndNumber(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
            ],
            'leading' => ['$type' => 'number', '$value' => 1.5],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString(':root {', $css);
        self::assertStringContainsString('--spacing-base: 16px;', $css);
        self::assertStringContainsString('--leading: 1.5;', $css);
    }

    public function testCamelCasePathSegmentsBecomeKebabCase(): void
    {
        $doc = (new Parser())->parseArray([
            'typography' => [
                '$type' => 'number',
                'lineHeight' => ['$value' => 1.25],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--typography-line-height: 1.25;', $css);
    }

    public function testResolvesReferencesToFinalValue(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 8, 'unit' => 'px']],
                'large' => ['$ref' => '#/spacing/base'],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--spacing-base: 8px;', $css);
        self::assertStringContainsString('--spacing-large: 8px;', $css);
    }

    public function testSkipsUnresolvableReferences(): void
    {
        $doc = (new Parser())->parseArray([
            'orphan' => ['$type' => 'dimension', '$ref' => '#/does/not/exist'],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertSame(":root {\n}\n", $css);
    }

    public function testFormatsDuration(): void
    {
        $doc = (new Parser())->parseArray([
            'time' => [
                '$type' => 'duration',
                'fast' => ['$value' => ['value' => 200, 'unit' => 'ms']],
                'slow' => ['$value' => ['value' => 0.3, 'unit' => 's']],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--time-fast: 200ms;', $css);
        self::assertStringContainsString('--time-slow: 0.3s;', $css);
    }

    public function testFontWeightKeywordIsMappedToNumber(): void
    {
        $doc = (new Parser())->parseArray([
            'w' => [
                '$type' => 'fontWeight',
                'bold' => ['$value' => 'bold'],
                'semi' => ['$value' => 'semi-bold'],
                'custom' => ['$value' => 650],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--w-bold: 700;', $css);
        self::assertStringContainsString('--w-semi: 600;', $css);
        self::assertStringContainsString('--w-custom: 650;', $css);
    }

    public function testCubicBezier(): void
    {
        $doc = (new Parser())->parseArray([
            'easings' => [
                '$type' => 'cubicBezier',
                'inOut' => ['$value' => [0.42, 0, 0.58, 1]],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--easings-in-out: cubic-bezier(0.42, 0, 0.58, 1);', $css);
    }

    public function testFontFamilyQuotesNonGenerics(): void
    {
        $doc = (new Parser())->parseArray([
            'fonts' => [
                '$type' => 'fontFamily',
                'stack' => ['$value' => ['Inter', 'system-ui', 'sans-serif']],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--fonts-stack: "Inter", system-ui, sans-serif;', $css);
    }

    public function testColorUsesHexShortcutWhenAvailable(): void
    {
        $doc = (new Parser())->parseArray([
            'c' => [
                '$type' => 'color',
                'red' => ['$value' => ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'hex' => '#ff0000']],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--c-red: #ff0000;', $css);
    }

    public function testColorWithAlphaUsesColorFunction(): void
    {
        $doc = (new Parser())->parseArray([
            'c' => [
                '$type' => 'color',
                'blue' => [
                    '$value' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1], 'alpha' => 0.5],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--c-blue: color(srgb 0 0 1 / 0.5);', $css);
    }

    public function testHslColorAppendsPercentToSaturationAndLightness(): void
    {
        $doc = (new Parser())->parseArray([
            'c' => [
                '$type' => 'color',
                'accent' => ['$value' => ['colorSpace' => 'hsl', 'components' => [120, 50, 40]]],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--c-accent: hsl(120 50% 40%);', $css);
    }

    public function testOklchColor(): void
    {
        $doc = (new Parser())->parseArray([
            'c' => [
                '$type' => 'color',
                'something' => ['$value' => ['colorSpace' => 'oklch', 'components' => [0.7, 0.15, 240]]],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--c-something: oklch(0.7 0.15 240);', $css);
    }

    public function testStrokeStyleKeyword(): void
    {
        $doc = (new Parser())->parseArray([
            'stroke' => ['$type' => 'strokeStyle', '$value' => 'dotted'],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--stroke: dotted;', $css);
    }

    public function testCompositeStrokeStyleFallsBackToDashed(): void
    {
        $doc = (new Parser())->parseArray([
            'stroke' => [
                '$type' => 'strokeStyle',
                '$value' => [
                    'dashArray' => [['value' => 4, 'unit' => 'px']],
                    'lineCap' => 'round',
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--stroke: dashed;', $css);
    }

    public function testCustomSelector(): void
    {
        $doc = (new Parser())->parseArray([
            'x' => ['$type' => 'number', '$value' => 1],
        ]);

        $css = (new CssCustomPropertiesSerializer('.dark'))->serialize($doc);

        self::assertStringStartsWith('.dark {', $css);
    }

    public function testEmptyDocumentProducesEmptyRuleset(): void
    {
        $doc = (new Parser())->parseArray([]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertSame(":root {\n}\n", $css);
    }

    public function testBorderEmitsShorthand(): void
    {
        $doc = (new Parser())->parseArray([
            'b' => [
                '$type' => 'border',
                'focus' => [
                    '$value' => [
                        'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1], 'hex' => '#0000ff'],
                        'width' => ['value' => 2, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--b-focus: 2px solid #0000ff;', $css);
    }

    public function testBorderWithCompositeStyleFallsBackToDashed(): void
    {
        $doc = (new Parser())->parseArray([
            'b' => [
                '$type' => 'border',
                'custom' => [
                    '$value' => [
                        'color' => ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'hex' => '#ff0000'],
                        'width' => ['value' => 1, 'unit' => 'px'],
                        'style' => [
                            'dashArray' => [['value' => 4, 'unit' => 'px']],
                            'lineCap' => 'round',
                        ],
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--b-custom: 1px dashed #ff0000;', $css);
    }

    public function testTransitionEmitsShorthand(): void
    {
        $doc = (new Parser())->parseArray([
            't' => [
                '$type' => 'transition',
                'fadeIn' => [
                    '$value' => [
                        'duration' => ['value' => 200, 'unit' => 'ms'],
                        'delay' => ['value' => 50, 'unit' => 'ms'],
                        'timingFunction' => [0.42, 0, 0.58, 1],
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString(
            '--t-fade-in: 200ms cubic-bezier(0.42, 0, 0.58, 1) 50ms;',
            $css,
        );
    }

    public function testSingleShadowEmitsBoxShadowValue(): void
    {
        $doc = (new Parser())->parseArray([
            's' => [
                '$type' => 'shadow',
                'card' => [
                    '$value' => [
                        'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'alpha' => 0.2],
                        'offsetX' => ['value' => 0, 'unit' => 'px'],
                        'offsetY' => ['value' => 4, 'unit' => 'px'],
                        'blur' => ['value' => 8, 'unit' => 'px'],
                        'spread' => ['value' => 0, 'unit' => 'px'],
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString(
            '--s-card: 0px 4px 8px 0px color(srgb 0 0 0 / 0.2);',
            $css,
        );
    }

    public function testMultiLayerShadowWithInsetIsCommaSeparated(): void
    {
        $doc = (new Parser())->parseArray([
            's' => [
                '$type' => 'shadow',
                'stack' => [
                    '$value' => [
                        [
                            'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'hex' => '#000000'],
                            'offsetX' => ['value' => 0, 'unit' => 'px'],
                            'offsetY' => ['value' => 1, 'unit' => 'px'],
                            'blur' => ['value' => 2, 'unit' => 'px'],
                            'spread' => ['value' => 0, 'unit' => 'px'],
                        ],
                        [
                            'color' => ['colorSpace' => 'srgb', 'components' => [1, 1, 1], 'hex' => '#ffffff'],
                            'offsetX' => ['value' => 0, 'unit' => 'px'],
                            'offsetY' => ['value' => 1, 'unit' => 'px'],
                            'blur' => ['value' => 0, 'unit' => 'px'],
                            'spread' => ['value' => 0, 'unit' => 'px'],
                            'inset' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString(
            '--s-stack: 0px 1px 2px 0px #000000, inset 0px 1px 0px 0px #ffffff;',
            $css,
        );
    }

    public function testGradientEmitsLinearGradientWithPercentStops(): void
    {
        $doc = (new Parser())->parseArray([
            'g' => [
                '$type' => 'gradient',
                'rainbow' => [
                    '$value' => [
                        ['color' => ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'hex' => '#ff0000'], 'position' => 0],
                        ['color' => ['colorSpace' => 'srgb', 'components' => [0, 1, 0], 'hex' => '#00ff00'], 'position' => 0.5],
                        ['color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1], 'hex' => '#0000ff'], 'position' => 1],
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString(
            '--g-rainbow: linear-gradient(#ff0000 0%, #00ff00 50%, #0000ff 100%);',
            $css,
        );
    }

    public function testIncompleteCompositeIsRejectedAtParseTimeAndNeverReachesSerializer(): void
    {
        // Invariant: a composite with a missing required field (e.g. border.color)
        // is rejected by its factory during parsing. The TOM cannot contain an
        // incomplete composite, so the serializer cannot emit malformed CSS like
        // `--b-focus: 2px solid ;`.
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('border $value.color is required (at /b/focus)');

        (new Parser())->parseArray([
            'b' => [
                '$type' => 'border',
                'focus' => [
                    '$value' => [
                        'width' => ['value' => 2, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);
    }

    public function testReferenceValueResolvesToTargetValue(): void
    {
        // A token with a curly-brace alias at $value root should serialize as
        // the resolved target's value — same behaviour as ReferenceToken.
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 8, 'unit' => 'px']],
                'alias' => ['$value' => '{spacing.base}'],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--spacing-base: 8px;', $css);
        self::assertStringContainsString('--spacing-alias: 8px;', $css);
    }

    public function testBrokenReferenceValueIsSilentlySkipped(): void
    {
        $doc = (new Parser())->parseArray([
            'alias' => ['$type' => 'dimension', '$value' => '{does.not.exist}'],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringNotContainsString('--alias', $css);
    }

    public function testBorderWithReferencedColorIsMaterializedAndEmitted(): void
    {
        // The serializer materializes per-token internally, so a border with
        // a curly-brace alias for color emits with the resolved hex.
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'color',
                'primary' => [
                    '$value' => [
                        'colorSpace' => 'srgb',
                        'components' => [0, 0, 1],
                        'hex' => '#0000ff',
                    ],
                ],
            ],
            'b' => [
                '$type' => 'border',
                'focus' => [
                    '$value' => [
                        'color' => '{colors.primary}',
                        'width' => ['value' => 2, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString('--b-focus: 2px solid #0000ff;', $css);
    }

    public function testCompositeWithBrokenSubFieldRefIsSkipped(): void
    {
        $doc = (new Parser())->parseArray([
            'b' => [
                '$type' => 'border',
                'broken' => [
                    '$value' => [
                        'color' => '{nonexistent}',
                        'width' => ['value' => 1, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringNotContainsString('--b-broken', $css);
    }

    public function testTypographyEmitsFontShorthandPlusSiblingLetterSpacing(): void
    {
        $doc = (new Parser())->parseArray([
            'type' => [
                '$type' => 'typography',
                'heading' => [
                    '$value' => [
                        'fontFamily' => ['Inter', 'sans-serif'],
                        'fontSize' => ['value' => 24, 'unit' => 'px'],
                        'fontWeight' => 'bold',
                        'letterSpacing' => ['value' => -0.5, 'unit' => 'px'],
                        'lineHeight' => 1.2,
                    ],
                ],
            ],
        ]);

        $css = (new CssCustomPropertiesSerializer())->serialize($doc);

        self::assertStringContainsString(
            '--type-heading: 700 24px/1.2 "Inter", sans-serif;',
            $css,
        );
        self::assertStringContainsString(
            '--type-heading-letter-spacing: -0.5px;',
            $css,
        );
    }
}
