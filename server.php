<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Socket\SocketServer;
use Saraf\GatePrices;
use Saraf\KucoinPrices;

require "vendor/autoload.php";

$loop = Loop::get();

$kucoinPrices = new KucoinPrices();
$gatePrices = new GatePrices();


$http = new HttpServer(
    new StreamingRequestMiddleware(),
    new LimitConcurrentRequestsMiddleware(100),
    new RequestBodyBufferMiddleware(2 * 1024 * 1024), // 2MB
    new RequestBodyParserMiddleware(),

    function (ServerRequestInterface $request) {
        global $kucoinPrices, $gatePrices;
        $path = $request->getUri()->getPath();

        return match ($path) {
            '/kucoin' => $kucoinPrices($request),
            '/gate' => $gatePrices($request),

            default => new Response(
                404,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'success' => false,
                    'message' => 'Route not found',
                ])
            ),
        };
    }
);

$socket = new SocketServer("127.0.0.1:9898");
$http->listen($socket);

echo "Start Running on http://0.0.0.0:9898" . PHP_EOL;
$loop->run();
