<?php

namespace Illuminate\Database\Connectors;

//https://github.com/swooletw/laravel-swoole/blob/master/src/Coroutine/Connectors/MySqlConnector.php

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Database\Swoole\PDO as SwoolePDO;
use Illuminate\Database\Connectors\CreateSwoolePDOConnection;

/**
 * Class MySqlConnector (5.6)
 */
class SwooleMySqlConnector extends MySqlConnector
{
    use CreateSwoolePDOConnection;

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