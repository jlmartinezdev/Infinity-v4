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

    /**
     * Ejecuta varios comandos en la misma sesión SSH (un solo login).
     *
     * @param  array<string, string>  $commands
     * @return array<string, string>
     */
    public function execMany(array $commands): array
    {
        $ssh = new SSH2($this->host, $this->port);
        $ssh->setTimeout($this->timeout);

        if (! $ssh->login($this->user, $this->password)) {
            throw new RuntimeException("SSH: no se pudo autenticar en {$this->host}:{$this->port} (usuario {$this->user}).");
        }

        $out = [];
        foreach ($commands as $key => $command) {
            $output = $ssh->exec($command);
            $out[$key] = $output === false ? '' : trim((string) $output);
        }

        return $out;
    }
}
