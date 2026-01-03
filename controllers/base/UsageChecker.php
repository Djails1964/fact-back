<?php

trait UsageChecker {
    protected function checkUsageInTables(int $id, string $column, array $tables): array {
        $conditions = [];
        $params = [];

        // 1. Définir un préfixe pour identifier facilement le log
        $prefixe = "CHECK_USAGE_PARAMS: ";
        
        // 2. Encoder le tableau $tables en JSON pour une lecture facile
        $tables_json = json_encode($tables, JSON_UNESCAPED_UNICODE);
        
        // 3. Concaténer tous les éléments en une seule chaîne lisible
        $log_message = $prefixe . 
                    "id=" . $id . 
                    " | column='" . $column . 
                    "' | tables=" . $tables_json;

        // 4. Écrire le message dans le log
        error_log($log_message);
        
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
