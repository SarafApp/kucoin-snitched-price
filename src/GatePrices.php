<?php

namespace Saraf;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

class GatePrices
{
    protected AsyncRequestJson $api;

    public function __construct()
    {
        $this->api = new AsyncRequestJson();
        $this->api->setConfig([
            'timeout' => 60
        ]);
    }

    public function start(string $host, string|int $port): void
    {

    }

    public function __invoke(ServerRequestInterface $request): PromiseInterface|Response
    {
        $pairs = @$request->getQueryParams()['pairs'];

        if (empty($pairs))
            return new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'result' => false,
                'error' => 'Error in getting pairs list'
            ]));

        $pairs = explode(",", $pairs);
        if (!is_array($pairs) || count($pairs) == 0)
            return new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'result' => false,
                'error' => 'Error in getting pairs list'
            ]));

        return $this->api
            ->get("https://api.gateio.ws/api/v4/spot/tickers")
            ->then(function ($result) use ($pairs) {
                if (!$result['result'])
                    return new Response(500, ['Content-Type' => 'application/json'], json_encode([
                        'result' => false,
                        'error' => 'Connection Error cause ' . $result['error']
                    ]));

                if ($result['code'] != 200)
                    return new Response($result['code'], ['Content-Type' => 'application/json'], json_encode($result['body']));

                $marketData = [];
                foreach ($result['body'] as $item) {
                    if (!in_array($item['currency_pair'], $pairs))
                        continue;

                    $marketData[$item['currency_pair']] = [
                        'lastPrice' => $item['last'],
                        'askPrice' => $item['lowest_ask'],
                        'bidPrice' => $item['highest_bid'],
                        'changeRate' => $item['change_percentage'],
                        'baseVolume' => $item['base_volume'],
                        'quoteVolume' => $item['quote_volume'],
                        'high24h' => $item['high_24h'],
                        'low24h' => $item['low_24h']
                    ];
                }


                return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'result' => true,
                    'list' => $marketData
                ]));
            });
    }
}