<?php

namespace App\Console\Commands;

use App\WebSocket\CompatibilityServerFactory;
use Illuminate\Console\Command;
use React\EventLoop\Loop;
use React\Socket\TcpServer;
use App\WebSocket\TtsWebSocketServer;

class TtsWebSocketServe extends Command
{
    protected $signature = 'tts:websocket
                            {--port=8091       : Port to listen on}
                            {--no-tls          : Disable TLS (plain ws://, for local dev only)}
                            {--cert=           : Path to TLS certificate file}
                            {--key=            : Path to TLS private key file}';
    protected $description = 'Start the TTS Reverb compatibility WebSocket server (wss:// via Let\'s Encrypt)';

    /** Default cert paths — ISPConfig Let's Encrypt location for mentalfitness.store */
    private const DEFAULT_CERT = '/var/www/mentalfitness.store/ssl/mentalfitness.store-le.crt';
    private const DEFAULT_KEY  = '/var/www/mentalfitness.store/ssl/mentalfitness.store-le.key';

    public function handle(): int
    {
        $port   = (int) $this->option('port');
        $noTls  = (bool) $this->option('no-tls');
        $cert   = $this->option('cert') ?: self::DEFAULT_CERT;
        $key    = $this->option('key')  ?: self::DEFAULT_KEY;

        $loop = Loop::get();
        $wsHandler = new TtsWebSocketServer();
        app()->instance(TtsWebSocketServer::class, $wsHandler);
        $tlsOptions = [];

        if ($noTls) {
            $this->info("Starting TTS WebSocket compatibility server (plain ws://) on port {$port}…");
            $this->warn('TLS disabled — do NOT use this in production.');
        } else {
            if (!file_exists($cert)) {
                $this->error("Certificate not found: {$cert}");
                $this->line("Run with --no-tls for local dev, or pass --cert= and --key=");
                return self::FAILURE;
            }
            if (!file_exists($key)) {
                $this->error("Private key not found: {$key}");
                return self::FAILURE;
            }

            $this->info("Starting TTS WebSocket compatibility server (wss://) on port {$port}…");
            $this->line("Certificate : {$cert}");
            $this->line("Private key : {$key}");

            $tlsOptions = [
                'local_cert'        => $cert,
                'local_pk'          => $key,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => false,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
                                     | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
            ];
        }

        $hostname = $noTls ? null : parse_url(config('app.url', 'https://mentalfitness.store'), PHP_URL_HOST);
        $servers = [];
        $listenHosts = ['[::]', '0.0.0.0'];

        foreach ($listenHosts as $listenHost) {
            try {
                $server = CompatibilityServerFactory::make(
                    $listenHost,
                    (string) $port,
                    $hostname,
                    10_000,
                    ['tls' => $tlsOptions],
                    $loop,
                );
                $servers[] = $server;
                $this->line("Listening on {$listenHost}:{$port}");
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                $alreadyInUse = str_contains($message, 'EADDRINUSE') || str_contains($message, 'Address already in use');

                if ($alreadyInUse && !empty($servers)) {
                    $this->warn("Skipping {$listenHost}:{$port} because an existing listener already covers it.");
                    continue;
                }

                if ($alreadyInUse) {
                    $this->error("Port {$port} is already in use. Is another instance running?");
                    $this->line("Run: fuser -k {$port}/tcp   to free it, then restart the service.");
                    return self::FAILURE;
                }

                if ($listenHost === '[::]') {
                    $this->warn("IPv6 listener unavailable: {$message}");
                    continue;
                }

                throw $e;
            }
        }

        if (empty($servers)) {
            $this->error('Could not start any websocket listener.');
            return self::FAILURE;
        }

        $this->line('Press Ctrl+C to stop.');

        // ── Internal TCP push channel (127.0.0.1:8092) ───────────────────────
        // AuthController writes newline-delimited JSON here; this loop
        // reads it and forwards to the SMS gateway WebSocket connection.
        $pushPort = (int) env('SMS_PUSH_PORT', 8092);
        try {
            $pushTcp = new TcpServer("127.0.0.1:{$pushPort}", $loop);
            $pushTcp->on('connection', function (\React\Socket\ConnectionInterface $pushConn) use ($wsHandler) {
                $buffer = '';
                $pushConn->on('data', function (string $chunk) use ($wsHandler, $pushConn, &$buffer) {
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line   = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $decoded = json_decode(trim($line), true);
                        if (is_array($decoded)) {
                            $wsHandler->pushSmsEvent($decoded);
                        }
                    }
                    $pushConn->close();
                });
            });
            $this->line("SMS push channel listening on 127.0.0.1:{$pushPort}");
        } catch (\RuntimeException $e) {
            $this->warn("Could not bind SMS push port {$pushPort}: " . $e->getMessage());
        }

        if ((bool) env('WS_LEGACY_CATALOG_PUSH_ENABLED', false)) {
            $loop->addPeriodicTimer(5, function () use ($wsHandler) {
                try {
                    $wsHandler->broadcastCatalogUpdatesIfNeeded();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[WS][CATALOG] Periodic broadcast check failed: ' . $e->getMessage());
                }
            });
        } else {
            \Illuminate\Support\Facades\Log::info('[WS][CATALOG] Legacy websocket catalog push disabled; using Reverb only');
        }

        foreach ($servers as $server) {
            $server->start();
        }

        return self::SUCCESS;
    }
}
