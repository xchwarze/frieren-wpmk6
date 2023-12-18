<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class PineAPHelper
{
    CONST CLI_PINEAPD = "/usr/sbin/pineapd";

    protected function getSetting($settingKey)
    {
        $configFile = file_get_contents("/tmp/pineap.conf");

        $configFile = explode("\n", $configFile);
        foreach($configFile as $row => $data) {
            $entry = str_replace(" ", "", $data);
            $entry = explode("=", $entry);

            if ($entry[0] == $settingKey) {
                if ($entry[1] == 'on') {
                    return true;
                } elseif ($entry[1] == 'off') {
                    return false;
                } else {
                    return $entry[1];
                }
            }
        }

        return false;
    }

    protected function setSetting($settingKey, $settingVal)
    {
        $configFile = file_get_contents("/tmp/pineap.conf");
        $configFileOut = "";

        $configFile = explode("\n", $configFile);
        foreach($configFile as $row => $data) {
            $entry = str_replace(" ", "", $data);
            $entry = explode("=", $entry);

            if ($entry[0] == $settingKey) {
                $entry[1] = $settingVal;
            }

            if ($entry[0] != "" && $entry[1] != "") {
                $configFileOut .= $entry[0] . " = " . $entry[1] . "\n";
            }
        }

        file_put_contents("/tmp/pineap.conf", "");
        file_put_contents("/tmp/pineap.conf", $configFileOut);

        return true;
    }

    protected function enableAssociations()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec("pineap /tmp/pineap.conf karma on");
        } else {
            $this->setSetting("karma", "on");
        }

        return true;
    }

    protected function disableAssociations()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec("pineap /tmp/pineap.conf karma off");
        } else {
            $this->setSetting("karma", "off");
        }

        return true;
    }

    protected function enablePineAP()
    {
        exec('/etc/init.d/pineapd start');
        return true;
    }

    protected function disablePineAP()
    {
        exec('/etc/init.d/pineapd stop');
        return true;
    }

    protected function enableLogging()
    {
        if (\helper\checkRunning('/usr/sbin/pineapd')) {
            exec("pineap /tmp/pineap.conf logging on");
        } else {
            $this->setSetting("logging", "on");
        }

        return true;
    }

    protected function disableLogging()
    {
        $this->setSetting("logging", "off");
        if (\helper\checkRunning('/usr/sbin/pineapd')) {
            exec("pineap /tmp/pineap.conf logging off");
        }
        return true;
    }

    protected function enableBeaconer()
    {
        $this->setSetting("broadcast_ssid_pool", "on");
        if (\helper\checkRunning('/usr/sbin/pineapd')) {
            exec('pineap /tmp/pineap.conf broadcast_pool on');
        }
        return true;
    }

    protected function disableBeaconer()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf broadcast_pool off');
        } else {
            $this->setSetting("broadcast_ssid_pool", "off");
        }
        return true;
    }

    protected function enableResponder()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf beacon_responses on');
        } else {
            $this->setSetting("beacon_responses", "on");
        }
        return true;
    }

    protected function disableResponder()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf beacon_responses off');
        } else {
            $this->setSetting("beacon_responses", "off");
        }
        return true;
    }

    protected function enableHarvester()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf capture_ssids on');
        } else {
            $this->setSetting("capture_ssids", "on");
        }
        return true;
    }

    protected function disableHarvester()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf capture_ssids off');
        } else {
            $this->setSetting("capture_ssids", "off");
        }
        return true;
    }

    protected function enableConnectNotifications()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf connect_notifications on');
        } else {
            $this->setSetting("connect_notifications", "on");
        }
        return true;
    }

    protected function disableConnectNotifications()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf connect_notifications off');
        } else {
            $this->setSetting("connect_notifications", "off");
        }
        return true;
    }

    protected function enableDisconnectNotifications()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf disconnect_notifications on');
        } else {
            $this->setSetting("disconnect_notifications", "on");
        }
        return true;
    }

    protected function disableDisconnectNotifications()
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            exec('pineap /tmp/pineap.conf disconnect_notifications off');
        } else {
            $this->setSetting("disconnect_notifications", "off");
        }
        return true;
    }

    protected function getTarget()
    {
        return $this->getSetting("target_mac");
    }

    protected function getSource()
    {
        return $this->getSetting("pineap_mac");
    }

    protected function setBeaconInterval($interval)
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            $interval = escapeshellarg($interval);
            exec("pineap /tmp/pineap.conf beacon_interval {$interval}");
        } else {
            $this->setSetting("beacon_interval", "{$interval}");
        }
    }

    protected function setResponseInterval($interval)
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            $interval = escapeshellarg($interval);
            exec("pineap /tmp/pineap.conf beacon_response_interval {$interval}");
        } else {
            $this->setSetting("beacon_response_interval", "{$interval}");
        }
    }

    protected function setSource($mac)
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            $mac = escapeshellarg($mac);
            exec("pineap /tmp/pineap.conf set_source {$mac}");
        } else {
            $this->setSetting("pineap_mac", "{$mac}");
        }
    }

    protected function setTarget($mac)
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            $mac = escapeshellarg($mac);
            exec("pineap /tmp/pineap.conf set_target {$mac}");
        } else {
            $this->setSetting("target_mac", "{$mac}");
        }
    }

    protected function deauth($target, $source, $channel, $multiplier = 1)
    {
        $channel = str_pad($channel, 2, "0", STR_PAD_LEFT);
        exec("pineap /tmp/pineap.conf deauth $source $target $channel $multiplier");
        return true;
    }

    protected function getPineapInterface()
    {
        return $this->getSetting("pineap_interface");
    }

    protected function setPineapInterface($interface)
    {
        if (\helper\checkRunning(self::CLI_PINEAPD)) {
            $this->disablePineAP();
        }

        $this->setSetting("pineap_interface", "{$interface}");
        return true;
    }
}
