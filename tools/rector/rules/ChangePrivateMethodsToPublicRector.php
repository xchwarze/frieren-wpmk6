<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ChangePrivateMethodsToPublicRector extends AbstractRector
{
    private array $extendsNames = [
        'APIModule', 'SystemModule','Module'
    ];

    private array $excludedMethods = [
        '__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__unset', '__sleep',
        '__wakeup', '__serialize', '__unserialize', '__toString', '__invoke', '__set_state', '__clone', '__debugInfo',
        'route'
    ];

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        //$className = $node->getAttribute('parent')->namespacedName->toString();
        //if (in_array(basename($className), $this->extendsNames) && $node->isPrivate() && !in_array($node->name->toString(), $this->excludedMethods)) {
        if ($node->isPrivate() && !in_array($node->name->toString(), $this->excludedMethods)) {
            $node->flags &= ~Node\Stmt\Class_::MODIFIER_PRIVATE;
            $node->flags |= Node\Stmt\Class_::MODIFIER_PUBLIC;

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Changes all api private methods to public', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}