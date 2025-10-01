<?php

namespace r\Queries\Misc;

use r\ProtocolBuffer\TermTermType;
use r\Query;
use r\ValuedQuery\ValuedQuery;

class Uuid extends ValuedQuery
{
    public function __construct(string|Query|null $str = null)
    {
        if (isset($str)) {
            $this->setPositionalArg(0, $this->nativeToDatum($str));
        }
    }

    protected function getTermType(): TermTermType
    {
        return TermTermType::PB_UUID;
    }
}
