<?php

namespace App\Support;

use RuntimeException;

class DotEnvWriter
{
    public function __construct(
        protected string $path
    ) {}

    public function set(string $key, string $value): void
    {
        if (! is_file($this->path)) {
            throw new RuntimeException('.env no encontrado');
        }

        $content = file_get_contents($this->path) ?: '';
        $line = $key.'='.$this->quote($value);

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $content)) {
            $content = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $content) ?? $content;
        } else {
            $content = rtrim($content, "\r\n")."\n".$line."\n";
        }

        file_put_contents($this->path, $content);
    }

    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
