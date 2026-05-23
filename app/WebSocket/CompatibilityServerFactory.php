<?php

namespace App\WebSocket;

use App\WebSocket\Controllers\CompatibilityWebSocketController;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Laravel\Reverb\Servers\Reverb\Http\Router;
use Laravel\Reverb\Servers\Reverb\Http\Server as HttpServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class CompatibilityServerFactory
{
    public static function make(
        string $host,
        string $port,
        ?string $hostname,
        int $maxRequestSize,
        array $options = [],
        ?LoopInterface $loop = null,
    ): HttpServer {
        $loop = $loop ?: Loop::get();
        $options['tls'] = static::configureTls($options['tls'] ?? [], $hostname);

        $uri = static::usesTls($options['tls']) ? "tls://{$host}:{$port}" : "{$host}:{$port}";
        $router = new Router(new UrlMatcher(static::routes(), new RequestContext));

        return new HttpServer(
            new SocketServer($uri, $options, $loop),
            $router,
            $maxRequestSize,
            $loop,
        );
    }

    private static function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('compat_socket_root', Route::get('/', app(CompatibilityWebSocketController::class)));

        return $routes;
    }

    protected static function configureTls(array $context, ?string $hostname): array
    {
        $context = array_filter($context, fn ($value) => $value !== null);

        if (! static::usesTls($context) && $hostname && \Laravel\Reverb\Certificate::exists($hostname)) {
            [$certificate, $key] = \Laravel\Reverb\Certificate::resolve($hostname);

            $context['local_cert'] = $certificate;
            $context['local_pk'] = $key;
            $context['verify_peer'] = app()->environment() === 'production';
        }

        return $context;
    }

    protected static function usesTls(array $context): bool
    {
        return ($context['local_cert'] ?? false) || ($context['local_pk'] ?? false);
    }
}
