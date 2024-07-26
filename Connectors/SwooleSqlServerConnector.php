<?php

namespace Illuminate\Database\Connectors;

use Illuminate\Database\Connectors\CreateSwoolePDOConnection;
use Illuminate\Support\Arr;
use PDO;

class SwooleSqlServerConnector extends SwooleSqlServerConnector
{
    use CreateSwoolePDOConnection;
}
