<?php

namespace Saraf;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;

class Prices
{
    protected AsyncRequestJson $api;

    public function __construct()
    {
        $this->api = new AsyncRequestJson();
        $this->api->setConfig([
            'timeout' => 30
        ]);
    }

    public function start(string $host, string|int $port): void
    {
        $loop = Loop::get();
        $http = new HttpServer(
            new StreamingRequestMiddleware(),
            new LimitConcurrentRequestsMiddleware(100),
            new RequestBodyBufferMiddleware(2 * 1024 * 1024), // 2MB
            new RequestBodyParserMiddleware(),
            $this
        );

        $socket = new SocketServer($host . ':' . $port);
        $http->listen($socket);
        $loop->run();
    }

    public function __invoke(ServerRequestInterface $request): PromiseInterface|Response
    {
        return $this->api
            ->get("https://api.kucoin.com/api/v1/market/allTickers")
            ->then(function ($result) {
                if (!$result['result'])
                    return new Response(500, ['Content-Type' => 'application/json'], json_encode([
                        'result' => false,
                        'error' => 'Connection Error cause ' . $result['error']
                    ]));

                if ($result['code'] != 200)
                    return new Response($result['code'], ['Content-Type' => 'application/json'], json_encode($result['body']));

                $okTickers = [];
                foreach ($result['body']['data']['ticker'] as &$item) {
                    if (in_array($item['symbol'], [
                        "BTC-USDT",
                        "ETH-USDT",
                        "PAXG-USDT",
                        "SOL-USDT",
                        "ADA-USDT",
                        "NOT-USDT",
                        "BNB-USDT",
                        "TON-USDT",
                        "DOGE-USDT",
                        "XRP-USDT",
                        "POL-USDT",
                        "ARB-USDT",
                        "UNI-USDT",
                        "AVAX-USDT",
                        "LTC-USDT",
                        "LINK-USDT",
                        "S-USDT",
                        "TRX-USDT",
                        "ICP-USDT",
                        "CAKE-USDT",
                        "DOGS-USDT",
                        "HMSTR-USDT",
                        "WLD-USDT",
                        "XLM-USDT",
                        "FIL-USDT",
                        "HBAR-USDT",
                        "SHIB-USDT",
                        "SAND-USDT",
                        "PEPE-USDT",
                        "SUI-USDT",
                        "ALGO-USDT",
                        "MANA-USDT",
                        "GMT-USDT",
                        "NEAR-USDT",
                        "CRO-USDT",
                        "BONK-USDT",
                        "FET-USDT",
                        "WIF-USDT",
                        "DOT-USDT",
                        "FLOKI-USDT",
                        "LUNA-USDT",
                        "RENDER-USDT",
                    ])) $okTickers[] = $item;
                }


                $result['body']['data']['ticker'] = $okTickers;
                return new Response(200, ['Content-Type' => 'application/json'], json_encode($result['body']));
            });
    }
}