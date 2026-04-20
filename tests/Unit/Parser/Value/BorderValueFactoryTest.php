<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\BorderValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\BorderValue;

#[CoversClass(BorderValueFactory::class)]
final class BorderValueFactoryTest extends TestCase
{
    private const VALID_COLOR = ['colorSpace' => 'srgb', 'components' => [0, 0, 1]];

    private const VALID_WIDTH = ['value' => 2, 'unit' => 'px'];

    public function testHappyPath(): void
    {
        $value = (new BorderValueFactory())->create(
            [
                'color' => self::VALID_COLOR,
                'width' => self::VALID_WIDTH,
                'style' => 'solid',
            ],
            '/b',
        );

        self::assertInstanceOf(BorderValue::class, $value);
        self::assertSame(Type::Border, $value->type());
    }

    public function testRejectsNonObject(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('border $value must be an object (at /b)');
        (new BorderValueFactory())->create('not an object', '/b');
    }

    public function testRejectsListInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('border $value must be an object (at /b)');
        (new BorderValueFactory())->create([1, 2, 3], '/b');
    }

    public function testRejectsUnknownProperty(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown border property 'thickness' (at /b)");
        (new BorderValueFactory())->create(
            [
                'color' => self::VALID_COLOR,
                'width' => self::VALID_WIDTH,
                'style' => 'solid',
                'thickness' => 2,
            ],
            '/b',
        );
    }

    public function testRejectsMissingColor(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('border $value.color is required (at /b)');
        (new BorderValueFactory())->create(
            ['width' => self::VALID_WIDTH, 'style' => 'solid'],
            '/b',
        );
    }

    public function testRejectsMissingWidth(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('border $value.width is required (at /b)');
        (new BorderValueFactory())->create(
            ['color' => self::VALID_COLOR, 'style' => 'solid'],
            '/b',
        );
    }

    public function testRejectsMissingStyle(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('border $value.style is required (at /b)');
        (new BorderValueFactory())->create(
            ['color' => self::VALID_COLOR, 'width' => self::VALID_WIDTH],
            '/b',
        );
    }

    public function testPropagatesColorSubFactoryError(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown color colorSpace 'cmyk' (at /b/color)");
        (new BorderValueFactory())->create(
            [
                'color' => ['colorSpace' => 'cmyk', 'components' => [0, 0, 0, 1]],
                'width' => self::VALID_WIDTH,
                'style' => 'solid',
            ],
            '/b',
        );
    }

    public function testPropagatesWidthSubFactoryError(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /b/width)');
        (new BorderValueFactory())->create(
            [
                'color' => self::VALID_COLOR,
                'width' => ['value' => 1, 'unit' => 'em'],
                'style' => 'solid',
            ],
            '/b',
        );
    }

    public function testPropagatesStyleSubFactoryError(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown strokeStyle keyword 'wiggly' (at /b/style)");
        (new BorderValueFactory())->create(
            [
                'color' => self::VALID_COLOR,
                'width' => self::VALID_WIDTH,
                'style' => 'wiggly',
            ],
            '/b',
        );
    }
}
