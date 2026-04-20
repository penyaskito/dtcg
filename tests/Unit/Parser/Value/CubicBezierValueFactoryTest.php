<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\CubicBezierValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\CubicBezierValue;

#[CoversClass(CubicBezierValueFactory::class)]
final class CubicBezierValueFactoryTest extends TestCase
{
    public function testHappyPath(): void
    {
        $value = (new CubicBezierValueFactory())->create([0.42, 0, 0.58, 1], '/cb');

        self::assertInstanceOf(CubicBezierValue::class, $value);
        self::assertSame([0.42, 0.0, 0.58, 1.0], $value->toTuple());
        self::assertSame(Type::CubicBezier, $value->type());
    }

    public function testYCoordinateMayBeOutOfZeroOneRange(): void
    {
        $value = (new CubicBezierValueFactory())->create([0.5, -1.0, 0.5, 2.0], '/cb');

        self::assertInstanceOf(CubicBezierValue::class, $value);
        self::assertSame([0.5, -1.0, 0.5, 2.0], $value->toTuple());
    }

    public function testRequiresFourItems(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cubicBezier $value must be a list of exactly 4 numbers (at /cb)');
        (new CubicBezierValueFactory())->create([0.5, 0, 0.5], '/cb');
    }

    public function testRejectsNonArrayInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cubicBezier $value must be a list of exactly 4 numbers (at /cb)');
        (new CubicBezierValueFactory())->create('0,0,1,1', '/cb');
    }

    public function testRejectsAssociativeArray(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cubicBezier $value must be a list of exactly 4 numbers (at /cb)');
        (new CubicBezierValueFactory())->create(['a' => 0.5, 'b' => 0, 'c' => 0.5, 'd' => 1], '/cb');
    }

    public function testRejectsNonNumericElement(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cubicBezier $value[1] must be a number (at /cb)');
        (new CubicBezierValueFactory())->create([0.5, 'oops', 0.5, 1], '/cb');
    }

    public function testRejectsFirstXBelowZero(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cubicBezier $value[0] (x coordinate) must be between 0 and 1 (at /cb)');
        (new CubicBezierValueFactory())->create([-0.1, 0, 0.5, 1], '/cb');
    }

    public function testRejectsFirstXAboveOne(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cubicBezier $value[0] (x coordinate) must be between 0 and 1 (at /cb)');
        (new CubicBezierValueFactory())->create([1.5, 0, 0.5, 1], '/cb');
    }

    public function testRejectsSecondXOutOfRange(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cubicBezier $value[2] (x coordinate) must be between 0 and 1 (at /cb)');
        (new CubicBezierValueFactory())->create([0.5, 0, 1.5, 1], '/cb');
    }
}
