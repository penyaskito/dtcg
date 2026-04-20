<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Fixture;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\Materializer;
use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\Serializer\DtcgJsonSerializer;
use Penyaskito\Dtcg\Validator\SemanticValidator;
use Penyaskito\Dtcg\Validator\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fixture-driven pipeline test for third-party fixtures.
 *
 * Each case in `tests/fixtures/third-party/<case>/` provides an
 * `input.tokens.json` plus any subset of sibling expectation files. Every
 * expectation file that is present contributes assertions; absent files
 * skip their stage:
 *
 *   - expected.parse-error.json  — parse MUST throw; asserts pointer and
 *     a substring of the message. If present, later stages are skipped.
 *   - expected.violations.json   — runs SemanticValidator; asserts the exact
 *     set of {source, path, constraint} tuples (order-insensitive). Optional
 *     messageContains per entry.
 *   - expected.serialized.json   — DtcgJsonSerializer output, compared as
 *     decoded arrays (key order matters, whitespace does not).
 *   - expected.materialized.json — Materializer output (refs resolved end-to-end)
 *     re-serialized with DtcgJsonSerializer, compared as decoded arrays.
 */
final class ThirdPartyFixtureTest extends TestCase
{
    private const CASES_DIR = __DIR__ . '/../../fixtures/third-party';

    #[DataProvider('cases')]
    public function testFixture(string $caseDir): void
    {
        $inputPath = $caseDir . '/input.tokens.json';
        self::assertFileExists($inputPath, 'fixture must have input.tokens.json');

        $parser = new Parser();

        $parseError = $this->loadExpected($caseDir . '/expected.parse-error.json');
        if ($parseError !== null) {
            \assert(isset($parseError['pointer']) && is_string($parseError['pointer']));
            \assert(isset($parseError['messageContains']) && is_string($parseError['messageContains']));
            try {
                $parser->parseFile($inputPath);
                self::fail('expected ParseError, got none');
            } catch (ParseError $e) {
                self::assertSame($parseError['pointer'], $e->pointer, 'pointer mismatch');
                self::assertStringContainsString(
                    $parseError['messageContains'],
                    $e->getMessage(),
                    'message substring mismatch',
                );
            }

            return;
        }

        $document = $parser->parseFile($inputPath);

        $expectedViolations = $this->loadExpected($caseDir . '/expected.violations.json');
        if ($expectedViolations !== null) {
            \assert(isset($expectedViolations['violations']) && is_array($expectedViolations['violations']));
            $violations = array_values($expectedViolations['violations']);
            $actual = (new SemanticValidator())->validate($document);
            $this->assertViolationsMatch($violations, $actual);
        }

        $expectedSerialized = $this->loadExpected($caseDir . '/expected.serialized.json');
        if ($expectedSerialized !== null) {
            $json = (new DtcgJsonSerializer())->serialize($document);
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($expectedSerialized, $decoded, 'serialized output mismatch');
        }

        $expectedMaterialized = $this->loadExpected($caseDir . '/expected.materialized.json');
        if ($expectedMaterialized !== null) {
            $materialized = (new Materializer(new Resolver($document)))->materialize($document);
            $json = (new DtcgJsonSerializer())->serialize($materialized);
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($expectedMaterialized, $decoded, 'materialized output mismatch');
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cases(): iterable
    {
        $cases = [
            'dispersa-atlassian-base',
            'dispersa-multibrand-palette',
            'terrazzo-colors',
            'terrazzo-sizes',
        ];
        foreach ($cases as $name) {
            yield $name => [self::CASES_DIR . '/' . $name];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadExpected(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        \assert($raw !== false);
        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        \assert(is_array($decoded));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param list<mixed>        $expected each entry: {source, path, constraint, messageContains?}
     * @param list<Violation>    $actual
     */
    private function assertViolationsMatch(array $expected, array $actual): void
    {
        self::assertCount(
            count($expected),
            $actual,
            sprintf('expected %d violations, got %d: %s', count($expected), count($actual), $this->formatActual($actual)),
        );

        $remaining = $actual;
        foreach ($expected as $exp) {
            \assert(is_array($exp));
            \assert(isset($exp['source']) && is_string($exp['source']));
            \assert(isset($exp['path']) && is_string($exp['path']));
            \assert(isset($exp['constraint']) && is_string($exp['constraint']));

            $matchIndex = null;
            foreach ($remaining as $i => $v) {
                if (
                    $v->source->value === $exp['source']
                    && $v->path === $exp['path']
                    && $v->constraint === $exp['constraint']
                ) {
                    if (isset($exp['messageContains'])) {
                        \assert(is_string($exp['messageContains']));
                        if (!str_contains($v->message, $exp['messageContains'])) {
                            continue;
                        }
                    }
                    $matchIndex = $i;
                    break;
                }
            }

            self::assertNotNull(
                $matchIndex,
                sprintf(
                    'no violation matches {source: %s, path: %s, constraint: %s}; actual = %s',
                    $exp['source'],
                    $exp['path'],
                    $exp['constraint'],
                    $this->formatActual($actual),
                ),
            );
            unset($remaining[$matchIndex]);
        }
    }

    /**
     * @param list<Violation> $violations
     */
    private function formatActual(array $violations): string
    {
        $lines = array_map(
            fn (Violation $v): string => sprintf(
                '{source: %s, path: %s, constraint: %s, message: %s}',
                $v->source->value,
                $v->path,
                $v->constraint,
                $v->message,
            ),
            $violations,
        );

        return '[' . implode(', ', $lines) . ']';
    }
}
