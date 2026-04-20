<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\ColorSpace;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\DimensionUnit;
use Penyaskito\Dtcg\Tom\Value\LineCap;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleKeyword;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final class ParseColorAndStrokeTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/valid/color-and-stroke.tokens.json';

    public function testParsesColorTokens(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);

        $blue = $this->leafToken($doc, ['colors', 'blue']);
        self::assertSame(Type::Color, $blue->type);
        self::assertInstanceOf(ColorValue::class, $blue->value);
        self::assertSame(ColorSpace::Srgb, $blue->value->colorSpace);
        self::assertSame([0.0, 0.0, 1.0], $blue->value->components);
        self::assertNull($blue->value->alpha);
        self::assertNull($blue->value->hex);

        $transparent = $this->leafToken($doc, ['colors', 'transparentRed']);
        self::assertInstanceOf(ColorValue::class, $transparent->value);
        self::assertSame(0.5, $transparent->value->alpha);
        self::assertSame('#ff0000', $transparent->value->hex);

        $none = $this->leafToken($doc, ['colors', 'withNone']);
        self::assertInstanceOf(ColorValue::class, $none->value);
        self::assertSame([0.5, null, 0.0], $none->value->components);
        self::assertSame(ColorSpace::Oklab, $none->value->colorSpace);
    }

    public function testParsesStrokeStyleKeywordAndComposite(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);

        $simple = $this->leafToken($doc, ['strokes', 'simple']);
        self::assertSame(Type::StrokeStyle, $simple->type);
        self::assertInstanceOf(StrokeStyleValue::class, $simple->value);
        self::assertTrue($simple->value->isKeyword());
        self::assertSame(StrokeStyleKeyword::Dashed, $simple->value->keyword);

        $custom = $this->leafToken($doc, ['strokes', 'custom']);
        self::assertInstanceOf(StrokeStyleValue::class, $custom->value);
        self::assertFalse($custom->value->isKeyword());
        self::assertNotNull($custom->value->dashArray);
        self::assertCount(2, $custom->value->dashArray);
        $first = $custom->value->dashArray[0];
        self::assertInstanceOf(\Penyaskito\Dtcg\Tom\Value\DimensionValue::class, $first);
        self::assertSame(4.0, $first->value);
        self::assertSame(DimensionUnit::Px, $first->unit);
        self::assertSame(LineCap::Round, $custom->value->lineCap);
    }

    /** @param list<string> $path */
    private function leafToken(Document $doc, array $path): ValueToken
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
