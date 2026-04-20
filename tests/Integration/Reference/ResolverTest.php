<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tests\Integration\Reference;

use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Reference\CyclicReferenceException;
use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Reference\ReferenceParser;
use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\Reference\UnresolvableReferenceException;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\ValueToken;
use PHPUnit\Framework\TestCase;

final class ResolverTest extends TestCase
{
    public function testJsonPointerResolvesToValueToken(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $target = $resolver->resolve(JsonPointerReference::parse('#/spacing/base'));

        self::assertInstanceOf(ValueToken::class, $target);
        self::assertSame('spacing.base', $target->path->toString());
    }

    public function testCurlyBraceResolvesToValueToken(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $target = $resolver->resolve(CurlyBraceReference::parse('{spacing.base}'));

        self::assertInstanceOf(ValueToken::class, $target);
    }

    public function testPointerToGroupReturnsGroup(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $target = $resolver->resolve(JsonPointerReference::parse('#/spacing'));

        self::assertInstanceOf(Group::class, $target);
        self::assertSame('spacing', $target->name);
    }

    public function testEmptyJsonPointerReturnsRootGroup(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $target = $resolver->resolve(JsonPointerReference::parse('#'));

        self::assertInstanceOf(Group::class, $target);
        self::assertTrue($target->isRoot());
    }

    public function testPropertyLevelReferenceThrowsNotYetSupported(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $this->expectException(UnresolvableReferenceException::class);
        $this->expectExceptionMessage('property-level references');

        $resolver->resolve(JsonPointerReference::parse('#/spacing/base/$value'));
    }

    public function testNonexistentSegmentThrows(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $this->expectException(UnresolvableReferenceException::class);
        $this->expectExceptionMessage("no token or group at 'spacing.nope'");

        $resolver->resolve(JsonPointerReference::parse('#/spacing/nope'));
    }

    public function testDescendingIntoATokenThrows(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $this->expectException(UnresolvableReferenceException::class);
        $this->expectExceptionMessage('is not a group');

        $resolver->resolve(JsonPointerReference::parse('#/spacing/base/somethingElse'));
    }

    public function testResolveChainFollowsReferenceToValueToken(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias1' => ['$ref' => '#/spacing/alias2'],
                'alias2' => ['$ref' => '#/spacing/base'],
            ],
        ]);
        $resolver = new Resolver($doc);

        $target = $resolver->resolveChain(ReferenceParser::parse('#/spacing/alias1'));

        self::assertInstanceOf(ValueToken::class, $target);
        self::assertSame('spacing.base', $target->path->toString());
    }

    public function testResolveChainFollowsCurlyBraceReferences(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias' => ['$ref' => '#/spacing/base'],
            ],
        ]);
        $resolver = new Resolver($doc);

        $target = $resolver->resolveChain(CurlyBraceReference::parse('{spacing.alias}'));

        self::assertInstanceOf(ValueToken::class, $target);
        self::assertSame('spacing.base', $target->path->toString());
    }

    public function testResolveChainDoesNotUnwrapAValueToken(): void
    {
        $resolver = new Resolver($this->singleTokenDoc());

        $target = $resolver->resolveChain(JsonPointerReference::parse('#/spacing/base'));

        self::assertInstanceOf(ValueToken::class, $target);
    }

    public function testResolveSingleHopReturnsReferenceToken(): void
    {
        $doc = (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
                'alias' => ['$ref' => '#/spacing/base'],
            ],
        ]);
        $resolver = new Resolver($doc);

        $hop = $resolver->resolve(JsonPointerReference::parse('#/spacing/alias'));

        self::assertInstanceOf(ReferenceToken::class, $hop);
    }

    public function testSelfReferenceCycleDetected(): void
    {
        $doc = (new Parser())->parseArray([
            'alias' => ['$ref' => '#/alias'],
        ]);
        $resolver = new Resolver($doc);

        $this->expectException(CyclicReferenceException::class);
        $this->expectExceptionMessage('cycle detected');

        $resolver->resolveChain(JsonPointerReference::parse('#/alias'));
    }

    public function testTwoNodeCycleDetected(): void
    {
        $doc = (new Parser())->parseArray([
            'a' => ['$ref' => '#/b'],
            'b' => ['$ref' => '#/a'],
        ]);
        $resolver = new Resolver($doc);

        try {
            $resolver->resolveChain(JsonPointerReference::parse('#/a'));
            self::fail('expected CyclicReferenceException');
        } catch (CyclicReferenceException $e) {
            self::assertNotSame([], $e->trail);
            self::assertContains('a', $e->trail);
            self::assertContains('b', $e->trail);
        }
    }

    private function singleTokenDoc(): Document
    {
        return (new Parser())->parseArray([
            'spacing' => [
                '$type' => 'dimension',
                'base' => ['$value' => ['value' => 16, 'unit' => 'px']],
            ],
        ]);
    }
}
