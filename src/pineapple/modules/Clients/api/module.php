<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Clients extends Controller
{
    protected $endpointRoutes = ['getClientData', 'kickClient'];
    private $dbConnection;

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;

        $dbLocation = $this->systemHelper->uciGet("pineap.@config[0].hostapd_db_path");
        if (file_exists($dbLocation)) {
            $this->dbConnection = new \frieren\orm\SQLite($dbLocation);
        }
    }

    private function getLeases() {
        $dhcpReport = array();
        $leases = explode("\n", @file_get_contents('/var/dhcp.leases'));
        if ($leases) {
            foreach ($leases as $lease) {
                $dhcpReport[explode(' ', $lease)[1]] = array_slice(explode(' ', $lease), 2, 2);
            }
        }
        return $dhcpReport;
    }

    private function getARPData() {
        $arpReport = array();
        exec('cat /proc/net/arp | awk \'{ if ($1 != "IP") {printf "%s %s\n", $1, $4;}}\'', $arpEntries);
        foreach ($arpEntries as $arpEntry) {
            $arpEntryArray = explode(' ', $arpEntry);
            $arpReport[$arpEntryArray[1]] = $arpEntryArray[0];
        }
        return $arpReport;
    }

    private function getSSIDData()
    {
        $ssidData = array();
        $clientRows = $this->dbConnection->queryLegacy("SELECT DISTINCT mac,ssid FROM log WHERE log_type=1 ORDER BY updated_at ASC;");
        foreach ($clientRows as $row) {
            $ssidData[strtolower($row['mac'])] = $row['ssid'];
        }
        return $ssidData;
    }

    private function getStations() {
        $stationsReport = array();
        exec('
            iw dev wlan0 station dump |
            awk \'{ if ($1 == "Station") { printf "%s ", $2; } else if ($1 == "inactive") {print $3;} }\'
        ', $stations);
        foreach ($stations as $key => $station) {
            if (empty($station)) {
                continue;
            }
            $stationArray = explode(' ', $station);
            $stationsReport[$stationArray[0]] = $stationArray[1];
        }
        exec('
            iw dev wlan0-2 station dump |
            awk \'{ if ($1 == "Station") { printf "%s ", $2; } else if ($1 == "inactive") {print $3;} }\'
        ', $stations);
        foreach ($stations as $key => $station) {
            if (empty($station)) {
                continue;
            }
            $stationArray = explode(' ', $station);
            $stationsReport[$stationArray[0]] = $stationArray[1];
        }
        return $stationsReport;
    }

    private function getClientData()
    {
        $connectedClients = array();
        $stationData = $this->getStations();
        $dhcpData = $this->getLeases();
        $arpData = $this->getARPData();
        $ssidData = $this->getSSIDData();
        foreach ($stationData as $mac => $signal) {
            $connectedClients[] = [
                'mac' => $mac,
                'ip' => $arpData[$mac],
                'ssid' => $ssidData[$mac],
                'host' => $dhcpData[$mac][1],
            ];
        }
        $this->responseHandler->setData(array(
            'clients' => $connectedClients
        ));
    }

    private function kickClient()
    {
        exec("hostapd_cli -i wlan0 deauthenticate {$this->request['mac']}");
        exec("hostapd_cli -i wlan0 disassociate {$this->request['mac']}");
        exec("hostapd_cli -i wlan0-2 deauthenticate {$this->request['mac']}");
        exec("hostapd_cli -i wlan0-2 disassociate {$this->request['mac']}");
        $this->responseHandler->setData(array('success' => true));
    }
}