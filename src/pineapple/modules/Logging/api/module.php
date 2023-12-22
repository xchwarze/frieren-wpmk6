<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Logging extends Controller
{
    public $endpointRoutes = ['getSyslog', 'getDmesg', 'getReportingLog', 'getPineapLog', 'clearPineapLog', 'getPineapLogLocation', 'setPineapLogLocation', 'downloadPineapLog'];
    public $dbConnection;

    private function setupDB()
    {
        $dbLocation = $this->systemHelper->uciGet("pineap.@config[0].hostapd_db_path");
        $this->dbConnection = new \frieren\orm\SQLite($dbLocation);
    }

    public function downloadPineapLog()
    {
        $this->setupDB();

        $rows = $this->dbConnection->query("SELECT * FROM log ORDER BY updated_at ASC;");
        $logFile = fopen("/tmp/pineap.log", 'w');
        $count = "-";
        foreach ($rows as $row) {
            switch ($row['log_type']) {
                case 0:
                    $type = "Probe Request";
                    $count = $row['dups'];
                    break;
                case 1:
                    $type = "Association";
                    break;
                case 2:
                    $type = "De-association";
                    break;
                default:
                    $type = "";
                    break;
            }
            fwrite($logFile, "${row['created_at']},\t${type},\t${row['mac']},\t${row['ssid']},\t${count}\n");
        }
        fclose($logFile);
        $this->responseHandler->setData(array("download" => $this->systemHelper->generateDownloadFile('/tmp/pineap.log')));
    }

    public function getSyslog()
    {
        exec("logread", $syslogOutput);
        $this->responseHandler->setData(implode("\n", $syslogOutput));
    }

    public function getDmesg()
    {
        exec("dmesg", $dmesgOutput);
        $this->responseHandler->setData(implode("\n", $dmesgOutput));
    }

    public function getReportingLog()
    {
        touch('/tmp/reporting.log');
        $this->streamFunction = function () {
            $fp = fopen('/tmp/reporting.log', 'r');
            while (($buf = fgets($fp)) !== false) {
                echo $buf;
            }
            fclose($fp);
        };
    }

    public function getPineapLog()
    {
        $this->setupDB();

        $rows = $this->dbConnection->query("SELECT * FROM log ORDER BY updated_at DESC;");
        $this->responseHandler->setData(array("pineap_log" => $rows));
    }

    public function clearPineapLog()
    {
        $this->setupDB();

        $this->dbConnection->exec("DELETE FROM log;");
        $this->responseHandler->setData(array('success' => true));
    }

    public function getPineapLogLocation()
    {
        $dbBasePath = dirname($this->systemHelper->uciGet("pineap.@config[0].hostapd_db_path"));
        $this->responseHandler->setData(array('location' => $dbBasePath . "/"));
    }

    public function setPineapLogLocation()
    {
        $dbLocation = dirname($this->request['location'] . '/fake_file');
        $this->systemHelper->uciSet("pineap.@config[0].hostapd_db_path", $dbLocation . '/log.db');
        $this->responseHandler->setData(array('success' => true));
    }
}
