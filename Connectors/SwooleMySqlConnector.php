<?php

namespace Illuminate\Database\Connectors;

//https://github.com/swooletw/laravel-swoole/blob/master/src/Coroutine/Connectors/MySqlConnector.php

use Illuminate\Support\Str;
use Illuminate\Database\Swoole\PDO as SwoolePDO;
use Throwable;

/**
 * Class MySqlConnector (5.6)
 */
class SwooleMySqlConnector extends MySqlConnector
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

    /**
     * Handle an exception that occurred during connect execution.
     *
     * @param \Throwable $e
     * @param  string $dsn
     * @param  string $username
     * @param  string $password
     * @param  array $options
     *
     * @return \PDO
     * @throws \Throwable
     */
    protected function tryAgainIfCausedByLostConnection(Throwable $e, $dsn, $username, $password, $options)
    {
        // https://github.com/swoole/swoole-src/blob/a414e5e8fec580abb3dbd772d483e12976da708f/swoole_mysql_coro.c#L196
        if ($this->causedByLostConnection($e) || Str::contains($e->getMessage(), 'is closed')) {
            return $this->createPdoConnection($dsn, $username, $password, $options);
        }

        throw $e;
    }
}