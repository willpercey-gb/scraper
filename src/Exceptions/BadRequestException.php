<?php

namespace UWebPro\Scraper\Exceptions;

class BadRequestException extends \Exception
{
    public function __construct($message = "Bad Request", $code = 400, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
