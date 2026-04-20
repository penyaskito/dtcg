<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Serializer;

use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Serializer\DtcgJsonSerializer;
use PHPUnit\Framework\TestCase;

final class DtcgJsonSerializerTest extends TestCase
{
    private const FIXTURES_VALID = __DIR__ . '/../../fixtures/valid';

    // --- shape tests ---------------------------------------------------

    public function testEmitsGroupLevelTypeAndOmitsRedundantTokenType(): void
    {
        // `spacing` group declares $type: dimension. `base` inherits it —
        // serializer should NOT re-emit $type on the token.
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $spacing = $this->arrayAt($decoded, 'spacing');
        $base = $this->arrayAt($spacing, 'base');

        self::assertSame('dimension', $spacing['$type']);
        self::assertArrayNotHasKey('$type', $base);
        self::assertSame(['value' => 16, 'unit' => 'px'], $base['$value']);
    }

    public function testEmitsTokenTypeWhenItDiffersFromInherited(): void
    {
        $doc = (new Parser())->parseArray([
            'outer' => [
                '$type' => 'dimension',
                'special' => ['$type' => 'number', '$value' => 42],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $special = $this->arrayAt($this->arrayAt($decoded, 'outer'), 'special');

        self::assertSame('number', $special['$type']);
    }

    public function testMetadataPreservedOnGroupsAndTokens(): void
    {
        $doc = (new Parser())->parseArray([
            'g' => [
                '$description' => 'grouped',
                '$extensions' => ['com.example' => ['a' => 1]],
                '$deprecated' => 'no longer',
                'leaf' => [
                    '$type' => 'number',
                    '$value' => 1,
                    '$description' => 'leaf doc',
                    '$deprecated' => true,
                ],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $g = $this->arrayAt($decoded, 'g');
        $leaf = $this->arrayAt($g, 'leaf');

        self::assertSame('grouped', $g['$description']);
        self::assertSame(['com.example' => ['a' => 1]], $g['$extensions']);
        self::assertSame('no longer', $g['$deprecated']);
        self::assertSame('leaf doc', $leaf['$description']);
        self::assertTrue($leaf['$deprecated']);
    }

    public function testEmptyMetadataIsOmitted(): void
    {
        $doc = (new Parser())->parseArray([
            'x' => ['$type' => 'number', '$value' => 1],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $x = $this->arrayAt($decoded, 'x');

        self::assertArrayNotHasKey('$description', $x);
        self::assertArrayNotHasKey('$extensions', $x);
        self::assertArrayNotHasKey('$deprecated', $x);
    }

    public function testReferenceTokenEmitsRefAndOmitsDeclaredTypeWhenInherited(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias' => ['$ref' => '#/spacing/base'],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $alias = $this->arrayAt($this->arrayAt($decoded, 'spacing'), 'alias');

        self::assertSame('#/spacing/base', $alias['$ref']);
        self::assertArrayNotHasKey('$type', $alias);
    }

    public function testReferenceTokenEmitsDeclaredTypeWhenItDiffersFromInherited(): void
    {
        $doc = (new Parser())->parseArray([
            'outer' => [
                '$type' => 'dimension',
                'alias' => ['$type' => 'number', '$ref' => '#/somewhere/else'],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $alias = $this->arrayAt($this->arrayAt($decoded, 'outer'), 'alias');

        self::assertSame('number', $alias['$type']);
        self::assertSame('#/somewhere/else', $alias['$ref']);
    }

    public function testFontFamilySingleListCollapsesToString(): void
    {
        $doc = (new Parser())->parseArray([
            'f' => ['$type' => 'fontFamily', 'only' => ['$value' => ['Inter']]],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $only = $this->arrayAt($this->arrayAt($decoded, 'f'), 'only');

        self::assertSame('Inter', $only['$value']);
    }

    public function testFontFamilyMultiEmitsList(): void
    {
        $doc = (new Parser())->parseArray([
            'f' => ['$type' => 'fontFamily', 'stack' => ['$value' => ['Inter', 'sans-serif']]],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $stack = $this->arrayAt($this->arrayAt($decoded, 'f'), 'stack');

        self::assertSame(['Inter', 'sans-serif'], $stack['$value']);
    }

    public function testShadowSingleLayerEmitsObject(): void
    {
        $doc = (new Parser())->parseArray([
            's' => [
                '$type' => 'shadow',
                'card' => [
                    '$value' => [
                        'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0]],
                        'offsetX' => ['value' => 0, 'unit' => 'px'],
                        'offsetY' => ['value' => 4, 'unit' => 'px'],
                        'blur' => ['value' => 8, 'unit' => 'px'],
                        'spread' => ['value' => 0, 'unit' => 'px'],
                    ],
                ],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $card = $this->arrayAt($this->arrayAt($decoded, 's'), 'card');
        $value = $card['$value'];

        self::assertIsArray($value);
        self::assertArrayHasKey('color', $value);
    }

    public function testShadowMultiLayerEmitsListAndPreservesInsetFlag(): void
    {
        $layer = [
            'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0]],
            'offsetX' => ['value' => 0, 'unit' => 'px'],
            'offsetY' => ['value' => 4, 'unit' => 'px'],
            'blur' => ['value' => 8, 'unit' => 'px'],
            'spread' => ['value' => 0, 'unit' => 'px'],
        ];
        $doc = (new Parser())->parseArray([
            's' => [
                '$type' => 'shadow',
                'stack' => ['$value' => [$layer, $layer + ['inset' => true]]],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $stack = $this->arrayAt($this->arrayAt($decoded, 's'), 'stack');
        $value = $stack['$value'];

        self::assertIsArray($value);
        self::assertCount(2, $value);

        $first = $value[0];
        $second = $value[1];
        self::assertIsArray($first);
        self::assertIsArray($second);
        self::assertArrayNotHasKey('inset', $first);
        self::assertTrue($second['inset']);
    }

    public function testColorNoneComponentSerializesBackToString(): void
    {
        $doc = (new Parser())->parseArray([
            'c' => [
                '$type' => 'color',
                'x' => ['$value' => ['colorSpace' => 'oklab', 'components' => [0.5, 'none', 0]]],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $x = $this->arrayAt($this->arrayAt($decoded, 'c'), 'x');
        $value = $x['$value'];
        self::assertIsArray($value);

        self::assertSame([0.5, 'none', 0], $value['components']);
    }

    public function testFontWeightKeywordEmittedAsString(): void
    {
        $doc = (new Parser())->parseArray([
            'w' => [
                '$type' => 'fontWeight',
                'bold' => ['$value' => 'bold'],
                'custom' => ['$value' => 650],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $w = $this->arrayAt($decoded, 'w');
        $bold = $this->arrayAt($w, 'bold');
        $custom = $this->arrayAt($w, 'custom');

        self::assertSame('bold', $bold['$value']);
        self::assertSame(650, $custom['$value']);
    }

    // --- $extends ------------------------------------------------------

    public function testExtendsReferenceIsEmitted(): void
    {
        $doc = (new Parser())->parseArray([
            'base' => [
                '$type' => 'dimension',
                'small' => ['$value' => ['value' => 4, 'unit' => 'px']],
            ],
            'spacing' => [
                '$extends' => '#/base',
                '$type' => 'dimension',
                'large' => ['$value' => ['value' => 16, 'unit' => 'px']],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $spacing = $this->arrayAt($decoded, 'spacing');

        self::assertSame('#/base', $spacing['$extends']);
    }

    public function testInheritedChildrenAreElidedFromOutput(): void
    {
        $doc = (new Parser())->parseArray([
            'base' => [
                '$type' => 'dimension',
                'small' => ['$value' => ['value' => 4, 'unit' => 'px']],
                'medium' => ['$value' => ['value' => 8, 'unit' => 'px']],
            ],
            'spacing' => [
                '$extends' => '#/base',
                '$type' => 'dimension',
                'large' => ['$value' => ['value' => 16, 'unit' => 'px']],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $spacing = $this->arrayAt($decoded, 'spacing');

        // Only own `large` survives; inherited small/medium are elided so
        // they'll be re-inherited from #/base when the output is re-parsed.
        self::assertArrayHasKey('large', $spacing);
        self::assertArrayNotHasKey('small', $spacing);
        self::assertArrayNotHasKey('medium', $spacing);
    }

    public function testOverriddenChildIsEmittedAsOwnEvenWhenInheritedSameName(): void
    {
        $doc = (new Parser())->parseArray([
            'base' => [
                '$type' => 'dimension',
                'medium' => ['$value' => ['value' => 8, 'unit' => 'px']],
            ],
            'compact' => [
                '$extends' => '#/base',
                '$type' => 'dimension',
                'medium' => ['$value' => ['value' => 6, 'unit' => 'px']],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $compact = $this->arrayAt($decoded, 'compact');
        $medium = $this->arrayAt($compact, 'medium');

        // The override survives; its value is 6, not the inherited 8.
        self::assertSame(['value' => 6, 'unit' => 'px'], $medium['$value']);
    }

    public function testCurlyBraceExtendsReferenceIsPreservedInOutput(): void
    {
        $doc = (new Parser())->parseArray([
            'base' => [
                '$type' => 'dimension',
                'x' => ['$value' => ['value' => 1, 'unit' => 'px']],
            ],
            'derived' => ['$extends' => '{base}', '$type' => 'dimension'],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $derived = $this->arrayAt($decoded, 'derived');

        self::assertSame('{base}', $derived['$extends']);
    }

    // --- ReferenceValue ($value = "{curly.brace}") ---------------------

    public function testReferenceValueRoundTripsAsCurlyBraceString(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias' => ['$value' => '{spacing.base}'],
            ],
        ]);

        $decoded = $this->decodeRoot((new DtcgJsonSerializer())->serialize($doc));
        $alias = $this->arrayAt($this->arrayAt($decoded, 'spacing'), 'alias');

        self::assertSame('{spacing.base}', $alias['$value']);
    }

    // --- round-trip tests ----------------------------------------------

    public function testRoundTripProducesAFixedPointAcrossAllValidFixtures(): void
    {
        $fixtures = glob(self::FIXTURES_VALID . '/*.tokens.json');
        self::assertNotFalse($fixtures);
        self::assertNotEmpty($fixtures);

        $parser = new Parser();
        $serializer = new DtcgJsonSerializer();

        foreach ($fixtures as $path) {
            $original = $parser->parseFile($path);
            $firstPass = $serializer->serialize($original);

            $decoded = json_decode($firstPass, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);

            $reparsed = $parser->parseArray($decoded);
            $secondPass = $serializer->serialize($reparsed);

            self::assertSame(
                $firstPass,
                $secondPass,
                sprintf('round-trip is not a fixed point for %s', basename($path)),
            );
        }
    }

    public function testCustomJsonFlagsAreHonoured(): void
    {
        $doc = (new Parser())->parseArray([
            'x' => ['$type' => 'number', '$value' => 1],
        ]);

        // JSON_UNESCAPED_SLASHES alone → compact output, no pretty-print.
        $serializer = new DtcgJsonSerializer(jsonFlags: JSON_UNESCAPED_SLASHES);
        $compact = $serializer->serialize($doc);

        self::assertStringNotContainsString("\n", trim($compact));
    }

    // --- helpers --------------------------------------------------------

    /** @return array<string, mixed> */
    private function decodeRoot(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function arrayAt(array $data, string $key): array
    {
        self::assertArrayHasKey($key, $data);
        $value = $data[$key];
        self::assertIsArray($value);

        /** @var array<string, mixed> $value */
        return $value;
    }
}
