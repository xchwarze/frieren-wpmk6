<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RenamePineappleNamespaceRector extends AbstractRector
{
    private array $extendsNames = [
        'APIModule', 'SystemModule','Module'
    ];

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Namespace_::class, Class_::class];
    }

    /**
     * @param Namespace_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // refactor namespace
        if ($node instanceof Node\Stmt\Namespace_ && $this->isName($node->name, 'pineapple')) {
            $node->name = new Node\Name('frieren\core');

            return $node;
        }

        // refactor extends
        if ($node instanceof Class_ && $node->extends) {
            if (in_array(basename($node->extends->toString()), $this->extendsNames)) {
                $node->extends = new Node\Name('Controller');

                return $node;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Renames namespace from "pineapple" to "frieren\core"', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}
