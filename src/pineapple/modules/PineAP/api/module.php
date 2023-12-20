<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

require_once('PineAPHelper.php');

class PineAP extends Controller
{
    public $endpointRoutes = ['getPool', 'clearPool', 'addSSID', 'addSSIDs', 'removeSSID', 'getPoolLocation', 'setPoolLocation', 'clearSessionCounter', 'setPineAPSettings', 'getPineAPSettings', 'setEnterpriseSettings', 'getEnterpriseSettings', 'detectEnterpriseCertificate', 'generateEnterpriseCertificate', 'clearEnterpriseCertificate', 'clearEnterpriseDB', 'getEnterpriseData', 'startHandshakeCapture', 'stopHandshakeCapture', 'getHandshake', 'getAllHandshakes', 'checkCaptureStatus', 'downloadHandshake', 'downloadAllHandshakes', 'clearAllHandshakes', 'deleteHandshake', 'deauth', 'enable', 'disable', 'enableAutoStart', 'disableAutoStart', 'downloadPineAPPool', 'loadProbes', 'inject', 'countSSIDs', 'downloadJTRHashes', 'downloadHashcatHashes'];
    const EAP_USER_FILE = "/etc/pineape/hostapd-pineape.eap_user";

    public $pineAPHelper;
    public $dbConnection;

    public function __construct($request)
    {
        $this->dbConnection = false;
        $this->pineAPHelper = new \frieren\helper\PineAPHelper();

        parent::__construct($request);
    }

    private function setupDB()
    {
        $dbLocation = $this->systemHelper->uciGet("pineap.@config[0].ssid_db_path");
        if (file_exists($dbLocation)) {
            $this->dbConnection = new \frieren\orm\SQLite($dbLocation);
        }
    }

    public function toggleComment($fileName, $lineNumber, $comment)
    {
        $data = file_get_contents($fileName);
        $lines = explode("\n", $data);
        $line = $lines[$lineNumber - 1];
        if (substr($line, 0, 1) === "#") {
            if ($comment) {
                return;
            }
            $line = substr($line, 1);
        } else {
            if (!$comment) {
                return;
            }
            $line = '#' . $line;
        }
        $lines[$lineNumber - 1] = $line;
        file_put_contents($fileName, join("\n", $lines));
    }

    public function isCommented($fileName, $lineNumber)
    {
        $data = file_get_contents($fileName);
        $lines = explode("\n", $data);
        $line = $lines[$lineNumber - 1];
        return substr($line, 0, 1) === "#";
    }

    public function getDowngradeType()
    {
        if (!$this->isCommented(PineAP::EAP_USER_FILE, 6)) {
            return "MSCHAPV2";
        } else if (!$this->isCommented(PineAP::EAP_USER_FILE, 5)) {
            return "GTC";
        }
        return "DISABLE";
    }

    public function enableMSCHAPV2Downgrade()
    {
        $this->toggleComment(PineAP::EAP_USER_FILE, 4, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 5, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 6, false);
    }

    public function enableGTCDowngrade()
    {
        $this->toggleComment(PineAP::EAP_USER_FILE, 4, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 5, false);
        $this->toggleComment(PineAP::EAP_USER_FILE, 6, true);
    }

    public function disableDowngrade()
    {
        $this->toggleComment(PineAP::EAP_USER_FILE, 4, false);
        $this->toggleComment(PineAP::EAP_USER_FILE, 5, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 6, true);
    }

    public function loadProbes()
    {
        if (!$this->systemHelper->checkRunning("/usr/sbin/pineapd", true)) {
            $this->responseHandler->setData(array('success' => false, 'reason' => "not running"));
            return;
        }

        $mac = strtolower($this->request['mac']);
        $probesArray = array();
        exec("/usr/bin/pineap list_probes ${mac}", $output);
        foreach ($output as $probeSSID) {
            $probesArray[] = $probeSSID;
        }

        $this->responseHandler->setData(array('success' => true, 'probes' => implode("\n", array_unique($probesArray))));
    }

