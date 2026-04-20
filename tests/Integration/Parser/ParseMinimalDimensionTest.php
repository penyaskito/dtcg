<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Parser;

use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\SpecVersion;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\DimensionUnit;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\ValueToken;
use PHPUnit\Framework\TestCase;

final class ParseMinimalDimensionTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/valid/minimal-dimension.tokens.json';

    public function testParsesMinimalDimensionTokensFile(): void
    {
        $parser = new Parser();
        $document = $parser->parseFile(self::FIXTURE);

        self::assertSame(SpecVersion::V2025_10, $document->specVersion);
        self::assertSame(self::FIXTURE, $document->sourceUri);

        $root = $document->root;
        self::assertTrue($root->isRoot());
        self::assertNull($root->defaultType);
        self::assertSame('', $root->sourceMap->pointer);
        self::assertSame(self::FIXTURE, $root->sourceMap->uri);

        $spacing = $root->child('spacing');
        self::assertInstanceOf(Group::class, $spacing);
        self::assertSame('spacing', $spacing->name);
        self::assertSame('spacing', $spacing->path->toString());
        self::assertSame(Type::Dimension, $spacing->defaultType);
        self::assertSame('/spacing', $spacing->sourceMap->pointer);

        $base = $spacing->child('base');
        self::assertInstanceOf(ValueToken::class, $base);
        self::assertSame('base', $base->name);
        self::assertSame('spacing.base', $base->path->toString());
        self::assertSame('/spacing/base', $base->sourceMap->pointer);
        self::assertSame(self::FIXTURE, $base->sourceMap->uri);
        self::assertSame(Type::Dimension, $base->type());

        self::assertInstanceOf(DimensionValue::class, $base->value);
        self::assertSame(16.0, $base->value->value);
        self::assertSame(DimensionUnit::Px, $base->value->unit);
    }
}
