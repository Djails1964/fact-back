<?php
// test_migration.php - Script de validation

require_once 'api/database.php';
require_once 'services/ServiceTarif.php';

function testMigration($conn) {
    echo "🧪 Test de migration - Services\n";
    echo "================================\n\n";
    
    $serviceTarif = new ServiceTarif($conn);
    
    // Test 1 : Récupération des services
    echo "Test 1 : Récupération des services...\n";
    $result = $serviceTarif->getServices();
    
    if ($result['success']) {
        echo "✅ SUCCESS - " . count($result['services']) . " services récupérés\n";
        
        // Afficher le premier service pour vérifier la structure
        if (!empty($result['services'])) {
            $firstService = $result['services'][0];
            echo "   Structure du premier service :\n";
            foreach ($firstService as $key => $value) {
                echo "   - $key: $value\n";
            }
        }
    } else {
        echo "❌ ERREUR - " . $result['message'] . "\n";
        return false;
    }
    echo "\n";
    
    // Test 2 : Récupération d'un service par ID
    if (!empty($result['services'])) {
        $testServiceId = $result['services'][0]['id_service'];
        echo "Test 2 : Récupération du service ID $testServiceId...\n";
        
        $serviceResult = $serviceTarif->getServiceById($testServiceId);
        
        if ($serviceResult['success']) {
            echo "✅ SUCCESS - Service récupéré : " . $serviceResult['service']['nom'] . "\n";
            
            // Vérifier que l'alias id_service est bien présent
            if (isset($serviceResult['service']['id_service'])) {
                echo "✅ ALIAS - id_service présent : " . $serviceResult['service']['id_service'] . "\n";
            } else {
                echo "❌ ALIAS - id_service manquant !\n";
                return false;
            }
        } else {
            echo "❌ ERREUR - " . $serviceResult['message'] . "\n";
            return false;
        }
    }
    echo "\n";
    
    // Test 3 : Vérification d'usage
    if (!empty($result['services'])) {
        $testServiceId = $result['services'][0]['id_service'];
        echo "Test 3 : Vérification d'usage du service ID $testServiceId...\n";
        
        $usageResult = $serviceTarif->checkServiceUsage($testServiceId);
        
        if ($usageResult['success']) {
            echo "✅ SUCCESS - Vérification d'usage : " . ($usageResult['isUsed'] ? 'Utilisé' : 'Non utilisé') . "\n";
            echo "   Message : " . $usageResult['message'] . "\n";
        } else {
            echo "❌ ERREUR - " . $usageResult['message'] . "\n";
            return false;
        }
    }
    echo "\n";
    
    // Test 4 : Comparaison avec l'ancien système
    echo "Test 4 : Comparaison avec l'ancien système...\n";
    
    try {
        // Ancien système
        $oldServices = TarifControleur::getServices($conn);
        
        // Nouveau système
        $newResult = $serviceTarif->getServices();
        $newServices = $newResult['services'];
        
        if (count($oldServices) === count($newServices)) {
            echo "✅ SUCCESS - Même nombre de services (" . count($oldServices) . ")\n";
            
            // Vérifier que les alias sont bien ajoutés
            $hasIdService = isset($newServices[0]['id_service']);
            $hasOldId = isset($oldServices[0]['id']);
            
            if ($hasIdService && $hasOldId) {
                echo "✅ ALIAS - Transformation id → id_service réussie\n";
            } else {
                echo "❌ ALIAS - Problème de transformation des alias\n";
                return false;
            }
        } else {
            echo "❌ ERREUR - Nombre de services différent (ancien: " . count($oldServices) . ", nouveau: " . count($newServices) . ")\n";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ ERREUR - Comparaison impossible : " . $e->getMessage() . "\n";
        return false;
    }
    echo "\n";
    
    echo "🎉 TOUS LES TESTS SONT PASSÉS !\n";
    echo "La migration des services est fonctionnelle.\n\n";
    
    return true;
}

function testCreationService($conn) {
    echo "🧪 Test de création de service\n";
    echo "==============================\n\n";
    
    $serviceTarif = new ServiceTarif($conn);
    
    $testData = [
        'code' => 'TEST_MIGRATION',
        'nom' => 'Service Test Migration',
        'description' => 'Service créé pour tester la migration',
        'actif' => true,
        'isDefault' => false
    ];
    
    echo "Création d'un service de test...\n";
    $result = $serviceTarif->createService($testData);
    
    if ($result['success']) {
        echo "✅ SUCCESS - Service créé avec ID : " . $result['id'] . "\n";
        
        // Vérifier qu'on peut le récupérer
        $retrievedService = $serviceTarif->getServiceById($result['id']);
        
        if ($retrievedService['success'] && $retrievedService['service']['nom'] === $testData['nom']) {
            echo "✅ SUCCESS - Service récupéré correctement\n";
            
            // Nettoyer : supprimer le service de test
            $deleteResult = $serviceTarif->deleteService($result['id']);
            if ($deleteResult['success']) {
                echo "✅ CLEANUP - Service de test supprimé\n";
            }
            
            return true;
        } else {
            echo "❌ ERREUR - Impossible de récupérer le service créé\n";
            return false;
        }
    } else {
        echo "❌ ERREUR - " . $result['message'] . "\n";
        return false;
    }
}

// Exécution des tests
try {
    echo "🚀 DÉBUT DES TESTS DE MIGRATION\n";
    echo "===============================\n\n";
    
    $success1 = testMigration($conn);
    $success2 = testCreationService($conn);
    
    if ($success1 && $success2) {
        echo "🎊 MIGRATION RÉUSSIE !\n";
        echo "Vous pouvez maintenant migrer les autres entités (Unités, Tarifs, etc.)\n";
    } else {
        echo "💥 PROBLÈME DÉTECTÉ !\n";
        echo "Vérifiez les erreurs ci-dessus avant de continuer.\n";
    }
    
} catch (Exception $e) {
    echo "💥 ERREUR CRITIQUE : " . $e->getMessage() . "\n";
}
?>