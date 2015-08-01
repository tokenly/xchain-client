<?php

namespace Tokenly\XChainClient\Exception;

use Exception;

/*
* XChainException
*/
class XChainException extends Exception
{

    public function setErrorName($account_error_name) {
        $this->account_error_name = $account_error_name;
    }

    public function getErrorName() {
        return $this->account_error_name;
    }


}
