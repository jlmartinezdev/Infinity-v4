<?php

namespace App\Services\Olt;

use RuntimeException;

/**
 * Cliente Telnet simple para CLI VSOL GPON.
 */
class VsolTelnetSession
{
    private const IAC = 255;

    private const DONT = 254;

    private const DO = 253;

    private const WONT = 252;

    private const WILL = 251;

    private $stream;

    private string $buffer = '';

    private float $lastWriteAt = 0.0;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $connectTimeoutSeconds = 15,
        private readonly int $commandPauseMs = 80,
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
        @stream_set_write_buffer($this->stream, 0);

        // Keepalive ayuda en Windows cuando el OLT cierra por idle/firewall.
        $socket = @socket_import_stream($this->stream);
        if ($socket !== false && $socket !== null) {
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        }

        // Negociación Telnet / banner inicial
        $this->read(2);
    }

    public function login(string $username, string $password, ?string $enablePassword = null): void
    {
        $this->waitFor(['Username:', 'username:', 'Login:', 'login:'], 20);
        $this->pace();
        $this->writeLine($username);

        $this->waitFor(['Password:', 'password:'], 20);
        $this->pace();
        $this->writeLine($password);

        $this->waitFor(['>', '#', 'Password:'], 25);

        $tail = substr($this->buffer, -200);
        if (stripos($tail, 'Password:') !== false && stripos($tail, '>') === false) {
            throw new RuntimeException('Credenciales Telnet incorrectas.');
        }

        $this->pace();
        $this->writeLine('enable');
        $this->waitFor(['Password:', '#', '>'], 15);

        if (stripos(substr($this->buffer, -120), 'Password:') !== false) {
            $this->pace();
            $this->writeLine($enablePassword ?: $password);
            $this->waitFor(['#', '>'], 15);
        }

        $this->pace();
        $this->writeLine('configure terminal');
        $this->waitFor(['(config', '#'], 15);

        // Desactivar pager si el firmware lo soporta (evita --More-- y cortes).
        try {
            $this->pace();
            $this->exec('terminal length 0', 6);
        } catch (RuntimeException) {
            // Algunos VSOL no aceptan el comando; seguir igual.
        }
    }

    public function exec(string $command, int $timeoutSeconds = 60): string
    {
        $this->pace();
        $this->writeLine($command);

        return $this->readUntilPrompt($timeoutSeconds);
    }

    public function isConnected(): bool
    {
        if (! is_resource($this->stream)) {
            return false;
        }

        $meta = @stream_get_meta_data($this->stream);
        if (($meta['eof'] ?? false) === true) {
            return false;
        }

        return ! feof($this->stream);
    }

    public function disconnect(): void
    {
        if (is_resource($this->stream)) {
            // No forzar writes si el socket ya murió (evita otro 10053).
            if ($this->isConnected()) {
                @fwrite($this->stream, "exit\r\n");
                usleep(50000);
            }
            @fclose($this->stream);
        }
        $this->stream = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function pace(): void
    {
        if ($this->commandPauseMs <= 0) {
            return;
        }
        $minGap = $this->commandPauseMs / 1000;
        $elapsed = microtime(true) - $this->lastWriteAt;
        if ($this->lastWriteAt > 0 && $elapsed < $minGap) {
            usleep((int) (($minGap - $elapsed) * 1_000_000));
        }
    }

    private function writeLine(string $line): void
    {
        $this->writeRaw($line."\r\n");
    }

    private function writeRaw(string $payload): void
    {
        if (! is_resource($this->stream)) {
            $this->failBrokenConnection('escritura');
        }

        // Peelear el socket antes de escribir: si el peer ya cerró, no disparamos fwrite.
        $read = [$this->stream];
        $write = null;
        $except = null;
        if (@stream_select($read, $write, $except, 0, 0) > 0) {
            $peek = @fread($this->stream, 8192);
            if ($peek === false || $peek === '' && feof($this->stream)) {
                $this->failBrokenConnection('escritura');
            }
            if (is_string($peek) && $peek !== '') {
                $this->buffer .= $this->stripTelnetIac($peek);
            }
        }

        if (! $this->isConnected()) {
            $this->failBrokenConnection('escritura');
        }

        $written = @fwrite($this->stream, $payload);
        $this->lastWriteAt = microtime(true);
        @fflush($this->stream);

        if ($written === false || ($written === 0 && $payload !== '')) {
            $this->failBrokenConnection('escritura');
        }
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function waitFor(array $needles, int $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            $this->read(1);
            if (! is_resource($this->stream)) {
                $this->failBrokenConnection('sesión');
            }
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
        $emptyReads = 0;

        while (microtime(true) < $deadline) {
            $chunk = $this->read(1);
            if ($chunk === '') {
                $emptyReads++;
                if (! is_resource($this->stream) || (($emptyReads > 3) && ! $this->isConnected())) {
                    $this->failBrokenConnection('lectura');
                }
                continue;
            }
            $emptyReads = 0;
            $output .= $chunk;

            if (preg_match('/--\s*more\s*--/i', $output)) {
                $output = preg_replace('/--\s*more\s*--/i', '', $output) ?? $output;
                usleep(30000);
                $this->writeRaw(' ');
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

        stream_set_timeout($this->stream, max(1, (int) ceil($timeoutSeconds)));
        $chunk = @fread($this->stream, 8192);
        if ($chunk === false || $chunk === '') {
            return '';
        }

        $chunk = $this->stripTelnetIac($chunk);
        if ($chunk === '') {
            return '';
        }

        $this->buffer .= $chunk;

        return $chunk;
    }

    /**
     * Responde negativamente a opciones Telnet (DO/WILL) y limpia IAC del buffer de texto.
     */
    private function stripTelnetIac(string $data): string
    {
        $out = '';
        $len = strlen($data);
        $replies = '';

        for ($i = 0; $i < $len; $i++) {
            $byte = ord($data[$i]);
            if ($byte !== self::IAC) {
                $out .= $data[$i];
                continue;
            }

            if ($i + 1 >= $len) {
                break;
            }

            $cmd = ord($data[++$i]);
            if ($cmd === self::IAC) {
                $out .= chr(self::IAC);
                continue;
            }

            // DO/DONT/WILL/WONT + opción
            if (in_array($cmd, [self::DO, self::DONT, self::WILL, self::WONT], true)) {
                if ($i + 1 >= $len) {
                    break;
                }
                $opt = ord($data[++$i]);
                if ($cmd === self::DO) {
                    $replies .= chr(self::IAC).chr(self::WONT).chr($opt);
                } elseif ($cmd === self::WILL) {
                    $replies .= chr(self::IAC).chr(self::DONT).chr($opt);
                }
                continue;
            }

            // SB ... SE u otros: saltar de forma conservadora
            if ($cmd === 250) { // SB
                while ($i + 1 < $len) {
                    $i++;
                    if (ord($data[$i]) === self::IAC && $i + 1 < $len && ord($data[$i + 1]) === 240) {
                        $i++;
                        break;
                    }
                }
            }
        }

        if ($replies !== '' && is_resource($this->stream)) {
            @fwrite($this->stream, $replies);
            $this->lastWriteAt = microtime(true);
        }

        return $out;
    }

    private function failBrokenConnection(string $fase): never
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }
        $this->stream = null;

        throw new RuntimeException(
            'La conexión Telnet con el OLT se interrumpió durante la '.$fase
            .' (socket cerrado por el equipo, red, firewall o antivirus). '
            .'Reintentá la consulta; si se repite, verificá que Telnet siga habilitado y que el OLT no esté saturado.'
        );
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
            if (preg_match('/\([^)]*config[^)]*\)\s*#\s*$/i', $trim)) {
                continue;
            }
            if (preg_match('/^Password:\s*$/i', $trim)) {
                continue;
            }
            if (preg_match('/^show\s+onu\b/i', $trim) && ! preg_match('/GPON\d+\/\d+:\d+/i', $trim)) {
                if (preg_match('/^show\s+onu\s+\d+\s+(desc|description|optical)/i', $trim)
                    || preg_match('/^show\s+onu\s+(desc|description|optical_info|optical-info|info|state|opm-diag)\b/i', $trim)) {
                    continue;
                }
            }
            $clean[] = $line;
        }

        return trim(implode("\n", $clean));
    }
}
