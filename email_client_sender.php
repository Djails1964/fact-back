<?php
/**
 * email_client_sender.php - VERSION MODERNE AVEC JAVASCRIPT
 * 
 * Interface moderne utilisant EmailClientSender.js et File System Access API
 */

// ============================================
// SECTION 1: GESTION SESSION (AVANT TOUT OUTPUT)
// ============================================

// ✅ CORRECTION: Démarrer le buffer de sortie IMMÉDIATEMENT
ob_start();

// Forcer l'ID de session AVANT que bootstrap.php ne démarre une session
if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID']) && session_status() === PHP_SESSION_NONE) {
    session_id($_GET['PHPSESSID']);
    error_log("🔑 Session ID forcé depuis URL: " . $_GET['PHPSESSID']);
}

// Démarrer la session maintenant
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("📋 Session démarrée avec ID: " . session_id());
}

// ============================================
// SECTION 2: BOOTSTRAP (APRÈS SESSION)
// ============================================

require_once 'bootstrap.php';

// ✅ CORRECTION: Vérifier que la fonction existe avant de l'utiliser
if (!function_exists('is_dev_mode')) {
    function is_dev_mode() {
        return $_ENV['APP_ENV'] === 'development' || 
               $_ENV['NODE_ENV'] === 'development' || 
               (defined('APP_ENV') && APP_ENV === 'development') ||
               true; // Forcer le mode dev pour simplifier
    }
}

// En mode développement, créer un utilisateur temporaire si nécessaire
if (is_dev_mode() && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'email_dev_user';
    $_SESSION['user_name'] = 'Utilisateur Email';
    error_log("🔧 Utilisateur temporaire créé");
}

// ============================================
// SECTION 3: RÉCUPÉRATION ET VALIDATION DES DONNÉES
// ============================================

$requestId = $_GET['request_id'] ?? '';

if (empty($requestId)) {
    die('ID de requête manquant');
}

// Debug session
error_log("🔍 DEBUG MODERNE - Session ID courante: " . session_id());
error_log("🔍 DEBUG MODERNE - Request ID recherché: " . $requestId);

// Récupérer les données d'email
$emailData = null;
$attachmentInfo = [];

if (isset($_SESSION['pending_emails'][$requestId])) {
    $pendingEmail = $_SESSION['pending_emails'][$requestId];
    
    if (!is_array($pendingEmail) || !isset($pendingEmail['emailData'])) {
        error_log("❌ Structure de données invalide pour: $requestId");
        die('Données d\'email corrompues');
    }
    
    $emailData = $pendingEmail['emailData'];
    $attachmentInfo = $pendingEmail['attachmentInfo'] ?? [];
    
    error_log("✅ Données email récupérées pour: $requestId (mode moderne)");
} else {
    error_log("❌ Aucune donnée trouvée pour: $requestId");
    die('Requête d\'email non trouvée ou expirée');
}

// Détection du client (pour l'affichage) avec support des préférences
function detectEmailClient() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $userAgent = strtolower($userAgent);
    
    // ✅ PRIORITÉ THUNDERBIRD PAR DÉFAUT
    if (strpos($userAgent, 'windows') !== false) {
        return [
            'primary' => 'thunderbird',  // ✅ Changé : Thunderbird en premier
            'secondary' => 'outlook',
            'os' => 'windows',
            'confidence' => 'low',       // Confiance faible pour déclencher le sélecteur
            'reason' => 'thunderbird-priority'
        ];
    }
    if (strpos($userAgent, 'macintosh') !== false || strpos($userAgent, 'mac os') !== false) {
        return [
            'primary' => 'apple-mail',
            'secondary' => 'thunderbird', 
            'os' => 'mac',
            'confidence' => 'high'
        ];
    }
    if (strpos($userAgent, 'linux') !== false) {
        return [
            'primary' => 'thunderbird',
            'secondary' => 'evolution',
            'os' => 'linux',
            'confidence' => 'high'
        ];
    }
    
    return [
        'primary' => 'thunderbird',  // ✅ Défaut universel : Thunderbird
        'secondary' => 'unknown',
        'os' => 'unknown',
        'confidence' => 'very-low'
    ];
}

$detectedClient = detectEmailClient();

// ============================================
// SECTION 4: TRAITEMENT POST (AVANT HTML !)
// ============================================

