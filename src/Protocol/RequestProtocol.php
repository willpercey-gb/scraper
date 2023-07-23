<?php

namespace UWebPro\Scraper\Protocol;

use UWebPro\Scraper\Transformer\RequestTransformer;

class RequestProtocol
{
    /** @var array<HeaderProtocol> */
    public array $headers = [];

    /** @var array<CookieProtocol>|null */
    public ?array $cookies = null;

    public string $method;

    public string $url;

    public function prepare(array $replacements = []): void
    {
        $this->url = RequestTransformer::transform($this, $replacements)->url;
    }

    //TODO body?
}
