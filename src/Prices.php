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
            ->get("https://api.kucoin.com/api/v1/market/allTickers")
            ->then(function ($result) use ($pairs) {
                if (!$result['result'])
                    return new Response(500, ['Content-Type' => 'application/json'], json_encode([
                        'result' => false,
                        'error' => 'Connection Error cause ' . $result['error']
                    ]));

                if ($result['code'] != 200)
                    return new Response($result['code'], ['Content-Type' => 'application/json'], json_encode($result['body']));

                $okTickers = [];
                foreach ($result['body']['data']['ticker'] as &$item) {
                    if (in_array($item['symbol'], $pairs)) {
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