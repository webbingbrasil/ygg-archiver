<?php

namespace Ygg\Archiver\Exception;

use Exception;

/**
 * Class UnsuportedArchiveException
 * @package Ygg\Archiver\Exception
 */
class UnsuportedArchiveException extends Exception
{
    /**
     * UnsuportedArchiveException constructor.
     * @param string $type Archive type name
     */
    public function __construct(string $type)
    {
        parent::__construct('Error: Your PHP version is not compiled with '.$type.' support');
    }
}
