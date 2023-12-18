<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

'';

class Dashboard extends SystemModule
{
    protected $endpointRoutes = ['getOverviewData', 'getLandingPageData', 'getBulletins'];
    private $dbConnection;
    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;
        if (file_exists('/tmp/landingpage.db')) {
            $this->dbConnection = new \frieren\orm\SQLite('/tmp/landingpage.db');
        }
    }

    private function getOverviewData()
    {
        $this->responseHandler->setData([
            "cpu" => $this->getCpu(),
            "uptime" => $this->getUptime(),
            "clients" => $this->getClients()
        ]);
    }

    private function getCpu()
    {
        $loads = sys_getloadavg();
        $load = round($loads[0]/2*100, 1);

        if ($load > 100) {
            return '100';
        }

        return $load;
    }

    private function getUptime()
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

    private function getClients()
    {
        return exec('iw dev wlan0 station dump | grep Station | wc -l');
    }

    private function getLandingPageData()
    {
        if ($this->dbConnection !== false) {
            $stats = [];
            $stats['Chrome'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'chrome\';'));
            $stats['Safari'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'safari\';'));
            $stats['Firefox'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'firefox\';'));
            $stats['Opera'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'opera\';'));
            $stats['Internet Explorer'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'internet_explorer\';'));
            $stats['Other'] = count($this->dbConnection->queryLegacy('SELECT browser FROM user_agents WHERE browser=\'other\';'));
            $this->responseHandler->setData($stats);
        } else {
            $this->responseHandler->setError("A connection to the database is not established.");
        }
    }


    private function getBulletins()
    {
        $bulletinData = @$this->systemHelper->fileGetContentsSSL(self::REMOTE_URL . "/json/news.json");
        if ($bulletinData !== false) {
            $this->responseHandler->setData(json_decode($bulletinData));
            if (json_last_error() === JSON_ERROR_NONE) {
                return;
            }
        }
        
        $this->responseHandler->setError("Error connecting to " . self::REMOTE_NAME . ". Please check your connection.");
    }
}
