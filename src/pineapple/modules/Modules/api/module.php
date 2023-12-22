<?php

namespace frieren\core;

class Modules extends Controller
{
    const DOWN_FLAG = '/tmp/moduleDownloaded';
    const INSTALL_FLAG = '/tmp/moduleInstalled';
    const SECURE_SPACE = 150000;

    public $modules;
    public $endpointRoutes = [
        'getModuleList', 'getAvailableModules', 'getInstalledModules', 'downloadModule',
        'downloadStatus','installModule', 'installStatus', 'checkDestination', 'removeModule'
    ];

    public function getModuleList()
    {
        $this->modules = [
            'systemModules' => [],
            'userModules'   => []
        ];

        $moduleDirPath = "../modules";
        $dir = new \DirectoryIterator($moduleDirPath);
        if (!$dir->isReadable()) {
            return $this->responseHandler->setError('Unable to access modules directory');
        }

        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDot() || !$fileinfo->isDir()) {
                continue;
            }

            $moduleFolder = $fileinfo->getFilename();
            $modulePath = "{$moduleDirPath}/{$moduleFolder}";
            $moduleInfoPath = "{$modulePath}/module.info";
            if (!file_exists($moduleInfoPath)) {
                continue;
            }

            $moduleInfo = json_decode(file_get_contents($moduleInfoPath));
            if (json_last_error() !== JSON_ERROR_NONE || isset($moduleInfo->cliOnly)) {
                continue;
            }

