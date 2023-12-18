<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Help extends Controller
{
    protected $endpointRoutes = ['generateDebugFile', 'downloadDebugFile', 'getConsoleOutput'];

    private function generateDebugFile()
    {
        @unlink('/tmp/debug.log');
        $this->systemHelper->execBackground("(/pineapple/modules/Help/files/debug 2>&1) > /tmp/debug_generation_output");
        $this->responseHandler->setData(array("success" => true));
    }

    private function downloadDebugFile()
    {
        if (!file_exists('/tmp/debug.log')) {
            $this->responseHandler->setError("The debug file is missing.");
            return;
        }
        $this->responseHandler->setData(array("success" => true, "downloadToken" => $this->systemHelper->downloadFile("/tmp/debug.log")));
    }

    private function getConsoleOutput()
    {
        $output = "";
        if (file_exists("/tmp/debug_generation_output")) {
            $output = file_get_contents("/tmp/debug_generation_output");
        }
        $this->responseHandler->setData(array("output" => $output));
    }
}
