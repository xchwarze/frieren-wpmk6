<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ConvertObjectAccessToArrayAccessRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Node::class];
    }

    /**
     * @param Node $node
     * @return Node|null
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof PropertyFetch && $node->var instanceof PropertyFetch) {
            $innerPropertyFetch = $node->var;
            if ($this->isName($innerPropertyFetch->var, 'this') && $this->isName($innerPropertyFetch->name, 'request')) {
                $propertyValue = $node->name->toString();

                return new ArrayDimFetch(
                    new PropertyFetch(new Variable('this'), 'request'),
                    new String_($propertyValue)
                );
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert object property access to array access for $this->request', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}