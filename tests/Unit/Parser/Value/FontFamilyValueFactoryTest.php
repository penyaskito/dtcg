<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Value\FontFamilyValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value\FontFamilyValue;

#[CoversClass(FontFamilyValueFactory::class)]
final class FontFamilyValueFactoryTest extends TestCase
{
    public function testCreatesFromSingleString(): void
    {
        $value = (new FontFamilyValueFactory())->create('Inter', '/ff');

        self::assertInstanceOf(FontFamilyValue::class, $value);
        self::assertSame(['Inter'], $value->families);
        self::assertSame('Inter', $value->primary());
        self::assertSame(Type::FontFamily, $value->type());
    }

    public function testCreatesFromList(): void
    {
        $value = (new FontFamilyValueFactory())->create(
            ['Inter', 'system-ui', 'sans-serif'],
            '/ff',
        );

        self::assertInstanceOf(FontFamilyValue::class, $value);
        self::assertSame(['Inter', 'system-ui', 'sans-serif'], $value->families);
        self::assertSame('Inter', $value->primary());
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily string must not be empty (at /ff)');
        (new FontFamilyValueFactory())->create('', '/ff');
    }

    public function testRejectsEmptyArray(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily $value must be a non-empty string or a non-empty list of strings (at /ff)');
        (new FontFamilyValueFactory())->create([], '/ff');
    }

    public function testRejectsNonStringInList(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily $value[1] must be a non-empty string (at /ff)');
        (new FontFamilyValueFactory())->create(['Inter', 42], '/ff');
    }

    public function testRejectsEmptyStringInList(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily $value[0] must be a non-empty string (at /ff)');
        (new FontFamilyValueFactory())->create(['', 'sans-serif'], '/ff');
    }

    public function testRejectsAssociativeArray(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily $value must be a non-empty string or a non-empty list of strings (at /ff)');
        (new FontFamilyValueFactory())->create(['primary' => 'Inter'], '/ff');
    }

    public function testRejectsNumber(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily $value must be a non-empty string or a non-empty list of strings (at /ff)');
        (new FontFamilyValueFactory())->create(42, '/ff');
    }

    public function testRejectsNull(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('fontFamily $value must be a non-empty string or a non-empty list of strings (at /ff)');
        (new FontFamilyValueFactory())->create(null, '/ff');
    }
}
