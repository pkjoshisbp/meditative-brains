<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\App;
use React\EventLoop\Loop;
use React\Socket\SecureServer;
use React\Socket\TcpServer;
use App\WebSocket\TtsWebSocketServer;

class TtsWebSocketServe extends Command
{
    protected $signature = 'tts:websocket
                            {--port=8091       : Port to listen on}
                            {--no-tls          : Disable TLS (plain ws://, for local dev only)}
                            {--cert=           : Path to TLS certificate file}
                            {--key=            : Path to TLS private key file}';

    protected $description = 'Start the TTS Ratchet WebSocket server (wss:// via Let\'s Encrypt)';

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
        $wsApp = new HttpServer(new WsServer($wsHandler));

        if ($noTls) {
            // Plain ws:// — local development only
            $this->info("Starting TTS WebSocket server (plain ws://) on port {$port}…");
            $this->warn('TLS disabled — do NOT use this in production.');

            $server = IoServer::factory($wsApp, $port, '0.0.0.0', $loop);
        } else {
            // wss:// — TLS via Let's Encrypt cert
            if (!file_exists($cert)) {
                $this->error("Certificate not found: {$cert}");
                $this->line("Run with --no-tls for local dev, or pass --cert= and --key=");
                return self::FAILURE;
            }
            if (!file_exists($key)) {
                $this->error("Private key not found: {$key}");
                return self::FAILURE;
            }

            $this->info("Starting TTS WebSocket server (wss://) on port {$port}…");
            $this->line("Certificate : {$cert}");
            $this->line("Private key : {$key}");

            try {
                $tcp = new TcpServer("0.0.0.0:{$port}", $loop);
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'EADDRINUSE') || str_contains($e->getMessage(), 'Address already in use')) {
                    $this->error("Port {$port} is already in use. Is another instance running?");
                    $this->line("Run: fuser -k {$port}/tcp   to free it, then restart the service.");
                    return self::FAILURE;
                }
                throw $e;
            }
            $secure = new SecureServer($tcp, $loop, [
                'local_cert'        => $cert,
                'local_pk'          => $key,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => false,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER
                                     | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
            ]);

            $server = new IoServer($wsApp, $secure, $loop);
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

        $server->run();

        return self::SUCCESS;
    }
}
