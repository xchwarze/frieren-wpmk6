registerController("ModulesController", ['$api', '$scope', '$timeout', '$interval', '$templateCache', '$rootScope', function($api, $scope, $timeout, $interval, $templateCache, $rootScope){
    $rootScope.availableModules = [];
    $rootScope.installedModules = [];
    $scope.installedModule = "";
    $scope.removedModule = "";
    $scope.gotAvailableModules = false;
    $scope.connectionError = false;
    $scope.selectedModule = false;
    $scope.downloading = false;
    $scope.installing = false;
    $scope.linking = false;

    $scope.getAvailableModules = (function() {
        $scope.loading = true;
        $api.request({
            module: "Modules",
            action: "getAvailableModules"
        }, function(response) {
            $scope.loading = false;
            if (response.error === undefined) {
                $rootScope.availableModules = response.availableModules;
                $scope.compareModuleLists();
                $scope.gotAvailableModules = true;
                $scope.connectionError = false;
            } else {
                $scope.connectionError = response.error;
            }
        });
    });

    $scope.getInstalledModules = (function() {
        $api.request({
            module: "Modules",
            action: "getInstalledModules"
        }, function(response) {
            $rootScope.installedModules = response.installedModules;
            if ($scope.gotAvailableModules) {
                $scope.compareModuleLists();
            }
        });
    });

    $scope.compareModuleLists = (function() {
        angular.forEach($rootScope.availableModules, function(module, moduleName){
            if ($rootScope.installedModules[moduleName] === undefined){
                module['installable'] = true;
            } else if ($rootScope.availableModules[moduleName].version <= $rootScope.installedModules[moduleName].version) {
                module['installed'] = true;
            }
        });
    });

    $scope.removeModule = (function(name) {
        $api.request({
            module: 'Modules',
            action: 'removeModule',
            moduleName: name
        }, function(response) {
            if (response.success === true) {
                $scope.getInstalledModules();
                $scope.removedModule = true;
                $api.reloadNavbar();
                $timeout(function(){
                    $scope.removedModule = false;
                }, 2000);
            }
        });
    });

    $scope.restoreSDcardModules = (function() {
        $api.request({
            module: 'Modules',
            action: 'restoreSDcardModules'
        }, function(response) {
            if (response.restored === true) {
                $scope.restoreSDcardModules();
            } else {
                $api.reloadNavbar();
                $scope.getInstalledModules();
                $scope.linking = false;
            }
        });
    });

    $scope.getInstalledModules();
}]);
