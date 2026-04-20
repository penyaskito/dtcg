<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\ValueToken;

final class ParseReferenceTokenTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures';

    public function testParsesJsonPointerRefIntoReferenceToken(): void
    {
        $parser = new Parser();
        $document = $parser->parseFile(self::FIXTURES . '/valid/dimension-with-ref.tokens.json');

        $spacing = $document->root->child('spacing');
        self::assertInstanceOf(Group::class, $spacing);

        $base = $spacing->child('base');
        self::assertInstanceOf(ValueToken::class, $base);

        $alias = $spacing->child('alias');
        self::assertInstanceOf(ReferenceToken::class, $alias);
        self::assertSame(Type::Dimension, $alias->declaredType);
        self::assertSame(Type::Dimension, $alias->type());
        self::assertSame('spacing.alias', $alias->path->toString());
        self::assertSame('/spacing/alias', $alias->sourceMap->pointer);

        self::assertInstanceOf(JsonPointerReference::class, $alias->reference);
        self::assertSame('#/spacing/base', $alias->reference->original());
        self::assertSame(['spacing', 'base'], $alias->reference->segments());
        self::assertSame('/spacing/base', $alias->reference->toJsonPointer());
    }

    public function testRejectsTokensWithBothValueAndRef(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$value and $ref are mutually exclusive (at /alias)');

        (new Parser())->parseArray([
            'alias' => [
                '$type' => 'dimension',
                '$value' => ['value' => 1, 'unit' => 'px'],
                '$ref' => '#/something',
            ],
        ]);
    }

    public function testCurlyBraceAtValueRootProducesValueTokenWithReferenceValue(): void
    {
        $doc = (new Parser())->parseArray([
            'alias' => [
                '$type' => 'dimension',
                '$value' => '{spacing.base}',
            ],
        ]);

        $alias = $doc->root->child('alias');
        self::assertInstanceOf(\Penyaskito\Dtcg\Tom\ValueToken::class, $alias);
        self::assertInstanceOf(\Penyaskito\Dtcg\Tom\Value\ReferenceValue::class, $alias->value);
        self::assertSame('{spacing.base}', $alias->value->reference->original());
    }

    public function testRejectsNonStringRef(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$ref must be a string (at /alias)');

        (new Parser())->parseArray([
            'alias' => ['$ref' => 42],
        ]);
    }

    public function testRejectsMalformedRef(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessageMatches("/invalid \\\$ref: .* \\(at \\/alias\\)/");

        (new Parser())->parseArray([
            'alias' => ['$ref' => 'not-a-pointer'],
        ]);
    }

    public function testReferenceTokenWithoutTypeHasNullType(): void
    {
        $parser = new Parser();
        $document = $parser->parseArray([
            'alias' => ['$ref' => '#/somewhere/else'],
        ]);

        $alias = $document->root->child('alias');
        self::assertInstanceOf(ReferenceToken::class, $alias);
        self::assertNull($alias->type());
    }
}
