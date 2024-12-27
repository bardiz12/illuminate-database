<?php

namespace Illuminate\Database\Swoole;

// use Swoole\Database\PDOPool;
use Illuminate\Contracts\Config\Repository;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOProxy;

class PDOPoolManager
{
    protected array $pools = [];
    public function __construct(protected ContainerInterface $c)
    {
        $config = $c->get(Repository::class);

        foreach ($config->get("database.connections") as $name => $config) {
            $pdoConfig = (new PDOConfig())
                ->withDriver($config["driver"])
                ->withHost($config["host"])
                ->withPort($config["port"])
                ->withDbName($config["database"])
                ->withCharset($config["charset"] ?? 'utf8mb4')
                ->withUsername($config["username"])
                ->withPassword($config["password"])
                ->withOptions($config["options"] ?? []);

            $pool = new PDOPool(
                $pdoConfig,
                $config["max_open_connections"] ?? PDOPool::DEFAULT_SIZE
            );
            $this->pools[$name] = $pool;
        }
    }

    public function getPool($poolName): PDOPool
    {
        return $this->pools[$poolName];
    }

    public function removeConnection(int $count, PDOPool $pool, \Closure|null $filter = null): int
    {
        $deleted = 0;
        for ($i = 0; $i < $count; $i++) {
            $pdo = $pool->get(0.001);
            if ($pdo === false) {
                continue;
            }
            $isDelete = $filter === null ? true : $filter($pdo);

            if (!$isDelete) {
                $pool->put($pdo, false);
                continue;
            }

            unset($pdo);
            $pool->decreaseConnectionCount();
        }

        return $deleted;
    }

    public function removeIdleConnections()
    {
        $config = $this->c->get(Repository::class);
        foreach ($this->pools as $poolName => $pool) {
            /** @var PDOPool $pool */
            $dbConfig = $config->get("database.connections.$poolName");

            $max_idle_connections = $dbConfig["max_idle_connections"] ?? 64;
            $max_connection_lifetime = $dbConfig["max_connection_lifetime"] ?? 72;
            if (!is_int($max_idle_connections)) {
                continue;
            }

            $currentCount = $pool->getConnectionCount();
            
            if ($max_idle_connections < $currentCount) {
                $idleToRemove = $currentCount - $max_idle_connections;
                $this->removeConnection(
                    count: $idleToRemove,
                    pool: $pool,
                    filter: fn (PDOTimed|PDOProxy $pdo) => $pdo->getLifeTime() + 10 < microtime(true)
                );
            }

            $toCheck = $pool->length();

            if ($toCheck > 0) {
                $this->removeConnection(
                    count: $toCheck,
                    pool: $pool,
                    filter: fn (PDOTimed|PDOProxy $pdo) => $pdo->getLifeTime() + $max_connection_lifetime < microtime(true)
                );
            }
        }
    }
}
