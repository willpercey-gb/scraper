<?php

namespace UWebPro\Scraper\Provider;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use UWebPro\Crawler\Crawler;
use UWebPro\Scrapoxy\Container;
use UWebPro\Scrapoxy\Instance;

/**
 * Class Scraper
 *
 * @package App\Support
 */
class ProxiedHttpScraper extends HttpScraper
{
    protected ?Container $proxy = null;

    public function __construct(
        protected array $proxyConfig = [
            'http' => '127.0.0.1:8888', // Use this proxy with "http"
            'https' => '127.0.0.1:8888', // Use this proxy with "https",
        ],
        bool $useCookies = true
    ) {
        $this->config = [
            'proxy' => $proxyConfig,
        ];

        parent::__construct($useCookies);
    }

    public function request(
        string $method,
        string $uri = '',
        array $options = [],
        int $attempts = 2,
        \Closure $onRedirect = null
    ): Crawler {
        try {
            return parent::request($method, $uri, $options, $attempts, $onRedirect);
        } catch (RequestException|GuzzleException $e) {
            $code = $e->getCode();
            if ($code === 407 && $attempts > 0) {
                //TODO implement logs?
//                if ($this->command) {
//                    $this->command->error($code . ' - Awaiting proxy configuration');
//                } else {
//                    dump($code . ' - Awaiting proxy configuration');
//                }

                /** @var Instance $instance */
                foreach ($this->proxy->getInstances() as $instance) {
                    $instance->remove();
                }

                $this->proxy->awaitLive();

                return $this->request($method, $uri, $options, $attempts, $onRedirect);
            }

            throw $e;
        }
    }

    public function setProxy(Container $proxy): static
    {
        $this->proxy = $proxy;
        return $this;
    }

}
