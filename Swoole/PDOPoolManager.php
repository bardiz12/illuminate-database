<?php

namespace Illuminate\Database\Swoole;

// use Swoole\Database\PDOPool;
use Swoole\Database\PDOConfig;
use Psr\Container\ContainerInterface;
use Illuminate\Contracts\Config\Repository;

class PDOPoolManager
{
    protected array $pools = [];
    public function __construct(protected ContainerInterface $c)
    {
        $config = $c->get(Repository::class);

        foreach ($config->get("database.connections") as $name => $config) {
            $pdoConfig = (new PDOConfig())
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
            // $pool->fill();
            $this->pools[$name] = $pool;
        }
    }

    public function getPool($poolName): PDOPool
    {
        return $this->pools[$poolName];
    }

    public function removeIdleConnections()
    {
        $config = $this->c->get(Repository::class);
        foreach ($this->pools as $poolName => $pool) {
            /** @var PDOPool $pool */
            $config = $config->get("database.connections.$poolName");

            $max_open_connections = $config["max_open_connections"] ?? 100;
            $max_idle_connections = $config["max_idle_connections"] ?? 64;
            $min_idle_connections = $config["min_idle_connections"] ?? 2;
            $max_connection_lifetime = $config["max_connection_lifetime"] ?? 72;
            if (!is_int($max_idle_connections)) {
                continue;
            }

            $first = true;
            $length = $pool->length();
            $stats = $pool->getChannel()->stats();
            $loop = 0;
            if ($length > $max_idle_connections) {
                $loop = $length - $max_idle_connections;
            } else {
                $loop = $length;
            }

            // dump($stats);
            if ($loop >= 0) {
                $i = 0;
                while ($i < $loop && $loop !== 0) {
                    /** @var PDOTimed $pdo */
                    if ($first) {
                        $pdo = $pool->get();
                    }

                    if (($pdo->getLifeTime() + $max_connection_lifetime) < microtime(true)) {
                        unset($pdo);
                        $pdo = $pool->get();
                    } else {
                        $pool->put($pdo, false);
                    }
                    $i++;
                }
            }



            // $pdo->
        }
    }
}
