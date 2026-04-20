<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser;

use JsonException;
use Penyaskito\Dtcg\Parser\Value\BorderValueFactory;
use Penyaskito\Dtcg\Parser\Value\ColorValueFactory;
use Penyaskito\Dtcg\Parser\Value\CubicBezierValueFactory;
use Penyaskito\Dtcg\Parser\Value\DimensionValueFactory;
use Penyaskito\Dtcg\Parser\Value\DurationValueFactory;
use Penyaskito\Dtcg\Parser\Value\FontFamilyValueFactory;
use Penyaskito\Dtcg\Parser\Value\FontWeightValueFactory;
use Penyaskito\Dtcg\Parser\Value\GradientValueFactory;
use Penyaskito\Dtcg\Parser\Value\NumberValueFactory;
use Penyaskito\Dtcg\Parser\Value\ShadowValueFactory;
use Penyaskito\Dtcg\Parser\Value\StrokeStyleValueFactory;
use Penyaskito\Dtcg\Parser\Value\TransitionValueFactory;
use Penyaskito\Dtcg\Parser\Value\TypographyValueFactory;
use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Reference\InvalidReferenceException;
use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Reference\Reference;
use Penyaskito\Dtcg\Reference\ReferenceParser;
use Penyaskito\Dtcg\SpecVersion;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Metadata;
use Penyaskito\Dtcg\Tom\Path;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\SourceMap;
use Penyaskito\Dtcg\Tom\Token;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final class Parser
{
    /** @var array<string, ValueFactory> */
    private readonly array $valueFactories;

    /** @param list<ValueFactory>|null $valueFactories */
    public function __construct(
        private readonly SpecVersion $specVersion = SpecVersion::V2025_10,
        ?array $valueFactories = null,
    ) {
        $factories = $valueFactories ?? self::defaultValueFactories();
        $map = [];
        foreach ($factories as $factory) {
            $map[$factory->type()->value] = $factory;
        }
        $this->valueFactories = $map;
    }

    /** @return list<ValueFactory> */
    public static function defaultValueFactories(): array
    {
        return [
            new DimensionValueFactory(),
            new NumberValueFactory(),
            new DurationValueFactory(),
            new FontWeightValueFactory(),
            new CubicBezierValueFactory(),
            new FontFamilyValueFactory(),
            new ColorValueFactory(),
            new StrokeStyleValueFactory(),
            new BorderValueFactory(),
            new TransitionValueFactory(),
            new ShadowValueFactory(),
            new GradientValueFactory(),
            new TypographyValueFactory(),
        ];
    }

    public function parseFile(string $path): Document
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw ParseError::at('', sprintf('cannot read file: %s', $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ParseError::at('', sprintf('invalid JSON in %s: %s', $path, $e->getMessage()), $e);
        }

        if (!is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
            throw ParseError::at('', 'root of a DTCG document must be an object');
        }

        return $this->parseArray($decoded, $path);
    }

    /** @param array<array-key, mixed> $data */
    public function parseArray(array $data, ?string $uri = null): Document
    {
        $root = $this->walkGroup(
            name: '',
            path: Path::root(),
            data: $data,
            uri: $uri,
            pointer: '',
            inheritedType: null,
        );

        $rawDocument = new Document($this->specVersion, $root, $uri);
        $materializedRoot = (new ExtendsMaterializer($rawDocument))->materialize($root);

        return new Document($this->specVersion, $materializedRoot, $uri);
    }

    /** @param array<array-key, mixed> $data */
    private function walkGroup(
        string $name,
        Path $path,
        array $data,
        ?string $uri,
        string $pointer,
        ?Type $inheritedType,
    ): Group {
        $ownType = $this->readType($data, $pointer);
        $childInheritedType = $ownType ?? $inheritedType;
        $extendsFrom = $this->readExtends($data, $pointer);

        $children = [];
        foreach ($data as $key => $value) {
            // PHP's json_decode(..., true) coerces numeric-string object keys
            // to int (e.g. {"2": ...} becomes [2 => ...]). DTCG keys are
            // strings; cast back so downstream paths and lookups work.
            $key = is_int($key) ? (string) $key : $key;
            if (str_starts_with($key, '$')) {
                continue;
            }
            if (!is_array($value)) {
                throw ParseError::at($pointer . '/' . self::escape($key), 'expected an object');
            }

            $childPointer = $pointer . '/' . self::escape($key);
            $childPath = $path->append($key);

            if (array_key_exists('$value', $value) || array_key_exists('$ref', $value)) {
                $children[$key] = $this->walkToken(
                    $key,
                    $childPath,
                    $value,
                    $uri,
                    $childPointer,
                    $childInheritedType,
                );
            } else {
                $children[$key] = $this->walkGroup(
                    $key,
                    $childPath,
                    $value,
                    $uri,
                    $childPointer,
                    $childInheritedType,
                );
            }
        }

        return new Group(
            name: $name,
            path: $path,
            defaultType: $ownType,
            metadata: $this->readMetadata($data, $pointer),
            sourceMap: new SourceMap($uri, $pointer),
            children: $children,
            extendsFrom: $extendsFrom,
        );
    }

    /** @param array<array-key, mixed> $data */
    private function readExtends(array $data, string $pointer): ?Reference
    {
        if (!array_key_exists('$extends', $data)) {
            return null;
        }
        $raw = $data['$extends'];
        if (!is_string($raw)) {
            throw ParseError::at($pointer, '$extends must be a string reference');
        }
        try {
            return ReferenceParser::parse($raw);
        } catch (InvalidReferenceException $e) {
            throw ParseError::at($pointer, sprintf('invalid $extends: %s', $e->getMessage()));
        }
    }

    /** @param array<array-key, mixed> $data */
    private function walkToken(
        string $name,
        Path $path,
        array $data,
        ?string $uri,
        string $pointer,
        ?Type $inheritedType,
    ): Token {
        if (array_key_exists('$value', $data) && array_key_exists('$ref', $data)) {
            throw ParseError::at($pointer, '$value and $ref are mutually exclusive');
        }

        if (array_key_exists('$ref', $data)) {
            return $this->walkReferenceToken($name, $path, $data, $uri, $pointer, $inheritedType);
        }

        $rawValue = $data['$value'] ?? null;

        // Curly-brace alias at $value root — the token's value IS a
        // reference to another token's value. Build a ValueToken whose
        // `value` is a ReferenceValue wrapper.
        if (is_string($rawValue) && str_starts_with($rawValue, '{')) {
            return $this->walkReferenceValueToken(
                $name,
                $path,
                $rawValue,
                $data,
                $uri,
                $pointer,
                $inheritedType,
            );
        }

        $type = $this->readType($data, $pointer) ?? $inheritedType;
        if ($type === null) {
            throw ParseError::at(
                $pointer,
                'token has no $type and no ancestor group provides one (strict mode)',
            );
        }

        $factory = $this->valueFactories[$type->value] ?? null;
        if ($factory === null) {
            throw ParseError::at(
                $pointer,
                sprintf("no value factory registered for type '%s'", $type->value),
            );
        }

        /** @var mixed $rawValue */
        $rawValue = $data['$value'];
        $value = $factory->create($rawValue, $pointer);

        return new ValueToken(
            name: $name,
            path: $path,
            type: $type,
            value: $value,
            metadata: $this->readMetadata($data, $pointer),
            sourceMap: new SourceMap($uri, $pointer),
        );
    }

    /** @param array<array-key, mixed> $data */
    private function walkReferenceToken(
        string $name,
        Path $path,
        array $data,
        ?string $uri,
        string $pointer,
        ?Type $inheritedType,
    ): ReferenceToken {
        $raw = $data['$ref'];
        if (!is_string($raw)) {
            throw ParseError::at($pointer, '$ref must be a string');
        }

        try {
            $reference = JsonPointerReference::parse($raw);
        } catch (InvalidReferenceException $e) {
            throw ParseError::at($pointer, sprintf('invalid $ref: %s', $e->getMessage()));
        }

        $declaredType = $this->readType($data, $pointer) ?? $inheritedType;

        return new ReferenceToken(
            name: $name,
            path: $path,
            declaredType: $declaredType,
            reference: $reference,
            metadata: $this->readMetadata($data, $pointer),
            sourceMap: new SourceMap($uri, $pointer),
        );
    }

    /** @param array<array-key, mixed> $data */
    private function walkReferenceValueToken(
        string $name,
        Path $path,
        string $rawValue,
        array $data,
        ?string $uri,
        string $pointer,
        ?Type $inheritedType,
    ): ValueToken {
        try {
            $reference = CurlyBraceReference::parse($rawValue);
        } catch (InvalidReferenceException $e) {
            throw ParseError::at(
                $pointer,
                sprintf('invalid curly-brace alias in $value: %s', $e->getMessage()),
            );
        }

        $type = $this->readType($data, $pointer) ?? $inheritedType;
        if ($type === null) {
            throw ParseError::at(
                $pointer,
                'token with reference $value has no $type and no ancestor group provides one (strict mode)',
            );
        }

        return new ValueToken(
            name: $name,
            path: $path,
            type: $type,
            value: new ReferenceValue($reference),
            metadata: $this->readMetadata($data, $pointer),
            sourceMap: new SourceMap($uri, $pointer),
        );
    }

    /** @param array<array-key, mixed> $data */
    private function readType(array $data, string $pointer): ?Type
    {
        if (!array_key_exists('$type', $data)) {
            return null;
        }
        $raw = $data['$type'];
        if (!is_string($raw)) {
            throw ParseError::at($pointer, '$type must be a string');
        }
        $type = Type::tryFrom($raw);
        if ($type === null) {
            throw ParseError::at($pointer, sprintf('unknown $type \'%s\'', $raw));
        }

        return $type;
    }

    /** @param array<array-key, mixed> $data */
    private function readMetadata(array $data, string $pointer): Metadata
    {
        $description = null;
        if (array_key_exists('$description', $data)) {
            $raw = $data['$description'];
            if (!is_string($raw)) {
                throw ParseError::at($pointer, '$description must be a string');
            }
            $description = $raw;
        }

        $extensions = [];
        if (array_key_exists('$extensions', $data)) {
            $raw = $data['$extensions'];
            if (!is_array($raw)) {
                throw ParseError::at($pointer, '$extensions must be an object');
            }
            /** @var array<string, mixed> $normalized */
            $normalized = [];
            foreach ($raw as $k => $v) {
                if (!is_string($k)) {
                    throw ParseError::at($pointer, '$extensions keys must be strings');
                }
                $normalized[$k] = $v;
            }
            $extensions = $normalized;
        }

        $deprecated = null;
        if (array_key_exists('$deprecated', $data)) {
            $raw = $data['$deprecated'];
            if (!is_bool($raw) && !is_string($raw)) {
                throw ParseError::at($pointer, '$deprecated must be a boolean or string');
            }
            $deprecated = $raw;
        }

        return new Metadata($description, $extensions, $deprecated);
    }

    private static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
