<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\DurationValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\DurationUnit;
use Penyaskito\Dtcg\Tom\Value\DurationValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DurationValueFactory::class)]
final class DurationValueFactoryTest extends TestCase
{
    public function testCreatesMillisecondValue(): void
    {
        $value = (new DurationValueFactory())->create(
            ['value' => 200, 'unit' => 'ms'],
            '/t',
        );

        self::assertInstanceOf(DurationValue::class, $value);
        self::assertSame(200.0, $value->value);
        self::assertSame(DurationUnit::Ms, $value->unit);
        self::assertSame(Type::Duration, $value->type());
    }

    public function testCreatesSecondValue(): void
    {
        $value = (new DurationValueFactory())->create(
            ['value' => 0.3, 'unit' => 's'],
            '/t',
        );

        self::assertInstanceOf(DurationValue::class, $value);
        self::assertSame(0.3, $value->value);
        self::assertSame(DurationUnit::S, $value->unit);
    }

    public function testRejectsNonObject(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('duration $value must be an object (at /t)');
        (new DurationValueFactory())->create('200ms', '/t');
    }

    public function testRejectsListInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('duration $value must be an object (at /t)');
        (new DurationValueFactory())->create([200, 'ms'], '/t');
    }

    public function testRejectsMissingValue(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('duration $value.value must be a number (at /t)');
        (new DurationValueFactory())->create(['unit' => 'ms'], '/t');
    }

    public function testRejectsNonNumericValue(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('duration $value.value must be a number (at /t)');
        (new DurationValueFactory())->create(['value' => 'fast', 'unit' => 'ms'], '/t');
    }

    public function testRejectsMissingUnit(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('duration $value.unit must be a string (at /t)');
        (new DurationValueFactory())->create(['value' => 200], '/t');
    }

    public function testRejectsNonStringUnit(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('duration $value.unit must be a string (at /t)');
        (new DurationValueFactory())->create(['value' => 200, 'unit' => 1], '/t');
    }

    public function testRejectsUnknownUnit(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("invalid duration unit 'm' (expected 'ms' or 's') (at /t)");
        (new DurationValueFactory())->create(['value' => 1, 'unit' => 'm'], '/t');
    }
}