// IMPORTANT : Traiter le POST AVANT d'afficher quoi que ce soit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_used'])) {
    if (isset($_SESSION['pending_emails'][$requestId])) {
        $_SESSION['pending_emails'][$requestId]['used'] = true;
        error_log("✅ Request ID $requestId marqué comme utilisé");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoi de facture - Mode moderne</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            max-width: 900px; 
            margin: 0 auto; 
            padding: 20px; 
            background: #f5f7fa;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); 
            color: white; 
            padding: 30px; 
            text-align: center; 
        }
        .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
        .header p { margin: 10px 0 0 0; opacity: 0.9; font-size: 16px; }
        .section { 
            margin: 0; 
            padding: 25px; 
            border-bottom: 1px solid #e9ecef;
        }
        .section:last-child { border-bottom: none; }
        .modern-badge {
            background: linear-gradient(45deg, #4CAF50, #8BC34A);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }
        .email-preview { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px;
            margin-top: 15px;
        }
        .attachments { 
            background: #e8f5e8; 
            padding: 15px; 
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        .btn { 
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white; 
            padding: 18px 35px; 
            border: none; 
            border-radius: 10px;
            cursor: pointer; 
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }
        .btn:hover { 
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .btn:hover::before {
            left: 100%;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        td:first-child { background: #f8f9fa; font-weight: 600; width: 120px; }
        
        .feature-list {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .feature-list h4 {
            color: #1976d2;
            margin-top: 0;
        }
        
        .feature-list ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .feature-list li {
            margin: 8px 0;
            line-height: 1.6;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-supported { background: #4CAF50; }
        .status-unsupported { background: #f44336; }
        .status-unknown { background: #ff9800; }
        
        .debug { 
            background: #fff3cd; 
            padding: 15px; 
            margin: 0; 
            border-left: 4px solid #ffc107;
            font-family: monospace;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header { padding: 20px; }
            .section { padding: 20px; }
            .btn { padding: 15px 25px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="modern-badge">🚀 MODE MODERNE</div>
            <h1>📧 Envoi de facture</h1>
            <p>Utilisation du File System Access API - Interface moderne</p>
        </div>

        <?php if (is_dev_mode()): ?>
        <div class="debug section">
            <h3>🔧 Mode développement - Interface moderne</h3>
            <div><strong>Session ID:</strong> <?= session_id() ?></div>
            <div><strong>Request ID:</strong> <?= htmlspecialchars($requestId) ?></div>
            <div><strong>Pièces jointes:</strong> <?= count($attachmentInfo) ?></div>
            <div><strong>Client détecté:</strong> <?= htmlspecialchars($detectedClient['primary']) ?></div>
            <div><strong>Mode:</strong> JavaScript + File System Access API</div>
        </div>
        <?php endif; ?>

        <div class="feature-list section">
            <h4>🎯 Avantages du mode moderne</h4>
            <ul>
                <li><span class="status-indicator status-supported"></span> <strong>Sélection libre</strong> de l'emplacement de sauvegarde</li>
                <li><span class="status-indicator status-supported"></span> <strong>Headers optimisés</strong> pour votre client de messagerie</li>
                <li><span class="status-indicator status-supported"></span> <strong>Instructions personnalisées</strong> selon votre configuration</li>
                <li><span class="status-indicator status-supported"></span> <strong>Gestion automatique</strong> des pièces jointes</li>
                <li><span class="status-indicator status-supported"></span> <strong>Expérience utilisateur</strong> améliorée</li>
                <li><span class="status-indicator status-supported"></span> <strong>Préférence utilisateur</strong> mémorisée</li>
            </ul>
            
            <div style="margin-top: 15px; padding: 12px; background: #e8f4fd; border-radius: 5px; border-left: 4px solid #2196f3;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>🎯 Client détecté :</strong> <?= ucfirst(str_replace('-', ' ', $detectedClient['primary'])) ?>
                        <div style="font-size: 0.9em; color: #666; margin-top: 2px;">
                            <span id="detectionSource">Détection automatique</span>
                        </div>
                    </div>
                    <button onclick="window.changeEmailClientPreference()" style="
                        background: #2196f3; color: white; border: none;
                        padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;
                    ">
                        ⚙️ Modifier
                    </button>
                </div>
            </div>
        </div>

        <div class="email-preview section">
            <h3>📧 Aperçu de l'email</h3>
            <table>
                <tr><td>À</td><td><?= htmlspecialchars($emailData['to']) ?></td></tr>
                <tr><td>DE</td><td><?= htmlspecialchars($emailData['from']) ?></td></tr>
                <tr><td>SUJET</td><td><?= htmlspecialchars($emailData['subject']) ?></td></tr>
                <tr><td>CLIENT</td><td><?= ucfirst(str_replace('-', ' ', $detectedClient['primary'])) ?> (<?= ucfirst($detectedClient['os']) ?>)</td></tr>
            </table>
            <div style="margin-top: 15px; padding: 15px; background: white; border-left: 3px solid #4CAF50; border-radius: 5px;">
                <?= nl2br(htmlspecialchars($emailData['message'])) ?>
            </div>
        </div>

        <?php if (!empty($attachmentInfo)): ?>
        <div class="attachments section">
            <h3>📎 Pièces jointes incluses</h3>
            <?php foreach ($attachmentInfo as $attachment): ?>
                <div style="display: flex; align-items: center; margin: 8px 0;">
                    <span style="margin-right: 10px;">📄</span>
                    <span style="font-weight: 500;"><?= htmlspecialchars($attachment['name']) ?></span>
                    <span style="margin-left: auto; color: #666; font-size: 0.9em;">
                        (<?= number_format($attachment['size'] / 1024, 1) ?> KB)
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="section" style="text-align: center; border-bottom: none;">
            <button id="createEmlButton" class="btn">
                🎯 Créer le fichier .eml avec File System Access
            </button>
            
            <div id="statusMessage" style="margin-top: 20px; padding: 15px; border-radius: 8px; display: none;"></div>
            
            <div id="compatibilityInfo" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; display: none;">
                <h4 style="margin: 0 0 10px 0;">🔍 Informations de compatibilité</h4>
                <div id="compatibilityDetails"></div>
            </div>
        </div>
    </div>

    <!-- ✅ CHARGEMENT DE EmailClientSender.js -->
    <script src="EmailClientSender.js"></script>

    <script>
        // ✅ DONNÉES TRANSMISES DEPUIS PHP VERS JAVASCRIPT
        window.emailData = <?= json_encode($emailData, JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        window.attachmentInfo = <?= json_encode($attachmentInfo, JSON_HEX_QUOT | JSON_HEX_APOS) ?>;
        window.requestId = <?= json_encode($requestId) ?>;
        window.detectedClient = <?= json_encode($detectedClient) ?>;

        // ✅ CONFIGURATION DE L'INTERFACE MODERNE AVEC PRÉFÉRENCES
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Interface moderne chargée avec système de préférence');
            console.log('📧 Données email:', window.emailData);
            console.log('📎 Pièces jointes:', window.attachmentInfo);
            console.log('🎯 Client détecté:', window.detectedClient);
            
            const createButton = document.getElementById('createEmlButton');
            const statusMessage = document.getElementById('statusMessage');
            const compatibilityInfo = document.getElementById('compatibilityInfo');
            const compatibilityDetails = document.getElementById('compatibilityDetails');
            
            // Vérifier la compatibilité du navigateur avec les préférences
            const compatibility = window.checkEmailClientCompatibility();
            
            // Mettre à jour l'affichage de la source de détection
            const detectionSource = document.getElementById('detectionSource');
            if (detectionSource) {
                const sourceText = {
                    'user-preference': '✅ Préférence utilisateur',
                    'auto-detection': '🔍 Détection automatique'
                };
                detectionSource.textContent = sourceText[compatibility.detectionSource] || 'Détection automatique';
                
                if (compatibility.hasUserPreference) {
                    detectionSource.style.color = '#28a745';
                    detectionSource.style.fontWeight = 'bold';
                }
            }
            
            // Afficher les informations de compatibilité
            let compatibilityHtml = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
            compatibilityHtml += `
                <div>
                    <span class="status-indicator ${compatibility.fileSystemAccess ? 'status-supported' : 'status-unsupported'}"></span>
                    <strong>File System Access API:</strong> ${compatibility.fileSystemAccess ? 'Supporté' : 'Non supporté'}
                </div>
                <div>
                    <span class="status-indicator status-supported"></span>
                    <strong>Protocole mailto:</strong> Supporté
                </div>
                <div>
                    <span class="status-indicator ${compatibility.hasUserPreference ? 'status-supported' : 'status-unknown'}"></span>
                    <strong>Client détecté:</strong> ${compatibility.detectedClient}
                </div>
                <div>
                    <span class="status-indicator status-supported"></span>
                    <strong>OS détecté:</strong> ${compatibility.detectedOS}
                </div>
                <div>
                    <span class="status-indicator ${compatibility.detectionConfidence === 'user-defined' ? 'status-supported' : 'status-unknown'}"></span>
                    <strong>Source:</strong> ${compatibility.detectionSource === 'user-preference' ? 'Préférence utilisateur' : 'Auto-détection'}
                </div>
                <div>
                    <span class="status-indicator ${compatibility.detectionConfidence === 'high' || compatibility.detectionConfidence === 'user-defined' ? 'status-supported' : 'status-unknown'}"></span>
                    <strong>Confiance:</strong> ${compatibility.detectionConfidence}
                </div>
            `;
            compatibilityHtml += '</div>';
            compatibilityHtml += `<p style="margin-top: 15px;"><strong>Recommandation:</strong> ${compatibility.recommendation}</p>`;
            
            if (compatibility.hasUserPreference) {
                compatibilityHtml += `
                    <div style="background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 10px;">
                        <strong>✅ Préférence utilisateur active :</strong> ${compatibility.userPreference}<br>
                        <small>Le fichier .eml sera optimisé spécifiquement pour ce client.</small>
                    </div>
                `;
            }
            
            compatibilityDetails.innerHTML = compatibilityHtml;
            compatibilityInfo.style.display = 'block';
            
            // Adapter le bouton selon la compatibilité
            if (!compatibility.fileSystemAccess) {
                createButton.innerHTML = '📧 Ouvrir avec mailto (pièces jointes limitées)';
                createButton.style.background = 'linear-gradient(135deg, #ff9800 0%, #f57c00 100%)';
            }
            
            // ✅ GESTIONNAIRE DU BOUTON PRINCIPAL
            createButton.addEventListener('click', async function() {
                console.log('🚀 Début création fichier .eml moderne avec préférences');
                
                // Désactiver le bouton
                this.disabled = true;
                this.innerHTML = '⏳ Création en cours...';
                
                // Masquer les messages précédents
                statusMessage.style.display = 'none';
                
                try {
                    // ✅ UTILISER EmailClientSender.js avec préférences
                    const result = await window.sendEmailViaClient(window.emailData);
                    
                    console.log('✅ Résultat:', result);
                    
                    if (result.success) {
                        showStatus('success', `✅ ${result.message}`);
                        
                        if (result.detectedClient) {
                            showStatus('info', `🎯 Optimisé pour ${result.detectedClient}`, 2000);
                        }
                        
                        // ✅ NOUVEAU: Programmer la fermeture automatique
                        // closePopupAfterSuccess(3000); // Fermer après 3 secondes
                        
                        // Marquer comme utilisé côté serveur
                        await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'mark_used=1'
                        }).catch(e => console.log('Info: marking as used failed:', e));
                        
                    } else {
                        showStatus('error', `❌ ${result.message}`);
                    }
                    
                } catch (error) {
                    console.error('❌ Erreur:', error);
                    showStatus('error', `❌ Erreur: ${error.message}`);
                } finally {
                    // Réactiver le bouton
                    this.disabled = false;
                    this.innerHTML = compatibility.fileSystemAccess ? 
                        '🎯 Créer le fichier .eml avec File System Access' : 
                        '📧 Ouvrir avec mailto (pièces jointes limitées)';
                }
            });
            
            // ✅ GESTIONNAIRE POUR LE CHANGEMENT DE CLIENT
            window.addEventListener('emailClientChanged', function(event) {
                console.log('🔄 Client de messagerie changé:', event.detail.newClient);
                
                // Mettre à jour l'affichage
                const detectionSource = document.getElementById('detectionSource');
                if (detectionSource) {
                    detectionSource.textContent = '✅ Préférence utilisateur';
                    detectionSource.style.color = '#28a745';
                    detectionSource.style.fontWeight = 'bold';
                }
                
                // Afficher une notification
                showStatus('info', `🎯 Client mis à jour vers ${event.detail.newClient.primary}`, 3000);
                
                // Recharger les informations de compatibilité
                setTimeout(() => {
                    const newCompatibility = window.checkEmailClientCompatibility();
                    console.log('🔄 Nouvelle compatibilité:', newCompatibility);
                }, 500);
            });
            
            // ✅ FONCTION D'AFFICHAGE DES STATUTS
            function showStatus(type, message, duration = 5000) {
                const colors = {
                    success: { bg: '#d4edda', color: '#155724', border: '#c3e6cb' },
                    error: { bg: '#f8d7da', color: '#721c24', border: '#f5c6cb' },
                    info: { bg: '#d1ecf1', color: '#0c5460', border: '#bee5eb' }
                };
                
                const style = colors[type] || colors.info;
                
                statusMessage.style.background = style.bg;
                statusMessage.style.color = style.color;
                statusMessage.style.border = `1px solid ${style.border}`;
                statusMessage.innerHTML = message;
                statusMessage.style.display = 'block';
                
                if (duration > 0) {
                    setTimeout(() => {
                        statusMessage.style.display = 'none';
                    }, duration);
                }
            }
            
            // ✅ VÉRIFIER SI UNE PRÉFÉRENCE EXISTE ET INFORMER L'UTILISATEUR
            if (compatibility.hasUserPreference) {
                setTimeout(() => {
                    showStatus('info', `🎯 Utilisation de votre préférence : ${compatibility.userPreference}`, 4000);
                }, 1000);
            } else if (compatibility.detectionConfidence === 'low' || compatibility.detectionConfidence === 'very-low') {
                setTimeout(() => {
                    showStatus('info', '💡 Vous pouvez définir votre client de messagerie préféré avec le bouton "Modifier"', 6000);
                }, 2000);
            } else {
                // Afficher un message d'accueil standard
                setTimeout(() => {
                    showStatus('info', '🚀 Interface moderne prête ! Cliquez pour créer votre fichier .eml.', 3000);
                }, 500);
            }
        });

        // ✅ FONCTION D'AUTO-FERMETURE DE LA POPUP
        function closePopupAfterSuccess(delay = 2000) {
            console.log('🚀 Programmation de la fermeture automatique de la popup dans', delay, 'ms');
            
            setTimeout(() => {
                try {
                    console.log('✅ Fermeture automatique de la popup');
                    
                    // Informer la fenêtre parent que la tâche est terminée
                    if (window.opener && !window.opener.closed) {
                        window.opener.postMessage({
                            type: 'emailTaskCompleted',
                            success: true,
                            timestamp: Date.now()
                        }, '*');
                    }
                    
                    // Fermer la popup
                    window.close();
                    
                } catch (error) {
                    console.error('❌ Erreur lors de la fermeture:', error);
                    // En cas d'erreur, afficher un message à l'utilisateur
                    showStatus('info', '✅ Tâche terminée ! Vous pouvez fermer cette fenêtre.', 0);
                }
            }, delay);
        }
        
        // ✅ DEBUG EN MODE DÉVELOPPEMENT AVEC PRÉFÉRENCES
        <?php if (is_dev_mode()): ?>
        window.debugModernEmail = {
            emailData: window.emailData,
            attachmentInfo: window.attachmentInfo,
            requestId: window.requestId,
            detectedClient: window.detectedClient,
            sessionId: '<?= session_id() ?>',
            compatibility: null,
            preferences: null
        };
        
        setTimeout(() => {
            window.debugModernEmail.compatibility = window.checkEmailClientCompatibility();
            
            // Informations sur les préférences
            try {
                const sender = new EmailClientSender();
                window.debugModernEmail.preferences = {
                    hasPreference: sender.preference.hasUserPreference(),
                    preference: sender.preference.getPreference(),
                    detectionMethod: sender.detectedEmailClient.source,
                    confidence: sender.detectedEmailClient.confidence
                };
            } catch (e) {
                window.debugModernEmail.preferences = { error: e.message };
            }
            
            console.log('🔧 Debug info disponible dans window.debugModernEmail');
            console.table(window.debugModernEmail.compatibility);
            console.log('🎯 Préférences:', window.debugModernEmail.preferences);
        }, 1000);
        
        // Fonction de test pour les développeurs
        window.testEmailClientPreference = function() {
            console.log('🧪 Test du système de préférence...');
            
            const sender = new EmailClientSender();
            console.log('Détection actuelle:', sender.detectedEmailClient);
            console.log('A une préférence:', sender.preference.hasUserPreference());
            console.log('Préférence:', sender.preference.getPreference());
            
            // Tester l'affichage du sélecteur
            sender.preference.showClientSelector(sender.detectedEmailClient, (selectedClient) => {
                console.log('✅ Client sélectionné dans le test:', selectedClient);
            });
        };
        
        // Fonction pour effacer la préférence (debug)
        window.clearEmailClientPreference = function() {
            const sender = new EmailClientSender();
            sender.preference.clearPreference();
            console.log('🗑️ Préférence effacée');
            window.location.reload();
        };
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// ============================================
// NETTOYAGE DE SESSION
// ============================================

// Nettoyer les anciennes requêtes
if (isset($_SESSION['pending_emails']) && is_array($_SESSION['pending_emails'])) {
    $currentTime = time();
    $cleaned = 0;
    
    foreach ($_SESSION['pending_emails'] as $id => $data) {
        if (is_array($data) && isset($data['timestamp'])) {
            $age = $currentTime - $data['timestamp'];
            if ($age > 3600) { // Plus d'1 heure
                unset($_SESSION['pending_emails'][$id]);
                $cleaned++;
            }
        }
    }
    
    if ($cleaned > 0 && is_dev_mode()) {
        error_log("🧹 Nettoyé $cleaned anciennes requêtes (mode moderne avec préférences)");
    }
}
?>