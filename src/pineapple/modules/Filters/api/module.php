<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Filters extends Controller
{
    protected $endpointRoutes = ['getClientData', 'getSSIDData', 'toggleClientMode', 'toggleSSIDMode', 'addClient', 'addClients', 'addSSID', 'removeClient', 'removeSSID', 'removeSSIDs', 'removeClients'];
    private $dbConnection;
    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;
        $dbPath = '/etc/pineapple/filters.db';
        if (file_exists($dbPath)) {
            $this->dbConnection = new \frieren\orm\SQLite($dbPath);
        }
    }

    private function getSSIDMode()
    {
        if (exec("hostapd_cli -i wlan0 karma_get_black_white") === "WHITE") {
            return "Allow";
        }
        return "Deny";
    }

    private function getClientMode()
    {
        if (exec("hostapd_cli -i wlan0 karma_get_mac_black_white") === "WHITE") {
            return "Allow";
        }
        return "Deny";
    }

    private function getSSIDFilters()
    {
        $ssidFilters = "";
        $rows = $this->dbConnection->queryLegacy("SELECT * FROM ssid_filter_list;");
        if (!isset($rows['databaseQueryError'])) {
            foreach ($rows as $row) {
                $ssidFilters .= "${row['ssid']}\n";
            }
        }
        return $ssidFilters;
    }

    private function getClientFilters()
    {
        $clientFilters = "";
        $rows = $this->dbConnection->queryLegacy("SELECT * FROM mac_filter_list;");
        if (!isset($rows['databaseQueryError'])) {
            foreach ($rows as $row) {
                $clientFilters .= "${row['mac']}\n";
            }
        }
        return $clientFilters;
    }

    private function toggleClientMode()
    {
        $value = ($this->request['mode'] === 'Allow') ? 'white' : 'black';
        exec("pineap /tmp/pineap.conf mac_filter {$value}");
        $this->systemHelper->uciSet('pineap.@config[0].mac_filter', $value);
    }

    private function toggleSSIDMode()
    {
        $value = ($this->request['mode'] === 'Allow') ? 'white' : 'black';
        exec("pineap /tmp/pineap.conf ssid_filter {$value}");
        $this->systemHelper->uciSet('pineap.@config[0].ssid_filter', $value);
    }

    private function getClientData()
    {
        $mode = $this->getClientMode();
        $filters = $this->getClientFilters();
        $this->responseHandler->setData(array("mode" => $mode, "clientFilters" => $filters));
    }

    private function getSSIDData()
    {
        $mode = $this->getSSIDMode();
        $filters = $this->getSSIDFilters();
        $this->responseHandler->setData(array("mode" => $mode, "ssidFilters" => $filters));
    }


    private function addSSID()
    {
        if (!empty($this->request['ssid'])) {
            $ssid_array = is_array($this->request['ssid']) ? $this->request['ssid'] : array($this->request['ssid']);
            foreach ($ssid_array as $ssid) {
                if (!empty($ssid)) {
                    @$this->dbConnection->execLegacy('INSERT INTO ssid_filter_list (ssid) VALUES (\'%s\')', $ssid);
                }
            }
            $this->getSSIDData();
        }
    }

    private function removeSSID()
    {
        if (isset($this->request['ssid'])) {
            $ssid = $this->request['ssid'];
            $this->dbConnection->execLegacy('DELETE FROM ssid_filter_list WHERE ssid=\'%s\'', $ssid);
            $this->getSSIDData();
        }
    }

    private function removeSSIDs()
    {
        if (isset($this->request['ssids']) && is_array($this->request['ssids'])) {
            foreach ($this->request['ssids'] as $ssid) {
                if (!empty($ssid)) {
                    $this->dbConnection->execLegacy('DELETE FROM ssid_filter_list WHERE ssid=\'%s\'', $ssid);
                }
            }
        }
        $this->getSSIDData();
    }

    private function addClient()
    {
        if (!empty($this->request['mac'])) {
            $mac_array = is_array($this->request['mac']) ? $this->request['mac'] : array($this->request['mac']);
            foreach ($mac_array as $mac) {
                if (!empty($mac) && $mac != '00:00:00:00:00:00') {
                    $mac = strtoupper(trim($mac));
                        @$this->dbConnection->execLegacy('INSERT INTO mac_filter_list (mac) VALUES (\'%s\')', $mac);
                }
            }
            $this->getClientData();
        }
    }

    private function addClients()
    {
        if (isset($this->request['clients']) && is_array($this->request['clients'])) {
            foreach ($this->request['clients'] as $client) {
                if (!empty($client) && $client != '00:00:00:00:00:00') {
                    $mac = strtoupper(trim($client));
                    @$this->dbConnection->execLegacy('INSERT INTO mac_filter_list (mac) VALUES (\'%s\')', $mac);
                }
            }
        }
        $this->getClientData();
    }

    private function removeClient()
    {
        if (isset($this->request['mac'])) {
            $mac = strtoupper(trim($this->request['mac']));
            $this->dbConnection->execLegacy('DELETE FROM mac_filter_list WHERE mac=\'%s\'', $mac);
            $this->getClientData();
        }
    }

    private function removeClients()
    {
        if (isset($this->request['clients']) && is_array($this->request['clients'])) {
            foreach ($this->request['clients'] as $client) {
                if (!empty($client)) {
                    $mac = strtoupper(trim($client));
                    $this->dbConnection->execLegacy('DELETE FROM mac_filter_list WHERE mac=\'%s\'', $mac);
                }
            }
        }
        $this->getClientData();
    }
}
