<?php

namespace UWebPro\Scraper\Provider;

use UWebPro\Crawler\Crawler;

interface ScraperContract
{
    public const USER_AGENTS = [
        'Mozilla/5.0 (X11; Linux x86_64; rv:81.0) Gecko/20100101 Firefox/81.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
    ];


    public function request(string $method, string $uri = '', array $options = [], int $attempts = 1): Crawler;

}