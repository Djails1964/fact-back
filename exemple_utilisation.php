<?php
/**
 * exemple_utilisation.php - VERSION CORRIGÉE
 * 
 * Correction du problème de session pour l'envoi d'emails
 */

// IMPORTANT: Inclure le bootstrap EN PREMIER pour configurer les sessions
require_once 'bootstrap.php';

// S'assurer que la session est démarrée
ensure_session_started();

// Inclure les dépendances après le bootstrap
require_once 'EmailService.php';

// Debug pour voir l'état de la session
if (is_dev_mode()) {
    error_log("=== DÉBUT exemple_utilisation.php ===");
    error_log("Session ID: " . session_id());
    error_log("Session status: " . session_status());
    error_log("Pending emails en session: " . count($_SESSION['pending_emails'] ?? []));
}

// Initialiser le service d'email
$emailService = new EmailService();

// Exemple 1: Envoi simple via le client de messagerie
if (isset($_POST['send_simple'])) {
    try {
        $to = $_POST['to'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        
        // Validation des données
        if (empty($to) || empty($subject) || empty($message)) {
            throw new Exception('Tous les champs sont requis');
        }
        
        // Forcer l'utilisation du client de messagerie
        $options = [
            'sendMode' => 'client',
            'from' => 'noreply@lagrange.ch',
            'replyTo' => 'contact@lagrange.ch'
        ];
        
        // Debug avant envoi
        if (is_dev_mode()) {
            error_log("Tentative d'envoi simple - Session ID: " . session_id());
        }
        
        $result = $emailService->envoyerReellement($to, $subject, $message, $options);
        
        if ($result['success']) {
            // Debug après succès
            if (is_dev_mode()) {
                error_log("Envoi réussi - Request ID: " . $result['requestId']);
                error_log("Pending emails après envoi: " . count($_SESSION['pending_emails'] ?? []));
            }
            
            // Rediriger vers la page d'envoi client
            $requestId = $result['requestId'];
            header("Location: email_client_sender.php?request_id=$requestId");
            exit;
        } else {
            $error = "Erreur: " . $result['message'];
        }
        
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
        if (is_dev_mode()) {
            error_log("Erreur dans send_simple: " . $e->getMessage());
        }
    }
}

// Exemple 2: Envoi avec pièces jointes
if (isset($_POST['send_with_attachments'])) {
    try {
        $to = $_POST['to'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        
        // Validation des données
        if (empty($to) || empty($subject) || empty($message)) {
            throw new Exception('Tous les champs sont requis');
        }
        
        // Préparer les pièces jointes (exemple avec des fichiers uploadés)
        $attachments = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $index => $filename) {
                if ($_FILES['attachments']['error'][$index] === UPLOAD_ERR_OK) {
                    $tmpPath = $_FILES['attachments']['tmp_name'][$index];
                    $uploadPath = 'uploads/' . uniqid() . '_' . $filename;
                    
                    // Créer le dossier uploads s'il n'existe pas
                    if (!is_dir('uploads')) {
                        mkdir('uploads', 0755, true);
                    }
                    
                    if (move_uploaded_file($tmpPath, $uploadPath)) {
                        $attachments[] = [
                            'path' => $uploadPath,
                            'name' => $filename,
                            'type' => $_FILES['attachments']['type'][$index] ?? 'application/octet-stream'
                        ];
                    }
                }
            }
        }
        
        $options = [
            'sendMode' => 'client',
            'from' => 'noreply@lagrange.ch',
            'replyTo' => 'contact@lagrange.ch',
            'attachments' => $attachments
        ];
        
        // Debug avant envoi
        if (is_dev_mode()) {
            error_log("Tentative d'envoi avec pièces jointes - Session ID: " . session_id());
            error_log("Nombre de pièces jointes: " . count($attachments));
        }
        
        $result = $emailService->envoyerReellement($to, $subject, $message, $options);
        
        if ($result['success']) {
            // Debug après succès
            if (is_dev_mode()) {
                error_log("Envoi avec PJ réussi - Request ID: " . $result['requestId']);
            }
            
            $requestId = $result['requestId'];
            header("Location: email_client_sender.php?request_id=$requestId");
            exit;
        } else {
            $error = "Erreur: " . $result['message'];
        }
        
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
        if (is_dev_mode()) {
            error_log("Erreur dans send_with_attachments: " . $e->getMessage());
        }
    }
}

