<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\StrokeStyleValueFactory;
use Penyaskito\Dtcg\Tom\Value\LineCap;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleKeyword;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;

#[CoversClass(StrokeStyleValueFactory::class)]
final class StrokeStyleValueFactoryTest extends TestCase
{
    public function testParsesKeyword(): void
    {
        $value = (new StrokeStyleValueFactory())->create('solid', '/s');

        self::assertInstanceOf(StrokeStyleValue::class, $value);
        self::assertTrue($value->isKeyword());
        self::assertSame(StrokeStyleKeyword::Solid, $value->keyword);
        self::assertSame(\Penyaskito\Dtcg\Tom\Type::StrokeStyle, $value->type());
    }

    public function testParsesCompositeForm(): void
    {
        $value = (new StrokeStyleValueFactory())->create(
            [
                'dashArray' => [
                    ['value' => 4, 'unit' => 'px'],
                    ['value' => 2, 'unit' => 'px'],
                ],
                'lineCap' => 'butt',
            ],
            '/s',
        );

        self::assertInstanceOf(StrokeStyleValue::class, $value);
        self::assertFalse($value->isKeyword());
        self::assertNotNull($value->dashArray);
        self::assertCount(2, $value->dashArray);
        self::assertSame(LineCap::Butt, $value->lineCap);
    }

    public function testRejectsUnknownKeyword(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown strokeStyle keyword 'wiggly' (at /s)");
        (new StrokeStyleValueFactory())->create('wiggly', '/s');
    }

    public function testRejectsCompositeMissingDashArray(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('strokeStyle $value.dashArray must be a non-empty list of dimension objects (at /s)');
        (new StrokeStyleValueFactory())->create(['lineCap' => 'round'], '/s');
    }

    public function testRejectsCompositeMissingLineCap(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('strokeStyle $value.lineCap must be a string (at /s)');
        (new StrokeStyleValueFactory())->create(
            ['dashArray' => [['value' => 1, 'unit' => 'px']]],
            '/s',
        );
    }

    public function testRejectsUnknownLineCap(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown strokeStyle lineCap 'flat' (at /s)");
        (new StrokeStyleValueFactory())->create(
            [
                'dashArray' => [['value' => 1, 'unit' => 'px']],
                'lineCap' => 'flat',
            ],
            '/s',
        );
    }

    public function testRejectsEmptyDashArray(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('strokeStyle $value.dashArray must be a non-empty list of dimension objects (at /s)');
        (new StrokeStyleValueFactory())->create(
            ['dashArray' => [], 'lineCap' => 'round'],
            '/s',
        );
    }

    public function testRejectsUnknownProperty(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown strokeStyle property 'extra' (at /s)");
        (new StrokeStyleValueFactory())->create(
            [
                'dashArray' => [['value' => 1, 'unit' => 'px']],
                'lineCap' => 'round',
                'extra' => 42,
            ],
            '/s',
        );
    }

    public function testPropagatesDimensionErrorsFromDashArrayItems(): void
    {
        // Nested pointer includes the failing dashArray index.
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /s/dashArray/0)');
        (new StrokeStyleValueFactory())->create(
            [
                'dashArray' => [['value' => 1, 'unit' => 'em']],
                'lineCap' => 'round',
            ],
            '/s',
        );
    }

    public function testRejectsListAsInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('strokeStyle $value must be a recognised keyword string or a {dashArray, lineCap} object (at /s)');
        (new StrokeStyleValueFactory())->create([1, 2, 3], '/s');
    }
}
