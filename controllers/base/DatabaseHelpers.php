<?php
// controllers/base/DatabaseHelpers.php

trait DatabaseHelpers {
    protected function executeQuery(string $sql, array $params = []): PDOStatement {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur base de données: ' . $e->getMessage());
        }
    }
    
    protected function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->executeQuery($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    protected function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    protected function getLastInsertId(): int {
        return (int)$this->conn->lastInsertId();
    }
}
?>