<?php

namespace Illuminate\Database\Swoole;

/**
 * Copyright: Swlib
 * Author: Twosee <twose@qq.com>
 * Modifier: Albert Chen
 * License: Apache 2.0
 */

use Exception;
use PDOStatement;
use PDO as BasePDO;
use Illuminate\Support\Arr;
use Illuminate\Database\QueryException;

/**
 * Class PDO
 */
class PDO extends BasePDO
{
    public static $keyMap = [
        'dbname' => 'database',
    ];

    private static $options = [
        'host' => '',
        'port' => 3306,
        'user' => '',
        'password' => '',
        'database' => '',
        'charset' => 'utf8mb4',
        'strict_type' => true,
        'timeout' => -1,
    ];

    /** @var \Swoole\Coroutine\Mysql */
    public $client;

    public $inTransaction = false;

    /**
     * PDO constructor.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     *
     * @throws ConnectionException
     */
    public function __construct(string $dsn, string $username = '', string $password = '', array $options = [])
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->setClient();
        $this->connect($this->getOptions(...func_get_args()));
    }

    /**
     * @param mixed $client
     */
    protected function setClient($client = null)
    {
        $this->client = $client ?: new \Swoole\Coroutine\Mysql;
    }

    /**
     * @param array $options
     *
     * @return $this
     * @throws ConnectionException
     */
    protected function connect(array $options = [])
    {
        $this->client->connect($options);

        if (!$this->client->connected) {
            $message = $this->client->connect_error ?: $this->client->error;
            $errorCode = $this->client->connect_errno ?: $this->client->errno;

            throw new ConnectionException($message, $errorCode);
        }

        return $this;
    }

    /**
     * @param $dsn
     * @param $username
     * @param $password
     * @param $driverOptions
     *
     * @return array
     */
    protected function getOptions($dsn, $username, $password, $driverOptions)
    {
        $dsn = explode(':', $dsn);
        $driver = ucwords(array_shift($dsn));
        $dsn = explode(';', implode(':', $dsn));
        $configuredOptions = [];

        static::checkDriver($driver);

        foreach ($dsn as $kv) {
            $kv = explode('=', $kv);
            if (count($kv)) {
                $configuredOptions[$kv[0]] = $kv[1] ?? '';
            }
        }

        $authorization = [
            'user' => $username,
            'password' => $password,
        ];

        $configuredOptions = $driverOptions + $authorization + $configuredOptions;

        foreach (static::$keyMap as $pdoKey => $swpdoKey) {
            if (isset($configuredOptions[$pdoKey])) {
                $configuredOptions[$swpdoKey] = $configuredOptions[$pdoKey];
                unset($configuredOptions[$pdoKey]);
            }
        }

        return array_merge(static::$options, $configuredOptions);
    }

    /**
     * @param string $driver
     */
    public static function checkDriver(string $driver)
    {
        if (!in_array($driver, static::getAvailableDrivers())) {
            throw new \InvalidArgumentException("{$driver} driver is not supported yet.");
        }
    }

    /**
     * @return array
     */
    public static function getAvailableDrivers(): array
    {
        return ['Mysql'];
    }

    /**
     * @return bool|void
     */
    public function beginTransaction(): bool
    {
        $bool = $this->client->begin();
        $this->inTransaction = true;
        return $bool;
    }

    /**
     * @return bool
     */
    public function rollBack(): bool
    {
        $rollback = $this->client->rollback();
        $this->inTransaction = false;
        return $rollback;
    }

    /**
     * @return bool
     */
    public function commit(): bool
    {
        $commit = $this->client->commit();
        $this->inTransaction = true;
        return $commit;
    }

    /**
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    /**
     * @param null $seqname
     *
     * @return bool|string
     */
    public function lastInsertId($seqname = null): bool|string
    {
        return $this->client->insert_id;
    }

    /**
     * @return string|null
     */
    public function errorCode(): string|null
    {
        return $this->client->errno;
    }

    /**
     * @return array
     */
    public function errorInfo(): array
    {
        return [
            $this->client->errno,
            $this->client->errno,
            $this->client->error,
        ];
    }

    /**
     * @param string $statement
     *
     * @return int
     */
    public function exec($statement): int
    {
        $this->query($statement);

        return $this->client->affected_rows;
    }

    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetchModeArgs): bool|PDOStatement
    {
        $result = $this->client->query($statement, Arr::get(self::$options, 'timeout'));

        if ($result === false) {
            $exception = new Exception($this->client->error, $this->client->errno);
            throw new QueryException("null", $statement, [], $exception);
        }

        return $result;
    }

    /**
     * @param string $statement
     * @param array $options
     *
     * @return bool|\PDOStatement|PDOStatement
     */
    public function prepare($statement, $options = null): bool|PDOStatement
    {
        $options = is_null($options) ? [] : $options;
        if (strpos($statement, ':') !== false) {
            $i = 0;
            $bindKeyMap = [];
            $statement = preg_replace_callback('/:([a-zA-Z_]\w*?)\b/', function ($matches) use (&$i, &$bindKeyMap) {
                $bindKeyMap[$matches[1]] = $i++;

                return '?';
            }, $statement);
        }

        $stmtObj = $this->client->prepare($statement);

        if ($stmtObj) {
            $stmtObj->bindKeyMap = $bindKeyMap ?? [];

            return new PDOStatement($this, $stmtObj, $options);
        } else {
            $statementException = new StatementException($this->client->error, $this->client->errno);
            throw new QueryException($statement, [], $statementException);
        }
    }

    /**
     * @param int $attribute
     *
     * @return bool|mixed|string
     */
    public function getAttribute($attribute)
    {
        switch ($attribute) {
            case \PDO::ATTR_AUTOCOMMIT:
                return true;
            case \PDO::ATTR_CASE:
            case \PDO::ATTR_CLIENT_VERSION:
            case \PDO::ATTR_CONNECTION_STATUS:
                return $this->client->connected;
            case \PDO::ATTR_DRIVER_NAME:
            case \PDO::ATTR_ERRMODE:
                return 'Swoole Style';
            case \PDO::ATTR_ORACLE_NULLS:
            case \PDO::ATTR_PERSISTENT:
            case \PDO::ATTR_PREFETCH:
            case \PDO::ATTR_SERVER_INFO:
                return self::$options['timeout'];
            case \PDO::ATTR_SERVER_VERSION:
                return 'Swoole Mysql';
            case \PDO::ATTR_TIMEOUT:
            default:
                throw new \InvalidArgumentException('Not implemented yet!');
        }
    }

    /**
     * @param string $string
     * @param null $paramtype
     *
     * @return string|void
     */
    public function quote($string, $paramtype = null)
    {
        throw new \BadMethodCallException(
            <<<TXT
If you are using this function to build SQL statements,
you are strongly recommended to use PDO::prepare() to prepare SQL statements
with bound parameters instead of using PDO::quote() to interpolate user input into an SQL statement.
Prepared statements with bound parameters are not only more portable, more convenient,
immune to SQL injection, but are often much faster to execute than interpolated queries,
as both the server and client side can cache a compiled form of the query.
TXT
        );
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->client->close();
    }
}
