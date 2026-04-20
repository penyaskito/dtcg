<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\FontWeightValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\FontWeightKeyword;
use Penyaskito\Dtcg\Tom\Value\FontWeightValue;

#[CoversClass(FontWeightValueFactory::class)]
final class FontWeightValueFactoryTest extends TestCase
{
    public function testCreatesNumericWeight(): void
    {
        $value = (new FontWeightValueFactory())->create(650, '/w');

        self::assertInstanceOf(FontWeightValue::class, $value);
        self::assertFalse($value->isKeyword());
        self::assertSame(650.0, $value->weight);
        self::assertSame(Type::FontWeight, $value->type());
    }

    public function testCreatesKeyword(): void
    {
        $value = (new FontWeightValueFactory())->create('bold', '/w');

        self::assertInstanceOf(FontWeightValue::class, $value);
        self::assertTrue($value->isKeyword());
        self::assertSame(FontWeightKeyword::Bold, $value->weight);
    }

    public function testAcceptsBoundaryValues(): void
    {
        $low = (new FontWeightValueFactory())->create(1, '/w');
        $high = (new FontWeightValueFactory())->create(1000, '/w');

        self::assertInstanceOf(FontWeightValue::class, $low);
        self::assertInstanceOf(FontWeightValue::class, $high);
    }

    /** @return iterable<string, array{int|float|string, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'below range' => [0, 'fontWeight numeric value must be between 1 and 1000, got 0'];
        yield 'above range' => [1001, 'fontWeight numeric value must be between 1 and 1000, got 1001'];
        yield 'unknown keyword' => ['very-bold', "unknown fontWeight keyword 'very-bold'"];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInputs(int|float|string $raw, string $expectedSubstring): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage($expectedSubstring . ' (at /w)');
        (new FontWeightValueFactory())->create($raw, '/w');
    }

    public function testRejectsBool(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontWeight $value must be a number (1-1000) or a recognised keyword string (at /w)');
        (new FontWeightValueFactory())->create(true, '/w');
    }

    public function testRejectsArray(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontWeight $value must be a number (1-1000) or a recognised keyword string (at /w)');
        (new FontWeightValueFactory())->create([400], '/w');
    }

    public function testRejectsNull(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontWeight $value must be a number (1-1000) or a recognised keyword string (at /w)');
        (new FontWeightValueFactory())->create(null, '/w');
    }
}
