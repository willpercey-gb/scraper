<?php

namespace UWebPro\Scraper\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use UWebPro\Crawler\Crawler;

/**
 * Class Scraper
 *
 * @package App\Support
 */
class HttpScraper implements ScraperContract
{
    public array $responseHeaders;

    public ResponseInterface $response;

    protected \GuzzleHttp\Client $client;

    protected array $cookies = [];

    protected array $headers;
    protected array $config = [];

    protected ?CookieJar $cookieJar = null;

    public function __construct(
        protected bool $useCookies = true
    ) {
    }

    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function setOpt($opt, $value): static
    {
        $this->config[$opt] = $value;
        return $this;
    }

    public function setUserAgent(?string $value = null): static
    {
        $this->headers['User-Agent'] = $value ?? self::USER_AGENTS[random_int(0, count(self::USER_AGENTS) - 1)];
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function setCookies(array $cookies): static
    {
        $this->cookies = $cookies;
        return $this;
    }

    public function request(
        string $method,
        string $uri = '',
        array $options = [],
        int $attempts = 2,
        \Closure $onRedirect = null
    ): Crawler {
        try {
            $attempts--;
            $this->setUserAgent();

            if ($this->useCookies) {
                $this->cookieJar ??= CookieJar::fromArray($this->cookies, parse_url($uri, PHP_URL_HOST));
            }

            $host = parse_url($uri, PHP_URL_HOST);
            $this->headers = [
                ...$this->headers,
                [
                    'Host' => $host,
                    'Connection' => 'keep-alive',
                ]
            ];

            $options = [
                ...[
                    'headers' => $this->headers,
                    'cookies' => $this->cookieJar ?? null,
                    'timeout' => 15,
                    'allow_redirects' => [
                        'max' => 10,
                        'strict' => true,
                        'referer' => true,
                        'protocols' => ['https'],
                        'on_redirect' => $onRedirect,
                        'track_redirects' => true,
                        'Accept-Encoding' => 'gzip',
                        'decode_content' => 'gzip',
                        'force_ip_resolve' => 'v4',
                        'debug' => true,
                    ],
                ],
                $options
            ];

            $client = new Client(array_merge($this->config, $options));

            $this->response = $client->request($method, $uri, $options);

            $this->responseHeaders = $this->response->getHeaders();

            return new Crawler($this->response->getBody()->getContents());
        } catch (RequestException|GuzzleException $e) {
            $code = $e->getCode();
            if (($code === 0) && $attempts > 0) {
                sleep(2);
                return $this->request($method, $uri, $options, $attempts - 1, $onRedirect);
            }
            throw $e;
        }
    }

    public function get(string $uri, array $parameters = []): Crawler
    {
        if (!empty($parameters)) {
            //TODO replace w/ http_build_query
            $uri .= '?' . implode('&', $parameters);
        }

        return $this->request('GET', $uri);
    }

    public function post(string $uri, mixed $contents, string $type = 'body'): Crawler
    {
        return $this->request(
            'POST',
            $uri,
            [
                $type => $type === 'body'
                    ? json_encode($contents, JSON_THROW_ON_ERROR)
                    : $contents,
            ]
        );
    }
}
