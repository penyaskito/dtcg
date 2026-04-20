<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\CubicBezierValue;
use Penyaskito\Dtcg\Tom\Value\DimensionUnit;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\DurationUnit;
use Penyaskito\Dtcg\Tom\Value\DurationValue;
use Penyaskito\Dtcg\Tom\Value\FontFamilyValue;
use Penyaskito\Dtcg\Tom\Value\FontWeightKeyword;
use Penyaskito\Dtcg\Tom\Value\FontWeightValue;
use Penyaskito\Dtcg\Tom\Value\GradientValue;
use Penyaskito\Dtcg\Tom\Value\NumberValue;
use Penyaskito\Dtcg\Tom\Value\ShadowValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleKeyword;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;
use Penyaskito\Dtcg\Tom\Value\TransitionValue;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;
use Penyaskito\Dtcg\Tom\ValueToken;
use PHPUnit\Framework\TestCase;

final class ParseCompositesTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/valid/composites.tokens.json';

    public function testParsesBorder(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $focus = $this->leaf($doc, ['border', 'focus']);

        self::assertSame(Type::Border, $focus->type);
        self::assertInstanceOf(BorderValue::class, $focus->value);
        self::assertInstanceOf(ColorValue::class, $focus->value->color);
        self::assertSame([0.0, 0.0, 1.0], $focus->value->color->components);
        self::assertInstanceOf(DimensionValue::class, $focus->value->width);
        self::assertSame(2.0, $focus->value->width->value);
        self::assertSame(DimensionUnit::Px, $focus->value->width->unit);
        self::assertInstanceOf(StrokeStyleValue::class, $focus->value->style);
        self::assertSame(StrokeStyleKeyword::Solid, $focus->value->style->keyword);
    }

    public function testParsesTransition(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $fadeIn = $this->leaf($doc, ['transition', 'fadeIn']);

        self::assertSame(Type::Transition, $fadeIn->type);
        self::assertInstanceOf(TransitionValue::class, $fadeIn->value);
        self::assertInstanceOf(DurationValue::class, $fadeIn->value->duration);
        self::assertSame(200.0, $fadeIn->value->duration->value);
        self::assertSame(DurationUnit::Ms, $fadeIn->value->duration->unit);
        self::assertInstanceOf(DurationValue::class, $fadeIn->value->delay);
        self::assertSame(0.0, $fadeIn->value->delay->value);
        self::assertInstanceOf(CubicBezierValue::class, $fadeIn->value->timingFunction);
        self::assertSame([0.42, 0.0, 0.58, 1.0], $fadeIn->value->timingFunction->toTuple());
    }

    public function testParsesSingleShadowObject(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $card = $this->leaf($doc, ['shadow', 'card']);

        self::assertInstanceOf(ShadowValue::class, $card->value);
        self::assertTrue($card->value->isSingleLayer());
        self::assertCount(1, $card->value->layers);
        $layer = $card->value->layers[0];
        self::assertInstanceOf(ColorValue::class, $layer->color);
        self::assertSame(0.2, $layer->color->alpha);
        self::assertInstanceOf(DimensionValue::class, $layer->offsetY);
        self::assertSame(4.0, $layer->offsetY->value);
        self::assertFalse($layer->inset);
    }

    public function testParsesShadowArrayAndInsetFlag(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $stack = $this->leaf($doc, ['shadow', 'stack']);

        self::assertInstanceOf(ShadowValue::class, $stack->value);
        self::assertFalse($stack->value->isSingleLayer());
        self::assertCount(2, $stack->value->layers);
        self::assertFalse($stack->value->layers[0]->inset);
        self::assertTrue($stack->value->layers[1]->inset);
    }

    public function testParsesGradient(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $rainbow = $this->leaf($doc, ['gradient', 'rainbow']);

        self::assertInstanceOf(GradientValue::class, $rainbow->value);
        self::assertCount(3, $rainbow->value->stops);
        self::assertSame(0.0, $rainbow->value->stops[0]->position);
        self::assertSame(0.5, $rainbow->value->stops[1]->position);
        self::assertSame(1.0, $rainbow->value->stops[2]->position);
        $firstColor = $rainbow->value->stops[0]->color;
        self::assertInstanceOf(ColorValue::class, $firstColor);
        self::assertSame([1.0, 0.0, 0.0], $firstColor->components);
    }

    public function testParsesTypography(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);
        $heading = $this->leaf($doc, ['typography', 'heading']);

        self::assertInstanceOf(TypographyValue::class, $heading->value);
        self::assertInstanceOf(FontFamilyValue::class, $heading->value->fontFamily);
        self::assertSame(['Inter', 'sans-serif'], $heading->value->fontFamily->families);
        self::assertInstanceOf(DimensionValue::class, $heading->value->fontSize);
        self::assertSame(24.0, $heading->value->fontSize->value);
        self::assertInstanceOf(FontWeightValue::class, $heading->value->fontWeight);
        self::assertSame(FontWeightKeyword::Bold, $heading->value->fontWeight->weight);
        self::assertInstanceOf(DimensionValue::class, $heading->value->letterSpacing);
        self::assertSame(-0.5, $heading->value->letterSpacing->value);
        self::assertInstanceOf(NumberValue::class, $heading->value->lineHeight);
        self::assertSame(1.2, $heading->value->lineHeight->value);
    }

    /** @param list<string> $path */
    private function leaf(Document $doc, array $path): ValueToken
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
