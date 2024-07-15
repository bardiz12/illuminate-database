<?php

namespace Illuminate\Database\Swoole;

use Swoole\ConnectionPool;
use Swoole\Coroutine\Channel;
use Swoole\Database\PDOProxy;
use Swoole\Database\PDOConfig;

class PDOPool extends ConnectionPool
{
    protected int $counter = 0;
    public function __construct(protected PDOConfig $config, int $size = self::DEFAULT_SIZE)
    {
        parent::__construct(function () {
            $driver = $this->config->getDriver();
            if ($driver === 'sqlite') {
                return new \PDO($this->createDSN('sqlite'));
            }

            $pdo = new PDOTimed($this->createDSN($driver), $this->config->getUsername(), $this->config->getPassword(), $this->config->getOptions());
            $pdo->touch();
            return $pdo;
        }, $size, PDOProxy::class);
    }

    public function get(float $timeout = -1)
    {
        $pdo = parent::get($timeout);
        if ($pdo != false) {
            /* @var \Swoole\Database\PDOProxy $pdo */
            $pdo->reset();
        }

        return $pdo;
    }

    public function put($connection, $touch = true): void
    {
        parent::put($connection);
        if ($touch) {
            $connection->touch();
        }
    }

    public function getConnectionCount(): int
    {
        return $this->num;
    }

    public function decreaseConnectionCount()
    {
        $this->num--;
    }

    public function getChannel(): ?Channel
    {
        return $this->pool;
    }
    public function stats(): array
    {
        return $this->pool->stats();
    }

    public function length(): int
    {
        return $this->pool->length();
    }

    /**
     * @purpose create DSN
     * @throws \Exception
     */
    private function createDSN(string $driver): string
    {
        switch ($driver) {
            case 'mysql':
                if ($this->config->hasUnixSocket()) {
                    $dsn = "mysql:unix_socket={$this->config->getUnixSocket()};dbname={$this->config->getDbname()};charset={$this->config->getCharset()}";
                } else {
                    $dsn = "mysql:host={$this->config->getHost()};port={$this->config->getPort()};dbname={$this->config->getDbname()};charset={$this->config->getCharset()}";
                }
                break;
            case 'pgsql':
                $dsn = 'pgsql:host=' . ($this->config->hasUnixSocket() ? $this->config->getUnixSocket() : $this->config->getHost()) . ";port={$this->config->getPort()};dbname={$this->config->getDbname()}";
                break;
            case 'oci':
                $dsn = 'oci:dbname=' . ($this->config->hasUnixSocket() ? $this->config->getUnixSocket() : $this->config->getHost()) . ':' . $this->config->getPort() . '/' . $this->config->getDbname() . ';charset=' . $this->config->getCharset();
                break;
            case 'sqlite':
                $dsn = 'sqlite:' . $this->config->getDbname();
                break;
            default:
                throw new \Exception('Unsupported Database Driver:' . $driver);
        }
        return $dsn;
    }
}
