<?php

namespace App\Services\Olt;

use RuntimeException;

/**
 * Cliente Telnet simple para CLI VSOL GPON.
 */
class VsolTelnetSession
{
    private $stream;

    private string $buffer = '';

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $connectTimeoutSeconds = 15,
    ) {}

    public function connect(): void
    {
        $errno = 0;
        $errstr = '';
        $target = "tcp://{$this->host}:{$this->port}";
        $this->stream = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $this->connectTimeoutSeconds,
            STREAM_CLIENT_CONNECT
        );

        if (! is_resource($this->stream)) {
            throw new RuntimeException("No se pudo conectar por Telnet a {$this->host}:{$this->port} — {$errstr} ({$errno})");
        }

        stream_set_timeout($this->stream, $this->connectTimeoutSeconds);
        stream_set_blocking($this->stream, true);
        $this->read(3);
    }

    public function login(string $username, string $password, ?string $enablePassword = null): void
    {
        $this->waitFor(['Username:', 'username:', 'Login:', 'login:'], 20);
        $this->writeLine($username);

        $this->waitFor(['Password:', 'password:'], 20);
        $this->writeLine($password);

        $this->waitFor(['>', '#', 'Password:'], 25);

        $tail = substr($this->buffer, -200);
        if (stripos($tail, 'Password:') !== false && stripos($tail, '>') === false) {
            throw new RuntimeException('Credenciales Telnet incorrectas.');
        }

        $this->writeLine('enable');
        $this->waitFor(['Password:', '#', '>'], 15);

        if (stripos(substr($this->buffer, -120), 'Password:') !== false) {
            $this->writeLine($enablePassword ?: $password);
            $this->waitFor(['#', '>'], 15);
        }

        $this->writeLine('configure terminal');
        $this->waitFor(['(config', '#'], 15);
    }

    public function exec(string $command, int $timeoutSeconds = 60): string
    {
        $this->writeLine($command);

        return $this->readUntilPrompt($timeoutSeconds);
    }

    public function disconnect(): void
    {
        if (is_resource($this->stream)) {
            @fwrite($this->stream, "exit\r\n");
            @fclose($this->stream);
        }
        $this->stream = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function writeLine(string $line): void
    {
        if (! is_resource($this->stream)) {
            throw new RuntimeException('Sesión Telnet cerrada.');
        }
        fwrite($this->stream, $line."\r\n");
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function waitFor(array $needles, int $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            $this->read(1);
            foreach ($needles as $needle) {
                if (stripos($this->buffer, $needle) !== false) {
                    return;
                }
            }
        }

        throw new RuntimeException('Tiempo de espera agotado esperando respuesta del OLT.');
    }

    private function readUntilPrompt(int $timeoutSeconds): string
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $output = '';

        while (microtime(true) < $deadline) {
            $chunk = $this->read(2);
            if ($chunk === '') {
                continue;
            }
            $output .= $chunk;

            if (preg_match('/--\s*more\s*--/i', $output)) {
                $output = preg_replace('/--\s*more\s*--/i', '', $output) ?? $output;
                $this->writeLine(' ');
                continue;
            }

            if (preg_match('/\r?\n[^\r\n]*(?:\(config[^)]*\)|gpon-olt|GPON-OLT|epon-olt)[^\r\n]*#\s*$/i', $output)) {
                break;
            }
        }

        return $this->cleanCliOutput($output);
    }

    private function read(float $timeoutSeconds): string
    {
        if (! is_resource($this->stream)) {
            return '';
        }

        stream_set_timeout($this->stream, (int) ceil($timeoutSeconds));
        $chunk = @fread($this->stream, 8192);
        if ($chunk === false || $chunk === '') {
            return '';
        }

        $this->buffer .= $chunk;

        return $chunk;
    }

    private function cleanCliOutput(string $text): string
    {
        $text = preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', $text) ?? $text;
        $text = preg_replace('/\x08+\s*\x08+/', '', $text) ?? $text;
        $text = preg_replace('/--\s*more\s*--/i', '', $text) ?? $text;
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (preg_match('/^(gpon-olt|GPON-OLT|epon-olt|DHAKA|VSOL).*(>|#)/i', $trim)) {
                continue;
            }
            if (preg_match('/^Password:\s*$/i', $trim)) {
                continue;
            }
            $clean[] = $line;
        }

        return trim(implode("\n", $clean));
    }
}
