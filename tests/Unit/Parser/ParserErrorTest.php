<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Parser;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
#[CoversClass(ParseError::class)]
final class ParserErrorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures';

    public function testParseFileFailsOnUnreadablePath(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('cannot read file: /does/not/exist/at/all.json (at <root>)');

        (new Parser())->parseFile('/does/not/exist/at/all.json');
    }

    public function testParseFileFailsOnInvalidJson(): void
    {
        // The full absolute fixture path varies per environment; assert the file
        // name and the root-level location suffix.
        $this->expectException(ParseError::class);
        $this->expectExceptionMessageMatches('/invalid JSON in .*not-json\.tokens\.json:.* \(at <root>\)/');

        (new Parser())->parseFile(self::FIXTURES . '/invalid/not-json.tokens.json');
    }

    public function testParseFileFailsWhenRootIsNotObject(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('root of a DTCG document must be an object (at <root>)');

        (new Parser())->parseFile(self::FIXTURES . '/invalid/array-root.tokens.json');
    }

    public function testRejectsNonStringType(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$type must be a string (at /foo)');

        (new Parser())->parseArray([
            'foo' => ['$type' => 42, '$value' => 1],
        ]);
    }

    public function testRejectsUnknownTypeString(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("unknown \$type 'notAType' (at /foo)");

        (new Parser())->parseArray([
            'foo' => ['$type' => 'notAType', '$value' => 1],
        ]);
    }

    public function testRejectsTokenWithoutTypeInStrictMode(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('token has no $type and no ancestor group provides one (strict mode) (at /foo)');

        (new Parser())->parseArray([
            'foo' => ['$value' => 42],
        ]);
    }

    public function testRejectsTypeWithoutRegisteredFactory(): void
    {
        $parser = new Parser(valueFactories: []);

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage("no value factory registered for type 'dimension' (at /foo)");

        $parser->parseArray([
            'foo' => [
                '$type' => 'dimension',
                '$value' => ['value' => 1, 'unit' => 'px'],
            ],
        ]);
    }

    public function testRejectsNonStringDescription(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$description must be a string (at /foo)');

        (new Parser())->parseArray([
            'foo' => [
                '$type' => 'number',
                '$value' => 1,
                '$description' => ['not', 'a', 'string'],
            ],
        ]);
    }

    public function testRejectsNonObjectExtensions(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$extensions must be an object (at /foo)');

        (new Parser())->parseArray([
            'foo' => [
                '$type' => 'number',
                '$value' => 1,
                '$extensions' => 'string-not-object',
            ],
        ]);
    }

    public function testRejectsDeprecatedValueWithWrongType(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$deprecated must be a boolean or string (at /foo)');

        (new Parser())->parseArray([
            'foo' => [
                '$type' => 'number',
                '$value' => 1,
                '$deprecated' => 42,
            ],
        ]);
    }

    public function testRejectsNonObjectChildOfGroup(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('expected an object (at /spacing)');

        (new Parser())->parseArray([
            'spacing' => 'a string where a group or token was expected',
        ]);
    }

    public function testRejectsNonStringRefValue(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$ref must be a string (at /alias)');

        (new Parser())->parseArray([
            'alias' => ['$ref' => 42],
        ]);
    }

    public function testNumericStringTokenNamesAreAccepted(): void
    {
        // PHP's json_decode(..., true) coerces numeric-string object keys to
        // int. Parser must treat them as strings — DTCG fixtures commonly use
        // numeric token names like {"size": {"2": ..., "4": ...}}.
        $document = (new Parser())->parseArray([
            0 => ['$type' => 'number', '$value' => 1],
            2 => ['$type' => 'number', '$value' => 4],
        ]);

        self::assertNotNull($document->root->child('0'));
        self::assertNotNull($document->root->child('2'));
    }

    public function testRejectsNonStringKeyInsideExtensions(): void
    {
        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('$extensions keys must be strings (at /foo)');

        (new Parser())->parseArray([
            'foo' => [
                '$type' => 'number',
                '$value' => 1,
                '$extensions' => [0 => 'a', 1 => 'b'],
            ],
        ]);
    }

    public function testPointerPropertyExposesLocation(): void
    {
        try {
            (new Parser())->parseArray([
                'foo' => ['$type' => 42, '$value' => 1],
            ]);
            self::fail('expected ParseError');
        } catch (ParseError $e) {
            self::assertSame('/foo', $e->pointer);
        }
    }

    public function testMetadataIsRetainedWhenValid(): void
    {
        $doc = (new Parser())->parseArray([
            'foo' => [
                '$type' => 'number',
                '$value' => 1,
                '$description' => 'a number',
                '$extensions' => ['com.example' => ['a' => 1]],
                '$deprecated' => 'use bar instead',
            ],
        ]);

        $foo = $doc->root->child('foo');
        self::assertNotNull($foo);
        self::assertSame('a number', $foo->metadata->description);
        self::assertSame(['com.example' => ['a' => 1]], $foo->metadata->extensions);
        self::assertSame('use bar instead', $foo->metadata->deprecated);
    }
}
