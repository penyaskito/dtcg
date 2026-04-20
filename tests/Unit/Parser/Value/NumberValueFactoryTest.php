<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\NumberValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\NumberValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumberValueFactory::class)]
final class NumberValueFactoryTest extends TestCase
{
    public function testHappyPath(): void
    {
        $value = (new NumberValueFactory())->create(1.5, '/n');

        self::assertInstanceOf(NumberValue::class, $value);
        self::assertSame(1.5, $value->value);
        self::assertSame(Type::Number, $value->type());
    }

    public function testAcceptsInteger(): void
    {
        $value = (new NumberValueFactory())->create(42, '/n');

        self::assertInstanceOf(NumberValue::class, $value);
        self::assertSame(42.0, $value->value);
    }

    public function testAcceptsNegativeNumber(): void
    {
        $value = (new NumberValueFactory())->create(-1.25, '/n');

        self::assertInstanceOf(NumberValue::class, $value);
        self::assertSame(-1.25, $value->value);
    }

    public function testRejectsString(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('number $value must be a number (at /n)');
        (new NumberValueFactory())->create('1.5', '/n');
    }

    public function testRejectsBool(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('number $value must be a number (at /n)');
        (new NumberValueFactory())->create(true, '/n');
    }

    public function testRejectsNull(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('number $value must be a number (at /n)');
        (new NumberValueFactory())->create(null, '/n');
    }

    public function testRejectsArray(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('number $value must be a number (at /n)');
        (new NumberValueFactory())->create([1.5], '/n');
    }
}
