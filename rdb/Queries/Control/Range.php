<?php

namespace r\Queries\Control;

use r\ProtocolBuffer\TermTermType;
use r\Query;
use r\ValuedQuery\ValuedQuery;

class Range extends ValuedQuery
{
    public function __construct(int|Query|null $startOrEndValue = null, int|Query|null $endValue = null)
    {
        if (isset($startOrEndValue)) {
            $this->setPositionalArg(0, $this->nativeToDatum($startOrEndValue));
            if (isset($endValue)) {
                $this->setPositionalArg(1, $this->nativeToDatum($endValue));
            }
        }
    }

    protected function getTermType(): TermTermType
    {
        return TermTermType::PB_RANGE;
    }
}
