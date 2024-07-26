<?php

namespace Illuminate\Database\Connectors;

use Illuminate\Database\Concerns\ParsesSearchPath;
use Illuminate\Database\Connectors\CreateSwoolePDOConnection;
use PDO;

class SwoolePostgresConnector extends PostgresConnector
{
    use CreateSwoolePDOConnection;
}
