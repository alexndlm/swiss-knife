<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */
declare (strict_types=1);
namespace EasyCI20220429\Nette\Neon\Node;

use EasyCI20220429\Nette\Neon\Node;
/** @internal */
abstract class ArrayNode extends \EasyCI20220429\Nette\Neon\Node
{
    /** @var ArrayItemNode[] */
    public $items = [];
    /** @return mixed[] */
    public function toValue() : array
    {
        return \EasyCI20220429\Nette\Neon\Node\ArrayItemNode::itemsToArray($this->items);
    }
    public function &getIterator() : \Generator
    {
        foreach ($this->items as &$item) {
            (yield $item);
        }
    }
}
