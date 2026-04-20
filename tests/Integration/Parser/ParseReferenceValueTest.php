<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final class ParseReferenceValueTest extends TestCase
{
    public function testCurlyBraceAliasAtValueRootBecomesValueTokenWithReferenceValue(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias' => ['$value' => '{spacing.base}'],
            ],
        ]);

        $spacing = $doc->root->child('spacing');
        self::assertInstanceOf(\Penyaskito\Dtcg\Tom\Group::class, $spacing);
        $alias = $spacing->child('alias');
        self::assertInstanceOf(ValueToken::class, $alias);
        self::assertSame(Type::Dimension, $alias->type);
        self::assertInstanceOf(ReferenceValue::class, $alias->value);
        self::assertInstanceOf(CurlyBraceReference::class, $alias->value->reference);
        self::assertSame('{spacing.base}', $alias->value->reference->original());
    }

    public function testReferenceValueTypeMethodReturnsNull(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 4, 'unit' => 'px']],
                'alias' => ['$value' => '{spacing.base}'],
            ],
        ]);
        $spacing = $doc->root->child('spacing');
        self::assertInstanceOf(\Penyaskito\Dtcg\Tom\Group::class, $spacing);
        $alias = $spacing->child('alias');
        self::assertInstanceOf(ValueToken::class, $alias);
        self::assertInstanceOf(ReferenceValue::class, $alias->value);

        // ReferenceValue's own type is unresolved (null). The ValueToken carries
        // the effective type (from $type or inheritance).
        self::assertNull($alias->value->type());
        self::assertSame(Type::Dimension, $alias->type);
    }

    public function testRejectsReferenceValueWithoutResolvableType(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('token with reference $value has no $type and no ancestor group provides one (strict mode) (at /alias)');

        (new Parser())->parseArray([
            'alias' => ['$value' => '{something}'],
        ]);
    }

    public function testRejectsMalformedCurlyBraceAlias(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessageMatches("/invalid curly-brace alias in \\\$value: .* \\(at \\/alias\\)/");

        (new Parser())->parseArray([
            'alias' => ['$type' => 'dimension', '$value' => '{bad..path}'],
        ]);
    }

    public function testResolverChainsThroughReferenceValueToFinalTarget(): void
    {
        $parser = new Parser();
        $doc = $parser->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias1' => ['$value' => '{spacing.alias2}'],
                'alias2' => ['$value' => '{spacing.base}'],
            ],
        ]);

        $resolver = new \Penyaskito\Dtcg\Reference\Resolver($doc);
        $target = $resolver->resolveChain(
            CurlyBraceReference::parse('{spacing.alias1}'),
        );

        self::assertInstanceOf(ValueToken::class, $target);
        self::assertSame('spacing.base', $target->path->toString());
        self::assertInstanceOf(DimensionValue::class, $target->value);
    }
}
