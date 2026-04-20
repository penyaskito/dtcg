<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator\Rule;

use Penyaskito\Dtcg\Reference\CyclicReferenceException;
use Penyaskito\Dtcg\Reference\ReferenceCollector;
use Penyaskito\Dtcg\Reference\UnresolvableReferenceException;
use Penyaskito\Dtcg\Tom\Walker;
use Penyaskito\Dtcg\Validator\Violation;
use Penyaskito\Dtcg\Validator\ViolationSource;

final class NoAliasCycles implements Rule
{
    public function name(): string
    {
        return 'NoAliasCycles';
    }

    public function check(Context $context): iterable
    {
        foreach (Walker::tokens($context->document) as $token) {
            foreach (ReferenceCollector::inToken($token) as $reference) {
                try {
                    $context->resolver->resolveChain($reference);
                } catch (CyclicReferenceException $e) {
                    yield new Violation(
                        source: ViolationSource::Semantic,
                        path: $token->path->toString(),
                        message: $e->getMessage(),
                        constraint: $this->name(),
                    );
                } catch (UnresolvableReferenceException) {
                    // AliasTargetExists reports this; don't double-report here.
                }
            }
        }
    }
}
