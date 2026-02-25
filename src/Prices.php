<?php

namespace Saraf;

require_once __DIR__ . '/pairs.php';

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
                    if (in_array($item['symbol'], PAIRS)) {
                        /*
                         * Kucoin response:
                         * {
                                "code": "200000",
                                "data": {
                                    "time": 1729173207043,
                                    "ticker": [
                                        {
                                            "symbol": "BTC-USDT",
                                            "symbolName": "BTC-USDT",
                                            "buy": "67192.5",
                                            "bestBidSize": "0.000025",
                                            "sell": "67192.6",
                                            "bestAskSize": "1.24949204",
                                            "changeRate": "-0.0014",
                                            "changePrice": "-98.5",
                                            "high": "68321.4",
                                            "low": "66683.3",
                                            "vol": "1836.03034612",
                                            "volValue": "124068431.06726933",
                                            "last": "67193",
                                            "averagePrice": "67281.21437289",
                                            "takerFeeRate": "0.001",
                                            "makerFeeRate": "0.001",
                                            "takerCoefficient": "1",
                                            "makerCoefficient": "1"
                                        }
                                    ]
                                }
                            }
                         */

                        $okTickers[] = [
                            'symbol' => $item['symbol'],
                            'buy' => $item['buy'],
                            'sell' => $item['sell'],
                            'changeRate' => $item['changeRate']
                        ];
                    }
                }


                $result['body']['data']['ticker'] = $okTickers;
                return new Response(200, ['Content-Type' => 'application/json'], json_encode($result['body']));
            });
    }
}