<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Notes extends Controller
{

    public $endpointRoutes = ['setName', 'setNote', 'getNotes', 'getNote', 'deleteNote', 'downloadNotes', 'getKeys'];
    public $dbConnection;
    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        $this->dbConnection = new \frieren\orm\SQLite(self::DATABASE);
        $this->dbConnection->execLegacy("CREATE TABLE IF NOT EXISTS notes (type INT, key TEXT UNIQUE NOT NULL, name TEXT, note TEXT);");

        parent::__construct($request);
    }

    public function setName()
    {
        $status = $this->dbConnection->execLegacy(
            "INSERT OR REPLACE INTO notes (type, key, name) VALUES('%d', '%s', '%s');",
            $this->request['type'], $this->request['key'], $this->request['name']
        );
        $this->responseHandler->setData($status);
    }

    public function setNote()
    {
        if (empty($this->request['name']) && empty($this->request['note'])) {
            $status = $this->deleteNote($this->request['key']);
        } else {
            $status = $this->dbConnection->execLegacy(
                "INSERT OR REPLACE INTO notes (type, key, name, note) VALUES ('%d', '%s', '%s', '%s');",
                $this->request['type'], $this->request['key'], $this->request['name'], $this->request['note']
            );
        }

        $this->responseHandler->setData($status);
    }

    public function getNotes()
    {
        $macs = $this->dbConnection->queryLegacy("SELECT type, key, name, note FROM notes WHERE type=0;");
        $ssids = $this->dbConnection->queryLegacy("SELECT type, key, name, note FROM notes WHERE type=1;");
        $this->responseHandler->setData(array("macs" => $macs, "ssids" => $ssids));
    }

    public function getNote()
    {
        $data = $this->dbConnection->queryLegacy("SELECT type, key, name, note FROM notes WHERE key='%s';", $this->request['key']);
        $this->responseHandler->setData(array("note" => $data));
    }

    public function deleteNote()
    {
        if (!isset($this->request['key'])) {
            $this->responseHandler->setData(array("success" => false));
        }

        $this->dbConnection->execLegacy("DELETE FROM notes WHERE key='%s';", $this->request['key']);
        $this->responseHandler->setData(array("success" => true));
    }

    public function downloadNotes()
    {
        $noteData = $this->dbConnection->queryLegacy('SELECT * FROM notes;');
        foreach ($noteData as $idx => $note) {
            if ($note['type'] == 0) {
                $note['type'] = 'MAC';
            } else if ($note['type'] == 1) {
                $note['type'] = 'SSID';
            }
            $noteData[$idx] = $note;
        }
        $fileName = '/tmp/notes.json';
        file_put_contents($fileName, json_encode($noteData, JSON_PRETTY_PRINT));
        $this->responseHandler->setData(array("download" => $this->systemHelper->generateDownloadFile($fileName)));
    }

    public function getKeys()
    {
        $keys = array();
        $res = $this->dbConnection->queryLegacy("SELECT key FROM notes;");
        foreach ($res as $idx => $key) {
            $keys[] = $key['key'];
        }
        $this->responseHandler->setData(array("keys" => $keys));
    }
}
