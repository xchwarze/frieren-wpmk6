<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

'';

class Dashboard extends Controller
{
    public $endpointRoutes = ['getOverviewData', 'getLandingPageData', 'getBulletins'];
    public $dbConnection;
    public function __construct($request)
    {
        $this->dbConnection = false;

        parent::__construct($request);
    }

    public function getOverviewData()
    {
        $this->responseHandler->setData([
            "cpu" => $this->getCpu(),
            "uptime" => $this->getUptime(),
            "clients" => $this->getClients()
        ]);
    }

    public function getCpu()
    {
        $loads = sys_getloadavg();
        $load = round($loads[0]/2*100, 1);

        if ($load > 100) {
            return '100';
        }

        return $load;
    }

    public function getUptime()
    {
        $seconds = intval(explode('.', file_get_contents('/proc/uptime'))[0]);
        $days = floor($seconds / (24 * 60 * 60));
        $hours = floor(($seconds % (24 * 60 * 60)) / (60 * 60));
        if ($days > 0) {
            return $days . ($days == 1 ? " day, " : " days, ") . $hours . ($hours == 1 ? " hour" : " hours");
        }
        $minutes = floor(($seconds % (60 * 60)) / 60);
        return $hours . ($hours == 1 ? " hour, " : " hours, ") . $minutes . ($minutes == 1 ? " minute" : " minutes");
    }

    public function getClients()
    {
        return exec('iw dev wlan0 station dump | grep Station | wc -l');
    }

    public function getLandingPageData()
    {
        if (file_exists('/tmp/landingpage.db')) {
            $this->dbConnection = new \frieren\orm\SQLite('/tmp/landingpage.db');

            $stats = [];
            $stats['Chrome'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'chrome\';'));
            $stats['Safari'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'safari\';'));
            $stats['Firefox'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'firefox\';'));
            $stats['Opera'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'opera\';'));
            $stats['Internet Explorer'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'internet_explorer\';'));
            $stats['Other'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'other\';'));

            return $this->responseHandler->setData($stats);
        }

        $this->responseHandler->setError("landingpage.db not found");
    }


    public function getBulletins()
    {
        $url = sprintf(\DeviceConfig::NEWS_PATH, \DeviceConfig::SERVER_URL);
        $bulletinData = @$this->systemHelper->fileGetContentsSSL($url);
        if ($bulletinData !== false) {
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->responseHandler->setData(json_decode($bulletinData));
            }
        }
        
        $this->responseHandler->setError("Error connecting to remote host. Please check your connection.");
    }
}
