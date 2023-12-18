<?php

declare(strict_types=1);

namespace Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ChangeMethodCallRector extends AbstractRector
{
    private array $methodNames = [
        // already exist in frieren
        'execBackground', 'checkDependency', 'isSDAvailable', 'checkRunning', 'uciGet',
        'uciSet', 'uciCommit', 'fileGetContentsSSL',

        // deprecated
        'sdReaderPresent', 'sdCardPresent',

        // deleted
        'uciAddList', 'checkRunningFull', 'getMacFromInterface',

        // ???
        'getBoard', 'downloadFile','getFirmwareVersion', 'getDevice', 'getDeviceConfig'
    ];

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->isName($node->var, 'this') && in_array($node->name->toString(), $this->methodNames)) {
            // refactor for deleted methods
            if (in_array($node->name->toString(), ['uciAddList', 'checkRunningFull'])) {
                // change method
                if ($node->name->toString() === 'uciAddList') {
                    $node->name = new Identifier('uciSet');
                } else {
                    $node->name = new Identifier('checkRunning');
                }

                // add args
                $trueNode = new ConstFetch(new Name('true'));
                $node->args[] = new Arg($trueNode);
            }

            // add ->systemHelper->
            $node->var = new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), 'systemHelper');

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change helper method call from $this-> to $this->systemHelper', [
                new CodeSample(
                    '',
                    ''
                ),
            ]
        );
    }
}