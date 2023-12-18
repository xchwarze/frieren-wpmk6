<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RefactorDatabaseUsesRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Include_::class, Assign::class, MethodCall::class];
    }

    /**
     * @param Node $node
     * @return Node|null
     */
    public function refactor(Node $node): ?Node
    {
        // delete require_once()
        if ($node instanceof Include_ && $node->expr instanceof String_ &&
            strpos($node->expr->value, 'DatabaseConnection.php') !== false) {

            // shitty hack...!
            return new String_('');
        }

        // refactor in constructor
        if ($node instanceof Assign &&
            $this->isName($node->var, 'dbConnection') &&
            $node->expr instanceof New_
            //&& $this->isName($node->expr->class, 'DatabaseConnection')
        ) {
            $node->expr = new New_(new FullyQualified('frieren\orm\SQLite'), $node->expr->args);

            return $node;
        }

        // refactor method name
        if ($node instanceof MethodCall &&
            $this->isName($node->var, 'dbConnection') &&
            ($this->isName($node->name, 'exec') || $this->isName($node->name, 'query'))) {
            if ($this->isName($node->name, 'exec')) {
                $node->name = new Node\Identifier('execLegacy');
            } else {
                $node->name = new Node\Identifier('queryLegacy');
            }

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Refactor old DatabaseConnection uses to new ORM', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}
