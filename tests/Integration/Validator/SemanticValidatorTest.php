<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Validator;

use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Validator\SemanticValidator;
use Penyaskito\Dtcg\Validator\Violation;
use Penyaskito\Dtcg\Validator\ViolationSource;
use PHPUnit\Framework\TestCase;

final class SemanticValidatorTest extends TestCase
{
    public function testValidDocumentProducesNoViolations(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias' => ['$ref' => '#/spacing/base'],
            ],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame([], $violations, self::describe($violations));
    }

    public function testReportsUnresolvableReference(): void
    {
        $doc = (new Parser())->parseArray([
            'orphan' => [
                '$type' => 'dimension',
                '$ref' => '#/nonexistent/target',
            ],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame(['AliasTargetExists'], self::constraints($violations));
        self::assertSame('orphan', $violations[0]->path);
        self::assertSame(ViolationSource::Semantic, $violations[0]->source);
    }

    public function testReportsAliasCycle(): void
    {
        $doc = (new Parser())->parseArray([
            'a' => ['$type' => 'dimension', '$ref' => '#/b'],
            'b' => ['$type' => 'dimension', '$ref' => '#/a'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        // Both 'a' and 'b' are starting points — each sees the cycle.
        $constraints = self::constraints($violations);
        self::assertContains('NoAliasCycles', $constraints);
        self::assertNotContains('AliasTargetExists', $constraints);
    }

    public function testReportsUnresolvableTypeForReferenceToGroup(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'dimension',
                'red' => ['$value' => ['value' => 1, 'unit' => 'px']],
            ],
            'alias' => ['$ref' => '#/colors'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame(['TypeResolvable'], self::constraints($violations));
        self::assertSame('alias', $violations[0]->path);
    }

    public function testReferenceWithExplicitTypePassesTypeResolvable(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'dimension',
                'red' => ['$value' => ['value' => 1, 'unit' => 'px']],
            ],
            'alias' => ['$type' => 'dimension', '$ref' => '#/colors'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertNotContains('TypeResolvable', self::constraints($violations));
    }

    public function testOnlyAliasTargetExistsFiresForBrokenRef(): void
    {
        $doc = (new Parser())->parseArray([
            'alias' => ['$type' => 'dimension', '$ref' => '#/nope'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame(['AliasTargetExists'], self::constraints($violations));
    }

    public function testTypeResolvableSwallowsUnresolvableReference(): void
    {
        // Untyped reference (no $type anywhere) pointing at nothing:
        // AliasTargetExists fires; TypeResolvable's catch block MUST swallow
        // the UnresolvableReferenceException so no duplicate is emitted.
        $doc = (new Parser())->parseArray([
            'orphan' => ['$ref' => '#/nonexistent'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame(['AliasTargetExists'], self::constraints($violations));
    }

    public function testTypeResolvableSwallowsCyclicReference(): void
    {
        // Untyped references in a cycle: NoAliasCycles fires; TypeResolvable's
        // catch block MUST swallow the CyclicReferenceException.
        $doc = (new Parser())->parseArray([
            'a' => ['$ref' => '#/b'],
            'b' => ['$ref' => '#/a'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame(['NoAliasCycles'], self::constraints($violations));
    }

    public function testDeepChainOfReferencesResolvesWithoutViolations(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'a' => ['$ref' => '#/spacing/b'],
                'b' => ['$ref' => '#/spacing/c'],
                'c' => ['$ref' => '#/spacing/base'],
            ],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame([], $violations, self::describe($violations));
    }

    public function testReferenceChainEndingInGroupStillFlagsTypeResolvable(): void
    {
        $doc = (new Parser())->parseArray([
            'colors' => [
                '$type' => 'dimension',
                'red' => ['$value' => ['value' => 1, 'unit' => 'px']],
            ],
            'midway' => ['$ref' => '#/colors'],
            'alias' => ['$ref' => '#/midway'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        $byPath = [];
        foreach ($violations as $v) {
            $byPath[$v->path][] = $v->constraint;
        }

        self::assertArrayHasKey('midway', $byPath);
        self::assertArrayHasKey('alias', $byPath);
        self::assertContains('TypeResolvable', $byPath['midway']);
        self::assertContains('TypeResolvable', $byPath['alias']);
    }

    public function testAliasTargetExistsReportsBrokenReferenceValue(): void
    {
        $doc = (new Parser())->parseArray([
            'alias' => ['$type' => 'dimension', '$value' => '{nonexistent.target}'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame(['AliasTargetExists'], self::constraints($violations));
        self::assertSame('alias', $violations[0]->path);
    }

    public function testNoAliasCyclesReportsCycleThroughReferenceValues(): void
    {
        $doc = (new Parser())->parseArray([
            'a' => ['$type' => 'dimension', '$value' => '{b}'],
            'b' => ['$type' => 'dimension', '$value' => '{a}'],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        $constraints = self::constraints($violations);
        self::assertContains('NoAliasCycles', $constraints);
    }

    public function testValidReferenceValueChainProducesNoViolations(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 8, 'unit' => 'px']],
                'alias1' => ['$value' => '{spacing.alias2}'],
                'alias2' => ['$value' => '{spacing.base}'],
            ],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertSame([], $violations, self::describe($violations));
    }

    public function testAliasTargetExistsReportsBrokenRefInsideComposite(): void
    {
        $doc = (new Parser())->parseArray([
            'b' => [
                '$type' => 'border',
                'broken' => [
                    '$value' => [
                        'color' => '{nonexistent.color}',
                        'width' => ['value' => 1, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertContains('AliasTargetExists', self::constraints($violations));
        self::assertSame('b.broken', $violations[0]->path);
    }

    public function testNoAliasCyclesReportsCycleViaCompositeSubFieldRefs(): void
    {
        $doc = (new Parser())->parseArray([
            // Two value tokens whose $value-root curly-brace aliases form a
            // cycle. The Border's color points to one of them; AliasCycles
            // detects the cycle when chasing the composite sub-field ref too.
            'a' => ['$type' => 'color', '$value' => '{b}'],
            'b' => ['$type' => 'color', '$value' => '{a}'],
            'c' => [
                '$type' => 'border',
                'broken' => [
                    '$value' => [
                        'color' => '{a}',
                        'width' => ['value' => 1, 'unit' => 'px'],
                        'style' => 'solid',
                    ],
                ],
            ],
        ]);

        $violations = (new SemanticValidator())->validate($doc);

        self::assertContains('NoAliasCycles', self::constraints($violations));
    }

    public function testCustomRuleListIsRespected(): void
    {
        $doc = (new Parser())->parseArray([
            'alias' => ['$type' => 'dimension', '$ref' => '#/nope'],
        ]);

        $validator = new SemanticValidator([new \Penyaskito\Dtcg\Validator\Rule\NoAliasCycles()]);
        $violations = $validator->validate($doc);

        self::assertSame([], $violations);
    }

    /**
     * @param list<Violation> $violations
     * @return list<string>
     */
    private static function constraints(array $violations): array
    {
        return array_values(array_unique(array_map(
            static fn (Violation $v): string => $v->constraint,
            $violations,
        )));
    }

    /** @param list<Violation> $violations */
    private static function describe(array $violations): string
    {
        if ($violations === []) {
            return '';
        }

        return "unexpected violations:\n" . implode("\n", array_map(
            static fn (Violation $v): string => sprintf(' - [%s] %s: %s', $v->constraint, $v->path, $v->message),
            $violations,
        ));
    }
}
