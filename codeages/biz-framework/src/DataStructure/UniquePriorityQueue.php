<?php

namespace Codeages\Biz\Framework\DataStructure;

class UniquePriorityQueue extends \SplPriorityQueue
{
    protected $values = [];

    protected $serial = PHP_INT_MAX;

    public function insert($value, $priority)
    {
        if (isset($this->values[$value])) {
            return;
        }
        parent::insert($value, [$priority, $this->serial--]);
        $this->values[$value] = true;
    }
}
