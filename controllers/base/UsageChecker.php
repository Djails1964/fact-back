<?php

trait UsageChecker {
    protected function checkUsageInTables(int $id, string $column, array $tables): array {
        $conditions = [];
        $params = [];
        
        foreach ($tables as $table) {
            $conditions[] = "SELECT 1 FROM {$table} WHERE {$column} = ?";
            $params[] = $id;
        }
        
        $sql = implode(" UNION ", $conditions) . " LIMIT 1";
        $stmt = $this->executeQuery($sql, $params);
        
        $isUsed = (bool)$stmt->fetch();
        
        return [
            'success' => true,
            'isUsed' => $isUsed,
            'message' => $isUsed 
                ? 'Cet élément est utilisé et ne peut pas être supprimé.' 
                : 'Cet élément peut être supprimé en toute sécurité.'
        ];
    }
}
?>
