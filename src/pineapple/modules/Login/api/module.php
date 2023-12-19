<?php

namespace frieren\core;

class Login extends Controller
{
    public $endpointRoutes = ['login', 'logout', 'checkAuth'];

    public function login()
    {
        if (isset($this->request['username']) && isset($this->request['password'])) {
            if ($this->systemHelper->verifyPassword($this->request['username'], $this->request['password'])) {
                $_SESSION['user_logged'] = true;

                // this routine is also used to synchronize the device time
                if (isset($this->request['time'])) {
                    $epoch = intval($this->request['time']);
                    exec("date -s @{$epoch}");
                }

                return $this->responseHandler->setData(['logged' => true]);
            }
        }

        $this->responseHandler->setError('Not logged_in');
    }

    public function logout()
    {
        unset($_SESSION['XSRF-TOKEN']);
        unset($_SESSION['user_logged']);
        unset($_COOKIE['XSRF-TOKEN']);
        setcookie('XSRF-TOKEN', '', time() - 3600, '/');
        session_destroy();

        $this->responseHandler->setData(['logged' => false]);
    }

    public function checkAuth()
    {
        if (isset($_SESSION['user_logged']) && $_SESSION['user_logged'] === true) {
            session_write_close();
            return $this->responseHandler->setData(["authenticated" => true]);
        }
        
        $this->responseHandler->setData(["error" => "Not Authenticated"]);
    }
}
