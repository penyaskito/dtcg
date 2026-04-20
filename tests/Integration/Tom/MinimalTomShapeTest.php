<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Tom;

use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\SpecVersion;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Metadata;
use Penyaskito\Dtcg\Tom\Path;
use Penyaskito\Dtcg\Tom\SourceMap;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\DimensionUnit;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final class MinimalTomShapeTest extends TestCase
{
    public function testBuildsMinimalDimensionTokenTree(): void
    {
        $value = new DimensionValue(16.0, DimensionUnit::Px);

        $token = new ValueToken(
            name: 'base',
            path: Path::fromDots('spacing.base'),
            type: Type::Dimension,
            value: $value,
            metadata: Metadata::empty(),
            sourceMap: SourceMap::synthetic('/spacing/base'),
        );

        $spacing = new Group(
            name: 'spacing',
            path: Path::fromDots('spacing'),
            defaultType: Type::Dimension,
            metadata: Metadata::empty(),
            sourceMap: SourceMap::synthetic('/spacing'),
            children: ['base' => $token],
        );

        $root = new Group(
            name: '',
            path: Path::root(),
            defaultType: null,
            metadata: Metadata::empty(),
            sourceMap: SourceMap::synthetic(''),
            children: ['spacing' => $spacing],
        );

        $document = new Document(
            specVersion: SpecVersion::V2025_10,
            root: $root,
        );

        self::assertSame('2025.10', $document->specVersion->value);
        self::assertTrue($document->root->isRoot());
        self::assertSame($spacing, $document->root->child('spacing'));

        $base = $spacing->child('base');
        self::assertInstanceOf(ValueToken::class, $base);
        self::assertSame('base', $base->name);
        self::assertSame('spacing.base', $base->path->toString());
        self::assertSame(Type::Dimension, $base->type());

        self::assertInstanceOf(DimensionValue::class, $base->value);
        self::assertSame(16.0, $base->value->value);
        self::assertSame(DimensionUnit::Px, $base->value->unit);
        self::assertSame(Type::Dimension, $base->value->type());
    }

    public function testSpecVersionResolvesToVendoredSchemaDir(): void
    {
        $dir = SpecVersion::V2025_10->schemaDir();

        self::assertDirectoryExists($dir);
        self::assertFileExists($dir . '/format.json');
        self::assertFileExists($dir . '/format/token.json');
        self::assertFileExists($dir . '/format/values/dimension.json');
    }
}
