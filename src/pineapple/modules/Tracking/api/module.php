<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Tracking extends SystemModule
{
    protected $endpointRoutes = ['getScript', 'saveScript', 'getTrackingList', 'addMac', 'removeMac', 'clearMacs'];
    const DATABASE = "/etc/pineapple/filters.db";

    private $dbConnection = null;

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;
        if (file_exists(self::DATABASE)) {
            $this->dbConnection = new \frieren\orm\SQLite(self::DATABASE);
        }
    }

    private function getScript()
    {
        $trackingScript = file_get_contents("/etc/pineapple/tracking_script_user");
        $this->responseHandler->setData(["trackingScript" => $trackingScript]);
    }

    private function saveScript()
    {
        if (isset($this->request['trackingScript'])) {
            file_put_contents("/etc/pineapple/tracking_script_user", $this->request['trackingScript']);
        }
        $this->responseHandler->setData(["success" => true]);
    }

    private function getTrackingList()
    {
        $trackingList = "";
        $result = $this->dbConnection->queryLegacy("SELECT mac FROM tracking;");

        foreach ($result as $row) {
            $trackingList .= $row['mac'] . "\n";
        }
        $this->responseHandler->setData(["trackingList" => $trackingList]);
    }

    private function addMac()
    {
        if (isset($this->request['mac']) && !empty($this->request['mac'])) {
            $mac = strtoupper($this->request['mac']);
            if(preg_match('^[a-fA-F0-9:]{17}|[a-fA-F0-9]{12}^', $mac)) {
                $this->dbConnection->execLegacy("INSERT INTO tracking (mac) VALUES ('%s');", $mac);
                $this->getTrackingList();
            } else {
                $this->responseHandler->setError("Please enter a valid MAC Address");
            }
        }
    }

    private function removeMac()
    {
        if (isset($this->request['mac']) && !empty($this->request['mac'])) {
            $mac = strtoupper($this->request['mac']);
            if(preg_match('^[a-fA-F0-9:]{17}|[a-fA-F0-9]{12}^', $mac)) {
                $this->dbConnection->execLegacy("DELETE FROM tracking WHERE mac='%s' COLLATE NOCASE;", $mac);
                $this->getTrackingList();
            } else {
                $this->responseHandler->setError("Please enter a valid MAC Address");
            }
        }
    }

    private function clearMacs()
    {
        $this->dbConnection->execLegacy("DELETE FROM tracking;");
        $this->getTrackingList();
    }
}
