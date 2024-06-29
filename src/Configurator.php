<?php declare(strict_types=1);

namespace webnarmin\AmphpWS;

class Configurator
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'websocket' => [
                'host' => '0.0.0.0',
                'port' => 8080,
                'use_ssl' => false,
                'ssl_cert' => null,
                'ssl_key' => null,
            ],
            'allow_origins' => ['*'],
            'max_connections' => 1000,
            'max_connections_per_ip' => 10,
            'timeout' => 60,
        ], $config);
    }

    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Configuration file not found: $filePath");
        }

        $config = require $filePath;
        if (!is_array($config)) {
            throw new \RuntimeException("Configuration file must return an array");
        }

        return new self($config);
    }

    public function getWebSocketAddress(): array
    {
        return [
            'host' => $this->config['websocket']['host'],
            'port' => $this->config['websocket']['port'],
        ];
    }

    public function getAllowOrigins(): array
    {
        return $this->config['allow_origins'];
    }

    public function isUseSSL(): bool
    {
        return $this->config['websocket']['use_ssl'] ?? false;
    }

    public function getSSLCertFile(): ?string
    {
        return $this->config['websocket']['ssl_cert'] ?? null;
    }

    public function getSSLKeyFile(): ?string
    {
        return $this->config['websocket']['ssl_key'] ?? null;
    }

    public function getMaxConnections(): int
    {
        return $this->config['max_connections'];
    }

    public function getMaxConnectionsPerIp(): int
    {
        return $this->config['max_connections_per_ip'];
    }

    public function getTimeout(): int
    {
        return $this->config['timeout'];
    }

    public function getFullConfig(): array
    {
        return $this->config;
    }
}