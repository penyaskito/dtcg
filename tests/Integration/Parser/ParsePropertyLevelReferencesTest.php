<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\GradientValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\ShadowValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;
use Penyaskito\Dtcg\Tom\Value\TransitionValue;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final class ParsePropertyLevelReferencesTest extends TestCase
{
    public function testBorderColorAcceptsCurlyBraceReference(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'color',
                'primary' => ['$value' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1]]],
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

        $focus = $this->leaf($doc, 'b', 'focus');
        self::assertInstanceOf(BorderValue::class, $focus->value);
        self::assertInstanceOf(ReferenceValue::class, $focus->value->color);
        self::assertInstanceOf(CurlyBraceReference::class, $focus->value->color->reference);
        self::assertInstanceOf(DimensionValue::class, $focus->value->width);
        self::assertInstanceOf(StrokeStyleValue::class, $focus->value->style);
    }

    public function testBorderColorAcceptsRefObjectForm(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'color',
                'primary' => ['$value' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1]]],
            ],
            'b' => [
                '$type' => 'border',
                'focus' => [
                    '$value' => [
                        'color' => ['$ref' => '#/colors/primary'],
                        'width' => ['value' => 2, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        $focus = $this->leaf($doc, 'b', 'focus');
        self::assertInstanceOf(BorderValue::class, $focus->value);
        self::assertInstanceOf(ReferenceValue::class, $focus->value->color);
        self::assertInstanceOf(JsonPointerReference::class, $focus->value->color->reference);
        self::assertSame('#/colors/primary', $focus->value->color->reference->original());
    }

    public function testTransitionFieldsAcceptReferences(): void
    {
        $doc = (new Parser())->parseArray([
            'durations' => [
                '$type' => 'duration',
                'fast' => ['$value' => ['value' => 200, 'unit' => 'ms']],
            ],
            'easings' => [
                '$type' => 'cubicBezier',
                'inOut' => ['$value' => [0.42, 0, 0.58, 1]],
            ],
            't' => [
                '$type' => 'transition',
                'fade' => [
                    '$value' => [
                        'duration' => '{durations.fast}',
                        'delay' => ['value' => 0, 'unit' => 'ms'],
                        'timingFunction' => ['$ref' => '#/easings/inOut'],
                    ],
                ],
            ],
        ]);

        $fade = $this->leaf($doc, 't', 'fade');
        self::assertInstanceOf(TransitionValue::class, $fade->value);
        self::assertInstanceOf(ReferenceValue::class, $fade->value->duration);
        self::assertInstanceOf(ReferenceValue::class, $fade->value->timingFunction);
    }

    public function testShadowLayerFieldsAcceptReferences(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'color',
                'shadow' => ['$value' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'alpha' => 0.2]],
            ],
            'sizes' => [
                '$type' => 'dimension',
                'sm' => ['$value' => ['value' => 4, 'unit' => 'px']],
            ],
            's' => [
                '$type' => 'shadow',
                'card' => [
                    '$value' => [
                        'color' => '{colors.shadow}',
                        'offsetX' => ['value' => 0, 'unit' => 'px'],
                        'offsetY' => ['$ref' => '#/sizes/sm'],
                        'blur' => ['value' => 8, 'unit' => 'px'],
                        'spread' => ['value' => 0, 'unit' => 'px'],
                    ],
                ],
            ],
        ]);

        $card = $this->leaf($doc, 's', 'card');
        self::assertInstanceOf(ShadowValue::class, $card->value);
        $layer = $card->value->layers[0];
        self::assertInstanceOf(ReferenceValue::class, $layer->color);
        self::assertInstanceOf(ReferenceValue::class, $layer->offsetY);
    }

    public function testGradientStopColorAndPositionAcceptReferences(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'color',
                'red' => ['$value' => ['colorSpace' => 'srgb', 'components' => [1, 0, 0]]],
            ],
            'positions' => [
                '$type' => 'number',
                'middle' => ['$value' => 0.5],
            ],
            'g' => [
                '$type' => 'gradient',
                'rainbow' => [
                    '$value' => [
                        ['color' => '{colors.red}', 'position' => 0],
                        [
                            'color' => ['colorSpace' => 'srgb', 'components' => [0, 1, 0]],
                            'position' => ['$ref' => '#/positions/middle'],
                        ],
                        ['color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1]], 'position' => 1],
                    ],
                ],
            ],
        ]);

        $rainbow = $this->leaf($doc, 'g', 'rainbow');
        self::assertInstanceOf(GradientValue::class, $rainbow->value);
        self::assertInstanceOf(ReferenceValue::class, $rainbow->value->stops[0]->color);
        self::assertInstanceOf(ReferenceValue::class, $rainbow->value->stops[1]->position);
    }

    public function testTypographyFieldsAcceptReferences(): void
    {
        $doc = (new Parser())->parseArray([
            'fams' => ['$type' => 'fontFamily', 'main' => ['$value' => ['Inter', 'sans-serif']]],
            'sizes' => ['$type' => 'dimension', 'h1' => ['$value' => ['value' => 24, 'unit' => 'px']]],
            'type' => [
                '$type' => 'typography',
                'heading' => [
                    '$value' => [
                        'fontFamily' => '{fams.main}',
                        'fontSize' => ['$ref' => '#/sizes/h1'],
                        'fontWeight' => 'bold',
                        'letterSpacing' => ['value' => 0, 'unit' => 'px'],
                        'lineHeight' => 1.2,
                    ],
                ],
            ],
        ]);

        $heading = $this->leaf($doc, 'type', 'heading');
        self::assertInstanceOf(TypographyValue::class, $heading->value);
        self::assertInstanceOf(ReferenceValue::class, $heading->value->fontFamily);
        self::assertInstanceOf(ReferenceValue::class, $heading->value->fontSize);
    }

    public function testStrokeStyleDashArrayItemsAcceptReferences(): void
    {
        $doc = (new Parser())->parseArray([
            'sizes' => ['$type' => 'dimension', 'sm' => ['$value' => ['value' => 4, 'unit' => 'px']]],
            's' => [
                '$type' => 'strokeStyle',
                'custom' => [
                    '$value' => [
                        'dashArray' => [
                            '{sizes.sm}',
                            ['value' => 2, 'unit' => 'px'],
                        ],
                        'lineCap' => 'round',
                    ],
                ],
            ],
        ]);

        $custom = $this->leaf($doc, 's', 'custom');
        self::assertInstanceOf(StrokeStyleValue::class, $custom->value);
        self::assertNotNull($custom->value->dashArray);
        self::assertInstanceOf(ReferenceValue::class, $custom->value->dashArray[0]);
        self::assertInstanceOf(DimensionValue::class, $custom->value->dashArray[1]);
    }

    private function leaf(Document $doc, string ...$path): ValueToken
    {
        $node = $doc->root;
        foreach ($path as $segment) {
            self::assertInstanceOf(Group::class, $node);
            $node = $node->child($segment);
            self::assertNotNull($node, "missing segment '$segment'");
        }
        self::assertInstanceOf(ValueToken::class, $node);

        return $node;
    }
}
