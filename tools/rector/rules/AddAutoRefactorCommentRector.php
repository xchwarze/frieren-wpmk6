<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class AddAutoRefactorCommentRector extends AbstractRector
{
    private string $commentText = 'Code modified by Frieren Auto Refactor';

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Namespace_::class];
    }

    /**
     * @param Namespace_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            $firstNode = $node->stmts[0] ?? null;
            if (!$firstNode instanceof Node\Stmt\Nop ||
                !str_contains($firstNode->getComments()[0]->getText(), $this->commentText)) {
                $nopNode = new Node\Stmt\Nop();
                $nopNode->setAttribute('comments', [new Comment("/* {$this->commentText} */")]);
                array_unshift($node->stmts, $nopNode);

                return $node;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add Auto Refactor script comment', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}
