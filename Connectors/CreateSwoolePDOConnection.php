<?php

namespace Illuminate\Database\Connectors;

use Illuminate\Database\Swoole\PDO as SwoolePDO;

trait CreateSwoolePDOConnection
{
    /**
     * Create a new PDO connection instance.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     *
     * @return \PDO
     * @throws \Illuminate\Database\Swoole\ConnectionException
     */
    protected function createPdoConnection($dsn, $username, $password, $options)
    {
        return new SwoolePDO($dsn, $username, $password, $options);
    }
}
