<?php

namespace Illuminate\Database\Swoole;

use Swoole\Database\PDOPool;

class PDOTimed extends \PDO
{
    protected float $time;

    public function getLifeTime(): float
    {
        return $this->time;
    }

    public function touch()
    {
        $this->time = microtime(true);
    }
}
