<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator\Rule;

use Penyaskito\Dtcg\Reference\CycleDetector;
use Penyaskito\Dtcg\Reference\CyclicReferenceException;
use Penyaskito\Dtcg\Reference\UnresolvableReferenceException;
use Penyaskito\Dtcg\Tom\Group;
use Penyaskito\Dtcg\Tom\Walker;
use Penyaskito\Dtcg\Validator\Violation;
use Penyaskito\Dtcg\Validator\ViolationSource;

/**
 * Detects cycles in `$extends` chains.
 *
 * Redundant for documents produced by `Parser` (which fails eagerly on
 * cycles during `$extends` materialization). Retained as a defensive check
 * for programmatically-constructed TOMs where `extendsFrom` graphs may not
 * have been resolved.
 */
final class NoExtendsCycles implements Rule
{
    public function name(): string
    {
        return 'NoExtendsCycles';
    }

    public function check(Context $context): iterable
    {
        foreach (Walker::groups($context->document) as $group) {
            if ($group->extendsFrom === null) {
                continue;
            }

            $detector = new CycleDetector();
            try {
                $detector->visit($group->path);
                $this->follow($group, $context, $detector);
            } catch (CyclicReferenceException $e) {
                yield new Violation(
                    source: ViolationSource::Semantic,
                    path: $group->path->toString(),
                    message: $e->getMessage(),
                    constraint: $this->name(),
                );
            } catch (UnresolvableReferenceException) {
                // A separate concern — not this rule's job.
            }
        }
    }

    private function follow(Group $group, Context $context, CycleDetector $detector): void
    {
        if ($group->extendsFrom === null) {
            return;
        }
        $target = $context->resolver->resolve($group->extendsFrom);
        if (!$target instanceof Group) {
            return; // malformed target is a different concern
        }
        $detector->visit($target->path);
        $this->follow($target, $context, $detector);
    }
}
