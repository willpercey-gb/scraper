<?php

namespace UWebPro\Scraper\Provider;

use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\CommunicationException\CannotReadResponse;
use HeadlessChromium\Exception\CommunicationException\InvalidResponse;
use HeadlessChromium\Exception\CommunicationException\ResponseHasError;
use HeadlessChromium\Exception\EvaluationFailed;
use HeadlessChromium\Exception\JavascriptException;
use HeadlessChromium\Exception\NavigationExpired;
use HeadlessChromium\Exception\NoResponseAvailable;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use UWebPro\Crawler\Crawler;
use UWebPro\DOMTransformer\DOMTransformer;
use UWebPro\Scraper\Exceptions\BadRequestException;

class ChromeScraper implements ScraperContract
{
    protected BrowserFactory $client;
    protected ProcessAwareBrowser $browser;
    protected ?\stdClass $params = null;
    protected string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36';
    protected \HeadlessChromium\Page $page;
    protected int $sensitivity = 0;
    protected bool $mustScroll = false;
    protected array $keys = [];
    protected array $allowedCodes = [200, 202, 204];

    public function __construct(?string $chromeBinaryPath = null)
    {
        if (!$chromeBinaryPath) {
            $this->client = PHP_OS !== 'Linux'
                ? new BrowserFactory()
                : new BrowserFactory('/opt/google/chrome/chrome');
        } else {
            $this->client = new BrowserFactory($chromeBinaryPath);
        }

        $this->browser = $this->client->createBrowser(
            [
                'headless' => false,
                'windowSize' => [1920, 1080],
                'enableImages' => true,
                'keepAlive' => false,
            ]
        );
    }

    public function setHasHeadlessDetection(?int $sensitivity = null): self
    {
        $this->sensitivity = $sensitivity ?? 1;
        if ($sensitivity > 2) {
            $this->mustScroll = true;
        }
        return $this;
    }

    public function setUserAgent(?string $value = null): static
    {
        $this->userAgent = $value;
        return $this;
    }

    public function setKeys(array $keys): static
    {
        $this->keys = $keys;
        return $this;
    }

    public function setMustScroll(bool $mustScroll = false): static
    {
        $this->mustScroll = $mustScroll;
        return $this;
    }


    public function get(string $uri, array $parameters = [], array $options = [], int $attempts = 1): Crawler
    {
        //transform parameters into query string
        if (!empty($parameters)) {
            $uri .= '?' . http_build_query($parameters);
        }

        return $this->request('get', $uri, $options, $attempts);
    }

    /**
     * @throws OperationTimedOut
     * @throws NavigationExpired
     * @throws CommunicationException
     * @throws NoResponseAvailable
     * @throws InvalidResponse
     * @throws CannotReadResponse
     * @throws EvaluationFailed
     * @throws ResponseHasError
     * @throws JavascriptException
     */
    public function request(string $method, $uri = '', array $options = [], int $attempts = 1): Crawler
    {
        try {
            $page = $this->browser->createPage();
            $page->setUserAgent($this->userAgent)->await();

            $navigator = $page->navigate($uri);
        } catch (OperationTimedOut $e) {
            if ($attempts <= 0) {
                throw $e;
            }
            return $this->request($method, $uri, $options, $attempts - 1);
        }
        $page->getSession()->on(
            'method:Network.responseReceived',
            function ($params) {
                $this->params = \Safe\json_decode(\Safe\json_encode($params), false);
            }
        );
        $navigator->waitForNavigation();
        $this->page = $page;

        $this->mimicHumanInteraction($page);

        if ($this->params && !in_array($this->params->response?->status, $this->allowedCodes, false)) {
            try {
                if (!$this->sensitivity) {
                    $page->close();
                }
            } catch (\Throwable) {
            }

            throw new BadRequestException(
                'ChromePHP not 200 was ' . $this->params->response?->status,
                $this->params->response?->status
            );
        }

        $innerText = $page->evaluate('document.documentElement.innerText')->getReturnValue();
        $content = DOMTransformer::fromRawHTML($page->getHtml());
        $crawler = new Crawler(
            $content->getDOM()
        );

        try {
            $content = $content->getXML();
            $isJson = (string)$content?->body?->pre;
        } catch (\Throwable $e) {
            return $crawler;
        }

        try {
            if (!$this->sensitivity) {
                $page->close();
            }
        } catch (\Throwable) {
        }

        if ($isJson) {
            return new Crawler($innerText);
        }

        return $crawler;
    }

    public function getPage(): \HeadlessChromium\Page
    {
        return $this->page;
    }

    /**
     * @throws OperationTimedOut
     * @throws NavigationExpired
     * @throws CommunicationException
     * @throws NoResponseAvailable
     * @throws InvalidResponse
     * @throws CannotReadResponse
     * @throws EvaluationFailed
     * @throws ResponseHasError
     * @throws JavascriptException
     */
    public function requestImage(string $method, string $uri): false|string
    {
        $page = $this->browser->createPage();
        $page->setUserAgent($this->userAgent);

        if ($this->sensitivity > 1) {
            $page->setDeviceMetricsOverride(['mobile' => true]);
        }

        $page->addPreScript(
            file_get_contents(
                'https://raw.githubusercontent.com/willpercey-gb/scraper-prescript/main/main.js'
            )
        );
        $navigator = $page->navigate($uri);
        $page->getSession()->on(
            'method:Network.responseReceived',
            function ($params) {
                $this->params = \Safe\json_decode(\Safe\json_encode($params), false);
            }
        );
        $navigator->waitForNavigation();

        if ($this->params && !in_array($this->params->response?->status, [200, 204], false)) {
            try {
                if (!$this->sensitivity) {
                    $page->close();
                }
            } catch (\Throwable) {
            }
            throw new BadRequestException('ChromePHP not 200 was ' . $this->params->response?->status);
        }

        $dataUrl = $page->evaluate('document.getElementsByTagName("img")[0].toDataUrl("image/png")')->getReturnValue();
        $parts = explode(',', $dataUrl, 2);

        $page->close();
        return base64_decode(end($parts));
    }

    public function addAllowedCodes(array $allowedCodes): ChromeScraper
    {
        $this->allowedCodes = array_merge($this->allowedCodes, $allowedCodes);
        return $this;
    }

    private function mimicHumanInteraction(Page $page): void
    {
        if ($this->mustScroll) {
            $height = $page->evaluate('document.body.scrollHeight')->getReturnValue();
            if ($height) {
                $page->keyboard()?->setKeyInterval(1000);
                foreach ($this->keys as $key) {
                    $this->sendKey($key, $page);
                }
                $page->mouse()?->scrollDown((int)$height);

                usleep(random_int(100000, 300000));
                $page->mouse()?->scrollUp(random_int(100, 300));

                usleep(random_int(100000, 300000));
            }
        }

        if ($this->sensitivity > 1) {
            usleep(random_int(100000, 300000));
            $page->mouse()->scrollDown(random_int(50, 300));
            if ($this->sensitivity > 2) {
                usleep(random_int(100000, 300000));
                $page->mouse()->scrollUp(random_int(25, 50));
            }
        }
    }

    private function sendKey(string $key, Page $page): void
    {
        if ($key === 'Enter') {
            $page->getSession()->sendMessageSync(
                new Message('Input.dispatchKeyEvent', [
                    "type" => "rawKeyDown",
                    "windowsVirtualKeyCode" => 13,
                    "unmodifiedText" => "\r",
                    "text" => "\r"
                ])
            );
        } else {
            $page->keyboard()?->typeRawKey($key);
        }
    }
}
