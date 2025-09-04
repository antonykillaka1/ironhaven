<?php
// src/Core/Database.php
namespace Ironhaven\Core;

class Database {
    private static $instance = null;
    private $connection;
    private $debug = true; // Abilita debug per default
    
    private function __construct() {
        try {
            $this->connection = new \PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (\PDOException $e) {
            die('Errore di connessione al database: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function setDebug($enabled) {
        $this->debug = $enabled;
    }
    
    public function query($sql, $params = []) {
        // Debug per trovare query con parentesi quadre
        if (preg_match('/\[.*\]/', $sql)) {
            error_log("CRITICAL ERROR: Invalid SQL query with square brackets detected!");
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            
            // Stack trace per trovare da dove viene la chiamata
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);
            error_log("Call stack for SQL error:");
            foreach ($trace as $i => $call) {
                if (isset($call['file']) && isset($call['line'])) {
                    error_log("  #{$i} {$call['file']}:{$call['line']} in " . 
                             ($call['function'] ?? 'unknown') . "()");
                }
            }
            
            // Lancia eccezione per fermare l'esecuzione
            throw new \Exception("Invalid SQL syntax: Square brackets detected in query");
        }
        
        // Log debug opzionale per tutte le query
        if ($this->debug) {
            error_log("Database Query: " . $sql);
            error_log("Query Params: " . json_encode($params));
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            error_log("PDO ERROR in query execution:");
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            error_log("Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "$column = ?";
        }
        $setString = implode(', ', $set);
        
        $sql = "UPDATE $table SET $setString WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Esegue una query generica (INSERT/UPDATE/DELETE/DDL).
     * Ritorna true/false a seconda dell’esito.
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params); // riusa logging/validazioni di query()
            return $stmt !== false;
        } catch (\PDOException $e) {
            // query() ha già loggato, rilancio per coerenza con il resto del wrapper
            throw $e;
        }
    }

    /**
     * Helper per cancellazioni: ritorna il numero di righe cancellate.
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }
    
    // Metodo specifico per debug popolazione
    public function fetchPopulation($settlementId) {
        $sql = "SELECT * FROM population WHERE settlement_id = ?";
        
        if ($this->debug) {
            error_log("=== fetchPopulation DEBUG ===");
            error_log("Settlement ID: " . $settlementId);
            error_log("SQL: " . $sql);
        }
        
        try {
            $result = $this->fetch($sql, [$settlementId]);
            
            if ($this->debug) {
                error_log("Population result: " . json_encode($result));
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("fetchPopulation ERROR: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Metodo per debug SQL
    public function debugQuery($message = '', $level = 1) {
        error_log("\n=== Database Debug Query ===");
        if ($message) {
            error_log("Message: " . $message);
        }
        
        $trace = debug_backtrace();
        error_log("Called from:");
        for ($i = 1; $i <= $level && isset($trace[$i]); $i++) {
            $call = $trace[$i];
            if (isset($call['file']) && isset($call['line'])) {
                error_log("  #{$i} {$call['file']}:{$call['line']} in " . 
                         ($call['function'] ?? 'unknown') . "()");
            }
        }
        error_log("=========================\n");
    }
    
    // Metodo per ottenere l'ultimo errore
    public function getLastError() {
        return $this->connection->errorInfo();
    }
    
    // Verifica integrita database per popolazione
    public function verifyPopulationIntegrity($settlementId) {
        error_log("=== Verify Population Integrity ===");
        error_log("Settlement ID: " . $settlementId);
        
        // 1. Verifica se esiste il record popolazione
        $population = $this->fetchPopulation($settlementId);
        if (!$population) {
            error_log("ERROR: No population record found for settlement " . $settlementId);
            return [
                'valid' => false,
                'error' => 'No population record',
                'population' => null
            ];
        }
        
        // 2. Conta le case completate
        $houses = $this->fetchAll(
            "SELECT * FROM buildings 
             WHERE settlement_id = ? AND type = 'house' 
             AND (construction_ends IS NULL OR construction_ends <= NOW())",
            [$settlementId]
        );
        
        // 3. Calcola popolazione attesa
        $expectedTotal = 5; // Base
        $houseDetails = [];
        
        foreach ($houses as $house) {
            $houseClass = "\\Ironhaven\\Buildings\\House";
            if (class_exists($houseClass)) {
                $houseInstance = new $houseClass($house);
                $addedPop = $houseInstance->getPopulation($house['level']);
                $expectedTotal += $addedPop;
                $houseDetails[] = [
                    'id' => $house['id'],
                    'level' => $house['level'],
                    'population' => $addedPop
                ];
            }
        }
        
        $isValid = $population['total'] == $expectedTotal;
        
        error_log("Current population: " . $population['total']);
        error_log("Expected population: " . $expectedTotal);
        error_log("Valid: " . ($isValid ? 'YES' : 'NO'));
        error_log("Houses: " . json_encode($houseDetails));
        
        return [
            'valid' => $isValid,
            'current' => $population['total'],
            'expected' => $expectedTotal,
            'houses' => $houses,
            'population' => $population
        ];
    }
}
