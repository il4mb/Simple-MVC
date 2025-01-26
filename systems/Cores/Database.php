<?php

namespace Il4mb\Simvc\Systems\Cores;

use InvalidArgumentException;
use PDO;
use PDOException;

class Database extends PDO
{
    private static $instance;
    protected string $hostname;
    protected string $port;
    protected string $username;
    protected string $password;
    protected string $database;
    protected string $charset = "utf8";

    private function __construct()
    {
        $this->hostname = $_ENV["APP_HOSTNAME"] ?? "localhost";
        $this->port = $_ENV["APP_PORT"] ?? "3306";
        $this->username = $_ENV["APP_USERNAME"] ?? "root";
        $this->password = $_ENV["APP_PASSWORD"] ?? "";
        $this->database = $_ENV["APP_DATABASE"] ?? "";
        $this->charset = $_ENV["APP_CHARSET"] ?? "utf8";



        parent::__construct(
            "mysql:dbname={$this->database};host={$this->hostname};port={$this->port};charset={$this->charset}",
            $this->username,
            $this->password
        );
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function buildConditions($conditions = [])
    {
        $conditionStack = [];
        $bindValues = [];
        $currentGroup = [];
        $isInGroup = false;
        $lastCondition = null;  // To store the last condition before adding to group

        foreach ($conditions as $key => $value) {
            // Handle OR which indicates the start of a new OR group
            if (strtoupper($value) === "OR") {
                if ($lastCondition !== null) {
                    // Store the last condition in the group when OR is encountered
                    $currentGroup[] = $lastCondition;
                }
                $isInGroup = true;
                $lastCondition = null;  // Reset the last condition after storing it
                continue;
            }

            // Handle AND which closes any ongoing OR group
            if (strtoupper($value) === "AND") {
                if (!empty($currentGroup)) {
                    // If there's a current group, close it with parentheses and push to the condition stack
                    $conditionStack[] = "(" . implode(" OR ", $currentGroup) . ")";
                    $currentGroup = [];
                }
                $isInGroup = false;
                $conditionStack[] = "AND";
                continue;
            }

            // Parse operand and key (e.g., "A.nama LIKE")
            preg_match("/\s(.*)/i", $key, $match);
            $operand = "=";
            if (isset($match[1])) {
                $operand = trim($match[1]);
                $key = trim(str_replace($match[1], "", $key));
            }
            $normalKey = strtolower(preg_replace("/[^a-z0-9_]+/i", "_", $key));
            $bindValues["where_{$normalKey}"] = $value;
            $conditionPart = "$key {$operand} :where_{$normalKey}";

            // Add condition to the correct group
            if ($isInGroup) {
                // If we're inside an OR group, add the condition to the current OR group
                $currentGroup[] = $conditionPart;
            } else {
                // If we're not in an OR group, push the current condition directly
                if ($lastCondition !== null) {
                    // If there's a previous condition, push it
                    $conditionStack[] = $lastCondition;
                }
                $lastCondition = $conditionPart;  // Store current condition as the last one
            }
        }

        // If there's any unclosed OR group, close it
        if (!empty($currentGroup)) {
            $conditionStack[] = "(" . implode(" OR ", $currentGroup) . ")";
        }
        if (!empty($lastCondition)) {
            $conditionStack[] = $lastCondition;
        }

        // Final condition string
        $conditionString = implode(" AND ", $conditionStack);

        return [
            $conditionString,
            $bindValues
        ];
    }


    private function buildJoin($join = [])
    {
        $joinClause = [];
        foreach ($join as $table => $colmuns) {
            $colmunsClause = "";
            foreach ($colmuns as $from => $to) {
                if (!empty($colmunsClause)) {
                    $colmunsClause .= " AND ";
                }
                $colmunsClause .= "$from = $to";
            }
            $joinClause[] = "LEFT JOIN $table ON {$colmunsClause} ";
        }

        return " " . implode(" ", $joinClause);
    }

    public function select(
        string $table,
        array $columns = ['*'],
        array $conditions = [],
        array $group = [],
        $order = null,
        $limit = null,
        $offset = null,
        bool $count = false,
        array $join = []
    ): array {
        $columnsList = implode(",", $columns);
        [$whereClause, $conditions] = $this->buildConditions($conditions);
        if (!empty($whereClause)) {
            $whereClause = " WHERE $whereClause";
        }
        $joinClause = "";
        if (!empty($join)) $joinClause = $this->buildJoin($join);

        // Add ORDER BY if specified
        $orderClause = $order ? " ORDER BY $order" : "";

        // Add LIMIT and OFFSET if specified
        $limitClause = "";
        if ($limit !== null) {
            $limitClause = " LIMIT :limit";
            if ($offset !== null) {
                $limitClause .= " OFFSET :offset";
            }
        }
        $groupClause = "";
        if (!empty($group)) {
            $groupClause = " GROUP BY " . implode(", ", $group);
        }
        // Build the query
        $query = "SELECT {$columnsList} FROM {$table}{$joinClause}{$whereClause}{$groupClause}{$orderClause}{$limitClause}";

        $stmt = $this->prepare($query);
        // Bind values for conditions
        foreach ($conditions as $key => $val)
            $stmt->bindValue($key, $val, PDO::PARAM_STR);

        // Bind values for LIMIT and OFFSET
        if ($limit !== null)
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        if ($offset !== null)
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $result = [];
        if ($count) {
            // Count query
            $countQuery = "SELECT COUNT(*) AS total FROM {$table}{$joinClause}{$whereClause}{$orderClause}";
            $countStmt = $this->prepare($countQuery);

            foreach ($conditions as $key => $val) {
                $countStmt->bindValue($key, $val, PDO::PARAM_STR);
            }

            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            $result['size'] = $total;
        }

        $stmt->execute();
        $result["rows"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    public function insert(string $table, array $params = []): ?int
    {
        if (empty($params)) {
            throw new InvalidArgumentException("Parameters cannot be empty.");
        }

        $columns = array_keys($params);
        $placeholders = ":" . implode(", :", $columns);
        $SQL = "INSERT INTO {$table} (" . implode(", ", $columns) . ") VALUES ({$placeholders})";


        $stmt = $this->prepare($SQL);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $this->lastInsertId();
    }


    public function update(string $table, array $params = [], array $conditions = []): bool
    {

        $setClause = implode(", ", array_map(fn($key) => "{$key} = :{$key}", array_keys($params)));

        [$whereClause, $conditions] = $this->buildConditions($conditions);
        if (!empty($whereClause)) $whereClause = " WHERE $whereClause";

        $query = "UPDATE {$table} SET {$setClause}{$whereClause}";
        try {
            $stmt = $this->prepare($query);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            foreach ($conditions as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }


    public function delete(string $table, array $conditions = []): bool
    {

        [$whereClause, $conditions] = $this->buildConditions($conditions);
        if (!empty($whereClause)) $whereClause = " WHERE $whereClause";

        $query = "DELETE FROM {$table}{$whereClause}";
        try {
            $stmt = $this->prepare($query);

            foreach ($conditions as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }


    public function execute(string $query, array $params = []): bool
    {
        try {
            $stmt = $this->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }

    public function getLastInsertId(): string
    {
        return $this->lastInsertId();
    }

    // Transaction methods
    public function beginTransaction(): bool
    {
        try {
            return parent::beginTransaction();
        } catch (PDOException $e) {
            die("Error starting transaction: " . $e->getMessage());
        }
    }

    public function commitTransaction(): bool
    {
        try {
            return parent::commit();
        } catch (PDOException $e) {
            die("Error committing transaction: " . $e->getMessage());
        }
    }

    public function rollbackTransaction(): bool
    {
        try {
            return parent::rollBack();
        } catch (PDOException $e) {
            die("Error rolling back transaction: " . $e->getMessage());
        }
    }
}
