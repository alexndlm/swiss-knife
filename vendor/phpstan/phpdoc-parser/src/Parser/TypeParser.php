<?php

declare (strict_types=1);
namespace EasyCI20220225\PHPStan\PhpDocParser\Parser;

use EasyCI20220225\PHPStan\PhpDocParser\Ast;
use EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer;
class TypeParser
{
    /** @var ConstExprParser|null */
    private $constExprParser;
    public function __construct(?\EasyCI20220225\PHPStan\PhpDocParser\Parser\ConstExprParser $constExprParser = null)
    {
        $this->constExprParser = $constExprParser;
    }
    /** @phpstan-impure */
    public function parse(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_NULLABLE)) {
            $type = $this->parseNullable($tokens);
        } else {
            $type = $this->parseAtomic($tokens);
            if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_UNION)) {
                $type = $this->parseUnion($tokens, $type);
            } elseif ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_INTERSECTION)) {
                $type = $this->parseIntersection($tokens, $type);
            }
        }
        return $type;
    }
    /** @phpstan-impure */
    private function parseAtomic(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        if ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_PARENTHESES)) {
            $type = $this->parse($tokens);
            $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_PARENTHESES);
            if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                return $this->tryParseArray($tokens, $type);
            }
            return $type;
        }
        if ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_THIS_VARIABLE)) {
            $type = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ThisTypeNode();
            if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                return $this->tryParseArray($tokens, $type);
            }
            return $type;
        }
        $currentTokenValue = $tokens->currentTokenValue();
        $tokens->pushSavePoint();
        // because of ConstFetchNode
        if ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_IDENTIFIER)) {
            $type = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode($currentTokenValue);
            if (!$tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_DOUBLE_COLON)) {
                $tokens->dropSavePoint();
                // because of ConstFetchNode
                if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_ANGLE_BRACKET)) {
                    $tokens->pushSavePoint();
                    $isHtml = $this->isHtml($tokens);
                    $tokens->rollback();
                    if ($isHtml) {
                        return $type;
                    }
                    $type = $this->parseGeneric($tokens, $type);
                    if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                        $type = $this->tryParseArray($tokens, $type);
                    }
                } elseif ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_PARENTHESES)) {
                    $type = $this->tryParseCallable($tokens, $type);
                } elseif ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                    $type = $this->tryParseArray($tokens, $type);
                } elseif ($type->name === 'array' && $tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_CURLY_BRACKET) && !$tokens->isPrecededByHorizontalWhitespace()) {
                    $type = $this->parseArrayShape($tokens, $type);
                    if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                        $type = $this->tryParseArray($tokens, $type);
                    }
                }
                return $type;
            } else {
                $tokens->rollback();
                // because of ConstFetchNode
            }
        } else {
            $tokens->dropSavePoint();
            // because of ConstFetchNode
        }
        $exception = new \EasyCI20220225\PHPStan\PhpDocParser\Parser\ParserException($tokens->currentTokenValue(), $tokens->currentTokenType(), $tokens->currentTokenOffset(), \EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_IDENTIFIER);
        if ($this->constExprParser === null) {
            throw $exception;
        }
        try {
            $constExpr = $this->constExprParser->parse($tokens, \true);
            if ($constExpr instanceof \EasyCI20220225\PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprArrayNode) {
                throw $exception;
            }
            return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ConstTypeNode($constExpr);
        } catch (\LogicException $e) {
            throw $exception;
        }
    }
    /** @phpstan-impure */
    private function parseUnion(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens, \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode $type) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        $types = [$type];
        while ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_UNION)) {
            $types[] = $this->parseAtomic($tokens);
        }
        return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\UnionTypeNode($types);
    }
    /** @phpstan-impure */
    private function parseIntersection(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens, \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode $type) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        $types = [$type];
        while ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_INTERSECTION)) {
            $types[] = $this->parseAtomic($tokens);
        }
        return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode($types);
    }
    /** @phpstan-impure */
    private function parseNullable(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_NULLABLE);
        $type = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode($tokens->currentTokenValue());
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_IDENTIFIER);
        if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_ANGLE_BRACKET)) {
            $type = $this->parseGeneric($tokens, $type);
        } elseif ($type->name === 'array' && $tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_CURLY_BRACKET) && !$tokens->isPrecededByHorizontalWhitespace()) {
            $type = $this->parseArrayShape($tokens, $type);
        }
        if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
            $type = $this->tryParseArray($tokens, $type);
        }
        return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\NullableTypeNode($type);
    }
    /** @phpstan-impure */
    public function isHtml(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens) : bool
    {
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_ANGLE_BRACKET);
        if (!$tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_IDENTIFIER)) {
            return \false;
        }
        $htmlTagName = $tokens->currentTokenValue();
        $tokens->next();
        if (!$tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_ANGLE_BRACKET)) {
            return \false;
        }
        while (!$tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_END)) {
            if ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_ANGLE_BRACKET) && \strpos($tokens->currentTokenValue(), '/' . $htmlTagName . '>') !== \false) {
                return \true;
            }
            $tokens->next();
        }
        return \false;
    }
    /** @phpstan-impure */
    public function parseGeneric(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens, \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode $baseType) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\GenericTypeNode
    {
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_ANGLE_BRACKET);
        $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        $genericTypes = [$this->parse($tokens)];
        $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        while ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_COMMA)) {
            $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
            if ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_ANGLE_BRACKET)) {
                // trailing comma case
                return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\GenericTypeNode($baseType, $genericTypes);
            }
            $genericTypes[] = $this->parse($tokens);
            $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        }
        $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_ANGLE_BRACKET);
        return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\GenericTypeNode($baseType, $genericTypes);
    }
    /** @phpstan-impure */
    private function parseCallable(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens, \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode $identifier) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_PARENTHESES);
        $parameters = [];
        if (!$tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_PARENTHESES)) {
            $parameters[] = $this->parseCallableParameter($tokens);
            while ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_COMMA)) {
                $parameters[] = $this->parseCallableParameter($tokens);
            }
        }
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_PARENTHESES);
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_COLON);
        $returnType = $this->parseCallableReturnType($tokens);
        return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\CallableTypeNode($identifier, $parameters, $returnType);
    }
    /** @phpstan-impure */
    private function parseCallableParameter(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\CallableTypeParameterNode
    {
        $type = $this->parse($tokens);
        $isReference = $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_REFERENCE);
        $isVariadic = $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_VARIADIC);
        if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_VARIABLE)) {
            $parameterName = $tokens->currentTokenValue();
            $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_VARIABLE);
        } else {
            $parameterName = '';
        }
        $isOptional = $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_EQUAL);
        return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\CallableTypeParameterNode($type, $isReference, $isVariadic, $parameterName, $isOptional);
    }
    /** @phpstan-impure */
    private function parseCallableReturnType(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_NULLABLE)) {
            $type = $this->parseNullable($tokens);
        } elseif ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_PARENTHESES)) {
            $type = $this->parse($tokens);
            $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_PARENTHESES);
        } else {
            $type = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode($tokens->currentTokenValue());
            $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_IDENTIFIER);
            if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_ANGLE_BRACKET)) {
                $type = $this->parseGeneric($tokens, $type);
            } elseif ($type->name === 'array' && $tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_CURLY_BRACKET) && !$tokens->isPrecededByHorizontalWhitespace()) {
                $type = $this->parseArrayShape($tokens, $type);
            }
        }
        if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
            $type = $this->tryParseArray($tokens, $type);
        }
        return $type;
    }
    /** @phpstan-impure */
    private function tryParseCallable(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens, \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode $identifier) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        try {
            $tokens->pushSavePoint();
            $type = $this->parseCallable($tokens, $identifier);
            $tokens->dropSavePoint();
        } catch (\EasyCI20220225\PHPStan\PhpDocParser\Parser\ParserException $e) {
            $tokens->rollback();
            $type = $identifier;
        }
        return $type;
    }
    /** @phpstan-impure */
    private function tryParseArray(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens, \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode $type) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        try {
            while ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET)) {
                $tokens->pushSavePoint();
                $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_SQUARE_BRACKET);
                $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_SQUARE_BRACKET);
                $tokens->dropSavePoint();
                $type = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode($type);
            }
        } catch (\EasyCI20220225\PHPStan\PhpDocParser\Parser\ParserException $e) {
            $tokens->rollback();
        }
        return $type;
    }
    /** @phpstan-impure */
    private function parseArrayShape(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens, \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode $type) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode
    {
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_CURLY_BRACKET);
        if ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_CURLY_BRACKET)) {
            return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode([]);
        }
        $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        $items = [$this->parseArrayShapeItem($tokens)];
        $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        while ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_COMMA)) {
            $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
            if ($tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_CURLY_BRACKET)) {
                // trailing comma case
                return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode($items);
            }
            $items[] = $this->parseArrayShapeItem($tokens);
            $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        }
        $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_PHPDOC_EOL);
        $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_CURLY_BRACKET);
        return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode($items);
    }
    /** @phpstan-impure */
    private function parseArrayShapeItem(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens) : \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode
    {
        try {
            $tokens->pushSavePoint();
            $key = $this->parseArrayShapeKey($tokens);
            $optional = $tokens->tryConsumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_NULLABLE);
            $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_COLON);
            $value = $this->parse($tokens);
            $tokens->dropSavePoint();
            return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode($key, $optional, $value);
        } catch (\EasyCI20220225\PHPStan\PhpDocParser\Parser\ParserException $e) {
            $tokens->rollback();
            $value = $this->parse($tokens);
            return new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode(null, \false, $value);
        }
    }
    /**
     * @phpstan-impure
     * @return Ast\ConstExpr\ConstExprIntegerNode|Ast\ConstExpr\ConstExprStringNode|Ast\Type\IdentifierTypeNode
     */
    private function parseArrayShapeKey(\EasyCI20220225\PHPStan\PhpDocParser\Parser\TokenIterator $tokens)
    {
        if ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_INTEGER)) {
            $key = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode($tokens->currentTokenValue());
            $tokens->next();
        } elseif ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_SINGLE_QUOTED_STRING)) {
            $key = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode(\trim($tokens->currentTokenValue(), "'"));
            $tokens->next();
        } elseif ($tokens->isCurrentTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_DOUBLE_QUOTED_STRING)) {
            $key = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode(\trim($tokens->currentTokenValue(), '"'));
            $tokens->next();
        } else {
            $key = new \EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode($tokens->currentTokenValue());
            $tokens->consumeTokenType(\EasyCI20220225\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_IDENTIFIER);
        }
        return $key;
    }
}