    public function downloadPineAPPool()
    {
        $poolLocation = '/tmp/ssid_pool.txt';
        $data = $this->getPoolData();
        file_put_contents($poolLocation, $data);
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile($poolLocation)));
    }

    public function countSSIDs()
    {
        $this->responseHandler->setData(array(
            'SSIDs' => substr_count($this->getPoolData(), "\n"),
            'newSSIDs' => substr_count($this->getNewPoolData(), "\n")
        ));
    }

    public function enable()
    {
        $this->pineAPHelper->enablePineAP();
        $this->responseHandler->setData(array("success" => true));
    }

    public function disable()
    {
        $this->pineAPHelper->disablePineAP();
        $this->responseHandler->setData(array("success" => true));
    }

    public function enableAutoStart()
    {
        $this->systemHelper->uciSet("pineap.@config[0].autostart", 1);
        $this->responseHandler->setData(array("success" => true));
    }

    public function disableAutoStart()
    {
        $this->systemHelper->uciSet("pineap.@config[0].autostart", 0);
        $this->responseHandler->setData(array("success" => true));
    }

    public function checkPineAP()
    {
        if (!$this->systemHelper->checkRunning('/usr/sbin/pineapd', true)) {
            $this->responseHandler->setData(array('error' => 'Please start PineAP', 'success' => false));
            return false;
        }
        return true;
    }

    public function deauth()
    {
        if ($this->checkPineAP()) {
            $sta = $this->request['sta'];
            $clients = $this->request['clients'];
            $multiplier = intval($this->request['multiplier']);
            $channel = $this->request['channel'];
            $success = false;

            if (empty($clients)) {
                $this->responseHandler->setData(array('error' => 'This AP has no clients', 'success' => false));
                return;
            }

            foreach ($clients as $client) {
                $mac = $client;
                if (isset($client->mac)) {
                    $mac = $client->mac;
                }
                $success = $this->pineAPHelper->deauth($mac, $sta, $channel, $multiplier);
            }

            if ($success) {
                $this->responseHandler->setData(array('success' => true));
            }
        } else {
            $this->responseHandler->setData(array('error' => 'Please start PineAP', 'success' => false));
        }
    }

    public function getPoolData()
    {
        $this->setupDB();
        $ssidPool = "";
        $rows = $this->dbConnection->queryLegacy('SELECT * FROM ssids;');
        if (!isset($rows['databaseQueryError'])) {
            foreach ($rows as $row) {
                $ssidPool .= $row['ssid'] . "\n";
            }
        }
        return $ssidPool;
    }

    public function getNewPoolData()
    {
        $this->setupDB();
        $ssidPool = "";
        $rows = $this->dbConnection->queryLegacy('SELECT * FROM ssids WHERE new_ssid=1;');
        if (!isset($rows['databaseQueryError'])) {
            foreach ($rows as $row) {
                $ssidPool .= $row['ssid'] . "\n";
            }
        }
        return $ssidPool;
    }

    public function getPool()
    {
        $this->responseHandler->setData(array('ssidPool' => $this->getPoolData(), 'success' => true));
    }

    public function clearPool()
    {
        $this->checkPineAP();
        $this->setupDB();
        $this->dbConnection->queryLegacy('DELETE FROM ssids;');
        $this->responseHandler->setData(array('success' => true));
    }

    public function addSSID()
    {
        $this->checkPineAP();
        $this->setupDB();
        $ssid = $this->request['ssid'];
        $created_date = date('Y-m-d H:i:s');
        if (strlen($ssid) < 1 || strlen($ssid) > 32) {
            $this->responseHandler->setError('Your SSID must have a length greater than 1 and less than 32.');
        } else {
            @$this->dbConnection->queryLegacy("INSERT INTO ssids (ssid, created_at) VALUES ('%s', '%s')", $ssid, $created_date);
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function addSSIDs()
    {
        $this->checkPineAP();
        $this->setupDB();
        $ssidList = $this->request['ssids'];
        $created_date = date('Y-m-d H:i:s');

        foreach ($ssidList as $ssid) {
            if (strlen($ssid) >= 1 && strlen($ssid) <= 32) {
                @$this->dbConnection->queryLegacy("INSERT INTO ssids (ssid, created_at) VALUES ('%s', '%s');", $ssid, $created_date);
            }
        }
        $this->responseHandler->setData(array('success' => true));
    }

    public function removeSSID()
    {
        $this->checkPineAP();
        $this->setupDB();
        $ssid = $this->request['ssid'];
        if (strlen($ssid) < 1 || strlen($ssid) > 32) {
            $this->responseHandler->setError('Your SSID must have a length greater than 1 and less than 32.');
        } else {
            $this->dbConnection->queryLegacy("DELETE FROM ssids WHERE ssid='%s';", $ssid);
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function getPoolLocation()
    {
        $dbBasePath = dirname($this->systemHelper->uciGet("pineap.@config[0].ssid_db_path"));
        $this->responseHandler->setData(array('poolLocation' => $dbBasePath . "/"));
    }

    public function setPoolLocation()
    {
        $dbLocation = dirname($this->request['location'] . '/fake_file');
        $this->systemHelper->uciSet("pineap.@config[0].ssid_db_path", $dbLocation . '/pineapple.db');
        $this->responseHandler->setData(array('success' => true));
    }

    public function clearSessionCounter()
    {
        $ret = 0;
        $output = array();
        exec('/usr/sbin/resetssids', $output, $ret);
        if ($ret !== 0) {
            $this->responseHandler->setError("Could not clear SSID session counter.");
        } else {
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function getPineAPSettings()
    {
        $sourceMAC = $this->pineAPHelper->getSource();
        $sourceMAC = $sourceMAC === false ? '00:00:00:00:00:00' : $sourceMAC;

        $targetMAC = $this->pineAPHelper->getTarget();
        $targetMAC = $targetMAC === false ? 'FF:FF:FF:FF:FF:FF' : $targetMAC;

        $settings = array(
            'pineAPDaemon' => $this->systemHelper->checkRunning("pineapd"),
            'autostartPineAP' => $this->systemHelper->uciGet("pineap.@config[0].autostart"),
            'allowAssociations' => $this->pineAPHelper->getSetting("karma"),
            'logEvents' => $this->pineAPHelper->getSetting("logging"),
            'beaconResponses' => $this->pineAPHelper->getSetting("beacon_responses"),
            'captureSSIDs' => $this->pineAPHelper->getSetting("capture_ssids"),
            'broadcastSSIDs' => $this->pineAPHelper->getSetting("broadcast_ssid_pool"),
            'connectNotifications' => $this->pineAPHelper->getSetting("connect_notifications"),
            'disconnectNotifications' => $this->pineAPHelper->getSetting("disconnect_notifications"),
            'broadcastInterval' => $this->pineAPHelper->getSetting("beacon_interval"),
            'responseInterval' => $this->pineAPHelper->getSetting("beacon_response_interval"),
            'monitorInterface' => $this->pineAPHelper->getSetting("pineap_interface"),
            'sourceInterface' => $this->systemHelper->uciGet("pineap.@config[0].pineap_source_interface"),
            'sourceMAC' => strtoupper($sourceMAC),
            'targetMAC' => strtoupper($targetMAC),
        );

        $this->responseHandler->setData(array('settings' => $settings, 'success' => true));
        return $settings;
    }

    public function setPineAPSettings()
    {
        $settings = $this->request['settings'];
        if ($settings->allowAssociations) {
            $this->pineAPHelper->enableAssociations();
            $this->systemHelper->uciSet("pineap.@config[0].karma", 'on');
        } else {
            $this->pineAPHelper->disableAssociations();
            $this->systemHelper->uciSet("pineap.@config[0].karma", 'off');
        }
        if ($settings->logEvents) {
            $this->pineAPHelper->enableLogging();
            $this->systemHelper->uciSet("pineap.@config[0].logging", 'on');
        } else {
            $this->pineAPHelper->disableLogging();
            $this->systemHelper->uciSet("pineap.@config[0].logging", 'off');
        }
        if ($settings->beaconResponses) {
            $this->pineAPHelper->enableResponder();
            $this->systemHelper->uciSet("pineap.@config[0].beacon_responses", 'on');
        } else {
            $this->pineAPHelper->disableResponder();
            $this->systemHelper->uciSet("pineap.@config[0].beacon_responses", 'off');
        }
        if ($settings->captureSSIDs) {
            $this->pineAPHelper->enableHarvester();
            $this->systemHelper->uciSet("pineap.@config[0].capture_ssids", 'on');
        } else {
            $this->pineAPHelper->disableHarvester();
            $this->systemHelper->uciSet("pineap.@config[0].capture_ssids", 'off');
        }
        if ($settings->broadcastSSIDs) {
            $this->pineAPHelper->enableBeaconer();
            $this->systemHelper->uciSet("pineap.@config[0].broadcast_ssid_pool", 'on');
        } else {
            $this->pineAPHelper->disableBeaconer();
            $this->systemHelper->uciSet("pineap.@config[0].broadcast_ssid_pool", 'off');
        }
        if ($settings->connectNotifications) {
            $this->pineAPHelper->enableConnectNotifications();
            $this->systemHelper->uciSet("pineap.@config[0].connect_notifications", 'on');
        } else {
            $this->pineAPHelper->disableConnectNotifications();
            $this->systemHelper->uciSet("pineap.@config[0].connect_notifications", 'off');
        }
        if ($settings->disconnectNotifications) {
            $this->pineAPHelper->enableDisconnectNotifications();
            $this->systemHelper->uciSet("pineap.@config[0].disconnect_notifications", 'on');
        } else {
            $this->pineAPHelper->disableDisconnectNotifications();
            $this->systemHelper->uciSet("pineap.@config[0].disconnect_notifications", 'off');
        }
        $this->pineAPHelper->setBeaconInterval($settings->broadcastInterval);
        $this->systemHelper->uciSet("pineap.@config[0].beacon_interval", $settings->broadcastInterval);
        $this->pineAPHelper->setResponseInterval($settings->responseInterval);
        $this->systemHelper->uciSet("pineap.@config[0].beacon_response_interval", $settings->responseInterval);
        $this->pineAPHelper->setTarget($settings->targetMAC);
        $this->systemHelper->uciSet("pineap.@config[0].target_mac", $settings->targetMAC);
        $this->pineAPHelper->setSource($settings->sourceMAC);
        $this->systemHelper->uciSet("pineap.@config[0].pineap_mac", $settings->sourceMAC);
        $this->systemHelper->uciSet("pineap.@config[0].pineap_source_interface", $settings->sourceInterface);
        $this->pineAPHelper->setPineapInterface($settings->monitorInterface);
        $this->systemHelper->uciSet("pineap.@config[0].pineap_interface", $settings->monitorInterface);

        $this->responseHandler->setData(array("success" => true));
    }


    public function detectEnterpriseCertificate()
    {
        if (file_exists('/etc/pineape/certs/server.crt')) {
            $this->responseHandler->setData(array("installed" => true));
        } else {
            $this->responseHandler->setData(array("installed" => false));
        }
    }

    public function generateEnterpriseCertificate()
    {
        $params = $this->request['certSettings'];

        $state = $params->state;
        $country = $params->country;
        $locality = $params->locality;
        $organization = $params->organization;
        $email = $params->email;
        $commonname = $params->commonname;

        if ((strlen($state) < 1 || strlen($state) > 32) ||
            (strlen($country) < 2 || strlen($country) > 2) ||
            (strlen($locality) < 1 || strlen($locality) > 32) ||
            (strlen($organization) < 1 || strlen($organization) > 32) ||
            (strlen($email) < 1 || strlen($email) > 32) ||
            (strlen($commonname) < 1 || strlen($commonname) > 32)) {
            $this->responseHandler->setError("Invalid settings provided.");
            return;
        }

        $state = escapeshellarg($params->state);
        $country = escapeshellarg($params->country);
        $locality = escapeshellarg($params->locality);
        $organization = escapeshellarg($params->organization);
        $email = escapeshellarg($params->email);
        $commonname = escapeshellarg($params->commonname);

        exec("cd /etc/pineape/certs && ./clean.sh");
        exec("/etc/pineape/certs/configure.sh -p pineapplesareyummy -c ${country} -s ${state} -l ${locality} -o ${organization} -e ${email} -n ${commonname}");
        $this->systemHelper->execBackground("/etc/pineape/certs/bootstrap.sh");

        $this->responseHandler->setData(array("success" => true));
    }

    public function clearEnterpriseCertificate()
    {
        exec("cd /etc/pineape/certs && ./clean.sh");
        $this->systemHelper->uciSet("wireless.@wifi-iface[2].disabled", "1");
        $this->systemHelper->execBackground("wifi down radio0 && wifi up radio0");
        $this->responseHandler->setData(array("success" => true));
    }

    public function clearEnterpriseDB()
    {
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new \frieren\orm\SQLite($dbLocation);

        $this->dbConnection->execLegacy("DELETE FROM chalresp; DELETE FROM basic;");
        $this->responseHandler->setData(array("success" => true));
    }

    public function getEnterpriseSettings()
    {
        $settings = array(
            'enabled' => $this->getEnterpriseRunning(),
            'enableAssociations' => $this->getEnterpriseAllowAssocs(),
            'ssid' => $this->systemHelper->uciGet('wireless.@wifi-iface[2].ssid'),
            'mac' => $this->systemHelper->uciGet('wireless.@wifi-iface[2].macaddr'),
            'encryptionType' => $this->systemHelper->uciGet('wireless.@wifi-iface[2].encryption'),
            'downgrade' => $this->getDowngradeType(),
        );

        $this->responseHandler->setData(array("settings" => $settings));
    }

    public function setEnterpriseSettings()
    {
        $settings = $this->request['settings'];
        if ((strlen($settings->ssid) < 1 || strlen($settings->ssid) > 32) ||
            (strlen($settings->mac) < 17 || strlen($settings->mac) > 17)) {
            $this->responseHandler->setError("Invalid settings provided.");
            return;
        }
        $this->systemHelper->uciSet("wireless.@wifi-iface[2].ssid", $settings->ssid);
        $this->systemHelper->uciSet("wireless.@wifi-iface[2].macaddr", $settings->mac);
        $this->systemHelper->uciSet("wireless.@wifi-iface[2].encryption", $settings->encryptionType);
        if ($settings->enabled) {
            $this->systemHelper->uciSet("wireless.@wifi-iface[2].disabled", "0");
        } else {
            $this->systemHelper->uciSet("wireless.@wifi-iface[2].disabled", "1");
        }

        if ($settings->enableAssociations) {
            $this->systemHelper->uciSet("pineap.@config[0].pineape_passthrough", "on");
        } else {
            $this->systemHelper->uciSet("pineap.@config[0].pineape_passthrough", "off");
        }

        switch (strtoupper($settings->downgrade)) {
            case "MSCHAPV2":
                $this->enableMSCHAPV2Downgrade();
                break;
            case "GTC":
                $this->enableGTCDowngrade();
                break;
            case "DISABLE":
            default:
                $this->disableDowngrade();
        }

        $this->systemHelper->execBackground("wifi down radio0 && wifi up radio0");
        $this->responseHandler->setData(array("success" => true));
    }

    public function getEnterpriseData()
    {
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new \frieren\orm\SQLite($dbLocation);

        $chalrespdata = array();
        $rows = $this->dbConnection->queryLegacy("SELECT type, username, hex(challenge), hex(response) FROM chalresp;");
        foreach ($rows as $row) {
            $chalrespdata[] = [
                'type' => $row['type'],
                'username' => $row['username'],
                'challenge' => $row['hex(challenge)'],
                'response' => $row['hex(response)'],
            ];
        }

        $basicdata = array();
        $rows = $this->dbConnection->queryLegacy("SELECT type, identity, password FROM basic;");
        foreach ($rows as $row) {
            $basicdata[] = [
                'type' => $row['type'],
                'username' => $row['identity'],
                'password' => $row['password'],
            ];
        }
        $this->responseHandler->setData(array("success" => true, "chalrespdata" => $chalrespdata, "basicdata" => $basicdata));
    }

    public function downloadJTRHashes()
    {
        $jtrLocation = '/tmp/enterprise_jtr.txt';
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new \frieren\orm\SQLite($dbLocation);
        $data = array();
        $rows = $this->dbConnection->queryLegacy("SELECT type, username, hex(challenge), hex(response) FROM chalresp;");
        foreach ($rows as $row) {
            if (strtoupper($row['type']) !== "MSCHAPV2" && strtoupper($row['type']) != "EAP-TTLS/MSCHAPV2") {
                continue;
            }
            $data[] = $row['username'] . ':$NETNTLM$' . $row['hex(challenge)'] . '$' . $row['hex(response)'];
        }
        file_put_contents($jtrLocation, join("\n", $data));
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile($jtrLocation)));
    }

    public function downloadHashcatHashes()
    {
        $hashcatLocation = '/tmp/enterprise_hashcat.txt';
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new \frieren\orm\SQLite($dbLocation);
        $data = array();
        $rows = $this->dbConnection->queryLegacy("SELECT type, username, hex(challenge), hex(response) FROM chalresp;");
        foreach ($rows as $row) {
            if (strtoupper($row['type']) !== "MSCHAPV2" && strtoupper($row['type']) != "EAP-TTLS/MSCHAPV2") {
                continue;
            }
            $data[] = $row['username'] . '::::' . $row['hex(response)'] . ':' . $row['hex(challenge)'];
        }
        file_put_contents($hashcatLocation, join("\n", $data));
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile($hashcatLocation)));
    }

    public function getEnterpriseRunning()
    {
        exec("hostapd_cli -i wlan0-2 pineape_enable_status", $statusOutput);
        if ($statusOutput[0] == "ENABLED") {
            return true;
        }
        return false;
    }

    public function getEnterpriseAllowAssocs()
    {
        exec("hostapd_cli -i wlan0-2 pineape_auth_passthrough_status", $statusOutput);
        if ($statusOutput[0] == "ENABLED") {
            return true;
        }
        return false;
    }

    public function startHandshakeCapture()
    {
        $bssid = $this->request['bssid'];
        $channel = $this->request['channel'];
        // We already set $this->response in checkPineAP() if it isnt running.
        if ($this->checkPineAP()) {
            $this->systemHelper->execBackground("pineap /etc/pineap.conf handshake_capture_start ${bssid} ${channel}");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function stopHandshakeCapture()
    {
        $this->systemHelper->execBackground('pineap /tmp/pineap.conf handshake_capture_stop');
        $this->responseHandler->setData(array('success' => true));
    }

    public function getHandshake()
    {
        $bssid = str_replace(':', '-', $this->request['bssid']);
        if (file_exists("/tmp/handshakes/{$bssid}_full.pcap")) {
            $this->responseHandler->setData(array('handshakeExists' => true, 'partial' => false));
        } else if (file_exists("/tmp/handshakes/{$bssid}_partial.pcap")) {
            $this->responseHandler->setData(array('handshakeExists' => true, 'partial' => true));
        } else {
            $this->responseHandler->setData(array('handshakeExists' => false));
        }
    }

    public function getAllHandshakes()
    {
        $handshakes = array();
        foreach (glob("/tmp/handshakes/*.pcap") as $handshake) {
            $handshake = str_replace(["/tmp/handshakes/", "_full.pcap", "_partial.pcap"], "", $handshake);
            $handshake = str_replace('-', ':', $handshake);
            $handshakes[] = $handshake;
        }

        $this->responseHandler->setData(array("handshakes" => $handshakes));
    }

    public function downloadAllHandshakes()
    {
        @unlink('/tmp/handshakes/handshakes.tar.gz');
        exec("tar -czf /tmp/handshakes/handshakes.tar.gz -C /tmp/handshakes .");
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/tmp/handshakes/handshakes.tar.gz")));
    }

    public function clearAllHandshakes()
    {
        @unlink("/tmp/handshakes/handshakes.tar.gz");
        foreach (glob("/tmp/handshakes/*.pcap") as $handshake) {
            unlink($handshake);
        }

        $this->responseHandler->setData(array("success" => true));
    }

    public function checkCaptureStatus()
    {
        $bssid = $this->request['bssid'];
        exec("pineap /tmp/pineap.conf get_status", $status_output);
        if ($status_output[0] === "PineAP is not running") {
            $this->responseHandler->setError("PineAP is not running");
        } else {
            $status_output = implode("\n", $status_output);
            $status_output = json_decode($status_output, true);
            if ($status_output['captureRunning'] === true && $status_output['bssid'] === $bssid) {
                // A scan is running for the supplied BSSID.
                $this->responseHandler->setData(array('running' => true, 'currentBSSID' => true, 'bssid' => $status_output['bssid']));
                return 3;
            } elseif ($status_output['captureRunning'] === true) {
                // A scan is running, but not for this BSSID.
                $this->responseHandler->setData(array('running' => true, 'currentBSSID' => false, 'bssid' => $status_output['bssid']));
                return 2;
            }

            // No scan is running.
            $this->responseHandler->setData(array('running' => false, 'currentBSSID' => false));
        }
        return 0;
    }

    public function downloadHandshake()
    {
        $bssid = str_replace(':', '-', $this->request['bssid']);
        $type = $this->request['type'];
        // JTR and hashcat don't care whether the data came from a full or partial handshake
        if ($type === "pcap") {
            $suffix = "_";
            if (file_exists("/tmp/handshakes/{$bssid}_full.pcap")) {
                $suffix .= "full";
            } else {
                $suffix .= "partial";
            }
            $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/tmp/handshakes/{$bssid}{$suffix}.pcap")));
        } else {
            $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/tmp/handshakes/{$bssid}.{$type}")));
        }
    }

    public function deleteHandshake()
    {
        $bssid = str_replace(':', '-', $this->request['bssid']);
        @unlink("/tmp/handshakes/${bssid}_full.pcap");
        @unlink("/tmp/handshakes/${bssid}_partial.pcap");
        @unlink("/tmp/handshakes/${bssid}.hccap");
        @unlink("/tmp/handshakes/${bssid}.txt");

        $this->responseHandler->setData(array('success' => true));
    }

    public function inject()
    {
        $payload = preg_replace('/[^A-Fa-f0-9]/', '', $this->request['payload']);
        if (hex2bin($payload) === false) {
            $this->responseHandler->setError('Invalid hex');
            return;
        }
        if (!$this->systemHelper->checkRunning('/usr/sbin/pineapd', true)) {
            $this->responseHandler->setError('Please start PineAP');
            return;
        }
        file_put_contents('/tmp/inject', $payload);
        $channel = intval($this->request['channel']);
        $frameCount = intval($this->request['frameCount']);
        $delay = intval($this->request['delay']);
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );
        $cmd = "/usr/bin/pineap /tmp/pineap.conf inject /tmp/inject ${channel} ${frameCount} ${delay}";
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            $this->responseHandler->setData(array('error' => "Failed to spawn process for command: ${cmd}", 'command' => $cmd));
            unlink('/tmp/inject');
            return;
        }
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);
        if (empty($output)) {
            $this->responseHandler->setData(array(
                'success' => true,
                'request' => $this->request,
                'payload' => json_encode($payload),
                'command' => $cmd
            ));
        } else {
            $this->responseHandler->setData(array(
                'error' => 'PineAP cli did not execute successfully',
                'command' => $cmd,
                'exitCode' => $exitCode,
                'stdout' => $output,
                'stderr' => $errorOutput
            ));
        }
        unlink('/tmp/inject');
    }
}