// Exemple 3: Génération directe d'une page complète
if (isset($_POST['generate_page'])) {
    try {
        $to = $_POST['to'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        
        // Validation des données
        if (empty($to) || empty($subject) || empty($message)) {
            throw new Exception('Tous les champs sont requis');
        }
        
        $options = [
            'sendMode' => 'client',
            'from' => 'noreply@lagrange.ch'
        ];
        
        $html = $emailService->generateEmailClientPage($to, $subject, $message, $options);
        echo $html;
        exit;
        
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
        if (is_dev_mode()) {
            error_log("Erreur dans generate_page: " . $e->getMessage());
        }
    }
}

// Debug final
if (is_dev_mode()) {
    error_log("=== FIN exemple_utilisation.php ===");
    error_log("Session finale - ID: " . session_id());
    error_log("Pending emails final: " . count($_SESSION['pending_emails'] ?? []));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test EmailService - Client de Messagerie</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], input[type="email"], textarea, input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        textarea {
            height: 100px;
            resize: vertical;
        }
        
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        button:hover {
            background: #0056b3;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
        
        .debug {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
            font-family: monospace;
            font-size: 12px;
        }
        
        .example {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test EmailService - Client de Messagerie</h1>
        
        <div class="info">
            <strong>ℹ️ Information :</strong><br>
            Cette page permet de tester la fonctionnalité d'envoi d'emails via le client de messagerie de l'utilisateur.
            L'email sera préparé et ouvert dans votre client de messagerie par défaut.
        </div>
        
        <?php if (is_dev_mode()): ?>
        <div class="debug">
            <strong>🐛 Debug Info:</strong><br>
            Session ID: <?= session_id() ?><br>
            Mode développement: <?= is_dev_mode() ? 'Activé' : 'Désactivé' ?><br>
            Emails en attente: <?= count($_SESSION['pending_emails'] ?? []) ?><br>
            <?php if (!empty($_SESSION['pending_emails'])): ?>
                Request IDs: <?= implode(', ', array_keys($_SESSION['pending_emails'])) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="example">
            <h2>📧 Exemple 1: Email simple</h2>
            <form method="post">
                <div class="form-group">
                    <label for="to1">Destinataire:</label>
                    <input type="email" id="to1" name="to" value="test@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="subject1">Sujet:</label>
                    <input type="text" id="subject1" name="subject" value="Test email simple" required>
                </div>
                
                <div class="form-group">
                    <label for="message1">Message:</label>
                    <textarea id="message1" name="message" required>Bonjour,

Ceci est un email de test envoyé via le client de messagerie.

Cordialement,
Centre La Grange</textarea>
                </div>
                
                <button type="submit" name="send_simple">📤 Envoyer via client de messagerie</button>
            </form>
        </div>
        
        <div class="example">
            <h2>📎 Exemple 2: Email avec pièces jointes</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="to2">Destinataire:</label>
                    <input type="email" id="to2" name="to" value="test@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="subject2">Sujet:</label>
                    <input type="text" id="subject2" name="subject" value="Test avec pièces jointes" required>
                </div>
                
                <div class="form-group">
                    <label for="message2">Message:</label>
                    <textarea id="message2" name="message" required>Bonjour,

Veuillez trouver ci-joint les documents demandés.

Cordialement,
Centre La Grange</textarea>
                </div>
                
                <div class="form-group">
                    <label for="attachments">Pièces jointes:</label>
                    <input type="file" id="attachments" name="attachments[]" multiple>
                    <small>Sélectionnez un ou plusieurs fichiers à joindre</small>
                </div>
                
                <button type="submit" name="send_with_attachments">📎 Envoyer avec pièces jointes</button>
            </form>
        </div>
        
        <div class="example">
            <h2>🖥️ Exemple 3: Page complète générée</h2>
            <form method="post">
                <div class="form-group">
                    <label for="to3">Destinataire:</label>
                    <input type="email" id="to3" name="to" value="test@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="subject3">Sujet:</label>
                    <input type="text" id="subject3" name="subject" value="Page générée automatiquement" required>
                </div>
                
                <div class="form-group">
                    <label for="message3">Message:</label>
                    <textarea id="message3" name="message" required>Cette page a été générée automatiquement par l'EmailService.

Elle inclut tous les contrôles nécessaires pour l'envoi via le client de messagerie.</textarea>
                </div>
                
                <button type="submit" name="generate_page">🖥️ Générer page complète</button>
            </form>
        </div>
    </div>
    
    <div class="container">
        <h2>🔧 Configuration requise</h2>
        <ul>
            <li><strong>Navigateur :</strong> Chrome, Edge, Firefox (récent) pour le File System Access API</li>
            <li><strong>Variables d'environnement :</strong> 
                <ul>
                    <li><code>MAIL_SEND_MODE=client</code> pour activer le mode client</li>
                    <li><code>MAIL_FROM</code> et <code>MAIL_REPLY_TO</code> configurés</li>
                </ul>
            </li>
            <li><strong>Fichiers requis :</strong>
                <ul>
                    <li><code>EmailClientSender.js</code> - Script JavaScript</li>
                    <li><code>get_email_data.php</code> - Endpoint pour les données</li>
                    <li><code>EmailService.php</code> - Service mis à jour</li>
                </ul>
            </li>
        </ul>
        
        <?php if (is_dev_mode()): ?>
        <h3>🛠️ Outils de debug</h3>
        <p><a href="debug_session.php" target="_blank">Voir l'état des sessions</a></p>
        <p><a href="get_email_data.php?request_id=test" target="_blank">Tester l'endpoint get_email_data.php</a></p>
        <?php endif; ?>
    </div>
</body>
</html>