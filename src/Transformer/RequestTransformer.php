<?php

use UWebPro\Scraper\RequestProtocol;

class RequestTransformer
{
    public static function transform(
        RequestProtocol $request,
        array $replacements = [],
        int $iteration = null
    ): RequestProtocol {
        foreach (self::replacements($iteration) as $find => $replacement) {
            $request->url = str_replace('{' . $find . '}', $replacement, $request->url);
        }
        foreach ($replacements as $find => $replacement) {
            $request->url = str_replace('{' . $find . '}', $replacement, $request->url);
        }

        return $request;
    }

    protected static function replacements($i = 0): array
    {
        return [
            'microtime' => str_replace('.', '', (string)microtime(true)),
            'iteration' => $i,
        ];
    }
}
