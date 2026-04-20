<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Reference;

use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\MaterializationException;
use Penyaskito\Dtcg\Reference\Materializer;
use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\GradientValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;
use Penyaskito\Dtcg\Tom\ValueToken;
use PHPUnit\Framework\TestCase;

final class MaterializerTest extends TestCase
{
    public function testMaterializesBorderColorReference(): void
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

        $materialized = $this->materializer($doc)->materialize($doc);
        $focus = $this->leaf($materialized, 'b', 'focus');

        self::assertInstanceOf(BorderValue::class, $focus->value);
        self::assertInstanceOf(ColorValue::class, $focus->value->color);
        self::assertSame([0.0, 0.0, 1.0], $focus->value->color->components);
    }

    public function testMaterializesGradientPositionNumberReference(): void
    {
        $doc = (new Parser())->parseArray([
            'positions' => ['$type' => 'number', 'middle' => ['$value' => 0.5]],
            'colors' => [
                '$type' => 'color',
                'red' => ['$value' => ['colorSpace' => 'srgb', 'components' => [1, 0, 0]]],
                'blue' => ['$value' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1]]],
            ],
            'g' => [
                '$type' => 'gradient',
                'rb' => [
                    '$value' => [
                        ['color' => '{colors.red}', 'position' => 0],
                        ['color' => '{colors.blue}', 'position' => ['$ref' => '#/positions/middle']],
                    ],
                ],
            ],
        ]);

        $materialized = $this->materializer($doc)->materialize($doc);
        $rb = $this->leaf($materialized, 'g', 'rb');

        self::assertInstanceOf(GradientValue::class, $rb->value);
        self::assertInstanceOf(ColorValue::class, $rb->value->stops[0]->color);
        self::assertIsFloat($rb->value->stops[1]->position);
        self::assertSame(0.5, $rb->value->stops[1]->position);
    }

    public function testMaterializesReferenceTokenIntoValueTokenWithResolvedValue(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'color',
                'primary' => ['$value' => ['colorSpace' => 'srgb', 'components' => [0, 0, 1]]],
            ],
            'theme' => ['$ref' => '#/colors/primary'],
        ]);

        $materialized = $this->materializer($doc)->materialize($doc);
        $theme = $materialized->root->child('theme');

        self::assertInstanceOf(ValueToken::class, $theme);
        self::assertSame('color', $theme->type->value);
        self::assertInstanceOf(ColorValue::class, $theme->value);
    }

    public function testThrowsOnUnresolvableReference(): void
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

        $this->expectException(MaterializationException::class);
        $this->materializer($doc)->materialize($doc);
    }

    public function testThrowsOnTypeMismatch(): void
    {
        $doc = (new Parser())->parseArray([
            'sizes' => ['$type' => 'dimension', 'big' => ['$value' => ['value' => 16, 'unit' => 'px']]],
            'b' => [
                '$type' => 'border',
                'wrong' => [
                    '$value' => [
                        // border.color resolves to a dimension — type mismatch.
                        'color' => '{sizes.big}',
                        'width' => ['value' => 1, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        $this->expectException(MaterializationException::class);
        $this->expectExceptionMessage('expected a value of type');
        $this->materializer($doc)->materialize($doc);
    }

    public function testThrowsOnShadowInsetReference(): void
    {
        // shadow.inset references use property-level JSON Pointers, not yet
        // supported.
        $doc = (new Parser())->parseArray([
            'flags' => [
                '$type' => 'number',
                'inner' => ['$value' => 1],
            ],
            's' => [
                '$type' => 'shadow',
                'card' => [
                    '$value' => [
                        'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0]],
                        'offsetX' => ['value' => 0, 'unit' => 'px'],
                        'offsetY' => ['value' => 4, 'unit' => 'px'],
                        'blur' => ['value' => 8, 'unit' => 'px'],
                        'spread' => ['value' => 0, 'unit' => 'px'],
                        'inset' => ['$ref' => '#/flags/inner'],
                    ],
                ],
            ],
        ]);

        $this->expectException(MaterializationException::class);
        $this->expectExceptionMessage('shadow.inset references');
        $this->materializer($doc)->materialize($doc);
    }

    public function testTypographyReferencesAreMaterialized(): void
    {
        $doc = (new Parser())->parseArray([
            'fams' => ['$type' => 'fontFamily', 'main' => ['$value' => ['Inter', 'sans-serif']]],
            'type' => [
                '$type' => 'typography',
                'heading' => [
                    '$value' => [
                        'fontFamily' => '{fams.main}',
                        'fontSize' => ['value' => 24, 'unit' => 'px'],
                        'fontWeight' => 'bold',
                        'letterSpacing' => ['value' => 0, 'unit' => 'px'],
                        'lineHeight' => 1.2,
                    ],
                ],
            ],
        ]);

        $materialized = $this->materializer($doc)->materialize($doc);
        $heading = $this->leaf($materialized, 'type', 'heading');

        self::assertInstanceOf(TypographyValue::class, $heading->value);
        // After materialization, fontFamily is concrete.
        self::assertNotInstanceOf(ReferenceValue::class, $heading->value->fontFamily);
    }

    public function testStrokeStyleDashArrayReferencesAreMaterialized(): void
    {
        $doc = (new Parser())->parseArray([
            'sizes' => ['$type' => 'dimension', 'sm' => ['$value' => ['value' => 4, 'unit' => 'px']]],
            's' => [
                '$type' => 'strokeStyle',
                'custom' => [
                    '$value' => [
                        'dashArray' => ['{sizes.sm}', ['value' => 2, 'unit' => 'px']],
                        'lineCap' => 'round',
                    ],
                ],
            ],
        ]);

        $materialized = $this->materializer($doc)->materialize($doc);
        $custom = $this->leaf($materialized, 's', 'custom');

        self::assertInstanceOf(StrokeStyleValue::class, $custom->value);
        self::assertNotNull($custom->value->dashArray);
        self::assertInstanceOf(DimensionValue::class, $custom->value->dashArray[0]);
        self::assertSame(4.0, $custom->value->dashArray[0]->value);
    }

    private function materializer(Document $doc): Materializer
    {
        return new Materializer(new Resolver($doc));
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
