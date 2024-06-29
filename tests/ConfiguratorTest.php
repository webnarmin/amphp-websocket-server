<?php

use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Configurator;

class ConfiguratorTest extends TestCase
{
    public function testDefaultConfiguration()
    {
        $configurator = new Configurator();
        $this->assertEquals('0.0.0.0', $configurator->getWebSocketAddress()['host']);
        $this->assertEquals(8080, $configurator->getWebSocketAddress()['port']);
        $this->assertEquals(['*'], $configurator->getAllowOrigins());
        $this->assertEquals(1000, $configurator->getMaxConnections());
        $this->assertEquals(10, $configurator->getMaxConnectionsPerIp());
        $this->assertEquals(60, $configurator->getTimeout());
    }

    public function testCustomConfiguration()
    {
        $config = [
            'websocket' => [
                'host' => '127.0.0.1',
                'port' => 9000,
            ],
            'allow_origins' => ['http://example.com'],
            'max_connections' => 500,
            'max_connections_per_ip' => 5,
            'timeout' => 30,
        ];
        $configurator = new Configurator($config);
        
        $this->assertEquals('127.0.0.1', $configurator->getWebSocketAddress()['host']);
        $this->assertEquals(9000, $configurator->getWebSocketAddress()['port']);
        $this->assertEquals(['http://example.com'], $configurator->getAllowOrigins());
        $this->assertEquals(500, $configurator->getMaxConnections());
        $this->assertEquals(5, $configurator->getMaxConnectionsPerIp());
        $this->assertEquals(30, $configurator->getTimeout());
    }
}