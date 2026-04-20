<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\DimensionValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\DimensionUnit;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;

#[CoversClass(DimensionValueFactory::class)]
final class DimensionValueFactoryTest extends TestCase
{
    public function testCreatesPixelValue(): void
    {
        $value = (new DimensionValueFactory())->create(
            ['value' => 16, 'unit' => 'px'],
            '/t',
        );

        self::assertInstanceOf(DimensionValue::class, $value);
        self::assertSame(16.0, $value->value);
        self::assertSame(DimensionUnit::Px, $value->unit);
        self::assertSame(Type::Dimension, $value->type());
    }

    public function testCreatesRemValue(): void
    {
        $value = (new DimensionValueFactory())->create(
            ['value' => 1.25, 'unit' => 'rem'],
            '/t',
        );

        self::assertInstanceOf(DimensionValue::class, $value);
        self::assertSame(1.25, $value->value);
        self::assertSame(DimensionUnit::Rem, $value->unit);
    }

    /** @return iterable<string, array{mixed}> */
    public static function nonObjectRawValues(): iterable
    {
        yield 'string' => ['16px'];
        yield 'int' => [16];
        yield 'null' => [null];
        yield 'list' => [[16, 'px']];
    }

    #[DataProvider('nonObjectRawValues')]
    public function testRejectsNonObjectRawValue(mixed $raw): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('dimension $value must be an object (at /t)');

        (new DimensionValueFactory())->create($raw, '/t');
    }

    public function testRejectsMissingValueKey(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('dimension $value.value must be a number (at /t)');

        (new DimensionValueFactory())->create(['unit' => 'px'], '/t');
    }

    public function testRejectsNonNumericValue(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('dimension $value.value must be a number (at /t)');

        (new DimensionValueFactory())->create(['value' => 'sixteen', 'unit' => 'px'], '/t');
    }

    public function testRejectsMissingUnit(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('dimension $value.unit must be a string (at /t)');

        (new DimensionValueFactory())->create(['value' => 16], '/t');
    }

    public function testRejectsNonStringUnit(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('dimension $value.unit must be a string (at /t)');

        (new DimensionValueFactory())->create(['value' => 16, 'unit' => 100], '/t');
    }

    public function testRejectsInvalidUnitKeyword(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("invalid dimension unit 'em' (expected 'px' or 'rem') (at /t)");

        (new DimensionValueFactory())->create(['value' => 16, 'unit' => 'em'], '/t');
    }
}
