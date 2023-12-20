<?php namespace frieren\helper;

/* Code modified by Frieren Auto Refactor */
class AccessPoint
{
    protected $systemHelper;

    public function __construct()
    {
        $this->systemHelper = new OpenWrtHelper();
    }

    public function saveAPConfig($apConfig)
    {
        if (is_array($apConfig)) {
            $apConfig = (object)$apConfig;
        }

        $this->systemHelper->uciSet('wireless.radio0.channel', $apConfig->selectedChannel);

        $this->systemHelper->uciSet('wireless.@wifi-iface[0].ssid', $apConfig->openSSID, false);
        $this->systemHelper->uciSet('wireless.@wifi-iface[0].disabled', $apConfig->disableOpenAP, false);
        $this->systemHelper->uciSet('wireless.@wifi-iface[0].hidden', $apConfig->hideOpenAP, false);
        $this->systemHelper->uciSet('wireless.@wifi-iface[0].maxassoc', $apConfig->maxClients, false);

        $this->systemHelper->uciSet('wireless.@wifi-iface[1].ssid', $apConfig->managementSSID, false);
        $this->systemHelper->uciSet('wireless.@wifi-iface[1].key', $apConfig->managementKey, false);
        $this->systemHelper->uciSet('wireless.@wifi-iface[1].disabled', $apConfig->disableManagementAP, false);
        $this->systemHelper->uciSet('wireless.@wifi-iface[1].hidden', $apConfig->hideManagementAP, false);

        $this->systemHelper->uciCommit();
        $this->systemHelper->execBackground('wifi');

        return ["success" => true];
    }

    public function getAPConfig($getChannelInfo = true)
    {
        $channels = [];
        if ($getChannelInfo) {
            exec("iwinfo phy0 freqlist", $output);
            preg_match_all("/\(Channel (\d+)\)$/m", implode("\n", $output), $channelList);

            // Remove radar detection channels
            foreach ($channelList[1] as $channel) {
                if ((int)$channel < 52 || (int)$channel > 140) {
                    $channels[] = $channel;
                }
            }
        }

        return [
            "selectedChannel" => $this->systemHelper->uciGet("wireless.radio0.channel"),
            "availableChannels" => $channels,

            "openSSID" => $this->systemHelper->uciGet("wireless.@wifi-iface[0].ssid"),
            "maxClients" => $this->systemHelper->uciGet("wireless.@wifi-iface[0].maxassoc", false),
            "disableOpenAP" => $this->systemHelper->uciGet("wireless.@wifi-iface[0].disabled"),
            "hideOpenAP" => $this->systemHelper->uciGet("wireless.@wifi-iface[0].hidden"),

            "managementSSID" => $this->systemHelper->uciGet("wireless.@wifi-iface[1].ssid"),
            "managementKey" => $this->systemHelper->uciGet("wireless.@wifi-iface[1].key"),
            "disableManagementAP" => $this->systemHelper->uciGet("wireless.@wifi-iface[1].disabled"),
            "hideManagementAP" => $this->systemHelper->uciGet("wireless.@wifi-iface[1].hidden")
        ];
    }
}
