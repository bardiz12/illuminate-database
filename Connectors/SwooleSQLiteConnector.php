<?php

namespace Illuminate\Database\Connectors;

use Illuminate\Database\Swoole\PDO as SwoolePDO;
use Illuminate\Database\Connectors\CreateSwoolePDOConnection;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;

class SwooleSQLiteConnector extends Connector implements ConnectorInterface
{
    use CreateSwoolePDOConnection;

    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \PDO
     *
     * @throws \Illuminate\Database\SQLiteDatabaseDoesNotExistException
     */
    public function connect(array $config)
    {
        $options = $this->getOptions($config);

        // SQLite supports "in-memory" databases that only last as long as the owning
        // connection does. These are useful for tests or for short lifetime store
        // querying. In-memory databases may only have a single open connection.
        if ($config['database'] === ':memory:') {
            $path = 'sqlite::memory:';
        } else {
            $path = realpath($config['database']);
        }


        // Here we'll verify that the SQLite database exists before going any further
        // as the developer probably wants to know if the database exists and this
        // SQLite driver will not throw any exception if it does not by default.
        if ($path === false) {
            throw new SQLiteDatabaseDoesNotExistException($config['database']);
        }

        // $this->createConnection("sqlite:{$path}", $config, $options);
        return new SwoolePDO("sqlite:{$path}", '', '', $options);
    }
}
