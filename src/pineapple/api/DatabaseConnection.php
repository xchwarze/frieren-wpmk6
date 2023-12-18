<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class DatabaseConnection
{
    private $databaseFile;
    private $dbConnection;
    public $error;

    public function __construct($databaseFile)
    {
        $this->responseHandler->setError([]);
        $this->databaseFile = $databaseFile;
        try {
            $this->dbConnection = new \frieren\orm\SQLite($this->databaseFile);
            $this->dbConnection->busyTimeout(20000);
        } catch (\Exception $e) {
            $this->error["databaseConnectionError"] = $e->getMessage();
        }
    }

    protected function strError()
    {
        foreach ($this->error as $errorType => $errorMessage) {
            switch ($errorType) {
                case 'databaseConnectionError':
                    return "Could not connect to database: $errorMessage";
                case 'databaseExecutionError':
                case 'databaseQueryError':
                    return "Could not execute query: $errorMessage";
                default:
                    return "Unknown database error";
            }
        }

        return true;
    }

    protected function getDatabaseFile()
    {
        return $this->databaseFile;
    }

    protected function getDbConnection()
    {
        return $this->dbConnection;
    }

    protected static function formatQuery(...$query)
    {
        $query = $query[0];
        $sqlQuery = $query[0];
        $sqlParameters = array_slice($query, 1);
        if (empty($sqlParameters)) {
            return $sqlQuery;
        }
        for ($i = 0; $i < count($sqlParameters); ++$i) {
            if (gettype($sqlParameters[$i]) === "string") {
                $escaped = \SQLite3::escapeString($sqlParameters[$i]);
                $sqlParameters[$i] = $escaped;
            }
        }
        return vsprintf($sqlQuery, $sqlParameters);
    }

    protected function query(...$query)
    {
        $safeQuery = DatabaseConnection::formatQuery($query);
        $result = $this->dbConnection->queryLegacy($safeQuery);
        if (!$result) {
            $this->error['databaseQueryError'] = $this->dbConnection->lastErrorMsg();
            return $this->error;
        }
        $resultArray = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $resultArray[] = $row;
        }
        return $resultArray;
    }

    protected function exec(...$query)
    {
        $safeQuery = DatabaseConnection::formatQuery($query);
        try {
            $result = $this->dbConnection->execLegacy($safeQuery);
        } catch (\Exception $e) {
            $this->error['databaseExecutionError'] = $e;
            return $this->error;
        }
        return ['success' => $result];
    }

    public function __destruct()
    {
        if ($this->dbConnection) {
            $this->dbConnection->close();
        }
    }
}
