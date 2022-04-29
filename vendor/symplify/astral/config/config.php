<?php

declare (strict_types=1);
namespace EasyCI20220429;

use EasyCI20220429\PhpParser\ConstExprEvaluator;
use EasyCI20220429\PhpParser\NodeFinder;
use EasyCI20220429\PHPStan\PhpDocParser\Lexer\Lexer;
use EasyCI20220429\PHPStan\PhpDocParser\Parser\ConstExprParser;
use EasyCI20220429\PHPStan\PhpDocParser\Parser\PhpDocParser;
use EasyCI20220429\PHPStan\PhpDocParser\Parser\TypeParser;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use EasyCI20220429\Symplify\Astral\PhpParser\SmartPhpParser;
use EasyCI20220429\Symplify\Astral\PhpParser\SmartPhpParserFactory;
use EasyCI20220429\Symplify\PackageBuilder\Php\TypeChecker;
use function EasyCI20220429\Symfony\Component\DependencyInjection\Loader\Configurator\service;
return static function (\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator) : void {
    $services = $containerConfigurator->services();
    $services->defaults()->autowire()->autoconfigure()->public();
    $services->load('EasyCI20220429\Symplify\Astral\\', __DIR__ . '/../src')->exclude([__DIR__ . '/../src/StaticFactory', __DIR__ . '/../src/ValueObject', __DIR__ . '/../src/NodeVisitor', __DIR__ . '/../src/PhpParser/SmartPhpParser.php', __DIR__ . '/../src/PhpDocParser/PhpDocNodeVisitor/CallablePhpDocNodeVisitor.php']);
    $services->set(\EasyCI20220429\Symplify\Astral\PhpParser\SmartPhpParser::class)->factory([\EasyCI20220429\Symfony\Component\DependencyInjection\Loader\Configurator\service(\EasyCI20220429\Symplify\Astral\PhpParser\SmartPhpParserFactory::class), 'create']);
    $services->set(\EasyCI20220429\PhpParser\ConstExprEvaluator::class);
    $services->set(\EasyCI20220429\Symplify\PackageBuilder\Php\TypeChecker::class);
    $services->set(\EasyCI20220429\PhpParser\NodeFinder::class);
    // phpdoc parser
    $services->set(\EasyCI20220429\PHPStan\PhpDocParser\Parser\PhpDocParser::class);
    $services->set(\EasyCI20220429\PHPStan\PhpDocParser\Lexer\Lexer::class);
    $services->set(\EasyCI20220429\PHPStan\PhpDocParser\Parser\TypeParser::class);
    $services->set(\EasyCI20220429\PHPStan\PhpDocParser\Parser\ConstExprParser::class);
};
