<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Configuration extends SystemModule
{
    protected $endpointRoutes = ['getCurrentTimeZone', 'getLandingPageData', 'saveLandingPage', 'changePass', 'changeTimeZone', 'resetPineapple', 'haltPineapple', 'rebootPineapple', 'getLandingPageStatus', 'getAutoStartStatus', 'enableLandingPage', 'disableLandingPage', 'enableAutoStart', 'disableAutoStart', 'getButtonScript', 'saveButtonScript', 'getDevice', 'getDeviceConfig'];

    private function haltPineapple()
    {
        $this->systemHelper->execBackground("sync && led all off && halt");
        $this->responseHandler->setData(array("success" => true));
    }

    private function rebootPineapple()
    {
        $this->systemHelper->execBackground("reboot");
        $this->responseHandler->setData(array("success" => true));
    }

    private function resetPineapple()
    {
        $this->systemHelper->execBackground("jffs2reset -y && reboot &");
        $this->responseHandler->setData(array("success" => true));
    }

    private function getCurrentTimeZone()
    {
        $currentTimeZone = exec('date +%Z%z');
        $this->responseHandler->setData(array("currentTimeZone" => $currentTimeZone));
    }

    private function changeTimeZone()
    {
        $timeZone = $this->request['timeZone'];
        file_put_contents('/etc/TZ', $timeZone);
        $this->systemHelper->uciSet('system.@system[0].timezone', $timeZone);
        $this->responseHandler->setData(array("success" => true));
    }

    private function getLandingPageData()
    {
        $landingPage = file_get_contents('/etc/pineapple/landingpage.php');
        $this->responseHandler->setData(array("landingPage" => $landingPage));
    }

    private function getLandingPageStatus()
    {
        if (!empty(exec("iptables -L -vt nat | grep 'www to:.*:80'"))) {
            $this->responseHandler->setData(array("enabled" => true));
            return;
        }
        $this->responseHandler->setData(array("enabled" => false));
    }

    private function enableLandingPage()
    {
        exec('iptables -t nat -A PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
        exec('iptables -t nat -A POSTROUTING -j MASQUERADE');
        copy('/pineapple/modules/Configuration/api/landingpage_index.php', '/www/index.php');
        $this->responseHandler->setData(array("success" => true));
    }

    private function disableLandingPage()
    {
        @unlink('/www/index.php');
        exec('iptables -t nat -D PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
        $this->responseHandler->setData(array("success" => true));
    }

    private function getAutoStartStatus()
    {
        if($this->systemHelper->uciGet("landingpage.@settings[0].autostart") == 1) {
            $this->responseHandler->setData(array("enabled" => true));
        } else {
            $this->responseHandler->setData(array("enabled" => false));
        }
    }

    private function enableAutoStart()
    {
        $this->systemHelper->uciSet("landingpage.@settings[0].autostart", "1");
        $this->responseHandler->setData(array("success" => true));
    }

    private function disableAutoStart()
    {
        $this->systemHelper->uciSet("landingpage.@settings[0].autostart", "0");
        $this->responseHandler->setData(array("success" => true));
    }

    private function saveLandingPageData()
    {
        if (file_put_contents('/etc/pineapple/landingpage.php', $this->request['landingPageData']) !== false) {
            $this->responseHandler->setData(array("success" => true));
        } else {
            $this->responseHandler->setError("Error saving Landing Page.");
        }
    }

    private function getButtonScript()
    {
        if (file_exists('/etc/pineapple/button_script')) {
            $script = file_get_contents('/etc/pineapple/button_script');
            $this->responseHandler->setData(array("buttonScript" => $script));
        } else {
            $this->responseHandler->setError("The button script does not exist.");
        }
    }

    private function saveButtonScript()
    {
        if (file_exists('/etc/pineapple/button_script')) {
            file_put_contents('/etc/pineapple/button_script', $this->request['buttonScript']);
            $this->responseHandler->setData(array("success" => true));
        } else {
            $this->responseHandler->setError("The button script does not exist.");
        }
    }

    private function getDeviceName()
    {
        $this->responseHandler->setData(array("device" => $this->systemHelper->getDevice()));
    }

    private function getDeviceConfigArray()
    {
        $this->responseHandler->setData(array("config" => $this->systemHelper->getDeviceConfig()));
    }

    protected function changePass()
    {
        if ($this->request['newPassword'] === $this->request['newPasswordRepeat']) {
            if (parent::changePassword($this->request['oldPassword'], $this->request['newPassword']) === true) {
                $this->responseHandler->setData(array("success" => true));
                return;
            }
        }

        $this->responseHandler->setData(array("success" => false));
    }
}