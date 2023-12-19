<?php

namespace frieren\core;

class Notifications extends Controller
{
    const DATABASE = "/etc/pineapple/pineapple.db";
    public $endpointRoutes = ['listNotifications', 'addNotification', 'clearNotifications'];
    public $orm;

    public function __construct($request)
    {
        $this->orm = new \frieren\orm\SQLite(self::DATABASE);
        $this->orm->exec("CREATE TABLE IF NOT EXISTS notifications (message VARCHAR NOT NULL, time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP);");

        parent::__construct($request);
    }

    public function listNotifications()
    {
        $notifications = $this->orm->query("SELECT message, time FROM notifications ORDER BY time DESC;");
        return $this->responseHandler->setData($notifications);
    }

    public function addNotification()
    {
        if (!isset($this->request['message'])) {
            return $this->responseHandler->setError('Message is required');
        }

        $result = $this->orm->insert("notifications", ['message' => $this->request['message']]);
        return $this->responseHandler->setData(['success' => $result]);
    }

    public function clearNotifications()
    {
        $result = $this->orm->delete('notifications');
        return $this->responseHandler->setData(['success' => $result]);
    }
}
