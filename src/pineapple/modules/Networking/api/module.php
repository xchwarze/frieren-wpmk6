<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

require_once('AccessPoint.php');
require_once('ClientMode.php');
require_once('Interfaces.php');

class Networking extends Controller
{
    public $endpointRoutes = ['getRoutingTable', 'restartDNS', 'updateRoute', 'getAdvancedData', 'setHostname', 'resetWirelessConfig', 'getInterfaceList', 'saveAPConfig', 'getAPConfig', 'getMacData', 'setMac', 'setRandomMac', 'resetMac', 'scanForNetworks', 'getClientInterfaces', 'connectToAP', 'checkConnection', 'disconnect', 'getOUI', 'getFirewallConfig', 'setFirewallConfig', 'saveWirelessConfig', 'getInfoData', 'interfaceActions'];

    public function getRoutingTable()
    {
        exec('ifconfig | grep encap:Ethernet | awk "{print \$1}"', $routeInterfaces);
        exec('route', $routingTable);
        $routingTable = implode("\n", $routingTable);
        $this->responseHandler->setData(['routeTable' => $routingTable, 'routeInterfaces' => $routeInterfaces]);
    }

    public function restartDNS()
    {
        $this->systemHelper->execBackground('/etc/init.d/dnsmasq restart');
        $this->responseHandler->setData(["success" => true]);
    }

    public function updateRoute()
    {
        $routeInterface = escapeshellarg($this->request['routeInterface']);
        $routeIP = escapeshellarg($this->request['routeIP']);
        exec("route del default");
        exec("route add default gw {$routeIP} {$routeInterface}");
        $this->responseHandler->setData(["success" => true]);
    }

    public function getAdvancedData()
    {
        $this->responseHandler->setData([
            "hostname" => gethostname(),
            "wireless" => file_get_contents('/etc/config/wireless')
        ]);
    }

    public function setHostname()
    {
        exec("uci set system.@system[0].hostname=" . escapeshellarg($this->request['hostname']));
        exec("uci commit system");
        exec("echo $(uci get system.@system[0].hostname) > /proc/sys/kernel/hostname");
        $this->responseHandler->setData(["success" => true]);
    }

    public function resetWirelessConfig()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->responseHandler->setData($interfaceHelper->resetWirelessConfig());
    }

    public function getInterfaceList()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->responseHandler->setData($interfaceHelper->getInterfaceList());
    }

    public function saveAPConfig()
    {
        $accessPointHelper = new \helper\AccessPoint();
        $config = $this->request['apConfig'];
        if (empty($config->openSSID) || empty($config->managementSSID)) {
            $this->responseHandler->setError("Error: SSIDs must be at least one character.");
            return;
        }
        if (strlen($config->managementKey) < 8 && !$config->disableManagementAP) {
            $this->responseHandler->setError("Error: WPA2 Passwords must be at least 8 characters long.");
            return;
        }
        $this->responseHandler->setData($accessPointHelper->saveAPConfig($config));
    }

    public function getAPConfig()
    {
        $accessPointHelper = new \helper\AccessPoint();
        $this->responseHandler->setData($accessPointHelper->getAPConfig());
    }

    public function getMacData()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->responseHandler->setData($interfaceHelper->getMacData());
    }

    public function setMac()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->responseHandler->setData($interfaceHelper->setMac(false, $this->request['interface'], $this->request['mac'], $this->request['forceReload']));
    }

    public function setRandomMac()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->responseHandler->setData($interfaceHelper->setMac(true, $this->request['interface'], $this->request['mac'], $this->request['forceReload']));
    }

    public function resetMac()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->responseHandler->setData($interfaceHelper->resetMac($this->request['interface']));
    }

    public function checkConnection()
    {
        $clientModeHelper = new \helper\ClientMode();
        $this->responseHandler->setData($clientModeHelper->checkConnection());
    }

    public function disconnect()
    {
        $interfaceHelper = new \helper\Interfaces();
        $clientModeHelper = new \helper\ClientMode();
        $interface = $this->request['interface'];
        $uciID = $interfaceHelper->getUciID($interface);
        $radioID = $interfaceHelper->getRadioID($interface);
        $this->responseHandler->setData($clientModeHelper->disconnect($uciID, $radioID));
    }

    public function connectToAP()
    {
        $interfaceHelper = new \helper\Interfaces();
        $clientModeHelper = new \helper\ClientMode();

        $interface = $this->request['interface'];
        $uciID = $interfaceHelper->getUciID($interface);
        $radioID = $interfaceHelper->getRadioID($interface);

        $this->responseHandler->setData($clientModeHelper->connectToAP($uciID, $this->request['ap'], $this->request['key'], $radioID));
    }

    public function scanForNetworks()
    {
        $interfaceHelper = new \helper\Interfaces();
        $clientModeHelper = new \helper\ClientMode();
        $interface = $this->request['interface'];
        $uciID = $interfaceHelper->getUciID($interface);
        $radioID = $interfaceHelper->getRadioID($interface);
        $this->responseHandler->setData($clientModeHelper->scanForNetworks($interface, $uciID, $radioID));
    }

    public function getClientInterfaces()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->responseHandler->setData($interfaceHelper->getClientInterfaces());
    }

    public function getOUI()
    {
        $url = sprintf(\DeviceConfig::OUI_PATH, \DeviceConfig::SERVER_URL);
        $data = @$this->systemHelper->fileGetContentsSSL($url);
        if ($data !== null) {
            $this->responseHandler->setData(["ouiText" => implode("\n", $data)]);
        } else {
            $this->responseHandler->setError("Failed to download OUI file from  remote host.");
        }
    }

    public function getFirewallConfig()
    {
        $this->responseHandler->setData([
            "allowWANSSH" => $this->systemHelper->uciGet("firewall.allowssh.enabled"),
            "allowWANUI" => $this->systemHelper->uciGet("firewall.allowui.enabled")
        ]);
    }

    public function setFirewallConfig()
    {
        $wan = $this->request['WANSSHAccess'] ? 1 : 0;
        $ui = $this->request['WANUIAccess'] ? 1 : 0;
        $this->systemHelper->uciSet("firewall.allowssh.enabled", $wan);
        $this->systemHelper->uciSet("firewall.allowui.enabled", $ui);
        $this->systemHelper->uciSet("firewall.allowws.enabled", $ui);
        $this->systemHelper->execBackground('/etc/init.d/firewall restart');

        $this->responseHandler->setData(["success" => true]);
    }

    public function saveWirelessConfig()
    {
        if (isset($this->request['wireless'])) {
            file_put_contents('/etc/config/wireless', $this->request['wireless']);
            $this->systemHelper->execBackground('wifi');
            $this->responseHandler->setData(["success" => true]);
        }
    }

    public function getInfoData()
    {
        switch ((int)$this->request['type']) {
            case 2:
                $command = 'iw dev';
                break;
            case 3:
                $command = 'airmon-ng';
                break;
            default:
                $command = 'ifconfig -a';
        }

        exec($command, $info);
        $this->responseHandler->setData(["info" => implode("\n", $info)]);
    }

    public function interfaceActions()
    {
        $interface = escapeshellarg($this->request['interface']);
        switch ((int)$this->request['type']) {
            case 2:
                $command = "ifconfig {$interface} down";
                break;
            case 3:
                $command = "airmon-ng start {$interface}";
                break;
            case 4:
                $command = "airmon-ng stop {$interface}";
                break;
            default:
                $command = "ifconfig {$interface} up";
        }

        exec($command, $info);
        $this->responseHandler->setData(["info" => implode("\n", $info)]);
    }
}
