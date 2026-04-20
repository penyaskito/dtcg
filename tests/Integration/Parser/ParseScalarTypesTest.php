<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\CubicBezierValue;
use Penyaskito\Dtcg\Tom\Value\DurationUnit;
use Penyaskito\Dtcg\Tom\Value\DurationValue;
use Penyaskito\Dtcg\Tom\Value\FontFamilyValue;
use Penyaskito\Dtcg\Tom\Value\FontWeightKeyword;
use Penyaskito\Dtcg\Tom\Value\FontWeightValue;
use Penyaskito\Dtcg\Tom\Value\NumberValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final class ParseScalarTypesTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/valid/scalar-types.tokens.json';

    public function testParsesAllFiveScalarTypes(): void
    {
        $doc = (new Parser())->parseFile(self::FIXTURE);

        $lineHeight = $this->leafToken($doc, ['n', 'lineHeight']);
        self::assertSame(Type::Number, $lineHeight->type);
        self::assertInstanceOf(NumberValue::class, $lineHeight->value);
        self::assertSame(1.5, $lineHeight->value->value);

        $fast = $this->leafToken($doc, ['time', 'fast']);
        self::assertSame(Type::Duration, $fast->type);
        self::assertInstanceOf(DurationValue::class, $fast->value);
        self::assertSame(200.0, $fast->value->value);
        self::assertSame(DurationUnit::Ms, $fast->value->unit);

        $bold = $this->leafToken($doc, ['weights', 'bold']);
        self::assertSame(Type::FontWeight, $bold->type);
        self::assertInstanceOf(FontWeightValue::class, $bold->value);
        self::assertTrue($bold->value->isKeyword());
        self::assertSame(FontWeightKeyword::Bold, $bold->value->weight);

        $custom = $this->leafToken($doc, ['weights', 'custom']);
        self::assertInstanceOf(FontWeightValue::class, $custom->value);
        self::assertFalse($custom->value->isKeyword());
        self::assertSame(650.0, $custom->value->weight);

        $inOut = $this->leafToken($doc, ['easings', 'inOut']);
        self::assertSame(Type::CubicBezier, $inOut->type);
        self::assertInstanceOf(CubicBezierValue::class, $inOut->value);
        self::assertSame([0.42, 0.0, 0.58, 1.0], $inOut->value->toTuple());

        $primary = $this->leafToken($doc, ['fonts', 'primary']);
        self::assertInstanceOf(FontFamilyValue::class, $primary->value);
        self::assertSame(['Inter'], $primary->value->families);

        $fallbacks = $this->leafToken($doc, ['fonts', 'fallbacks']);
        self::assertInstanceOf(FontFamilyValue::class, $fallbacks->value);
        self::assertSame(['Inter', 'system-ui', 'sans-serif'], $fallbacks->value->families);
        self::assertSame('Inter', $fallbacks->value->primary());
    }

    /** @param list<string> $path */
    private function leafToken(\Penyaskito\Dtcg\Tom\Document $doc, array $path): ValueToken
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
