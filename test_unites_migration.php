<?php
// test_unites_migration.php - Script de validation pour les unités

require_once 'api/database.php';
require_once 'services/ServiceTarif.php';

function testUnitesMigration($conn) {
    echo "🧪 Test de migration - Unités\n";
    echo "==============================\n\n";
    
    $serviceTarif = new ServiceTarif($conn);
    
    // Test 1 : Récupération de toutes les unités
    echo "Test 1 : Récupération de toutes les unités...\n";
    $result = $serviceTarif->getUnites();
    
    if ($result['success']) {
        echo "✅ SUCCESS - " . count($result['unites']) . " unités récupérées\n";
        
        // Afficher la première unité pour vérifier la structure
        if (!empty($result['unites'])) {
            $firstUnite = $result['unites'][0];
            echo "   Structure de la première unité :\n";
            foreach ($firstUnite as $key => $value) {
                echo "   - $key: $value\n";
            }
            
            // Vérifier que l'alias id_unite est bien présent
            if (isset($firstUnite['id_unite'])) {
                echo "✅ ALIAS - id_unite présent : " . $firstUnite['id_unite'] . "\n";
            } else {
                echo "❌ ALIAS - id_unite manquant !\n";
                return false;
            }
        }
    } else {
        echo "❌ ERREUR - " . $result['message'] . "\n";
        return false;
    }
    echo "\n";
    
    // Test 2 : Récupération des relations services-unités
    echo "Test 2 : Récupération des relations services-unités...\n";
    $relationsResult = $serviceTarif->getServicesUnites();
    
    if ($relationsResult['success']) {
        echo "✅ SUCCESS - " . count($relationsResult['servicesUnites']) . " relations récupérées\n";
        
        // Vérifier la structure des relations
        if (!empty($relationsResult['servicesUnites'])) {
            $firstRelation = $relationsResult['servicesUnites'][0];
            echo "   Structure de la première relation :\n";
            foreach ($firstRelation as $key => $value) {
                echo "   - $key: $value\n";
            }
            
            // Vérifier les alias
            if (isset($firstRelation['id_service']) && isset($firstRelation['id_unite'])) {
                echo "✅ ALIAS - id_service et id_unite présents\n";
            } else {
                echo "❌ ALIAS - Alias manquants dans les relations !\n";
                return false;
            }
        }
    } else {
        echo "❌ ERREUR - " . $relationsResult['message'] . "\n";
        return false;
    }
    echo "\n";
    
    // Test 3 : Récupération des unités pour un service spécifique
    $servicesResult = $serviceTarif->getServices();
    if ($servicesResult['success'] && !empty($servicesResult['services'])) {
        $testServiceId = $servicesResult['services'][0]['id_service'];
        echo "Test 3 : Récupération des unités pour le service ID $testServiceId...\n";
        
        $unitesServiceResult = $serviceTarif->getUnites($testServiceId);
        
        if ($unitesServiceResult['success']) {
            echo "✅ SUCCESS - " . count($unitesServiceResult['unites']) . " unités pour ce service\n";
            
            // Vérifier que les unités ont bien l'alias id_unite
            if (!empty($unitesServiceResult['unites'])) {
                $firstUniteService = $unitesServiceResult['unites'][0];
                if (isset($firstUniteService['id_unite'])) {
                    echo "✅ ALIAS - id_unite présent dans les unités par service\n";
                } else {
                    echo "❌ ALIAS - id_unite manquant dans les unités par service !\n";
                    return false;
                }
            }
        } else {
            echo "❌ ERREUR - " . $unitesServiceResult['message'] . "\n";
            return false;
        }
    }
    echo "\n";
    
    // Test 4 : Test de l'unité par défaut
    if ($servicesResult['success'] && !empty($servicesResult['services'])) {
        $testServiceId = $servicesResult['services'][0]['id_service'];
        echo "Test 4 : Récupération de l'unité par défaut pour le service ID $testServiceId...\n";
        
        $defaultUniteResult = $serviceTarif->getUniteDefault($testServiceId);
        
        if ($defaultUniteResult['success']) {
            echo "✅ SUCCESS - Unité par défaut : " . ($defaultUniteResult['id_unite'] ?? 'aucune') . "\n";
            
            // Vérifier que la clé est bien id_unite et non unite_id
            if (array_key_exists('id_unite', $defaultUniteResult)) {
                echo "✅ ALIAS - Clé id_unite présente dans le résultat\n";
            } else {
                echo "❌ ALIAS - Clé id_unite manquante !\n";
                return false;
            }
        } else {
            echo "❌ ERREUR - " . $defaultUniteResult['message'] . "\n";
            return false;
        }
    }
    echo "\n";
    
    // Test 5 : Comparaison avec l'ancien système
    echo "Test 5 : Comparaison avec l'ancien système...\n";
    
    try {
        // Ancien système
        $oldUnites = TarifControleur::getUnites($conn);
        
        // Nouveau système
        $newResult = $serviceTarif->getUnites();
        $newUnites = $newResult['unites'];
        
        if (count($oldUnites) === count($newUnites)) {
            echo "✅ SUCCESS - Même nombre d'unités (" . count($oldUnites) . ")\n";
            
            // Vérifier que les alias sont bien ajoutés
            $hasIdUnite = isset($newUnites[0]['id_unite']);
            $hasOldId = isset($oldUnites[0]['id']);
            
            if ($hasIdUnite && $hasOldId) {
                echo "✅ ALIAS - Transformation id → id_unite réussie\n";
            } else {
                echo "❌ ALIAS - Problème de transformation des alias\n";
                return false;
            }
        } else {
            echo "❌ ERREUR - Nombre d'unités différent (ancien: " . count($oldUnites) . ", nouveau: " . count($newUnites) . ")\n";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ ERREUR - Comparaison impossible : " . $e->getMessage() . "\n";
        return false;
    }
    echo "\n";
    
    echo "🎉 TOUS LES TESTS SONT PASSÉS !\n";
    echo "La migration des unités est fonctionnelle.\n\n";
    
    return true;
}

function testCreationUnite($conn) {
    echo "🧪 Test de création d'unité\n";
    echo "===========================\n\n";
    
    $serviceTarif = new ServiceTarif($conn);
    
    $testData = [
        'code' => 'TEST_MIGRATION_UNITE',
        'nom' => 'Unité Test Migration',
        'description' => 'Unité créée pour tester la migration'
    ];
    
    echo "Création d'une unité de test...\n";
    $result = $serviceTarif->createUnite($testData);
    
    if ($result['success']) {
        echo "✅ SUCCESS - Unité créée avec ID : " . $result['id'] . "\n";
        
        // Vérifier qu'on peut la récupérer via getUnites (toutes)
        $allUnites = $serviceTarif->getUnites();
        
        $foundUnite = null;
        foreach ($allUnites['unites'] as $unite) {
            if ($unite['id_unite'] == $result['id']) {
                $foundUnite = $unite;
                break;
            }
        }
        
        if ($foundUnite && $foundUnite['nom'] === $testData['nom']) {
            echo "✅ SUCCESS - Unité récupérée correctement dans la liste complète\n";
            
            // Nettoyer : supprimer l'unité de test
            $deleteResult = $serviceTarif->deleteUnite($result['id']);
            if ($deleteResult['success']) {
                echo "✅ CLEANUP - Unité de test supprimée\n";
            }
            
            return true;
        } else {
            echo "❌ ERREUR - Impossible de récupérer l'unité créée\n";
            return false;
        }
    } else {
        echo "❌ ERREUR - " . $result['message'] . "\n";
        return false;
    }
}

function testLiaisonServiceUnite($conn) {
    echo "🧪 Test de liaison service-unité\n";
    echo "================================\n\n";
    
    $serviceTarif = new ServiceTarif($conn);
    
    // Récupérer un service et une unité existants
    $servicesResult = $serviceTarif->getServices();
    $unitesResult = $serviceTarif->getUnites();
    
    if (!$servicesResult['success'] || !$unitesResult['success'] || 
        empty($servicesResult['services']) || empty($unitesResult['unites'])) {
        echo "❌ ERREUR - Impossible de récupérer des services ou unités pour le test\n";
        return false;
    }
    
    $testServiceId = $servicesResult['services'][0]['id_service'];
    $testUniteId = $unitesResult['unites'][0]['id_unite'];
    
    echo "Test avec service ID $testServiceId et unité ID $testUniteId...\n";
    
    // Test de vérification d'usage
    $usageResult = $serviceTarif->checkServiceUniteUsageInFacture($testServiceId, $testUniteId);
    
    if ($usageResult['success']) {
        echo "✅ SUCCESS - Vérification d'usage dans factures : " . ($usageResult['isUsed'] ? 'Utilisé' : 'Non utilisé') . "\n";
        if ($usageResult['isUsed']) {
            echo "   Nombre d'utilisations : " . $usageResult['count'] . "\n";
        }
    } else {
        echo "❌ ERREUR - " . $usageResult['message'] . "\n";
        return false;
    }
    
    return true;
}

// Exécution des tests
try {
    echo "🚀 DÉBUT DES TESTS DE MIGRATION - UNITÉS\n";
    echo "=========================================\n\n";
    
    $success1 = testUnitesMigration($conn);
    $success2 = testCreationUnite($conn);
    $success3 = testLiaisonServiceUnite($conn);
    
    if ($success1 && $success2 && $success3) {
        echo "🎊 MIGRATION DES UNITÉS RÉUSSIE !\n";
        echo "Vous pouvez maintenant migrer les tarifs.\n";
    } else {
        echo "💥 PROBLÈME DÉTECTÉ !\n";
        echo "Vérifiez les erreurs ci-dessus avant de continuer.\n";
    }
    
} catch (Exception $e) {
    echo "💥 ERREUR CRITIQUE : " . $e->getMessage() . "\n";
}
?>