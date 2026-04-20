<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\ShadowValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\ShadowValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShadowValueFactory::class)]
final class ShadowValueFactoryTest extends TestCase
{
    private const COLOR = ['colorSpace' => 'srgb', 'components' => [0, 0, 0]];

    private const ZERO = ['value' => 0, 'unit' => 'px'];

    private const VALID_LAYER = [
        'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0]],
        'offsetX' => ['value' => 0, 'unit' => 'px'],
        'offsetY' => ['value' => 4, 'unit' => 'px'],
        'blur' => ['value' => 8, 'unit' => 'px'],
        'spread' => ['value' => 0, 'unit' => 'px'],
    ];

    public function testHappyPathSingleObject(): void
    {
        $value = (new ShadowValueFactory())->create(self::VALID_LAYER, '/s');

        self::assertInstanceOf(ShadowValue::class, $value);
        self::assertTrue($value->isSingleLayer());
        self::assertSame(Type::Shadow, $value->type());
    }

    public function testHappyPathList(): void
    {
        $value = (new ShadowValueFactory())->create(
            [self::VALID_LAYER, self::VALID_LAYER],
            '/s',
        );

        self::assertInstanceOf(ShadowValue::class, $value);
        self::assertFalse($value->isSingleLayer());
        self::assertCount(2, $value->layers);
    }

    public function testRejectsNonObjectNonListInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('shadow object must be a non-empty object (at /s)');
        (new ShadowValueFactory())->create('not-shadowy', '/s');
    }

    public function testRejectsUnknownProperty(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown shadow property 'glow' (at /s)");
        (new ShadowValueFactory())->create(
            self::VALID_LAYER + ['glow' => true],
            '/s',
        );
    }

    public function testRejectsMissingColor(): void
    {
        $raw = self::VALID_LAYER;
        unset($raw['color']);

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('shadow.color is required (at /s)');
        (new ShadowValueFactory())->create($raw, '/s');
    }

    public function testRejectsMissingOffsetX(): void
    {
        $raw = self::VALID_LAYER;
        unset($raw['offsetX']);

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('shadow.offsetX is required (at /s)');
        (new ShadowValueFactory())->create($raw, '/s');
    }

    public function testRejectsMissingOffsetY(): void
    {
        $raw = self::VALID_LAYER;
        unset($raw['offsetY']);

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('shadow.offsetY is required (at /s)');
        (new ShadowValueFactory())->create($raw, '/s');
    }

    public function testRejectsMissingBlur(): void
    {
        $raw = self::VALID_LAYER;
        unset($raw['blur']);

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('shadow.blur is required (at /s)');
        (new ShadowValueFactory())->create($raw, '/s');
    }

    public function testRejectsMissingSpread(): void
    {
        $raw = self::VALID_LAYER;
        unset($raw['spread']);

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('shadow.spread is required (at /s)');
        (new ShadowValueFactory())->create($raw, '/s');
    }

    public function testRejectsNonBooleanInset(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('shadow.inset must be a boolean (at /s)');
        (new ShadowValueFactory())->create(
            self::VALID_LAYER + ['inset' => 'yes'],
            '/s',
        );
    }

    public function testInsetDefaultsToFalseWhenOmitted(): void
    {
        $value = (new ShadowValueFactory())->create(self::VALID_LAYER, '/s');

        self::assertInstanceOf(ShadowValue::class, $value);
        self::assertFalse($value->layers[0]->inset);
    }

    public function testPropagatesSubFactoryErrorForSingleLayer(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /s/offsetX)');
        (new ShadowValueFactory())->create(
            [
                'color' => self::COLOR,
                'offsetX' => ['value' => 1, 'unit' => 'em'],
                'offsetY' => self::ZERO,
                'blur' => self::ZERO,
                'spread' => self::ZERO,
            ],
            '/s',
        );
    }

    public function testPropagatesSubFactoryErrorForLayerWithinList(): void
    {
        // Checks the iterator walks past layer 0 — a bug where only the first
        // layer got validated would be invisible without this.
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /s/1/blur)');
        (new ShadowValueFactory())->create(
            [
                self::VALID_LAYER,
                [
                    'color' => self::COLOR,
                    'offsetX' => self::ZERO,
                    'offsetY' => self::ZERO,
                    'blur' => ['value' => 8, 'unit' => 'em'],
                    'spread' => self::ZERO,
                ],
            ],
            '/s',
        );
    }
}
