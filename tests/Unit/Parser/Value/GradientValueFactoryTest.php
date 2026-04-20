<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\GradientValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\GradientValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GradientValueFactory::class)]
final class GradientValueFactoryTest extends TestCase
{
    private const RED = ['colorSpace' => 'srgb', 'components' => [1, 0, 0]];

    private const BLUE = ['colorSpace' => 'srgb', 'components' => [0, 0, 1]];

    public function testHappyPath(): void
    {
        $value = (new GradientValueFactory())->create(
            [
                ['color' => self::RED, 'position' => 0],
                ['color' => self::BLUE, 'position' => 1],
            ],
            '/g',
        );

        self::assertInstanceOf(GradientValue::class, $value);
        self::assertCount(2, $value->stops);
        self::assertSame(Type::Gradient, $value->type());
    }

    public function testRejectsNonListInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient $value must be a non-empty list of stops (at /g)');
        (new GradientValueFactory())->create(['color' => self::RED, 'position' => 0], '/g');
    }

    public function testRejectsEmptyList(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient $value must be a non-empty list of stops (at /g)');
        (new GradientValueFactory())->create([], '/g');
    }

    public function testRejectsNonObjectStop(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient stop must be an object (at /g/0)');
        (new GradientValueFactory())->create(['red'], '/g');
    }

    public function testRejectsUnknownStopProperty(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown gradient stop property 'label' (at /g/0)");
        (new GradientValueFactory())->create(
            [['color' => self::RED, 'position' => 0, 'label' => 'start']],
            '/g',
        );
    }

    public function testRejectsMissingColor(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient stop.color is required (at /g/0)');
        (new GradientValueFactory())->create([['position' => 0]], '/g');
    }

    public function testRejectsMissingPosition(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient stop.position is required (at /g/0)');
        (new GradientValueFactory())->create([['color' => self::RED]], '/g');
    }

    public function testRejectsNonNumericPosition(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient stop.position must be a number (at /g/0)');
        (new GradientValueFactory())->create(
            [['color' => self::RED, 'position' => 'start']],
            '/g',
        );
    }

    public function testRejectsPositionOutOfRangeHigh(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient stop.position must be between 0 and 1 (at /g/0)');
        (new GradientValueFactory())->create(
            [['color' => self::RED, 'position' => 1.5]],
            '/g',
        );
    }

    public function testRejectsPositionOutOfRangeLow(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient stop.position must be between 0 and 1 (at /g/0)');
        (new GradientValueFactory())->create(
            [['color' => self::RED, 'position' => -0.1]],
            '/g',
        );
    }

    public function testPropagatesColorSubFactoryError(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown color colorSpace 'cmyk' (at /g/0/color)");
        (new GradientValueFactory())->create(
            [['color' => ['colorSpace' => 'cmyk', 'components' => [0, 0, 0, 1]], 'position' => 0]],
            '/g',
        );
    }

    public function testValidatesStopsBeyondTheFirst(): void
    {
        // Checks the iterator walks past stop 0 — a bug where only the first
        // stop got validated would be invisible without this.
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('gradient stop.color is required (at /g/1)');
        (new GradientValueFactory())->create(
            [
                ['color' => self::RED, 'position' => 0],
                ['position' => 0.5],
                ['color' => self::BLUE, 'position' => 1],
            ],
            '/g',
        );
    }
}
