<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

use Penyaskito\Dtcg\Tom\Document;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\ReferenceToken;
use Penyaskito\Dtcg\Tom\Token;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\ValueToken;

final readonly class Resolver
{
    public function __construct(
        private Document $document,
    ) {
    }

    /**
     * Resolve a reference to its immediate target. Does not follow chains through
     * intermediate ReferenceTokens — use resolveChain() for that.
     */
    public function resolve(Reference $reference): Token|Group
    {
        $current = $this->document->root;
        $seen = '';

        foreach ($reference->segments() as $segment) {
            if (str_starts_with($segment, '$')) {
                throw new UnresolvableReferenceException(
                    sprintf(
                        "property-level references (segment '%s') are not yet supported: '%s'",
                        $segment,
                        $reference->original(),
                    ),
                    $reference,
                );
            }

            if (!$current instanceof Group) {
                throw new UnresolvableReferenceException(
                    sprintf(
                        "cannot descend into '%s' under '%s': target at '%s' is not a group",
                        $segment,
                        $seen,
                        $seen,
                    ),
                    $reference,
                );
            }

            $child = $current->child($segment);
            if ($child === null) {
                throw new UnresolvableReferenceException(
                    sprintf(
                        "no token or group at '%s' (resolving '%s')",
                        $seen === '' ? $segment : $seen . '.' . $segment,
                        $reference->original(),
                    ),
                    $reference,
                );
            }

            $seen = $seen === '' ? $segment : $seen . '.' . $segment;
            $current = $child;
        }

        return $current;
    }

    /**
     * Follow a reference through any intermediate references (either
     * {@see ReferenceToken}s or {@see ValueToken}s whose `$value` is a
     * {@see ReferenceValue}) until a non-reference target is reached.
     * Throws CyclicReferenceException on a loop.
     */
    public function resolveChain(Reference $reference): Token|Group
    {
        $detector = new CycleDetector();
        $current = $this->resolve($reference);

        while (true) {
            if ($current instanceof ReferenceToken) {
                $detector->visit($current->path);
                $current = $this->resolve($current->reference);
                continue;
            }
            if ($current instanceof ValueToken && $current->value instanceof ReferenceValue) {
                $detector->visit($current->path);
                $current = $this->resolve($current->value->reference);
                continue;
            }
            break;
        }

        return $current;
    }
}
