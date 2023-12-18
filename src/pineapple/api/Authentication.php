<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

'';

class Authentication extends APIModule
{
    protected $endpointRoutes = ['login', 'logout', 'checkAuth', 'checkApiToken', 'addApiToken', 'getApiTokens'];
    private $dbConnection;

    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        parent::__construct($request);
        $this->dbConnection = new \frieren\orm\SQLite(self::DATABASE);
        $this->dbConnection->execLegacy("CREATE TABLE IF NOT EXISTS api_tokens (token VARCHAR NOT NULL, name VARCHAR NOT NULL);");
    }

    protected function getApiTokens()
    {
        $this->responseHandler->setData(array("tokens" => $this->dbConnection->queryLegacy("SELECT token,name FROM api_tokens;")));
    }

    protected function checkApiToken()
    {
        if (isset($this->request['token'])) {
            $token = $this->request['token'];
            $result = $this->dbConnection->queryLegacy("SELECT token FROM api_tokens WHERE token='%s';", $token);
            if (!empty($result) && isset($result[0]["token"]) && $result[0]["token"] === $token) {
                $this->responseHandler->setData(array("valid" => true));
                return;
            }
        }
        $this->responseHandler->setData(array("valid" => false));
    }

    protected function addApiToken()
    {
        if (isset($this->request['token']) && isset($this->request['name'])) {
            $token = $this->request['token'];
            $name = $this->request['name'];
            $this->dbConnection->execLegacy("INSERT INTO api_tokens(token, name) VALUES('%s','%s');", $token, $name);
            $this->responseHandler->setData(array("success" => true));
            return;
        }
        $this->responseHandler->setError("Missing token or name");
    }

    private function login()
    {
        if (isset($this->request['username']) && isset($this->request['password'])) {
            if ($this->verifyPassword($this->request['password'])) {
                $_SESSION['logged_in'] = true;
                $this->responseHandler->setData(array("logged_in" => true));
                if (!isset($this->request['time'])) {
                    return;
                }
                $epoch = intval($this->request['time']);
                if ($epoch > 1) {
                    exec('date -s @' . $epoch);
                }
                return;
            }
        }

        $this->responseHandler->setData(array("logged_in" => false));
    }

    private function verifyPassword($password)
    {
        $shadowContents = file_get_contents('/etc/shadow');
        $rootArray = explode(':', explode('root:', $shadowContents)[1]);
        $rootPass = $rootArray[0];
        if (!empty($rootPass) && gettype($rootPass) === "string") {
            return hash_equals($rootPass, crypt($password, $rootPass));
        }
        return false;
    }

    private function logout()
    {
        $this->responseHandler->setData(array("logged_in" => false));
        unset($_COOKIE['XSRF-TOKEN']);
        setcookie('XSRF-TOKEN', '', time()-3600);
        unset($_SESSION['XSRF-TOKEN']);
        unset($_SESSION['logged_in']);
        session_destroy();
    }

    private function checkAuth()
    {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            $this->responseHandler->setData(array("authenticated" => true));
        } else {
            if (file_exists("/etc/pineapple/setupRequired")) {
                $this->responseHandler->setData(array("error" => "Not Authenticated", "setupRequired" => true));
            } else {
                $this->responseHandler->setData(array("error" => "Not Authenticated"));
            }
        }
    }
}
