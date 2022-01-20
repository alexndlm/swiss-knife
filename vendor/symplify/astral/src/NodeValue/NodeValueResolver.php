<?php

declare (strict_types=1);
namespace EasyCI20220120\Symplify\Astral\NodeValue;

use EasyCI20220120\PhpParser\ConstExprEvaluationException;
use EasyCI20220120\PhpParser\ConstExprEvaluator;
use EasyCI20220120\PhpParser\Node\Expr;
use EasyCI20220120\PhpParser\Node\Expr\Cast;
use EasyCI20220120\PhpParser\Node\Expr\Instanceof_;
use EasyCI20220120\PhpParser\Node\Expr\MethodCall;
use EasyCI20220120\PhpParser\Node\Expr\PropertyFetch;
use EasyCI20220120\PhpParser\Node\Expr\Variable;
use EasyCI20220120\PHPStan\Analyser\Scope;
use EasyCI20220120\PHPStan\Type\ConstantScalarType;
use EasyCI20220120\PHPStan\Type\UnionType;
use EasyCI20220120\Symplify\Astral\Contract\NodeValueResolver\NodeValueResolverInterface;
use EasyCI20220120\Symplify\Astral\Exception\ShouldNotHappenException;
use EasyCI20220120\Symplify\Astral\Naming\SimpleNameResolver;
use EasyCI20220120\Symplify\Astral\NodeFinder\SimpleNodeFinder;
use EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\ClassConstFetchValueResolver;
use EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\ConstFetchValueResolver;
use EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\FuncCallValueResolver;
use EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\MagicConstValueResolver;
use EasyCI20220120\Symplify\PackageBuilder\Php\TypeChecker;
/**
 * @see \Symplify\Astral\Tests\NodeValue\NodeValueResolverTest
 */
final class NodeValueResolver
{
    /**
     * @var \PhpParser\ConstExprEvaluator
     */
    private $constExprEvaluator;
    /**
     * @var string|null
     */
    private $currentFilePath;
    /**
     * @var \Symplify\Astral\NodeValue\UnionTypeValueResolver
     */
    private $unionTypeValueResolver;
    /**
     * @var array<NodeValueResolverInterface>
     */
    private $nodeValueResolvers = [];
    /**
     * @var \Symplify\Astral\Naming\SimpleNameResolver
     */
    private $simpleNameResolver;
    /**
     * @var \Symplify\PackageBuilder\Php\TypeChecker
     */
    private $typeChecker;
    public function __construct(\EasyCI20220120\Symplify\Astral\Naming\SimpleNameResolver $simpleNameResolver, \EasyCI20220120\Symplify\PackageBuilder\Php\TypeChecker $typeChecker, \EasyCI20220120\Symplify\Astral\NodeFinder\SimpleNodeFinder $simpleNodeFinder)
    {
        $this->simpleNameResolver = $simpleNameResolver;
        $this->typeChecker = $typeChecker;
        $this->constExprEvaluator = new \EasyCI20220120\PhpParser\ConstExprEvaluator(function (\EasyCI20220120\PhpParser\Node\Expr $expr) {
            return $this->resolveByNode($expr);
        });
        $this->unionTypeValueResolver = new \EasyCI20220120\Symplify\Astral\NodeValue\UnionTypeValueResolver();
        $this->nodeValueResolvers[] = new \EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\ClassConstFetchValueResolver($this->simpleNameResolver, $simpleNodeFinder);
        $this->nodeValueResolvers[] = new \EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\ConstFetchValueResolver($this->simpleNameResolver);
        $this->nodeValueResolvers[] = new \EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\MagicConstValueResolver();
        $this->nodeValueResolvers[] = new \EasyCI20220120\Symplify\Astral\NodeValue\NodeValueResolver\FuncCallValueResolver($this->simpleNameResolver, $this->constExprEvaluator);
    }
    /**
     * @return array|bool|float|int|mixed|string|null
     */
    public function resolveWithScope(\EasyCI20220120\PhpParser\Node\Expr $expr, \EasyCI20220120\PHPStan\Analyser\Scope $scope)
    {
        $this->currentFilePath = $scope->getFile();
        try {
            return $this->constExprEvaluator->evaluateDirectly($expr);
        } catch (\EasyCI20220120\PhpParser\ConstExprEvaluationException $exception) {
        }
        $exprType = $scope->getType($expr);
        if ($exprType instanceof \EasyCI20220120\PHPStan\Type\ConstantScalarType) {
            return $exprType->getValue();
        }
        if ($exprType instanceof \EasyCI20220120\PHPStan\Type\UnionType) {
            return $this->unionTypeValueResolver->resolveConstantTypes($exprType);
        }
        return null;
    }
    /**
     * @return array|bool|float|int|mixed|string|null
     */
    public function resolve(\EasyCI20220120\PhpParser\Node\Expr $expr, string $filePath)
    {
        $this->currentFilePath = $filePath;
        try {
            return $this->constExprEvaluator->evaluateDirectly($expr);
        } catch (\EasyCI20220120\PhpParser\ConstExprEvaluationException $exception) {
            return null;
        }
    }
    /**
     * @return mixed|string|int|bool|null
     */
    private function resolveByNode(\EasyCI20220120\PhpParser\Node\Expr $expr)
    {
        if ($this->currentFilePath === null) {
            throw new \EasyCI20220120\Symplify\Astral\Exception\ShouldNotHappenException();
        }
        foreach ($this->nodeValueResolvers as $nodeValueResolver) {
            if (\is_a($expr, $nodeValueResolver->getType(), \true)) {
                return $nodeValueResolver->resolve($expr, $this->currentFilePath);
            }
        }
        // these values cannot be resolved in reliable way
        if ($this->typeChecker->isInstanceOf($expr, [\EasyCI20220120\PhpParser\Node\Expr\Variable::class, \EasyCI20220120\PhpParser\Node\Expr\Cast::class, \EasyCI20220120\PhpParser\Node\Expr\MethodCall::class, \EasyCI20220120\PhpParser\Node\Expr\PropertyFetch::class, \EasyCI20220120\PhpParser\Node\Expr\Instanceof_::class])) {
            throw new \EasyCI20220120\PhpParser\ConstExprEvaluationException();
        }
        return null;
    }
}