<?php

namespace App\Services\Huawei;

use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class HuaweiOnuCli
{
    public function __construct(
        private string $host,
        private string $user,
        private string $password,
        private int $sshPort = 22,
        private int $telnetPort = 23,
        private int $timeout = 12,
    ) {}

    /**
     * @param  list<string>  $commands
     * @param  (callable(list<string>): list<string>)|null  $followUp
     * @return array{via: string, outputs: list<string>}
     */
    public function run(array $commands, ?callable $followUp = null): array
    {
        try {
            return ['via' => 'ssh', 'outputs' => $this->runSsh($commands, $followUp)];
        } catch (Throwable $e) {
            try {
                return ['via' => 'telnet', 'outputs' => $this->runTelnet($commands, $followUp)];
            } catch (Throwable $telnet) {
                throw new RuntimeException(
                    'No se pudo entrar a la ONU Huawei en '.$this->host
                    .' (SSH: '.$e->getMessage().' · Telnet: '.$telnet->getMessage().').'
                );
            }
        }
    }

    /**
     * @param  list<string>  $commands
     * @param  (callable(list<string>): list<string>)|null  $followUp
     * @return list<string>
     */
    protected function runSsh(array $commands, ?callable $followUp = null): array
    {
        $ssh = new SSH2($this->host, $this->sshPort, $this->timeout);
        $ssh->setTimeout($this->timeout);
        if (! $ssh->login($this->user, $this->password)) {
            throw new RuntimeException('autenticación SSH rechazada');
        }

        $banner = $ssh->read('/WAP>|Login:/i', SSH2::READ_REGEX);
        if (preg_match('/Login:/i', $banner) && ! preg_match('/WAP>/', $banner)) {
            $ssh->write($this->user."\n");
            $ssh->read('/Password:/i', SSH2::READ_REGEX);
            $ssh->write($this->password."\n");
            $after = $this->leerHastaPromptSsh($ssh);
            if (preg_match('/wrong/i', $after)) {
                throw new RuntimeException('login WAP interno rechazado');
            }
        } elseif (! preg_match('/WAP>/', $banner)) {
            $this->leerHastaPromptSsh($ssh);
        }

        $ssh->write("\n");
        $this->leerHastaPromptSsh($ssh);

        $outputs = $this->ejecutarListaSsh($ssh, $commands);
        if ($followUp) {
            $mas = $followUp($outputs);
            if (is_array($mas) && $mas !== []) {
                $outputs = array_merge($outputs, $this->ejecutarListaSsh($ssh, $mas));
            }
        }
        $ssh->write("exit\n");
        $ssh->disconnect();

        return $outputs;
    }

    /**
     * Envía reboot (no factory reset). La sesión suele caer: eso cuenta como enviado.
     *
     * @return array{via: string, outputs: list<string>}
     */
    public function reboot(): array
    {
        try {
            return ['via' => 'ssh', 'outputs' => $this->rebootSsh()];
        } catch (Throwable $e) {
            try {
                return ['via' => 'telnet', 'outputs' => $this->rebootTelnet()];
            } catch (Throwable $telnet) {
                throw new RuntimeException(
                    'No se pudo reiniciar la ONU Huawei en '.$this->host
                    .' (SSH: '.$e->getMessage().' · Telnet: '.$telnet->getMessage().').'
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function rebootSsh(): array
    {
        $ssh = new SSH2($this->host, $this->sshPort, $this->timeout);
        $ssh->setTimeout($this->timeout);
        if (! $ssh->login($this->user, $this->password)) {
            throw new RuntimeException('autenticación SSH rechazada');
        }

        $banner = $ssh->read('/WAP>|Login:/i', SSH2::READ_REGEX);
        if (preg_match('/Login:/i', $banner) && ! preg_match('/WAP>/', $banner)) {
            $ssh->write($this->user."\n");
            $ssh->read('/Password:/i', SSH2::READ_REGEX);
            $ssh->write($this->password."\n");
            $this->leerHastaPromptSsh($ssh);
        } elseif (! preg_match('/WAP>/', $banner)) {
            $this->leerHastaPromptSsh($ssh);
        }
        $ssh->write("\n");
        $this->leerHastaPromptSsh($ssh);

        $ssh->setTimeout(8);
        $buf = '';
        try {
            $ssh->write("reboot\n");
            $buf = $this->leerHastaConfirmacionOCaidaSsh($ssh);
            if (preg_match('/\(y\/n\)|yes\/no|\[Y\/N\]/i', $buf)) {
                $ssh->write("y\n");
                $buf .= $this->leerHastaConfirmacionOCaidaSsh($ssh);
            }
        } catch (Throwable) {
        }
        try {
            $ssh->disconnect();
        } catch (Throwable) {
        }

        return [$this->limpiarSalida($buf)];
    }

    /**
     * @return list<string>
     */
    protected function rebootTelnet(): array
    {
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            'tcp://'.$this->host.':'.$this->telnetPort,
            $errno,
            $errstr,
            $this->timeout
        );
        if (! is_resource($fp)) {
            throw new RuntimeException($errstr !== '' ? $errstr : 'puerto Telnet cerrado');
        }
        stream_set_timeout($fp, $this->timeout);
        $this->telnetReadUntil($fp, 'Login:');
        $this->telnetWrite($fp, $this->user);
        $this->telnetReadUntil($fp, 'Password:');
        $this->telnetWrite($fp, $this->password);
        $login = $this->telnetReadUntil($fp, 'WAP>');
        if (stripos($login, 'wrong') !== false) {
            fclose($fp);
            throw new RuntimeException('usuario o clave WAP incorrectos');
        }

        $this->telnetWrite($fp, 'reboot');
        $buf = $this->telnetLeerHastaCaida($fp);
        if (preg_match('/\(y\/n\)|yes\/no|\[Y\/N\]/i', $buf)) {
            $this->telnetWrite($fp, 'y');
            $buf .= $this->telnetLeerHastaCaida($fp);
        }
        if (is_resource($fp)) {
            fclose($fp);
        }

        return [$this->limpiarSalida($buf)];
    }

    protected function leerHastaConfirmacionOCaidaSsh(SSH2 $ssh): string
    {
        try {
            return (string) $ssh->read('/WAP>|\(y\/n\)|yes\/no|\[Y\/N\]/i', SSH2::READ_REGEX);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param  resource  $fp
     */
    protected function telnetLeerHastaCaida($fp): string
    {
        $buf = '';
        $deadline = microtime(true) + 8;
        while (microtime(true) < $deadline) {
            $chunk = @fread($fp, 2048);
            if ($chunk === false || $chunk === '') {
                if (feof($fp)) {
                    break;
                }
                usleep(80000);

                continue;
            }
            $buf .= $this->stripTelnetIac($chunk);
            if (preg_match('/WAP>|\(y\/n\)|yes\/no|\[Y\/N\]/i', $buf)) {
                break;
            }
        }

        return $buf;
    }

    /**
     * @param  list<string>  $commands
     * @return list<string>
     */
    protected function ejecutarListaSsh(SSH2 $ssh, array $commands): array
    {
        $outputs = [];
        foreach ($commands as $cmd) {
            $ssh->write($cmd."\n");
            $outputs[] = $this->limpiarSalida($this->leerHastaPromptSsh($ssh));
        }

        return $outputs;
    }

    /**
     * @param  list<string>  $commands
     * @param  (callable(list<string>): list<string>)|null  $followUp
     * @return list<string>
     */
    protected function runTelnet(array $commands, ?callable $followUp = null): array
    {
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            'tcp://'.$this->host.':'.$this->telnetPort,
            $errno,
            $errstr,
            $this->timeout
        );
        if (! is_resource($fp)) {
            throw new RuntimeException($errstr !== '' ? $errstr : 'puerto Telnet cerrado');
        }
        stream_set_timeout($fp, $this->timeout);

        $this->telnetReadUntil($fp, 'Login:');
        $this->telnetWrite($fp, $this->user);
        $this->telnetReadUntil($fp, 'Password:');
        $this->telnetWrite($fp, $this->password);
        $login = $this->telnetReadUntil($fp, 'WAP>');
        if (stripos($login, 'wrong') !== false) {
            fclose($fp);
            throw new RuntimeException('usuario o clave WAP incorrectos');
        }

        $outputs = $this->ejecutarListaTelnet($fp, $commands);
        if ($followUp) {
            $mas = $followUp($outputs);
            if (is_array($mas) && $mas !== []) {
                $outputs = array_merge($outputs, $this->ejecutarListaTelnet($fp, $mas));
            }
        }
        $this->telnetWrite($fp, 'exit');
        fclose($fp);

        return $outputs;
    }

    /**
     * @param  resource  $fp
     * @param  list<string>  $commands
     * @return list<string>
     */
    protected function ejecutarListaTelnet($fp, array $commands): array
    {
        $outputs = [];
        foreach ($commands as $cmd) {
            $this->telnetWrite($fp, $cmd);
            $outputs[] = $this->limpiarSalida($this->telnetReadUntil($fp, 'WAP>'));
        }

        return $outputs;
    }

    protected function leerHastaPromptSsh(SSH2 $ssh): string
    {
        $buf = '';
        $deadline = microtime(true) + $this->timeout;
        while (microtime(true) < $deadline) {
            $restante = max(1, (int) ceil($deadline - microtime(true)));
            $ssh->setTimeout($restante);
            $chunk = (string) $ssh->read('/WAP>|--More--/i', SSH2::READ_REGEX);
            $buf .= $chunk;
            if (preg_match('/--More--/i', $chunk)) {
                $ssh->write(' ');
                continue;
            }
            if (preg_match('/WAP>/', $chunk)) {
                return $buf;
            }
        }

        throw new RuntimeException('timeout esperando «WAP>»');
    }

    protected function telnetWrite($fp, string $line): void
    {
        fwrite($fp, $line."\r\n");
    }

    protected function telnetReadUntil($fp, string $needle): string
    {
        $buf = '';
        $deadline = microtime(true) + $this->timeout;
        while (microtime(true) < $deadline) {
            $chunk = fread($fp, 2048);
            if ($chunk === false || $chunk === '') {
                if (feof($fp)) {
                    break;
                }
                usleep(80000);

                continue;
            }
            $buf .= $this->stripTelnetIac($chunk);
            if (stripos($buf, $needle) !== false) {
                return $buf;
            }
        }

        throw new RuntimeException('timeout esperando «'.$needle.'»');
    }

    protected function stripTelnetIac(string $data): string
    {
        $out = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            if (ord($data[$i]) === 255 && $i + 1 < $len) {
                $cmd = ord($data[$i + 1]);
                $i += ($cmd >= 251 && $cmd <= 254 && $i + 2 < $len) ? 2 : 1;

                continue;
            }
            $out .= $data[$i];
        }

        return $out;
    }

    protected function limpiarSalida(string $raw): string
    {
        $raw = str_replace(["\x07", "\r"], '', $raw);
        $raw = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $raw) ?? $raw;

        return trim($raw);
    }
}
