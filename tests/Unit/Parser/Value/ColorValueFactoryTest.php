<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\ColorValueFactory;
use Penyaskito\Dtcg\Tom\Value\ColorSpace;
use Penyaskito\Dtcg\Tom\Value\ColorValue;

#[CoversClass(ColorValueFactory::class)]
final class ColorValueFactoryTest extends TestCase
{
    public function testCreatesBasicSrgbColor(): void
    {
        $color = (new ColorValueFactory())->create(
            ['colorSpace' => 'srgb', 'components' => [0.1, 0.2, 0.3]],
            '/c',
        );

        self::assertInstanceOf(ColorValue::class, $color);
        self::assertSame(ColorSpace::Srgb, $color->colorSpace);
        self::assertSame([0.1, 0.2, 0.3], $color->components);
        self::assertSame(\Penyaskito\Dtcg\Tom\Type::Color, $color->type());
    }

    public function testNoneKeywordBecomesNull(): void
    {
        $color = (new ColorValueFactory())->create(
            ['colorSpace' => 'oklab', 'components' => [0.5, 'none', 0]],
            '/c',
        );

        self::assertInstanceOf(ColorValue::class, $color);
        self::assertSame([0.5, null, 0.0], $color->components);
    }

    public function testRejectsNonObject(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('color $value must be an object (at /c)');
        (new ColorValueFactory())->create('srgb(1 0 0)', '/c');
    }

    public function testRejectsUnknownColorSpace(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown color colorSpace 'cmyk' (at /c)");
        (new ColorValueFactory())->create(
            ['colorSpace' => 'cmyk', 'components' => [0, 0, 0, 1]],
            '/c',
        );
    }

    public function testRejectsMissingColorSpace(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('color $value.colorSpace must be a string (at /c)');
        (new ColorValueFactory())->create(['components' => [1, 0, 0]], '/c');
    }

    public function testRejectsNonListComponents(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('color $value.components must be a list (at /c)');
        (new ColorValueFactory())->create(
            ['colorSpace' => 'srgb', 'components' => ['r' => 1, 'g' => 0, 'b' => 0]],
            '/c',
        );
    }

    public function testRejectsBadComponent(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("color \$value.components[1] must be a number or 'none' (at /c)");
        (new ColorValueFactory())->create(
            ['colorSpace' => 'srgb', 'components' => [1, 'pink', 0]],
            '/c',
        );
    }

    public function testRejectsAlphaOutOfRange(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('color $value.alpha must be between 0 and 1 (at /c)');
        (new ColorValueFactory())->create(
            ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'alpha' => 1.5],
            '/c',
        );
    }

    public function testRejectsBadHexPattern(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('color $value.hex must be a 6-digit hex string (e.g. "#ff00ff") (at /c)');
        (new ColorValueFactory())->create(
            ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'hex' => '#fff'],
            '/c',
        );
    }

    public function testRejectsUnknownProperty(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown color property 'fallback' (at /c)");
        (new ColorValueFactory())->create(
            ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'fallback' => 'red'],
            '/c',
        );
    }
}
