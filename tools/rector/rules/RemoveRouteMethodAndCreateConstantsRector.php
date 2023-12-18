<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveRouteMethodAndCreateConstantsRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Class_) {
            foreach ($node->stmts as $key => $stmt) {
                if ($stmt instanceof ClassMethod && $this->isName($stmt, 'route')) {
                    // I do it in this order because extractConstantsFromRouteMethod() mutates the order inside the node!
                    unset($node->stmts[$key]);
                    $this->extractConstantsFromRouteMethod($stmt, $node);

                    return $node;
                }
            }
        }

        return null;
    }

    private function extractConstantsFromRouteMethod(ClassMethod $method, Node\Stmt\Class_ $class)
    {
        $endpointRoutes = [];

        // Make sure that the method has a statement block (stmts)
        if ($method->stmts === null) {
            return;
        }

        foreach ($method->stmts as $stmt) {
            // Search for a switch statement
            if ($stmt instanceof Node\Stmt\Switch_) {
                foreach ($stmt->cases as $case) {
                    // Make sure there is a value in case
                    if ($case->cond !== null && $case->cond instanceof Node\Scalar\String_) {
                        $endpointRoutes[] = $case->cond->value;
                    }
                }
            }
        }

        // Create the $endpointRoutes property if it does not exist
        if (!$this->classHasProperty($class, 'endpointRoutes')) {
            $propertyNode = new Node\Stmt\Property(
                Node\Stmt\Class_::MODIFIER_PROTECTED,
                [new Node\Stmt\PropertyProperty('endpointRoutes', new Node\Expr\Array_($this->createArrayItemsFromValues($endpointRoutes)))]
            );
            //$class->stmts[] = $propertyNode;
            array_unshift($class->stmts, $propertyNode);
        }
    }

    private function createArrayItemsFromValues(array $values): array
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = new Node\Expr\ArrayItem(new Node\Scalar\String_($value));
        }

        return $items;
    }

    private function classHasProperty(Node\Stmt\Class_ $class, string $propertyName): bool
    {
        foreach ($class->getProperties() as $property) {
            if ($this->isName($property, $propertyName)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove route method and create constants from old switch cases', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}