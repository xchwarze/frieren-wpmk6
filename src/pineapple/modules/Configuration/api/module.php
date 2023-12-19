<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Configuration extends Controller
{
    public $endpointRoutes = ['getCurrentTimeZone', 'getLandingPageData', 'saveLandingPageData', 'changePass', 'changeTimeZone', 'resetPineapple', 'haltPineapple', 'rebootPineapple', 'getLandingPageStatus', 'getAutoStartStatus', 'enableLandingPage', 'disableLandingPage', 'enableAutoStart', 'disableAutoStart', 'getButtonScript', 'saveButtonScript', 'getDevice', 'getDeviceConfig'];

    private function changePassword($current, $new)
    {
        $shadow_file = file_get_contents('/etc/shadow');
        $root_array = explode(":", explode("\n", $shadow_file)[0]);
        $salt = '$1$'.explode('$', $root_array[1])[2].'$';
        $current_shadow_pass = $salt.explode('$', $root_array[1])[3];
        $current = crypt($current, $salt);
        $new = crypt($new, $salt);
        if ($current_shadow_pass == $current) {
            $find = implode(":", $root_array);
            $root_array[1] = $new;
            $replace = implode(":", $root_array);

            $shadow_file = str_replace($find, $replace, $shadow_file);
            file_put_contents("/etc/shadow", $shadow_file);

            return true;
        }
        return false;
    }

    public function haltPineapple()
    {
        $this->systemHelper->execBackground("sync && led all off && halt");
        $this->responseHandler->setData(array("success" => true));
    }

    public function rebootPineapple()
    {
        $this->systemHelper->execBackground("reboot");
        $this->responseHandler->setData(array("success" => true));
    }

    public function resetPineapple()
    {
        $this->systemHelper->execBackground("jffs2reset -y && reboot &");
        $this->responseHandler->setData(array("success" => true));
    }

    public function getCurrentTimeZone()
    {
        $currentTimeZone = exec('date +%Z%z');
        $this->responseHandler->setData(array("currentTimeZone" => $currentTimeZone));
    }

    public function changeTimeZone()
    {
        $timeZone = $this->request['timeZone'];
        file_put_contents('/etc/TZ', $timeZone);
        $this->systemHelper->uciSet('system.@system[0].timezone', $timeZone);
        $this->responseHandler->setData(array("success" => true));
    }

    public function getLandingPageData()
    {
        $landingPage = file_get_contents('/etc/pineapple/landingpage.php');
        $this->responseHandler->setData(array("landingPage" => $landingPage));
    }

    public function getLandingPageStatus()
    {
        if (!empty(exec("iptables -L -vt nat | grep 'www to:.*:80'"))) {
            $this->responseHandler->setData(array("enabled" => true));
            return;
        }
        $this->responseHandler->setData(array("enabled" => false));
    }

    public function enableLandingPage()
    {
        exec('iptables -t nat -A PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
        exec('iptables -t nat -A POSTROUTING -j MASQUERADE');
        copy('/pineapple/modules/Configuration/api/landingpage_index.php', '/www/index.php');
        $this->responseHandler->setData(array("success" => true));
    }

    public function disableLandingPage()
    {
        @unlink('/www/index.php');
        exec('iptables -t nat -D PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
        $this->responseHandler->setData(array("success" => true));
    }

    public function getAutoStartStatus()
    {
        if($this->systemHelper->uciGet("landingpage.@settings[0].autostart") == 1) {
            $this->responseHandler->setData(array("enabled" => true));
        } else {
            $this->responseHandler->setData(array("enabled" => false));
        }
    }

    public function enableAutoStart()
    {
        $this->systemHelper->uciSet("landingpage.@settings[0].autostart", "1");
        $this->responseHandler->setData(array("success" => true));
    }

    public function disableAutoStart()
    {
        $this->systemHelper->uciSet("landingpage.@settings[0].autostart", "0");
        $this->responseHandler->setData(array("success" => true));
    }

    public function saveLandingPageData()
    {
        if (file_put_contents('/etc/pineapple/landingpage.php', $this->request['landingPageData']) !== false) {
            $this->responseHandler->setData(array("success" => true));
        } else {
            $this->responseHandler->setError("Error saving Landing Page.");
        }
    }

    public function getButtonScript()
    {
        if (file_exists('/etc/pineapple/button_script')) {
            $script = file_get_contents('/etc/pineapple/button_script');
            $this->responseHandler->setData(array("buttonScript" => $script));
        } else {
            $this->responseHandler->setError("The button script does not exist.");
        }
    }

    public function saveButtonScript()
    {
        if (file_exists('/etc/pineapple/button_script')) {
            file_put_contents('/etc/pineapple/button_script', $this->request['buttonScript']);
            $this->responseHandler->setData(array("success" => true));
        } else {
            $this->responseHandler->setError("The button script does not exist.");
        }
    }

    public function getDeviceName()
    {
        $this->responseHandler->setData(array("device" => $this->systemHelper->getDevice()));
    }

    public function getDeviceConfig()
    {
        $this->responseHandler->setData(array("config" => $this->systemHelper->getDeviceConfig()));
    }

    public function changePass()
    {
        if ($this->request['newPassword'] === $this->request['newPasswordRepeat']) {
            if ($this->changePassword($this->request['oldPassword'], $this->request['newPassword']) === true) {
                $this->responseHandler->setData(array("success" => true));
                return;
            }
        }

        $this->responseHandler->setData(array("success" => false));
    }
}