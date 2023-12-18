<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Advanced extends SystemModule
{
    protected $endpointRoutes = ['getResources', 'dropCaches', 'getUSB', 'getFstab', 'saveFstab', 'getCSS', 'saveCSS', 'formatSDCard', 'formatSDCardStatus', 'checkForUpgrade', 'downloadUpgrade', 'getDownloadStatus', 'performUpgrade', 'getCurrentVersion', 'checkApiToken', 'addApiToken', 'getApiTokens', 'revokeApiToken'];
    private $dbConnection;

    const DATABASE = "/etc/pineapple/pineapple.db";
    const UP_PATH = "/tmp/upgrade.bin";
    const UP_FLAG = "/tmp/upgradeDownloaded";
    const UP_PATCH = "/tmp/hotpatch.patch";

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = new \frieren\orm\SQLite(self::DATABASE);
        $this->dbConnection->execLegacy("CREATE TABLE IF NOT EXISTS api_tokens (token VARCHAR NOT NULL, name VARCHAR NOT NULL);");
    }

    private function getResources()
    {
        exec('df -h', $freeDisk);
        $freeDisk = implode("\n", $freeDisk);

        exec('free -m', $freeMem);
        $freeMem = implode("\n", $freeMem);

        $this->responseHandler->setData(["freeDisk" => $freeDisk, "freeMem" => $freeMem]);
    }

    private function dropCaches()
    {
        $this->systemHelper->execBackground('echo 3 > /proc/sys/vm/drop_caches');
        $this->responseHandler->setData(['success' => true]);
    }

    private function getUSB()
    {
        exec('lsusb', $lsusb);
        $lsusb = implode("\n", $lsusb);
        $this->responseHandler->setData(['lsusb' => $lsusb]);
    }

    private function getFstab()
    {
        $fstab = file_get_contents('/etc/config/fstab');
        $this->responseHandler->setData(['fstab' => $fstab]);
    }

    private function saveFstab()
    {
        if (isset($this->request['fstab'])) {
            file_put_contents('/etc/config/fstab', $this->request['fstab']);
            $this->responseHandler->setData(['success' => true]);
        }
    }

    private function getCSS()
    {
        $css = file_get_contents('/pineapple/css/main.css');
        $this->responseHandler->setData(['css' => $css]);
    }

    private function saveCSS()
    {
        if (isset($this->request['css'])) {
            file_put_contents('/pineapple/css/main.css', $this->request['css']);
            $this->responseHandler->setData(['success' => true]);
        }
    }

    private function checkForUpgrade()
    {
        $upgradeData = @$this->systemHelper->fileGetContentsSSL(self::REMOTE_URL . "/json/upgrades.json");
        if ($upgradeData !== false) {
            $upgradeData = json_decode($upgradeData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if ($this->compareFirmwareVersion($upgradeData['version']) === true) {
                    $board = $this->systemHelper->getBoard();
                    if ($upgradeData['hotpatch'] != null) {
                        $hotpatch = base64_decode($upgradeData['hotpatch']);
                        file_put_contents($hotpatch, self::UP_PATCH);
                    } else if ($board && isset($upgradeData['updates'][ $board ])) {
                        $download = $upgradeData['updates'][ $board ];
                        $upgradeData = array_merge($upgradeData, $download);
                    }

                    unset($upgradeData['updates']);
                    $this->responseHandler->setData(["upgrade" => true, "upgradeData" => $upgradeData]);
                } else {
                    $this->responseHandler->setError("No upgrade found.");
                }
            }
        } else {
            $this->responseHandler->setError("Error connecting to " . self::REMOTE_NAME . ". Please check your connection.");
        }
    }

    private function downloadUpgrade()
    {
        if (file_exists(self::UP_PATCH)) {
            exec("cd / && patch < " . self::UP_PATCH);
        }

        @unlink(self::UP_PATH);
        @unlink(self::UP_FLAG);
        $url = escapeshellarg($this->request['upgradeUrl']);
        $this->systemHelper->execBackground("uclient-fetch -q -T 10 -O " . self::UP_PATH . " {$url} && touch " . self::UP_FLAG);
        $this->responseHandler->setData(["success" => true]);
    }

    private function getDownloadStatus()
    {
        if (file_exists(self::UP_FLAG)) {
            $fileHash = hash_file('sha256', self::UP_PATH);
            if ((bool)$this->request['isManuelUpdate']) {
                $bytes = filesize(self::UP_PATH);
                $sz = 'BKMGTP';
                $factor = floor((strlen($bytes) - 1) / 3);
  
                $this->responseHandler->setData([
                    "completed" => true,
                    "sha256" => $fileHash,
                    "downloaded" => sprintf("%.2f", $bytes / pow(1024, $factor)) . @$sz[$factor]
                ]);
            } else if ($fileHash == $this->request['checksum']) {
                $this->responseHandler->setData(["completed" => true]);
            } else {
                $this->responseHandler->setError("Checksum mismatch");
            }
        } else {
            $this->responseHandler->setData([
                "completed" => false,
                "downloaded" => filesize(self::UP_PATH)
            ]);
        }
    }

    private function performUpgrade()
    {
        if (file_exists(self::UP_PATH)) {
            $params = "-n";
            if ($this->request['keepSettings']) {
                $params = "";
            }

            $this->systemHelper->execBackground("sysupgrade {$params} " . self::UP_PATH);
            $this->responseHandler->setData(["success" => true]);
        } else {
            $this->responseHandler->setError("Upgrade failed.");
        }
    }

    private function compareFirmwareVersion($version)
    {
        return version_compare($this->systemHelper->getFirmwareVersion(), $version, '<');
    }

    private function getCurrentVersion()
    {
        $this->responseHandler->setData(["firmwareVersion" => $this->systemHelper->getFirmwareVersion()]);
    }

    private function formatSDCard()
    {
        $this->systemHelper->execBackground("/pineapple/modules/Advanced/formatSD/format_sd");
        $this->responseHandler->setData(['success' => true]);
    }

    private function formatSDCardStatus()
    {
        $this->responseHandler->setData(['success' => (!file_exists('/tmp/sd_format.progress'))]);
    }

    private function getApiTokens()
    {
        $tokens = $this->dbConnection->queryLegacy("SELECT ROWID, token, name FROM api_tokens;");
        $this->responseHandler->setData(["tokens" => $tokens]);
    }

    private function checkApiToken()
    {
        if (isset($this->request['token'])) {
            $token = $this->request['token'];
            $result = $this->dbConnection->queryLegacy("SELECT token FROM api_tokens WHERE token='%s';", $token);
            if (!empty($result) && isset($result[0]["token"]) && $result[0]["token"] === $token) {
                $this->responseHandler->setData(["valid" => true]);
                return;
            }
        }

        $this->responseHandler->setData(["valid" => false]);
    }

    private function addApiToken()
    {
        if (isset($this->request['name'])) {
            $token = hash('sha512', random_bytes(32));
            $name = $this->request['name'];
            $this->dbConnection->execLegacy("INSERT INTO api_tokens(token, name) VALUES('%s','%s');", $token, $name);
            $this->responseHandler->setData(["success" => true, "token" => $token, "name" => $name]);
        } else {
            $this->responseHandler->setError("Missing token name");
        }
    }

    private function revokeApiToken()
    {
        if (isset($this->request['id'])) {
            $this->dbConnection->execLegacy("DELETE FROM api_tokens WHERE ROWID='%s'", $this->request['id']);
        } elseif (isset($this->request['token'])) {
            $this->dbConnection->execLegacy("DELETE FROM api_tokens WHERE token='%s'", $this->request['token']);
        } elseif (isset($this->request['name'])) {
            $this->dbConnection->execLegacy("DELETE FROM api_tokens WHERE name='%s'", $this->request['name']);
        } else {
            $this->responseHandler->setError("The revokeApiToken API call requires either a 'id', 'token', or 'name' parameter");
        }
    }
}
