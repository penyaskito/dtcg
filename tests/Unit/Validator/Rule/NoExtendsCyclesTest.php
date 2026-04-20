<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Unit\Validator\Rule;

use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\SpecVersion;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Metadata;
use Penyaskito\Dtcg\Tom\Path;
use Penyaskito\Dtcg\Tom\SourceMap;
use Penyaskito\Dtcg\Validator\Rule\Context;
use Penyaskito\Dtcg\Validator\Rule\NoExtendsCycles;
use Penyaskito\Dtcg\Validator\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoExtendsCycles::class)]
final class NoExtendsCyclesTest extends TestCase
{
    public function testNoCyclesProducesNoViolations(): void
    {
        $doc = $this->docWithExtendsGraph([
            'a' => '#/b',
            'b' => '#/c',
            'c' => null,
        ]);

        $violations = $this->runRule($doc);

        self::assertSame([], $violations);
    }

    public function testSelfCycleIsReported(): void
    {
        $doc = $this->docWithExtendsGraph([
            'a' => '#/a',
        ]);

        $violations = $this->runRule($doc);

        self::assertCount(1, $violations);
        self::assertSame('NoExtendsCycles', $violations[0]->constraint);
        self::assertStringContainsString('cycle detected', $violations[0]->message);
    }

    public function testTwoNodeCycleIsReported(): void
    {
        $doc = $this->docWithExtendsGraph([
            'a' => '#/b',
            'b' => '#/a',
        ]);

        $violations = $this->runRule($doc);

        self::assertNotEmpty($violations);
        foreach ($violations as $v) {
            self::assertSame('NoExtendsCycles', $v->constraint);
        }
    }

    public function testUnresolvableExtendsIsSwallowed(): void
    {
        // This rule focuses on cycles only; missing targets are a separate
        // concern we don't report from here.
        $doc = $this->docWithExtendsGraph([
            'a' => '#/does/not/exist',
        ]);

        $violations = $this->runRule($doc);

        self::assertSame([], $violations);
    }

    /** @return list<Violation> */
    private function runRule(Document $doc): array
    {
        $rule = new NoExtendsCycles();
        $context = new Context($doc, new Resolver($doc));
        $violations = [];
        foreach ($rule->check($context) as $v) {
            $violations[] = $v;
        }

        return $violations;
    }

    /**
     * Build a document with the given extends graph. Each key is a top-level
     * group name; each value is either the `$extends` JSON-Pointer string or
     * null (no extends).
     *
     * @param array<string, string|null> $graph
     */
    private function docWithExtendsGraph(array $graph): Document
    {
        $children = [];
        foreach ($graph as $name => $extendsRef) {
            $extendsFrom = $extendsRef !== null ? JsonPointerReference::parse($extendsRef) : null;
            $children[$name] = new Group(
                name: $name,
                path: Path::fromDots($name),
                defaultType: null,
                metadata: Metadata::empty(),
                sourceMap: SourceMap::synthetic('/' . $name),
                children: [],
                extendsFrom: $extendsFrom,
            );
        }

        $root = new Group(
            name: '',
            path: Path::root(),
            defaultType: null,
            metadata: Metadata::empty(),
            sourceMap: SourceMap::synthetic(''),
            children: $children,
        );

        return new Document(SpecVersion::V2025_10, $root);
    }
}
