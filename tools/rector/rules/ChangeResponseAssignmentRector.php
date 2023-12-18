<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ChangeResponseAssignmentRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Assign::class];
    }

    /**
     * @param Assign $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Assign && $node->var instanceof PropertyFetch && $node->var->var instanceof Variable) {
            if ($this->isName($node->var->var, 'this')) {
                if ($this->isName($node->var->name, 'response')) {
                    return new MethodCall(
                        new PropertyFetch(new Variable('this'), 'responseHandler'),
                        'setData',
                        [new Arg($node->expr)]
                    );
                } else if ($this->isName($node->var->name, 'error')) {
                    return new MethodCall(
                        new PropertyFetch(new Variable('this'), 'responseHandler'),
                        'setError',
                        [new Arg($node->expr)]
                    );
                }
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Changes assignments to $this->response and $this->error to use responseHandler methods', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}
