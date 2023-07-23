<?php

namespace UWebPro\Scraper;

class RequestProtocol
{
    /** @var array<HeaderProtocol> */
    public array $headers = [];

    /** @var array<CookieProtocol>|null  */
    public ?array $cookies = null;

    public string $method;

    public string $url;
}
