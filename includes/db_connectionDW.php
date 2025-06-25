<?php

header('Content-Type: text/html; charset=UTF-8');

class OKRDatabase {
    private $serverName;
    private $database;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->loadEnv();
        $this->serverName = getenv('DB_SERVER');
        $this->database   = getenv('DB_DATABASE_ORK');
        $this->username   = getenv('DB_USERNAME');
        $this->password   = getenv('DB_PASSWORD');
        
        $this->connect();
    }

    private function loadEnv() {
        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) {
            die('Arquivo .env não encontrado.');
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;  // Ignorar comentários
            }
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }

    private function connect() {
        $connectionOptions = [
            "Database" => $this->database,
            "Uid" => $this->username,
            "PWD" => $this->password,
            "TrustServerCertificate" => true,
            "CharacterSet" => "UTF-8"
        ];

        $this->conn = sqlsrv_connect($this->serverName, $connectionOptions);

        if (!$this->conn) {
            die("Falha na conexão: " . print_r(sqlsrv_errors(), true));
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
