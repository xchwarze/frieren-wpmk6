<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Modules extends APIModule
{
    protected $endpointRoutes = ['getModuleList'];
    private $modules;

    public function __construct($request)
    {
        Parent::__construct($request);
        $this->modules = [
            'systemModules' => [],
            'userModules'   => []
        ];
    }

    protected function getModules()
    {
        '';

        $dir = scandir("../modules");
        if ($dir === false) {
            $this->responseHandler->setError("Unable to access modules directory");
            return $this->modules;
        }

        natcasesort($dir);
        foreach ($dir as $moduleFolder) {
            $modulePath = "../modules/{$moduleFolder}";
            if ($moduleFolder[0] === '.' || !file_exists("{$modulePath}/module.info")) {
                continue;
            }

            $moduleInfo = @json_decode(file_get_contents("{$modulePath}/module.info"));
            if (json_last_error() !== JSON_ERROR_NONE || isset($moduleInfo->cliOnly)) {
                continue;
            }

            $jsonModulePath = "/modules/${moduleFolder}";
            $module = [
                "name"     => $moduleFolder,
                "title"    => isset($moduleInfo->title) ? $moduleInfo->title : $moduleFolder,
                "icon"     => null,
                "injectJS" => isset($moduleInfo->injectJS) ? "${jsonModulePath}/{$moduleInfo->injectJS}" : null,
            ];

            if (file_exists("$modulePath/module_icon.svg")) {
                $module["icon"] = "${jsonModulePath}/module_icon.svg";
            } elseif (file_exists("$modulePath/module_icon.png")) {
                $module["icon"] = "${jsonModulePath}/module_icon.png";
            }

            if (isset($moduleInfo->system)) {
                if (isset($moduleInfo->index)) {
                    $this->modules['systemModules'][$moduleInfo->index] = $module;
                }
            } else {
                $this->modules['userModules'][] = $module;
            }
        }

        return $this->modules;
    }
}
