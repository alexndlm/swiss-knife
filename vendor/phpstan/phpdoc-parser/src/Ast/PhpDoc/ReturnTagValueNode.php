<?php

declare (strict_types=1);
namespace EasyCI20220225\PHPStan\PhpDocParser\Ast\PhpDoc;

use EasyCI20220225\PHPStan\PhpDocParser\Ast\NodeAttributes;
use EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode;
class ReturnTagValueNode implements \EasyCI20220225\PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode
{
    use NodeAttributes;
    /** @var TypeNode */
    public $type;
    /** @var string (may be empty) */
    public $description;
    public function __construct(\EasyCI20220225\PHPStan\PhpDocParser\Ast\Type\TypeNode $type, string $description)
    {
        $this->type = $type;
        $this->description = $description;
    }
    public function __toString() : string
    {
        return \trim("{$this->type} {$this->description}");
    }
}