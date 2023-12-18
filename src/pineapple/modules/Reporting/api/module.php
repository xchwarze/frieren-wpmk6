<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Reporting extends SystemModule
{
    protected $endpointRoutes = ['getReportConfiguration', 'getReportContents', 'getEmailConfiguration', 'setReportConfiguration', 'setReportContents', 'setEmailConfiguration', 'testReportConfiguration'];

    private function getReportConfiguration()
    {
        $this->responseHandler->setData([
            "config" => [
                "generateReport" => !(exec("grep files/reporting /etc/crontabs/root") == ""),
                "storeReport" => $this->systemHelper->uciGet("reporting.@settings[0].save_report"),
                "sendReport" => $this->systemHelper->uciGet("reporting.@settings[0].send_email"),
                "interval" => (string) $this->systemHelper->uciGet("reporting.@settings[0].interval")
            ],
            "sdDisabled" => !$this->systemHelper->isSDAvailable(),
        ]);
    }

    private function getReportContents()
    {
        $this->responseHandler->setData([
            "config" => [
                "pineAPLog" => $this->systemHelper->uciGet("reporting.@settings[0].log"),
                "clearLog" => $this->systemHelper->uciGet("reporting.@settings[0].clear_log"),
                "siteSurvey" => $this->systemHelper->uciGet("reporting.@settings[0].survey"),
                "siteSurveyDuration" => $this->systemHelper->uciGet("reporting.@settings[0].duration"),
                "client" => $this->systemHelper->uciGet("reporting.@settings[0].client"),
                "tracking" => $this->systemHelper->uciGet("reporting.@settings[0].tracking")
            ]
        ]);
    }

    private function getEmailConfiguration()
    {
        $this->responseHandler->setData([
            "config" => [
                "from" => $this->systemHelper->uciGet("reporting.@ssmtp[0].from"),
                "to" => $this->systemHelper->uciGet("reporting.@ssmtp[0].to"),
                "server" => $this->systemHelper->uciGet("reporting.@ssmtp[0].server"),
                "port" => $this->systemHelper->uciGet("reporting.@ssmtp[0].port"),
                "domain" => $this->systemHelper->uciGet("reporting.@ssmtp[0].domain"),
                "username" => $this->systemHelper->uciGet("reporting.@ssmtp[0].username"),
                "password" => $this->systemHelper->uciGet("reporting.@ssmtp[0].password"),
                "tls" => $this->systemHelper->uciGet("reporting.@ssmtp[0].tls"),
                "starttls" => $this->systemHelper->uciGet("reporting.@ssmtp[0].starttls")
            ]
        ]);
    }

    private function setReportConfiguration()
    {
        $this->systemHelper->uciSet("reporting.@settings[0].save_report", $this->request['config']->storeReport);
        $this->systemHelper->uciSet("reporting.@settings[0].send_email", $this->request['config']->sendReport);
        $this->systemHelper->uciSet("reporting.@settings[0].interval", $this->request['config']->interval);
        $this->responseHandler->setData(["success" => true]);

        if ($this->request['config']->generateReport === true) {
            $hours_minus_one = $this->systemHelper->uciGet("reporting.@settings[0].interval")-1;
            $hour_string = ($hours_minus_one == 0) ? "*" : "*/" . ($hours_minus_one + 1);
            exec("sed -i '/DO NOT TOUCH/d /\\/pineapple\\/modules\\/Reporting\\/files\\/reporting/d' /etc/crontabs/root");
            exec("echo -e '#DO NOT TOUCH BELOW\\n0 {$hour_string} * * * /pineapple/modules/Reporting/files/reporting\\n#DO NOT TOUCH ABOVE' >> /etc/crontabs/root");
        } else {
            exec("sed -i '/DO NOT TOUCH/d /\\/pineapple\\/modules\\/Reporting\\/files\\/reporting/d' /etc/crontabs/root");
            exec("/etc/init.d/cron stop");
        }
        exec("/etc/init.d/cron start");
    }

    private function setReportContents()
    {
        $this->systemHelper->uciSet("reporting.@settings[0].log", $this->request['config']->pineAPLog);
        $this->systemHelper->uciSet("reporting.@settings[0].clear_log", $this->request['config']->clearLog);
        $this->systemHelper->uciSet("reporting.@settings[0].survey", $this->request['config']->siteSurvey);
        $this->systemHelper->uciSet("reporting.@settings[0].duration", $this->request['config']->siteSurveyDuration);
        $this->systemHelper->uciSet("reporting.@settings[0].client", $this->request['config']->client);
        $this->systemHelper->uciSet("reporting.@settings[0].tracking", $this->request['config']->tracking);
        $this->responseHandler->setData(["success" => true]);
    }

    private function setEmailConfiguration()
    {
        $this->systemHelper->uciSet("reporting.@ssmtp[0].from", $this->request['config']->from);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].to", $this->request['config']->to);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].server", $this->request['config']->server);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].port", $this->request['config']->port);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].domain", $this->request['config']->domain);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].username", $this->request['config']->username);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].password", $this->request['config']->password);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].tls", $this->request['config']->tls);
        $this->systemHelper->uciSet("reporting.@ssmtp[0].starttls", $this->request['config']->starttls);

        file_put_contents("/etc/ssmtp/ssmtp.conf", "FromLineOverride=YES\n");
        file_put_contents("/etc/ssmtp/ssmtp.conf", "AuthUser={$this->request['config']->username}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "AuthPass={$this->request['config']->password}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "mailhub={$this->request['config']->server}:{$this->request['config']->port}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "hostname={$this->request['config']->domain}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "rewriteDomain={$this->request['config']->domain}\n", FILE_APPEND);
        if ($this->request['config']->tls) {
            file_put_contents("/etc/ssmtp/ssmtp.conf", "UseTLS=YES\n", FILE_APPEND);
        }
        if ($this->request['config']->starttls) {
            file_put_contents("/etc/ssmtp/ssmtp.conf", "UseSTARTTLS=YES\n", FILE_APPEND);
        }

        $this->responseHandler->setData(["success" => true]);
    }

    private function testReportConfiguration()
    {
        $this->systemHelper->execBackground('/pineapple/modules/Reporting/files/reporting force_email');
        $this->responseHandler->setData(["success" => true]);
    }
}
