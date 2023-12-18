<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

'';

class Notifications extends APIModule
{
    protected $endpointRoutes = ['listNotifications', 'addNotification', 'clearNotifications'];
    private $notifications;
    private $dbConnection;
    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        parent::__construct($request);
        $this->dbConnection = new \frieren\orm\SQLite(self::DATABASE);
        if (!empty($this->dbConnection->error)) {
            $this->responseHandler->setError($this->dbConnection->strError());
            return;
        }
        $this->notifications = [];
        $this->dbConnection->execLegacy("CREATE TABLE IF NOT EXISTS notifications (message VARCHAR NOT NULL, time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP);");
        if (!empty($this->dbConnection->error)) {
            $this->responseHandler->setError($this->dbConnection->strError());
        }
    }

    protected function addNotification($message)
    {
        return $this->dbConnection->execLegacy("INSERT INTO notifications (message) VALUES('%s');", $message);
    }

    protected function getNotifications()
    {
        $result = $this->dbConnection->queryLegacy("SELECT message,time from notifications ORDER BY time DESC;");
        $this->notifications = $result;
        return $this->notifications;
    }

    protected function clearNotifications()
    {
        $result = $this->dbConnection->execLegacy('DELETE FROM notifications;');
        unset($this->notifications);
        return $result;
    }
}