            $module = $this->processModule($moduleFolder, $moduleInfo, $modulePath);
            $this->categorizeModule($module, $moduleInfo);
        }

        $this->responseHandler->setData($this->modules);
    }

    public function processModule($moduleFolder, $moduleInfo, $modulePath) {
        $jsonModulePath = "/modules/{$moduleFolder}";
        $module = [
            "name"     => $moduleFolder,
            "title"    => $moduleInfo->title ?? $moduleFolder,
            "icon"     => $this->getModuleIcon($modulePath, $jsonModulePath),
            "injectJS" => isset($moduleInfo->injectJS) ? "{$jsonModulePath}/{$moduleInfo->injectJS}" : null
        ];

        return $module;
    }

    public function getModuleIcon($modulePath, $jsonModulePath) {
        if (file_exists("{$modulePath}/module_icon.svg")) {
            return "{$jsonModulePath}/module_icon.svg";
        } elseif (file_exists("{$modulePath}/module_icon.png")) {
            return "{$jsonModulePath}/module_icon.png";
        }
        return null;
    }

    public function categorizeModule($module, $moduleInfo) {
        if (isset($moduleInfo->system)) {
            $index = $moduleInfo->index ?? count($this->modules['systemModules']);
            $this->modules['systemModules'][$index] = $module;
        } else {
            $this->modules['userModules'][] = $module;
        }
    }

    public function getAvailableModules()
    {
        $url = sprintf(\DeviceConfig::MODULES_PATH, \DeviceConfig::SERVER_URL);
        $moduleData = $this->systemHelper->fileGetContentsSSL($url);
        if ($moduleData !== false) {
            $moduleData = json_decode($moduleData);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->responseHandler->setData(['availableModules' => $moduleData]);
            }
        }

        $this->responseHandler->setError('Error connecting to remote host. Please check your connection.');
    }

    public function getInstalledModules()
    {
        $modules = [];        
        $moduleDirPath = "../modules";
        $dir = new \DirectoryIterator($moduleDirPath);
        if (!$dir->isReadable()) {
            return $this->responseHandler->setError('Unable to access modules directory');
        }

        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDot() || !$fileinfo->isDir()) {
                continue;
            }

            $moduleFolder = $fileinfo->getFilename();
            $modulePath = "{$moduleDirPath}/{$moduleFolder}";
            $moduleInfoPath = "{$modulePath}/module.info";
            if (!file_exists($moduleInfoPath)) {
                continue;
            }

            $moduleInfo = json_decode(file_get_contents($moduleInfoPath));
            if (json_last_error() !== JSON_ERROR_NONE || isset($moduleInfo->cliOnly)) {
                continue;
            }

            if (\DeviceConfig::HIDE_SYSTEM_MODULES === true && isset($moduleInfo->system)) {
                continue;
            }

            $module = [
                'title' => $moduleInfo->title,
                'author' => $moduleInfo->author,
                'version' => $moduleInfo->version,
                'description' => $moduleInfo->description,
                //'size' => $fileinfo->getSize(),
                'size' => exec("du -sh /pineapple/modules/{$moduleFolder}/ | awk '{print $1;}'"),
                'type' => 'GUI',
            ];

            if (isset($moduleData->system)) {
                $module['type'] = 'System';
            } elseif (isset($moduleData->cliOnly)) {
                $module['type'] = 'CLI';
            }

            $modules[$moduleFolder] = $module;
        }

        $this->responseHandler->setData(['installedModules' => $modules]);
    }

    public function downloadModule()
    {
        @unlink(self::DOWN_FLAG);
        $destination = $this->request['destination'] === 'sd' ? '/sd/tmp/' : '/tmp/';
        @mkdir($destination, 0777, true);

        $moduleFileName = "{$this->request['moduleName']}.tar.gz";
        //$url = sprintf(\DeviceConfig::INSTALL_MODULE_PATH, \DeviceConfig::SERVER_URL, $moduleFileName);
        $url = sprintf(\DeviceConfig::INSTALL_MODULE_PATH, $moduleFileName);
        $this->systemHelper->downloadFile($url, "{$destination}{$moduleFileName}", self::DOWN_FLAG);

        $this->responseHandler->setData(['success' => true]);
    }

    public function downloadStatus()
    {
        if (file_exists(self::DOWN_FLAG)) {
            $destination = $this->request['destination'] === 'sd' ? '/sd/tmp/' : '/tmp/';
            $moduleFileName = "{$destination}{$this->request['moduleName']}.tar.gz";

            if (hash_file('sha256', $moduleFileName) == $this->request['checksum']) {
                return $this->responseHandler->setData(['success' => true]);
            }
        }
        
        $this->responseHandler->setData(['success' => false]);
    }

    public function installModule()
    {
        @unlink(self::INSTALL_FLAG);
        $this->removeModule();

        $destination = $this->request['destination'] === 'sd' ? '/sd/tmp/' : '/tmp/';
        $installDestination = $this->request['destination'] === 'sd' ? '/sd/modules/' : '/pineapple/modules/';
        if ($this->request['destination'] === 'sd') {
            @mkdir('/sd/modules/', 0777, true);
            exec("ln -s /sd/modules/{$this->request['moduleName']} /pineapple/modules/{$this->request['moduleName']}");
        }

        $moduleFileName = "{$this->request['moduleName']}.tar.gz";
        $this->systemHelper->execBackground(
            "tar -xzvC {$installDestination} -f {$destination}{$moduleFileName} && " .
            "rm {$destination}{$moduleFileName} && " .
            "touch " . self::INSTALL_FLAG
        );
        $this->responseHandler->setData(['success' => true]);
    }

    public function installStatus()
    {
        $this->responseHandler->setData(['success' => file_exists(self::INSTALL_FLAG)]);
    }

    public function checkDestination()
    {
        $config = $this->systemHelper->getDeviceConfig();
        $validSpace = disk_free_space('/') > ($this->request['size'] + self::SECURE_SPACE);

        $this->responseHandler->setData([
            'module' => $this->request['name'],
            'internal' => $validSpace && $config['useInternalStorage'],
            'sd' => ($this->systemHelper->isSDAvailable() && $config['useUSBStorage']),
        ]);
    }

    public function removeModule()
    {
        $modulePath = "/pineapple/modules/{$this->request['moduleName']}";
        if (is_link($modulePath)) {
            @unlink($modulePath);
            exec("rm -rf /sd/modules/{$this->request['moduleName']}");
        } else {
            exec("rm -rf {$modulePath}");
        }

        $this->responseHandler->setData(['success' => true]);
    }
}
