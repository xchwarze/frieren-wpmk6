<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Setup extends Controller
{
    public $endpointRoutes = [];

    public function changePassword()
    {
        if ($this->request['rootPassword'] !== $this->request['confirmRootPassword']) {
            $this->responseHandler->setError('The root passwords do not match.');
            return false;
        }
        $new = $this->request['rootPassword'];
        $shadow_file = file_get_contents('/etc/shadow');
        $root_array = explode(":", explode("\n", $shadow_file)[0]);
        $salt = '$1$' . explode('$', $root_array[1])[2] . '$';
        $new = crypt($new, $salt);
        $find = implode(":", $root_array);
        $root_array[1] = $new;
        $replace = implode(":", $root_array);

        $shadow_file = str_replace($find, $replace, $shadow_file);
        file_put_contents("/etc/shadow", $shadow_file);
        return true;
    }

    public function checkButtonStatus()
    {
        $buttonPressed = file_exists('/tmp/button_setup');
        $bootStatus = !file_exists('/etc/pineapple/init');
        $this->responseHandler->setData(array('buttonPressed' => $buttonPressed, 'booted' => $bootStatus));

        return $buttonPressed;
    }

    public function getChanges()
    {
        if (file_exists("/pineapple/changes")) {
            $changes = file_get_contents("/pineapple/changes");
            $version = trim(file_get_contents('/pineapple/pineapple_version'));
            $this->responseHandler->setData(array('changes' => $changes, 'fwversion' => $version));
        } else {
            $this->responseHandler->setData(array('changes' => NULL));
        }
        return true;
    }

    public function getDeviceData()
    {
        # Disable setup in "keep settings" scenario
        $complete = file_exists('/etc/pineapple/setup_complete');
        if ($complete) {
            exec('/bin/rm -rf /pineapple/modules/Setup /pineapple/api/Setup.php /etc/pineapple/setupRequired /etc/pineapple/init');
        }

        $this->responseHandler->setData(array(
            'complete' => $complete,
            'config'   => $this->systemHelper->getDeviceConfig(),
        ));
    }

    public function populateFields()
    {
        exec('cat /sys/class/ieee80211/phy0/macaddress|awk -F ":" \'{print $5""$6 }\'| tr a-z A-Z', $macOctets);
        $this->responseHandler->setData(array('openSSID' => "Pineapple_{$macOctets[0]}", 'hideOpenAP' => true));
        return true;
    }

    public function setupWifi()
    {
        $managementSSID = $this->request['managementSSID'];
        $managementPass = $this->request['managementPass'];
        $hideManagementAP = $this->request['hideManagementAP'];
        $disableManagementAP = $this->request['disableManagementAP'];
        $openSSID = $this->request['openSSID'];
        $hideOpenAP = $this->request['hideOpenAP'];
        $countryCode = $this->request['countryCode'];

        if (strlen($managementSSID) < 1) {
            $this->responseHandler->setError('The Management SSID cannot be empty.');
            return false;
        }
        if (strlen($openSSID) < 1) {
            $this->responseHandler->setError('The Open AP SSID cannot be empty.');
            return false;
        }
        if ($managementPass !== $this->request['confirmManagementPass']) {
            $this->responseHandler->setError('The WPA2 Passwords do not match.');
            return false;
        }
        if (strlen($managementPass) < 8) {
            $this->responseHandler->setError('The WPA2 passwords must be at least 8 characters.');
            return false;
        }

        $managementSSID = substr(escapeshellarg($managementSSID), 0, 32);
        $openSSID = substr(escapeshellarg($openSSID), 0, 32);
        $managementPass = escapeshellarg($managementPass);

        exec('/sbin/wifi config > /etc/config/wireless');
        exec("uci set wireless.@wifi-iface[1].ssid={$managementSSID}");
        exec("uci set wireless.@wifi-iface[1].key={$managementPass}");
        exec("uci set wireless.@wifi-iface[1].hidden={$hideManagementAP}");
        exec("uci set wireless.@wifi-iface[1].disabled={$disableManagementAP}");
        exec("uci set wireless.@wifi-iface[0].ssid={$openSSID}");
        exec("uci set wireless.@wifi-iface[0].hidden={$hideOpenAP}");
        exec("uci set wireless.radio0.country={$countryCode}");
        exec("uci set wireless.radio1.country={$countryCode}");
        exec('uci commit wireless');

        return true;
    }

    public function enableSSH()
    {
        exec('echo "/etc/init.d/sshd enable" | at now');
        exec('echo "/etc/init.d/sshd start" | at now');
        $pid = explode("\n", exec('pgrep /usr/sbin/sshd'))[0];
        if (is_numeric($pid) && intval($pid) > 0) {
            return true;
        }
        return false;
    }

    public function restartWifi()
    {
        exec('echo "/sbin/wifi" | at now');
    }

    public function setupPineAP()
    {
        if ($this->request['macFilterMode'] === "Allow") {
            exec('hostapd_cli -i wlan0 karma_mac_white');
            exec('uci set pineap.@config[0].mac_filter=white');
        } else {
            exec('hostapd_cli -i wlan0 karma_mac_black');
            exec('uci set pineap.@config[0].mac_filter=black');
        }
        if ($this->request['ssidFilterMode'] === "Allow") {
            exec('hostapd_cli -i wlan0 karma_white');
            exec('uci set pineap.@config[0].ssid_filter=white');
        } else {
            exec('hostapd_cli -i wlan0 karma_black');
            exec('uci set pineap.@config[0].ssid_filter=black');
        }
        exec('uci commit pineap');
    }

    public function restartFirewall()
    {
        exec("/etc/init.d/firewall restart");
    }

    public function setupFirewall()
    {
        if ($this->request['WANSSHAccess']) {
            exec("uci set firewall.allowssh.enabled=1");
            exec("uci commit firewall");
        }

        if ($this->request['WANUIAccess']) {
            exec("uci set firewall.allowui.enabled=1");
            exec("uci commit firewall");
        }
    }

    public function finalizeSetup()
    {
        $this->enableSSH();
        $this->restartFirewall();
        $this->restartWifi();
        @unlink('/etc/pineapple/setupRequired');
        @unlink('/pineapple/api/Setup.php');
        $timeZone = $this->request['timeZone'];
        exec("echo {$timeZone} > /etc/TZ");
        exec("uci set system.@system[0].timezone={$timeZone}");
        exec("uci commit");
        exec('killall blink');
        exec('led reset');
        exec('/bin/rm -rf /pineapple/modules/Setup');
        exec('/bin/touch /etc/pineapple/setup_complete');
    }

    public function performSetup()
    {
        if (!$this->checkButtonStatus()) {
            $this->responseHandler->setError("Not verified.");
            return false;
        }

        if ($this->request['eula'] !== true || $this->request['license'] !== true) {
            $this->responseHandler->setError("Please accept the EULA and Software License.");
            return false;
        }

        if ($this->request['macFilterMode'] !== "Allow" && $this->request['macFilterMode'] !== "Deny") {
            $this->responseHandler->setError("Please choose a setting for the Client Filter.");
            return false;
        }

        if ($this->request['ssidFilterMode'] !== "Allow" && $this->request['ssidFilterMode'] !== "Deny") {
            $this->responseHandler->setError("Please choose a setting for the SSID Filter.");
            return false;
        }


        if ($this->changePassword() && $this->setupWifi()) {
            $this->setupPineAP();
            $this->setupFirewall();
            $this->finalizeSetup();
        }

        return true;
    }
}
