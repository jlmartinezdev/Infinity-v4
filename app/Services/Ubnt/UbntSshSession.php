<?php

namespace App\Services\Ubnt;

use phpseclib3\Net\SSH2;
use RuntimeException;

class UbntSshSession
{
    public function __construct(
        private string $host,
        private int $port = 22,
        private string $user = 'ubnt',
        private string $password = '',
        private int $timeout = 15,
    ) {}

    public function exec(string $command): string
    {
        $ssh = new SSH2($this->host, $this->port);
        $ssh->setTimeout($this->timeout);

        if (! $ssh->login($this->user, $this->password)) {
            throw new RuntimeException("SSH: no se pudo autenticar en {$this->host}:{$this->port} (usuario {$this->user}).");
        }

        $output = $ssh->exec($command);
        if ($output === false) {
            throw new RuntimeException("SSH: falló la ejecución de «{$command}» en {$this->host}.");
        }

        return trim((string) $output);
    }
}
