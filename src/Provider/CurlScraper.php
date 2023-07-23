<?php

namespace UWebPro\Scraper\Provider;

use UWebPro\Crawler\Crawler;

class CurlScraper implements ScraperContract
{
    public function __construct(
        private bool $useProxy = false,
        private array $proxyConfig = [
            'http' => '127.0.0.1:8888', // Use this proxy with "http"
            'https' => '127.0.0.1:8888', // Use this proxy with "https",
        ],
    ) {
    }

    public function request(string $method, string $uri = '', array $options, int $attempts = 1): Crawler
    {
        $curl = new Curl();
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $curl->setOpt(CURLOPT_ENCODING, 'gzip, deflate');
        if ($this->useProxy) {
            $curl->setProxy($this->proxyConfig['https'] ?? $this->proxyConfig['http'] ?? null);
        }
        $curl->setUserAgent(
            $this->headers['User-Agent'] ?? self::USER_AGENTS[random_int(0, count(self::USER_AGENTS) - 1)]
        );
        $curl->setHeaders($this->headers);
        $curl->{$method}($url, $options);

        if ($curl->error) {
//            if ($this->command) {
//                $this->command->error($curl->getErrorMessage());
//            }
            throw new \Exception($curl->getErrorMessage());
        }

        return new Crawler($curl->getResponse());
    }

    public function post(string $uri, mixed $contents): Crawler
    {
        return $this->request('post', $uri, $contents);
    }

    public function get(string $uri, array $parameters = []): Crawler
    {
        return $this->request('get', $uri, $parameters);
    }

}