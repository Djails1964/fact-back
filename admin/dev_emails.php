<?php
// admin/dev_emails.php

// Inclure la configuration
$config = require_once '../bootstrap.php';

// Vérifier si l'environnement est de développement
if (env('APP_ENV', 'production') === 'production') {
    die('Cette page est uniquement disponible en environnement de développement.');
}

// Inclure le service d'emails
require_once '../EmailService.php';

// Créer le service d'emails
$emailService = new EmailService();

// Message de feedback pour l'utilisateur
$feedback = null;
$feedbackType = null; // 'success' ou 'error'
$mailtoUrl = null;

// Traiter la demande d'envoi réel d'un email
if (isset($_POST['send_real_email']) && isset($_POST['email_file'])) {
    $emailFile = $_POST['email_file'];
    $emailContent = $emailService->lireEmailCapture($emailFile);
    
    // Récupérer le mode d'envoi choisi
    $sendMode = isset($_POST['send_mode']) ? $_POST['send_mode'] : $emailService->getDefaultSendMode();
    
    if ($emailContent) {
        // Extraire les informations de l'email
        preg_match('/To: (.+)/', $emailContent, $toMatches);
        preg_match('/From: (.+)/', $emailContent, $fromMatches);
        preg_match('/Subject: (.+)/', $emailContent, $subjectMatches);
        
        $parts = explode("\n\n", $emailContent, 2);
        $body = $parts[1] ?? 'Contenu non disponible';
        
        // Extraire les pièces jointes si présentes
        $hasAttachment = false;
        $attachmentPath = null;
        
        // Recherche d'informations sur les pièces jointes dans l'en-tête
        if (preg_match('/Content-Disposition: attachment; filename="(.+?)"/', $emailContent, $attachmentMatches)) {
            $hasAttachment = true;
            
            // Pour extraire le chemin de la pièce jointe, il faudrait ajouter cette info lors de la capture
            // Ici, on suppose que cette information est stockée ailleurs ou extraite du contenu
            if (isset($_POST['attachment_path']) && file_exists($_POST['attachment_path'])) {
                $attachmentPath = $_POST['attachment_path'];
            } else {
                // Essayer d'extraire le chemin à partir du contenu de l'email s'il a été ajouté lors de la capture
                if (preg_match('/Original-Attachment-Path: (.+)/', $emailContent, $pathMatches)) {
                    $potentialPath = trim($pathMatches[1]);
                    if (file_exists($potentialPath)) {
                        $attachmentPath = $potentialPath;
                    }
                }
            }
        }
        
        // Préparer les options pour l'envoi
        $options = [
            'from' => trim($fromMatches[1] ?? $emailService->getDefaultSender()),
            'sendMode' => $sendMode
        ];
        
        // Ajouter les pièces jointes si présentes
        if ($hasAttachment && $attachmentPath) {
            $options['attachments'] = [
                [
                    'path' => $attachmentPath,
                    'name' => $attachmentMatches[1] ?? basename($attachmentPath),
                    'type' => 'application/pdf', // Par défaut
                ]
            ];
        }
        
        // Si mode client est choisi et qu'il y a des pièces jointes, afficher un avertissement
        if ($sendMode === 'client' && $hasAttachment) {
            $feedback = "ATTENTION: Le protocole mailto: ne supporte pas les pièces jointes. Elles ne seront pas incluses dans l'email.";
            $feedbackType = 'warning';
        }
        
        // Envoyer l'email
        $result = $emailService->envoyerReellement(
            trim($toMatches[1]),
            trim($subjectMatches[1] ?? 'Sans sujet'),
            $body,
            $options
        );
        
        // Traiter le résultat
        if ($sendMode === 'client') {
            // Pour le mode client, on retourne un lien mailto:
            if (is_array($result) && isset($result['mailtoUrl'])) {
                $mailtoUrl = $result['mailtoUrl'];
                $feedback = "Lien mailto: généré avec succès. Cliquez sur le bouton ci-dessous pour ouvrir votre client de messagerie.";
                $feedbackType = 'success';
            } else {
                $feedback = "Erreur lors de la génération du lien mailto:.";
                $feedbackType = 'error';
            }
        } else {
            // Pour le mode direct
            if ($result === true) {
                $feedback = "L'email a été envoyé avec succès à " . trim($toMatches[1]);
                $feedbackType = 'success';
            } else {
                $feedback = "Erreur lors de l'envoi de l'email.";
                $feedbackType = 'error';
            }
        }
    } else {
        $feedback = "Impossible de lire le contenu de l'email.";
        $feedbackType = 'error';
    }
}

