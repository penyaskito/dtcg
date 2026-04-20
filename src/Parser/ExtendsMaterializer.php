<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser;

use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\Reference\UnresolvableReferenceException;
use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Path;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\Token;
use Penyaskito\Dtcg\Tom\ValueToken;

/**
 * Resolves `$extends` eagerly over a raw TOM: for each group with an
 * `extendsFrom` reference, materializes the inherited children into the
 * extending group (own children win on name collision) and populates
 * `inheritedFrom` so consumers can tell where each child was declared.
 *
 * Cycles and bad extends targets throw ParseError. Operates purely on the
 * raw Document — the output Document is a new, fully-materialized tree.
 */
final class ExtendsMaterializer
{
    private readonly Resolver $resolver;

    public function __construct(Document $rawDocument)
    {
        $this->resolver = new Resolver($rawDocument);
    }

    public function materialize(Group $root): Group
    {
        return $this->materializeGroup($root, []);
    }

    /**
     * @param list<string> $resolving paths currently on the extends-resolution stack
     */
    private function materializeGroup(Group $group, array $resolving): Group
    {
        // Resolve descendants first (inner groups materialize before outer).
        $newChildren = [];
        foreach ($group->children as $name => $child) {
            $newChildren[$name] = $child instanceof Group
                ? $this->materializeGroup($child, $resolving)
                : $child;
        }

        // If this group has no `$extends`, we're done — just return the group
        // with its materialized descendants.
        if ($group->extendsFrom === null) {
            return new Group(
                name: $group->name,
                path: $group->path,
                defaultType: $group->defaultType,
                metadata: $group->metadata,
                sourceMap: $group->sourceMap,
                children: $newChildren,
                extendsFrom: null,
                inheritedFrom: $group->inheritedFrom,
            );
        }

        // Cycle check.
        $pathStr = $group->path->toString();
        if (in_array($pathStr, $resolving, true)) {
            $trail = [...$resolving, $pathStr];
            throw ParseError::at(
                $group->sourceMap->pointer,
                sprintf('$extends cycle detected: %s', implode(' -> ', $trail)),
            );
        }

        // Resolve the target group.
        try {
            $target = $this->resolver->resolve($group->extendsFrom);
        } catch (UnresolvableReferenceException $e) {
            throw ParseError::at(
                $group->sourceMap->pointer,
                sprintf(
                    "\$extends target '%s' cannot be resolved: %s",
                    $group->extendsFrom->original(),
                    $e->getMessage(),
                ),
            );
        }
        if (!$target instanceof Group) {
            throw ParseError::at(
                $group->sourceMap->pointer,
                sprintf(
                    "\$extends target '%s' must be a group, got a token",
                    $group->extendsFrom->original(),
                ),
            );
        }

        // Materialize the target first (so its own `$extends` chains through).
        $resolvedTarget = $this->materializeGroup($target, [...$resolving, $pathStr]);

        // Merge: extending group's own children first (preserving declaration
        // order), then inherited children that weren't shadowed.
        $merged = $newChildren;
        $inheritedFrom = $group->inheritedFrom;
        foreach ($resolvedTarget->children as $name => $child) {
            if (array_key_exists($name, $merged)) {
                continue;
            }
            $merged[$name] = $this->relocate($child, $group->path->append($name));
            $inheritedFrom[$name] = $resolvedTarget->inheritedFrom[$name] ?? $resolvedTarget->path;
        }

        return new Group(
            name: $group->name,
            path: $group->path,
            defaultType: $group->defaultType,
            metadata: $group->metadata,
            sourceMap: $group->sourceMap,
            children: $merged,
            extendsFrom: $group->extendsFrom,
            inheritedFrom: $inheritedFrom,
        );
    }

    /**
     * Rebuild a subtree with updated paths reflecting its new location in
     * the extending group. SourceMap is preserved — it points at the
     * original declaration in the source file.
     */
    private function relocate(Group|Token $node, Path $newPath): Group|Token
    {
        if ($node instanceof ValueToken) {
            return new ValueToken(
                name: $node->name,
                path: $newPath,
                type: $node->type,
                value: $node->value,
                metadata: $node->metadata,
                sourceMap: $node->sourceMap,
            );
        }
        if ($node instanceof ReferenceToken) {
            return new ReferenceToken(
                name: $node->name,
                path: $newPath,
                declaredType: $node->declaredType,
                reference: $node->reference,
                metadata: $node->metadata,
                sourceMap: $node->sourceMap,
            );
        }

        \assert($node instanceof Group);

        // Group — recurse into children.
        $newChildren = [];
        foreach ($node->children as $name => $child) {
            $newChildren[$name] = $this->relocate($child, $newPath->append($name));
        }

        return new Group(
            name: $node->name,
            path: $newPath,
            defaultType: $node->defaultType,
            metadata: $node->metadata,
            sourceMap: $node->sourceMap,
            children: $newChildren,
            extendsFrom: $node->extendsFrom,
            inheritedFrom: $node->inheritedFrom,
        );
    }
}
