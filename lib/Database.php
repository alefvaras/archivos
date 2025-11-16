<?php
/**
 * Clase de conexión a base de datos
 * Maneja conexiones MySQL/MariaDB con PDO
 * Singleton pattern para reutilizar conexiones
 */

class Database {
    private static $instance = null;
    private $connection = null;
    private $config = [];

    /**
     * Constructor privado (Singleton)
     */
    private function __construct($config = []) {
        $defaults = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: 3306,
            'database' => getenv('DB_NAME') ?: 'boletas_electronicas',
            'username' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ];

        $this->config = array_merge($defaults, $config);
    }

    /**
     * Obtener instancia única (Singleton)
     */
    public static function getInstance($config = []) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Obtener conexión PDO
     */
    public function getConnection() {
        if ($this->connection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database'],
                    $this->config['charset']
                );

                $this->connection = new PDO(
                    $dsn,
                    $this->config['username'],
                    $this->config['password'],
                    $this->config['options']
                );
            } catch (PDOException $e) {
                error_log("Error de conexión a base de datos: " . $e->getMessage());
                throw new Exception("No se pudo conectar a la base de datos");
            }
        }

        return $this->connection;
    }

    /**
     * Ejecutar query simple
     */
    public function query($sql, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Error en query: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Obtener todos los resultados
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Obtener un solo resultado
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Insertar y retornar ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, array_values($data));
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Actualizar registros
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = ?";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transacción
     */
    public function commit() {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transacción
     */
    public function rollback() {
        return $this->getConnection()->rollBack();
    }

    /**
     * Verificar si la conexión está activa
     */
    public function isConnected() {
        return $this->connection !== null;
    }

    /**
     * Cerrar conexión
     */
    public function close() {
        $this->connection = null;
    }

    /**
     * Prevenir clonación (Singleton)
     */
    private function __clone() {}

    /**
     * Prevenir deserialización (Singleton)
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar un Singleton");
    }
}
