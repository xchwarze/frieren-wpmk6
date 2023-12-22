<?php

class DeviceConfig
{
    // third party modules can change the options based on this
    // the allowed values are: 'nano' or 'tetra'
    const DEVICE_TYPE = 'tetra';

    const USE_INTERNAL_STORAGE = true;

    const USE_USB_STORAGE = true;

    const SHOW_FIREWALL_CONFIG = true;

    // third party modules do not have this flag implemented
    const SHOW_SCAN_TYPE = true;

    // hide wlan0 in getClientInterfaces() enumeration
    const HIDE_WLAN0_CLIENT = true;

    // hide system modules
    const HIDE_SYSTEM_MODULES = true;

    // Remote content
    const SERVER_URL = 'https://raw.githubusercontent.com/xchwarze/wifi-pineapple-community/main';
    const NEWS_PATH = '%s/json/news.json';
    const UPGRADE_PATH = '%s/json/upgrades.json';
    const MODULES_PATH = 'https://raw.githubusercontent.com/xchwarze/wifi-pineapple-community/frieren/modules/build/modules.json';
    const INSTALL_MODULE_PATH = 'https://raw.githubusercontent.com/xchwarze/wifi-pineapple-community/frieren/modules/build/%s';
    const OUI_PATH = '%s/oui/oui.txt';
}