// Traiter la demande de suppression
if (isset($_GET['clear']) && $_GET['clear'] === 'all') {
    $emailPath = env('ABSOLUTE_EMAIL_LOG_PATH', __DIR__ . '/../logs/emails');
    $files = glob($emailPath . '/*.eml');
    foreach ($files as $file) {
        unlink($file);
    }
    header('Location: dev_emails.php');
    exit;
}

// Traiter la demande de lecture d'un email spécifique
$emailContent = null;
$emailFile = null;
$hasAttachment = false;
$attachmentName = null;

if (isset($_GET['view']) && !empty($_GET['view'])) {
    $emailFile = $_GET['view'];
    $emailContent = $emailService->lireEmailCapture($emailFile);
    
    // Vérifier si l'email contient une pièce jointe
    if ($emailContent && preg_match('/Content-Disposition: attachment; filename="(.+?)"/', $emailContent, $matches)) {
        $hasAttachment = true;
        $attachmentName = $matches[1];
    }
}

// Récupérer tous les emails capturés
$emails = $emailService->getEmailsCapturesTousLogs();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emails capturés en développement - Centre La Grange</title>
    <style>
        :root {
            --primary-color: #A51C30;
            --secondary-color: #F5F5F5;
            --text-color: #333333;
            --light-accent: #E6E6E6;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --success-color: #28a745;
            --error-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--secondary-color);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .dev-banner {
            background-color: #ff9800;
            color: white;
            text-align: center;
            padding: 0.5rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
        }
        
        .actions {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .button.secondary {
            background-color: #6c757d;
        }
        
        .button.success {
            background-color: var(--success-color);
        }
        
        .email-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .email-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .email-card:hover {
            transform: translateY(-3px);
        }
        
        .email-card h3 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .email-card p {
            margin-bottom: 0.5rem;
            color: #666;
        }
        
        .email-content {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-top: 2rem;
            white-space: pre-wrap;
            font-family: monospace;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .email-meta {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-accent);
        }
        
        .email-meta span {
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .email-meta strong {
            color: var(--primary-color);
        }
        
        .no-emails {
            text-align: center;
            padding: 2rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .email-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .feedback {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
        }
        
        .feedback.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .feedback.error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }
        
        .feedback.warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid var(--warning-color);
        }
        
        .attachment-info {
            background-color: #f8f9fa;
            padding: 0.5rem;
            margin-top: 0.5rem;
            border-radius: var(--border-radius);
            font-style: italic;
        }
        
        .send-options {
            margin-top: 1rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
        }
        
        .send-options label {
            margin-right: 1rem;
            cursor: pointer;
        }
        
        .send-options input[type="radio"] {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dev-banner">
            <strong>Mode développement</strong> - Les emails sont capturés et non envoyés
        </div>
        
        <h1>Emails capturés en développement</h1>
        
        <?php if ($feedback): ?>
            <div class="feedback <?php echo $feedbackType; ?>">
                <?php echo $feedback; ?>
                
                <?php if ($mailtoUrl): ?>
                    <div style="margin-top: 10px;">
                        <a href="<?php echo $mailtoUrl; ?>" class="button success">Ouvrir dans le client de messagerie</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="dev_emails.php" class="button">Rafraîchir</a>
            <a href="dev_emails.php?clear=all" class="button secondary" onclick="return confirm('Êtes-vous sûr de vouloir supprimer tous les emails capturés ?')">Supprimer tous les emails</a>
        </div>
        
        <?php if (empty($emails)): ?>
            <div class="no-emails">
                <p>Aucun email capturé pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="email-grid">
                <?php foreach ($emails as $email): ?>
                    <a href="dev_emails.php?view=<?php echo urlencode($email['file']); ?>" class="email-card">
                        <h3><?php echo htmlspecialchars(preg_match('/Subject: (.+)/', $email['content'], $matches) ? $matches[1] : 'Sans sujet'); ?></h3>
                        <p><?php echo htmlspecialchars(preg_match('/To: (.+)/', $email['content'], $matches) ? $matches[1] : 'Destinataire inconnu'); ?></p>
                        <p><?php echo htmlspecialchars($email['date']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($emailContent): ?>
                <div class="email-content">
                    <div class="email-meta">
                        <?php 
                            preg_match('/To: (.+)/', $emailContent, $toMatches);
                            preg_match('/From: (.+)/', $emailContent, $fromMatches);
                            preg_match('/Subject: (.+)/', $emailContent, $subjectMatches);
                            preg_match('/Date: (.+)/', $emailContent, $dateMatches);
                        ?>
                        <span><strong>À:</strong> <?php echo htmlspecialchars($toMatches[1] ?? 'Inconnu'); ?></span>
                        <span><strong>De:</strong> <?php echo htmlspecialchars($fromMatches[1] ?? 'Inconnu'); ?></span>
                        <span><strong>Sujet:</strong> <?php echo htmlspecialchars($subjectMatches[1] ?? 'Sans sujet'); ?></span>
                        <span><strong>Date:</strong> <?php echo htmlspecialchars($dateMatches[1] ?? 'Inconnue'); ?></span>
                        
                        <?php if ($hasAttachment): ?>
                            <div class="attachment-info">
                                <span><strong>Pièce jointe:</strong> <?php echo htmlspecialchars($attachmentName); ?></span>
                                <?php if ($hasAttachment && isset($_POST['send_mode']) && $_POST['send_mode'] === 'client'): ?>
                                    <p style="color: #856404; margin-top: 5px;">Attention: Les pièces jointes ne sont pas supportées en mode client de messagerie.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php 
                        // Extraire le corps du message
                        $parts = explode("\n\n", $emailContent, 2);
                        echo nl2br(htmlspecialchars($parts[1] ?? 'Contenu non disponible'));
                    ?>
                    
                    <form method="post" action="dev_emails.php?view=<?php echo urlencode($emailFile); ?>" class="send-options">
                        <h4>Options d'envoi réel :</h4>
                        <div style="margin: 10px 0;">
                            <label>
                                <input type="radio" name="send_mode" value="direct" checked> 
                                Envoyer directement (PHP mail)
                            </label>
                            <label>
                                <input type="radio" name="send_mode" value="client"> 
                                Ouvrir dans le client de messagerie
                            </label>
                        </div>
                        
                        <input type="hidden" name="email_file" value="<?php echo htmlspecialchars($emailFile); ?>">
                        <?php if ($hasAttachment && isset($_POST['attachment_path'])): ?>
                            <input type="hidden" name="attachment_path" value="<?php echo htmlspecialchars($_POST['attachment_path']); ?>">
                        <?php endif; ?>
                        
                        <div class="email-actions">
                            <button type="submit" name="send_real_email" class="button success" onclick="return confirm('Êtes-vous sûr de vouloir envoyer réellement cet email ?')">
                                Envoyer réellement
                            </button>
                        </div>
                        
                        <?php if ($hasAttachment): ?>
                            <p style="margin-top: 10px; font-size: 0.9em; color: #666;">
                                Note: Si vous choisissez le mode "client de messagerie", les pièces jointes ne seront pas incluses 
                                car elles ne sont pas supportées par le protocole mailto:.
                            </p>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>