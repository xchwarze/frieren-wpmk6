<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
abstract class APIModule
{
    protected $request;
    protected $response;
    protected $error;

    public function __construct($request)
    {
        $this->request = $request;
    }

    protected function getResponse()
    {
        if (empty($this->error) && !empty($this->response)) {
            return $this->response;
        } elseif (empty($this->error) && empty($this->response)) {
            return ['error' => 'API returned empty response'];
        } else {
            return ['error' => $this->error];
        }
    }
}
