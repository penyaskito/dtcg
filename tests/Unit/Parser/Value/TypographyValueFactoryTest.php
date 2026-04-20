<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\TypographyValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;

#[CoversClass(TypographyValueFactory::class)]
final class TypographyValueFactoryTest extends TestCase
{
    private const VALID = [
        'fontFamily' => ['Inter', 'sans-serif'],
        'fontSize' => ['value' => 16, 'unit' => 'px'],
        'fontWeight' => 'bold',
        'letterSpacing' => ['value' => 0, 'unit' => 'px'],
        'lineHeight' => 1.4,
    ];

    public function testHappyPath(): void
    {
        $value = (new TypographyValueFactory())->create(self::VALID, '/t');

        self::assertInstanceOf(TypographyValue::class, $value);
        self::assertSame(Type::Typography, $value->type());
    }

    public function testRejectsNonObject(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('typography $value must be an object (at /t)');
        (new TypographyValueFactory())->create('bold', '/t');
    }

    public function testRejectsListInput(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('typography $value must be an object (at /t)');
        (new TypographyValueFactory())->create([1, 2, 3], '/t');
    }

    public function testRejectsUnknownProperty(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown typography property 'textTransform' (at /t)");
        (new TypographyValueFactory())->create(
            self::VALID + ['textTransform' => 'uppercase'],
            '/t',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function requiredFields(): iterable
    {
        yield 'fontFamily' => ['fontFamily'];
        yield 'fontSize' => ['fontSize'];
        yield 'fontWeight' => ['fontWeight'];
        yield 'letterSpacing' => ['letterSpacing'];
        yield 'lineHeight' => ['lineHeight'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('requiredFields')]
    public function testRejectsMissingField(string $field): void
    {
        $raw = self::VALID;
        unset($raw[$field]);

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage(sprintf('typography $value.%s is required (at /t)', $field));
        (new TypographyValueFactory())->create($raw, '/t');
    }

    public function testPropagatesFontFamilySubFactoryError(): void
    {
        $raw = self::VALID;
        $raw['fontFamily'] = '';

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily string must not be empty (at /t/fontFamily)');
        (new TypographyValueFactory())->create($raw, '/t');
    }

    public function testPropagatesFontSizeSubFactoryError(): void
    {
        $raw = self::VALID;
        $raw['fontSize'] = ['value' => 16, 'unit' => 'em'];

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /t/fontSize)');
        (new TypographyValueFactory())->create($raw, '/t');
    }

    public function testPropagatesFontWeightSubFactoryError(): void
    {
        $raw = self::VALID;
        $raw['fontWeight'] = 'super-ultra-mega-bold';

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /t/fontWeight)');
        (new TypographyValueFactory())->create($raw, '/t');
    }

    public function testPropagatesLetterSpacingSubFactoryError(): void
    {
        $raw = self::VALID;
        $raw['letterSpacing'] = ['value' => 1, 'unit' => 'em'];

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('(at /t/letterSpacing)');
        (new TypographyValueFactory())->create($raw, '/t');
    }

    public function testPropagatesLineHeightSubFactoryError(): void
    {
        $raw = self::VALID;
        $raw['lineHeight'] = 'one-and-a-half';

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('number $value must be a number (at /t/lineHeight)');
        (new TypographyValueFactory())->create($raw, '/t');
    }
}
