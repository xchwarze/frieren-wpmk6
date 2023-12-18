<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Notes extends Controller
{

    protected $endpointRoutes = ['setName', 'setNote', 'getNotes', 'getNote', 'deleteNote', 'downloadNotes', 'getKeys'];
    private $dbConnection;
    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = new \frieren\orm\SQLite(self::DATABASE);
        if (!empty($this->dbConnection->error)) {
            $this->responseHandler->setError($this->dbConnection->strError());
            return;
        }
        $this->dbConnection->execLegacy("CREATE TABLE IF NOT EXISTS notes (type INT, key TEXT UNIQUE NOT NULL, name TEXT, note TEXT);");
        if (!empty($this->dbConnection->error)) {
            $this->responseHandler->setError($this->dbConnection->strError());
        }
    }

    protected function setName($type, $key, $name)
    {
        return $this->dbConnection->execLegacy("INSERT OR REPLACE INTO notes (type, key, name) VALUES('%d', '%s', '%s');", $type, $key, $name);
    }

    protected function setNote($type, $key, $name, $note)
    {
        if (empty($name) && empty($note)) {
            return $this->deleteNote($key);
        } else {
            return $this->dbConnection->execLegacy("INSERT OR REPLACE INTO notes (type, key, name, note) VALUES ('%d', '%s', '%s', '%s');", $type, $key, $name, $note);
        }
    }

    protected function getNotes()
    {
        $macs = $this->dbConnection->queryLegacy("SELECT type, key, name, note FROM notes WHERE type=0;");
        $ssids = $this->dbConnection->queryLegacy("SELECT type, key, name, note FROM notes WHERE type=1;");
        return array("macs" => $macs, "ssids" => $ssids);
    }

    protected function getNote($key)
    {
        return array("note" => $this->dbConnection->queryLegacy("SELECT type, key, name, note FROM notes WHERE key='%s';", $key));
    }

    protected function deleteNote($key)
    {
        if (!isset($key)) {
            return array("success" => false);
        }
        $this->dbConnection->execLegacy("DELETE FROM notes WHERE key='%s';", $key);
        return array("success" => true);
    }

    protected function downloadNotes()
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
        return array("download" => $this->systemHelper->downloadFile($fileName));
    }

    protected function getKeys()
    {
        $keys = array();
        $res = $this->dbConnection->queryLegacy("SELECT key FROM notes;");
        foreach ($res as $idx => $key) {
            $keys[] = $key['key'];
        }
        return array("keys" => $keys);
    }
}
