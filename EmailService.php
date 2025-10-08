<?php
/**
 * EmailService.php
 * 
 * Service pour l'envoi d'emails avec support pour le mode développement
 * MISE À JOUR: Intégration complète du client de messagerie avec File System Access API
 */

if (!defined('APP_INITIATED')) {
    $config = require_once 'bootstrap.php';
}

// Imports pour PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $defaultSender;
    private $defaultReplyTo;
    private $isDevelopment;
    private $capturedEmails = [];
    private $emailLogPath;
    private $defaultSendMode;
    
    public function __construct($defaultSender = null, $defaultReplyTo = null, $isDevelopment = null, $emailLogPath = null, $defaultSendMode = null) {
        $this->defaultSender = $defaultSender ?? env('MAIL_FROM', 'noreply@lagrange.ch');
        $this->defaultReplyTo = $defaultReplyTo ?? env('MAIL_REPLY_TO', 'contact@lagrange.ch');
        $this->isDevelopment = $isDevelopment ?? env('APP_ENV', 'production') !== 'production';
        $this->emailLogPath = $emailLogPath ?? env('ABSOLUTE_EMAIL_LOG_PATH', realpath(__DIR__ . '/logs/emails'));
        $this->defaultSendMode = $defaultSendMode ?? env('MAIL_SEND_MODE', 'direct');

        error_log("=======EmailService initialisé avec les paramètres suivants:=====");
        error_log(" - Expéditeur par défaut: " . $this->defaultSender); 
        error_log(" - Répondre à: " . $this->defaultReplyTo);
        error_log(" - Mode développement: " . ($this->isDevelopment ? 'Oui' : 'Non'));
        error_log(" - Chemin des logs d'emails: " . $this->emailLogPath);
        error_log(" - Mode d'envoi par défaut: " . $this->defaultSendMode);
        error_log("========================FIN DEBUG ===============================");
        
        // Créer le dossier de logs d'emails si nécessaire
        if ($this->isDevelopment && !is_dir($this->emailLogPath)) {
            mkdir($this->emailLogPath, 0755, true);
        }
    }
    
    /**
     * Envoie un email (ou le capture en mode développement)
     */
    public function envoyer($to, $subject, $message, $options = []) {
        // Utiliser PHPMailer par défaut si disponible
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->envoyerAvecPHPMailer($to, $subject, $message, $options);
        }
        
        // Sinon, utiliser l'ancienne méthode (code existant)
        $from = $options['from'] ?? $this->defaultSender;
        $replyTo = $options['replyTo'] ?? $this->defaultReplyTo;
        
        $headers = 'From: ' . $from . "\r\n" .
                   'Reply-To: ' . $replyTo . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
        
        // Ajouter des en-têtes supplémentaires si spécifiés
        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $headers .= $name . ': ' . $value . "\r\n";
            }
        }
        
        // Créer l'objet email
        $email = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'from' => $from,
            'replyTo' => $replyTo,
            'timestamp' => date('Y-m-d H:i:s'),
            'options' => $options
        ];
        
        // En mode développement, capturer l'email au lieu de l'envoyer
        if ($this->isDevelopment) {
            return $this->captureEmail($email);
        }
        
        // En production, envoyer réellement l'email
        $sent = mail($to, $subject, $message, $headers);
        
        // Logger le résultat
        if (!$sent) {
            error_log("Échec d'envoi d'email à $to, sujet: $subject");
        }
        
        return $sent;
    }
    
    /**
     * Capture un email en mode développement - CORRIGÉ pour les pièces jointes
     */
    private function captureEmail($email) {
        // Ajouter l'email à la liste des emails capturés
        $this->capturedEmails[] = $email;
        
        // Générer un nom de fichier unique pour cet email
        $filename = date('Ymd_His') . '_' . md5($email['to'] . time()) . '.eml';
        $filepath = $this->emailLogPath . '/' . $filename;
        
        // Si on a des pièces jointes, créer un email MIME complet
        if (isset($email['options']['attachments']) && !empty($email['options']['attachments'])) {
            $content = $this->generateMimeEmailWithAttachments($email);
        } else {
            // Email simple sans pièce jointe
            $content = $this->generateSimpleEmail($email);
        }
        
        // Écrire l'email dans un fichier
        file_put_contents($filepath, $content);
        
        // Enregistrer dans le log système
        error_log("Email capturé en mode développement: $filepath");
        
        return true;
    }
    
    /**
     * Génère un email simple pour la capture
     */
    private function generateSimpleEmail($email) {
        $content = "To: " . $email['to'] . "\n";
        $content .= "From: " . $email['from'] . "\n";
        $content .= "Subject: " . $email['subject'] . "\n";
        $content .= "Date: " . $email['timestamp'] . "\n";
        $content .= "Content-Type: text/plain; charset=UTF-8\n";
        $content .= "\n"; // Ligne vide entre les en-têtes et le corps
        $content .= $email['message'];
        
        return $content;
    }
    
    /**
     * Génère un email MIME complet avec pièces jointes pour la capture
     * CORRECTION: Les pièces jointes sont vraiment attachées, pas affichées dans le corps
     */
    private function generateMimeEmailWithAttachments($email) {
        $boundary = 'boundary_' . md5(time() . rand());
        
        // En-têtes de l'email
        $content = "To: " . $email['to'] . "\n";
        $content .= "From: " . $email['from'] . "\n";
        $content .= "Subject: " . $email['subject'] . "\n";
        $content .= "Date: " . $email['timestamp'] . "\n";
        $content .= "MIME-Version: 1.0\n";
        $content .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\n";
        $content .= "\n"; // Ligne vide après les en-têtes
        
        // Avertissement : ceci est un email de développement
        $content .= "This is a multi-part message in MIME format.\n";
        $content .= "\n";
        
        // Corps du message (partie texte)
        $content .= "--$boundary\n";
        $content .= "Content-Type: text/plain; charset=UTF-8\n";
        $content .= "Content-Transfer-Encoding: 8bit\n";
        $content .= "\n";
        $content .= $email['message'] . "\n";
        
        // Ajouter les pièces jointes comme vraies pièces jointes
        foreach ($email['options']['attachments'] as $attachment) {
            if (empty($attachment['path']) || !file_exists($attachment['path'])) {
                // Si le fichier n'existe pas, l'indiquer mais ne pas casser l'email
                error_log("ATTENTION: Pièce jointe manquante: " . ($attachment['path'] ?? 'chemin non défini'));
                continue;
            }
            
            $filename = $attachment['name'] ?? basename($attachment['path']);
            $filetype = $this->getMimeType($attachment['path']);
            
            // Lire et encoder le fichier
            $fileContent = file_get_contents($attachment['path']);
            $fileContentEncoded = chunk_split(base64_encode($fileContent));
            
            // Créer la partie pièce jointe
            $content .= "\n--$boundary\n";
            $content .= "Content-Type: $filetype; name=\"$filename\"\n";
            $content .= "Content-Transfer-Encoding: base64\n";
            $content .= "Content-Disposition: attachment; filename=\"$filename\"\n";
            $content .= "\n";
            $content .= $fileContentEncoded;
        }
        
        // Finaliser le message MIME
        $content .= "\n--$boundary--\n";
        
        return $content;
    }
    
    /**
     * Détermine le type MIME d'un fichier
     */
    private function getMimeType($filepath) {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
        ];
        
        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
    
    /**
     * Envoie un email en utilisant PHPMailer avec vérification de MailHog
     */
    public function envoyerAvecPHPMailer($to, $subject, $message, $options = []) {
        // Récupérer le mode d'envoi depuis .env (env() est déjà définie dans bootstrap.php)
        $sendMode = env('MAIL_SEND_MODE', 'direct');
        $bypassCapture = $options['bypassCapture'] ?? false;
        
        error_log("Mode d'envoi configuré: $sendMode" . ($bypassCapture ? " (avec bypass)" : ""));
        
        // Si le mode est "client", utiliser la méthode de préparation pour client de messagerie
        if ($sendMode === 'client') {
            error_log("Mode client détecté - préparation pour client de messagerie avec File System Access API");
            return $this->envoyerViaClientEmailAvecJS($to, $subject, $message, 
                                                    $options['from'] ?? env('MAIL_FROM', $this->defaultSender),
                                                    $options['replyTo'] ?? env('MAIL_REPLY_TO', $this->defaultReplyTo),
                                                    $options);
        }

        // Mode direct - utiliser PHPMailer
        $mail = new PHPMailer(true);
        
        try {
            // Paramètres de base
            $mail->CharSet = 'UTF-8';
            
            // Logique de configuration SMTP
            if ($this->isDevelopment && !$bypassCapture) {
                // Mode développement normal - vérifier MailHog
                $mailhogRunning = $this->isMailHogRunning();
            
                if ($mailhogRunning) {
                    // Configuration pour MailHog
                    $mail->isSMTP();
                    $mail->Host = 'localhost';
                    $mail->Port = 1025;
                    $mail->SMTPAuth = false;
                    
                    error_log("MailHog détecté - envoi via SMTP localhost:1025");
                } else {
                    // MailHog n'est pas accessible, on utilise la capture de fichier
                    error_log("MailHog non détecté - capture de l'email dans un fichier");
                    
                    $email = [
                        'to' => $to,
                        'subject' => $subject,
                        'message' => $message,
                        'headers' => 'Sent via PHPMailer (MailHog non disponible)',
                        'from' => $options['from'] ?? env('MAIL_FROM', $this->defaultSender),
                        'replyTo' => $options['replyTo'] ?? env('MAIL_REPLY_TO', $this->defaultReplyTo),
                        'timestamp' => date('Y-m-d H:i:s'),
                        'options' => $options
                    ];
                    
                    return $this->captureEmail($email);
                }
            } elseif ($this->isDevelopment && $bypassCapture) {
                // Mode développement avec bypass - utiliser la configuration de production
                error_log("BYPASS CAPTURE ACTIVÉ - Envoi direct en mode développement");
                
                // Configuration SMTP de production
                $mail->isSMTP();
                $mail->Host = env('MAIL_HOST');
                $mail->Port = (int)env('MAIL_PORT', 587);
                $mail->SMTPAuth = true;
                $mail->Username = env('MAIL_USERNAME');
                $mail->Password = env('MAIL_PASSWORD');
                $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls');
            } else {
                // Configuration SMTP de production normale
                $mail->isSMTP();
                $mail->Host = env('MAIL_HOST');
                $mail->Port = (int)env('MAIL_PORT', 587);
                $mail->SMTPAuth = true;
                $mail->Username = env('MAIL_USERNAME');
                $mail->Password = env('MAIL_PASSWORD');
                $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls');
            }
            
            // Expéditeurs et destinataires
            $from = $options['from'] ?? env('MAIL_FROM', $this->defaultSender);
            $fromName = $options['fromName'] ?? env('APP_NAME', 'Centre La Grange');
            $replyTo = $options['replyTo'] ?? env('MAIL_REPLY_TO', $this->defaultReplyTo);
            
            $mail->setFrom($from, $fromName);
            $mail->addReplyTo($replyTo);
            $mail->addAddress($to);
            
            // CC et BCC si spécifiés
            if (isset($options['cc']) && !empty($options['cc'])) {
                $mail->addCC($options['cc']);
            }
            
            if (isset($options['bcc']) && !empty($options['bcc'])) {
                $mail->addBCC($options['bcc']);
            }
            
            // Contenu
            $mail->isHTML(false); // Format texte par défaut
            if (isset($options['isHtml']) && $options['isHtml']) {
                $mail->isHTML(true);
            }
            
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Pièces jointes
            if (isset($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (empty($attachment['path']) || !file_exists($attachment['path'])) {
                        error_log("ATTENTION: Pièce jointe manquante: " . ($attachment['path'] ?? 'chemin non défini'));
                        continue;
                    }
                    
                    $filename = $attachment['name'] ?? basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $filename);
                    error_log("Pièce jointe ajoutée: " . $attachment['path'] . " comme " . $filename);
                }
            }
            
            // Envoyer l'email
            $mail->send();
            
            // Log adapté selon le mode
            if ($this->isDevelopment && $bypassCapture) {
                error_log("Email envoyé directement en mode développement (BYPASS): $to, sujet: $subject");
            } elseif ($this->isDevelopment) {
                error_log("Email envoyé à MailHog: $to, sujet: $subject");
            } else {
                error_log("Email de production envoyé à $to via {$mail->Host}, sujet: $subject");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Échec d'envoi d'email à $to: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Récupère les emails capturés (en mode développement uniquement)
     * 
     * @return array Liste des emails capturés
     */
    public function getEmailsCaptures() {
        return $this->capturedEmails;
    }
    
    /**
     * Retourne tous les emails capturés dans le dossier de logs
     * 
     * @param int $limit Nombre maximal d'emails à retourner
     * @return array Liste des fichiers d'emails
     */
    public function getEmailsCapturesTousLogs($limit = 50) {
        if (!$this->isDevelopment || !is_dir($this->emailLogPath)) {
            return [];
        }
        
        error_log("Récupération des emails capturés depuis le dossier: " . $this->emailLogPath);

        // Chercher les fichiers .eml
        $files = glob($this->emailLogPath . '/*.eml');
        error_log("Nombre de fichiers .eml trouvés: " . count($files));
        
        // Trier par date (le plus récent d'abord)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Limiter le nombre de fichiers retournés
        $files = array_slice($files, 0, $limit);
        
        $emails = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $emails[] = [
                'file' => basename($file),
                'path' => $file,
                'content' => $content,
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        return $emails;
    }
    
    /**
     * Lit le contenu d'un email capturé spécifique
     * 
     * @param string $filename Nom du fichier
     * @return string|false Contenu de l'email ou false si le fichier n'existe pas
     */
    public function lireEmailCapture($filename) {
        $filepath = $this->emailLogPath . '/' . basename($filename);
        
        if (file_exists($filepath)) {
            return file_get_contents($filepath);
        }
        
        return false;
    }
    
    /**
     * Envoie un email de réinitialisation de mot de passe
     * 
     * @param array $user Données de l'utilisateur (nom, prenom, email, username)
     * @param string $token Token de réinitialisation
     * @param string $resetLink Lien complet de réinitialisation
     * @return bool True si l'email a été envoyé avec succès
     */
    public function envoyerResetPassword($user, $token, $resetLink) {
        try {
            // Préparer les données pour le template
            $templateData = [
                'prenom' => $user['prenom'] ?? '',
                'nom' => $user['nom'] ?? '',
                'username' => $user['username'] ?? '',
                'reset_link' => $resetLink,
                'year' => date('Y')
            ];
            
            // Générer le contenu HTML
            $htmlContent = $this->generateResetPasswordTemplate($templateData);
            
            // Générer le contenu texte
            $textContent = $this->generateResetPasswordTextTemplate($templateData);
            
            // Configuration de l'email - CORRECTION ICI
            $subject = 'Réinitialisation de votre mot de passe - Centre La Grange';
            $toEmail = $user['email'];
            $toName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: $user['username'];
            
            // Log pour débogage
            error_log("EmailService - Envoi reset password à: {$toEmail}");
            error_log("EmailService - Nom destinataire: {$toName}");
            error_log("EmailService - Lien reset: {$resetLink}");
            
            // CORRECTION: Les paramètres sont dans le bon ordre maintenant
            // OLD: $this->envoyerAvecPHPMailer($toEmail, $toName, $subject, $htmlContent, $textContent);
            // NEW: $this->envoyerAvecPHPMailer($to, $subject, $message, $options)
            $emailSent = $this->envoyerAvecPHPMailer($toEmail, $subject, $htmlContent, [
                'from' => $this->defaultSender,
                'fromName' => env('APP_NAME', 'Centre La Grange'),
                'replyTo' => $this->defaultReplyTo,
                'isHtml' => true
            ]);
            
            if ($emailSent) {
                error_log("EmailService - Email de reset envoyé avec succès à {$toEmail}");
            } else {
                error_log("EmailService - Échec envoi email de reset à {$toEmail}");
            }
            
            return $emailSent;
            
        } catch (Exception $e) {
            error_log("Erreur EmailService reset password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère le template HTML pour l'email de réinitialisation
     */
    private function generateResetPasswordTemplate($data) {
        $template = file_get_contents(__DIR__ . '/templates/reset-password-email.html');
        
        // Si le fichier template n'existe pas, utiliser un template intégré
        if ($template === false) {
            $template = $this->getInlineResetPasswordTemplate();
        }
        
        // Remplacer les variables dans le template
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $template);
        }
        
        return $template;
    }

    /**
     * Génère le template texte pour l'email de réinitialisation
     */
    private function generateResetPasswordTextTemplate($data) {
        $content = "Centre La Grange - Réinitialisation de mot de passe\n\n";
        $content .= "Bonjour " . trim($data['prenom'] . ' ' . $data['nom']) . ",\n\n";
        $content .= "Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte utilisateur '{$data['username']}'.\n\n";
        $content .= "Si vous êtes à l'origine de cette demande, cliquez sur le lien ci-dessous pour définir un nouveau mot de passe :\n\n";
        $content .= $data['reset_link'] . "\n\n";
        $content .= "⏰ Important : Ce lien est valable pendant 1 heure seulement.\n\n";
        $content .= "🔒 Sécurité : Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.\n\n";
        $content .= "Besoin d'aide ? Contactez l'administrateur système.\n\n";
        $content .= "© {$data['year']} Centre La Grange. Tous droits réservés.\n";
        $content .= "Cet email a été envoyé automatiquement. Veuillez ne pas répondre à cet email.";
        
        return $content;
    }

    /**
     * Template HTML intégré si le fichier externe n'existe pas
     */
    private function getInlineResetPasswordTemplate() {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .container { background-color: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #A51C30; }
        .logo { color: #A51C30; font-size: 28px; font-weight: 600; letter-spacing: 2px; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; }
        .greeting { font-size: 18px; margin-bottom: 20px; color: #A51C30; }
        .message { margin-bottom: 20px; line-height: 1.8; }
        .reset-button { text-align: center; margin: 30px 0; }
        .reset-link { display: inline-block; background-color: #A51C30; color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 500; }
        .warning { background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">LA GRANGE</div>
            <div class="subtitle">Système de gestion du centre</div>
        </div>
        
        <div class="greeting">Bonjour {{prenom}} {{nom}},</div>
        
        <div class="message">
            Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte utilisateur <strong>{{username}}</strong>.
        </div>
        
        <div class="message">
            Si vous êtes à l\'origine de cette demande, cliquez sur le bouton ci-dessous :
        </div>
        
        <div class="reset-button">
            <a href="{{reset_link}}" class="reset-link">Réinitialiser mon mot de passe</a>
        </div>
        
        <div class="warning">
            <strong>🔒 Sécurité :</strong> Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email.
            <br><strong>⏰ Important :</strong> Ce lien expire dans 1 heure.
        </div>
        
        <div class="message">
            Lien direct : <a href="{{reset_link}}" style="color: #A51C30;">{{reset_link}}</a>
        </div>
        
        <div class="footer">
            <p>&copy; {{year}} Centre La Grange. Tous droits réservés.</p>
            <p>Cet email a été envoyé automatiquement. Ne pas répondre.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Envoie une notification de changement de mot de passe
     * 
     * @param array $user Données de l'utilisateur
     * @return bool Succès de l'envoi
     */
    public function envoyerNotificationChangementMotDePasse($user) {
        $subject = 'Confirmation de changement de mot de passe - Centre La Grange';
        
        $message = "Bonjour " . $user['prenom'] . " " . $user['nom'] . ",\n\n";
        $message .= "Nous vous confirmons que votre mot de passe a été modifié avec succès.\n\n";
        $message .= "Si vous n'êtes pas à l'origine de cette modification, veuillez contacter immédiatement l'administrateur.\n\n";
        $message .= "Cordialement,\nL'équipe du Centre La Grange";
        
        return $this->envoyer($user['email'], $subject, $message);
    }

    /**
     * Envoie un email avec pièce jointe
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $message Corps du message
     * @param array $options Options supplémentaires (from, replyTo, attachments, etc.)
     * @return bool Succès de l'envoi ou de la capture
     */
    public function envoyerAvecPieceJointe($to, $subject, $message, $options = []) {
        // Utiliser PHPMailer par défaut si disponible
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->envoyerAvecPHPMailer($to, $subject, $message, $options);
        }

        // Générer une frontière unique pour séparer les parties du message
        $boundary = md5(time());
        
        // Préparer les en-têtes
        $from = $options['from'] ?? $this->defaultSender;
        $replyTo = $options['replyTo'] ?? $this->defaultReplyTo;
        
        $headers = "From: $from\r\n";
        $headers .= "Reply-To: $replyTo\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Préparer le contenu du message - le corps en texte brut
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message . "\r\n\r\n";
        
        // Ajouter les pièces jointes
        if (isset($options['attachments']) && is_array($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                if (empty($attachment['path']) || !file_exists($attachment['path'])) {
                    continue;
                }
                
                $filename = $attachment['name'] ?? basename($attachment['path']);
                $filetype = $attachment['type'] ?? 'application/octet-stream';
                
                $fileContent = file_get_contents($attachment['path']);
                $fileContentEncoded = chunk_split(base64_encode($fileContent));
                
                $body .= "--$boundary\r\n";
                $body .= "Content-Type: $filetype; name=\"$filename\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
                $body .= $fileContentEncoded . "\r\n";
            }
        }
        
        // Finaliser le message
        $body .= "--$boundary--\r\n";
        
        // Créer l'objet email pour la capture en mode développement
        $email = [
            'to' => $to,
            'subject' => $subject,
            'message' => $body,
            'headers' => $headers,
            'from' => $from,
            'replyTo' => $replyTo,
            'timestamp' => date('Y-m-d H:i:s'),
            'options' => $options
        ];
        
        // En mode développement, capturer l'email au lieu de l'envoyer
        if ($this->isDevelopment) {
            // On sauvegarde ce que PHPMailer aurait envoyé, mais juste le contenu original
            $email = [
                'to' => $to,
                'subject' => $subject,
                'message' => $message, // Message original, pas le format MIME
                'headers' => 'Sent via PHPMailer',
                'from' => $from,
                'replyTo' => $replyTo,
                'timestamp' => date('Y-m-d H:i:s'),
                'options' => $options
            ];
            
            // Si on a des pièces jointes, les stocker dans un format qui ne sera pas affiché dans le corps
            if (isset($options['attachments']) && is_array($options['attachments'])) {
                $email['attachments_metadata'] = [];
                foreach ($options['attachments'] as $attachment) {
                    if (!empty($attachment['path'])) {
                        $email['attachments_metadata'][] = [
                            'filename' => basename($attachment['path']),
                            'path' => $attachment['path'] // Ne sera pas affiché dans le corps
                        ];
                    }
                }
            }
            
            return $this->captureEmail($email);
        }
        
        // En production, envoyer réellement l'email
        $sent = mail($to, $subject, $body, $headers);
        
        // Logger le résultat
        if (!$sent) {
            error_log("Échec d'envoi d'email à $to, sujet: $subject");
        }
        
        return $sent;
    }

    /**
     * Envoie réellement un email même en mode développement
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $message Corps du message
     * @param array $options Options supplémentaires (from, replyTo, attachments, sendMode, etc.)
     * @return bool|array Succès de l'envoi ou données pour client de messagerie
     */
    public function envoyerReellement($to, $subject, $message, $options = []) {
        if (empty($to) || empty($subject)) {
            error_log("Envoi d'email impossible: destinataire ou sujet manquant");
            return false;
        }
        
        // Déterminer le mode d'envoi
        $sendMode = $options['sendMode'] ?? $this->defaultSendMode;
        
        // Vérifier le bypass de capture
        $bypassCapture = $options['bypassCapture'] ?? false;
        
        // CORRECTION IMPORTANTE: Si le mode est "client", ignorer le bypass
        // Le bypass ne s'applique qu'au mode "direct"
        if ($sendMode === 'client') {
            error_log("Mode client demandé - création de fichier .eml (bypass ignoré en mode client)");
            return $this->envoyerViaClientEmailAvecJS($to, $subject, $message, 
                                                    $options['from'] ?? $this->defaultSender,
                                                    $options['replyTo'] ?? $this->defaultReplyTo,
                                                    $options);
        }
        
        // Log de débogage pour mode direct
        $logMessage = "Tentative d'envoi réel d'email en mode " . $sendMode . " à: " . $to;
        if ($this->isDevelopment && $bypassCapture) {
            $logMessage .= " [BYPASS CAPTURE ACTIVÉ]";
        }
        error_log($logMessage);
        
        // Préparer les paramètres communs
        $from = $options['from'] ?? $this->defaultSender;
        $replyTo = $options['replyTo'] ?? $this->defaultReplyTo;
        
        // Mode direct uniquement
        return $this->envoyerDirect($to, $subject, $message, $from, $replyTo, $options);
    }

    /**
     * Envoie un email via le client de messagerie de l'utilisateur en utilisant JavaScript
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $message Corps du message
     * @param string $from Expéditeur
     * @param string $replyTo Adresse de réponse
     * @param array $options Options supplémentaires
     * @return array Données pour l'envoi côté client
     */
    private function envoyerViaClientEmailAvecJS($to, $subject, $message, $from, $replyTo, $options = []) {
        // S'assurer que la session est démarrée
        ensure_session_started();
        
        // Debug session
        if (is_dev_mode()) {
            error_log("=== DEBUG envoyerViaClientEmailAvecJS (MODE MODERNE) ===");
            error_log("Mode: CLIENT MODERNE - Interface JavaScript + File System Access API");
            error_log("Session ID: " . session_id());
            error_log("Session name: " . session_name());
            error_log("Session status: " . session_status());
        }
        
        // Préparer les données pour JavaScript
        $emailData = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'from' => $from,
            'replyTo' => $replyTo
        ];
        
        // Ajouter CC et BCC si présents
        if (isset($options['cc']) && !empty($options['cc'])) {
            $emailData['cc'] = $options['cc'];
        }
        
        if (isset($options['bcc']) && !empty($options['bcc'])) {
            $emailData['bcc'] = $options['bcc'];
        }
        
        // Informations sur les pièces jointes
        $attachmentInfo = [];
        // ✅ DEBUG : Afficher ce qui arrive dans $options
        error_log("🔍 DEBUG EmailService - options['attachments'] : " . print_r($options['attachments'] ?? 'NON DÉFINI', true));

        if (isset($options['attachments']) && is_array($options['attachments'])) {
            error_log("📎 DEBUG - Nombre de pièces jointes à traiter : " . count($options['attachments']));
            
            foreach ($options['attachments'] as $index => $attachment) {
                error_log("📄 DEBUG - Pièce jointe [$index] : " . print_r($attachment, true));
                
                if (!empty($attachment['path']) && file_exists($attachment['path'])) {
                    $filename = basename($attachment['path']);
                    
                    error_log("✅ DEBUG - Fichier valide : $filename");
                    error_log("   - Chemin complet : " . $attachment['path']);
                    error_log("   - Chemin relatif : storage/factures/$filename");
                    error_log("   - Taille : " . filesize($attachment['path']) . " octets");
                    
                    $attachmentInfo[] = [
                        'name' => $attachment['name'] ?? $filename,
                        'path' => 'storage/factures/' . $filename,  // ✅ Chemin relatif web
                        'size' => filesize($attachment['path']),
                        'type' => $this->getMimeType($attachment['path'])
                    ];
                } else {
                    error_log("❌ DEBUG - Fichier invalide ou inexistant");
                    error_log("   - path fourni : " . ($attachment['path'] ?? 'VIDE'));
                    error_log("   - file_exists : " . (file_exists($attachment['path'] ?? '') ? 'OUI' : 'NON'));
                }
            }
            
            error_log("📋 DEBUG - attachmentInfo final : " . print_r($attachmentInfo, true));
        } else {
            error_log("⚠️ DEBUG - Aucune pièce jointe dans options ou pas un tableau");
        }
        
        // Générer un ID unique pour cette requête
        $requestId = 'email_modern_' . uniqid() . '_' . time();
        
        // DEBUG - AVANT SAUVEGARDE
        error_log("=== AVANT SAUVEGARDE EMAIL SERVICE MODERNE ===");
        error_log("Session ID: " . session_id());
        error_log("Request ID: $requestId");
        error_log("Pending emails avant: " . count($_SESSION['pending_emails'] ?? []));

        // Initialiser le tableau des emails en attente si nécessaire
        if (!isset($_SESSION['pending_emails'])) {
            $_SESSION['pending_emails'] = [];
            if (is_dev_mode()) {
                error_log("Initialisation du tableau pending_emails (mode moderne)");
            }
        }
        
        // ✅ SAUVEGARDE AVEC STRUCTURE COMPLÈTE
        $_SESSION['pending_emails'][$requestId] = [
            'emailData' => $emailData,
            'attachmentInfo' => $attachmentInfo,
            'timestamp' => time(),
            'used' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'session_id_stored' => session_id(),
            'mode' => 'modern' // ✅ NOUVEAU: Identifier le mode
        ];

        // DEBUG - APRÈS SAUVEGARDE
        error_log("=== APRÈS SAUVEGARDE EMAIL SERVICE MODERNE ===");
        error_log("Données sauvegardées: " . json_encode($_SESSION['pending_emails'][$requestId]));
        error_log("Pending emails après: " . count($_SESSION['pending_emails']));

        // ✅ FORCER LA SAUVEGARDE DE LA SESSION IMMÉDIATEMENT
        session_write_close();
        
        // ✅ REDÉMARRER LA SESSION POUR VÉRIFIER
        session_start();
        
        // ✅ VÉRIFICATION IMMÉDIATE
        if (!isset($_SESSION['pending_emails'][$requestId])) {
            error_log("❌ ERREUR CRITIQUE: Données non sauvegardées en session!");
            throw new Exception("Erreur de sauvegarde en session");
        }

        // Construire l'URL avec l'ID de session explicite
        $sessionName = session_name();
        $sessionId = session_id();
        
        // ✅ URL MODERNE avec timestamp pour éviter le cache
        $newWindowUrl = "email_client_sender.php?request_id=$requestId&{$sessionName}=$sessionId&mode=modern&_t=" . time();

        // Debug : Vérifier que les données sont bien stockées
        if (is_dev_mode()) {
            error_log("✅ URL moderne avec session explicite: $newWindowUrl");
            error_log("Mode: MODERNE (JavaScript + File System Access API)");
            error_log("Session name: $sessionName");
            error_log("Session ID: $sessionId");
            error_log("Données stockées pour request ID: $requestId");
            error_log("Vérification immédiate - données présentes: " . (isset($_SESSION['pending_emails'][$requestId]) ? 'OUI' : 'NON'));
        }
        
        // Nettoyer les anciennes requêtes
        $this->cleanupOldEmailRequests();
        
        return [
            'success' => true,
            'method' => 'client_email_modern',
            'requestId' => $requestId,
            'emailData' => $emailData,
            'hasAttachments' => !empty($attachmentInfo),
            'attachmentCount' => count($attachmentInfo),
            'message' => 'Interface moderne prête - File System Access API + JavaScript',
            'shouldOpenNewWindow' => true,
            'newWindowUrl' => $newWindowUrl,
            'mode' => 'modern', // ✅ NOUVEAU: Identifier le mode dans la réponse
            'debug' => [
                'session_id' => $sessionId,
                'session_name' => $sessionName,
                'stored_successfully' => isset($_SESSION['pending_emails'][$requestId]),
                'timestamp' => time(),
                'pending_emails_count' => count($_SESSION['pending_emails']),
                'mode' => 'modern'
            ]
        ];
    }

    /**
     * NOUVELLE MÉTHODE: Vérifier le contenu de la session
     */
    public function debugPendingEmails() {
        ensure_session_started();
        
        return [
            'session_id' => session_id(),
            'pending_emails' => $_SESSION['pending_emails'] ?? [],
            'count' => count($_SESSION['pending_emails'] ?? []),
            'timestamp' => time()
        ];
    }

    /**
     * Nettoie les anciennes requêtes d'email en attente
     */
    private function cleanupOldEmailRequests() {
        if (!isset($_SESSION['pending_emails'])) {
            return;
        }
        
        $currentTime = time();
        $maxAge = 3600; // 1 heure
        $cleaned = 0;
        
        foreach ($_SESSION['pending_emails'] as $requestId => $data) {
            // Nettoyer si expiré OU si utilisé ET plus vieux que 5 minutes
            $shouldClean = ($currentTime - $data['timestamp']) > $maxAge || 
                        ($data['used'] && ($currentTime - $data['timestamp']) > 300);
            
            if ($shouldClean) {
                unset($_SESSION['pending_emails'][$requestId]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0 && is_dev_mode()) {
            error_log("Nettoyé $cleaned anciennes requêtes d'email");
        }
    }

    /**
     * Récupère les données d'email pour une requête spécifique
     * 
     * @param string $requestId ID de la requête
     * @return array|false Données de l'email ou false si non trouvé
     */
    public function getEmailDataForRequest($requestId) {
        if (!isset($_SESSION['pending_emails'][$requestId])) {
            return false;
        }
        
        $data = $_SESSION['pending_emails'][$requestId];
        
        // Marquer comme utilisé
        $_SESSION['pending_emails'][$requestId]['used'] = true;
        
        return [
            'emailData' => $data['emailData'],
            'attachmentInfo' => $data['attachmentInfo']
        ];
    }

    /**
     * Génère le JavaScript nécessaire pour envoyer l'email côté client
     * 
     * @param string $requestId ID de la requête
     * @param string $attachmentInputId ID de l'input file pour les pièces jointes (optionnel)
     * @return string Code JavaScript
     */
    public function generateClientEmailJS($requestId, $attachmentInputId = null) {
        $data = $this->getEmailDataForRequest($requestId);
        if (!$data) {
            return "console.error('Requête d\'email non trouvée: $requestId');";
        }
        
        $emailDataJson = json_encode($data['emailData'], JSON_HEX_QUOT | JSON_HEX_APOS);
        $attachmentInputParam = $attachmentInputId ? "'$attachmentInputId'" : 'null';
        
        $js = "
// Envoi d'email via le client de messagerie
(async function() {
    try {
        console.log('Envoi d\'email via le client de messagerie...');
        
        const emailData = $emailDataJson;
        const result = await window.sendEmailViaClient(emailData, $attachmentInputParam);
        
        if (result.success) {
            console.log('✅ Email préparé avec succès:', result.message);
            
            // Afficher une notification à l'utilisateur
            if (typeof showNotification === 'function') {
                showNotification('success', result.message);
            } else {
                alert('✅ ' + result.message);
            }
        } else {
            console.error('❌ Erreur:', result.message);
            alert('❌ Erreur: ' + result.message);
        }
    } catch (error) {
        console.error('Erreur lors de l\'envoi:', error);
        alert('❌ Erreur: ' + error.message);
    }
})();
        ";
        
        return $js;
    }

    /**
     * Génère le HTML nécessaire pour intégrer l'envoi côté client
     * 
     * @param string $requestId ID de la requête
     * @param array $options Options d'affichage
     * @return string HTML complet
     */
    public function generateClientEmailHTML($requestId, $options = []) {
        $data = $_SESSION['pending_emails'][$requestId] ?? null;
        if (!$data) {
            return '<div class="error">Requête d\'email non trouvée</div>';
        }
        
        $emailData = $data['emailData'];
        $attachmentInfo = $data['attachmentInfo'];
        $hasAttachments = !empty($attachmentInfo);
        
        // ID unique pour les éléments
        $containerId = 'email-client-container-' . $requestId;
        $formId = 'email-client-form-' . $requestId;
        $attachmentInputId = 'attachments-' . $requestId;
        
        $html = "
<div id='$containerId' class='email-client-sender'>
    <div class='email-preview'>
        <h3>📧 Email prêt à envoyer</h3>
        <div class='email-details'>
            <p><strong>À:</strong> {$emailData['to']}</p>
            <p><strong>Sujet:</strong> {$emailData['subject']}</p>
            <p><strong>De:</strong> {$emailData['from']}</p>";
        
        if (isset($emailData['cc'])) {
            $html .= "\n            <p><strong>CC:</strong> {$emailData['cc']}</p>";
        }
        
        if (isset($emailData['bcc'])) {
            $html .= "\n            <p><strong>BCC:</strong> {$emailData['bcc']}</p>";
        }
        
        $html .= "
        </div>
        
        <div class='email-message'>
            <h4>Message:</h4>
            <div class='message-preview'>" . nl2br(htmlspecialchars($emailData['message'])) . "</div>
        </div>";
        
        if ($hasAttachments) {
            $html .= "
        <div class='attachment-section'>
            <h4>Pièces jointes:</h4>
            <ul>";
            
            foreach ($attachmentInfo as $attachment) {
                $sizeKB = round($attachment['size'] / 1024, 1);
                $html .= "\n                <li>📎 {$attachment['name']} ({$sizeKB} KB)</li>";
            }
            
            $html .= "
            </ul>
            <p class='attachment-note'>
                <strong>Important:</strong> Pour inclure les pièces jointes, sélectionnez-les à nouveau ci-dessous:
            </p>
            <input type='file' id='$attachmentInputId' multiple accept='*/*' class='attachment-input'>
        </div>";
        }
        
        $html .= "
    </div>
    
    <div class='email-actions'>
        <button type='button' onclick='sendEmailNow(\"$requestId\", \"$attachmentInputId\")' class='btn btn-primary'>
            📤 Ouvrir dans mon client de messagerie
        </button>
        <button type='button' onclick='checkCompatibility()' class='btn btn-secondary'>
            🔍 Vérifier la compatibilité
        </button>
    </div>
    
    <div id='compatibility-info' class='compatibility-info' style='display: none;'>
        <!-- Informations de compatibilité seront ajoutées ici -->
    </div>
</div>

<script>
// Fonction pour envoyer l'email
async function sendEmailNow(requestId, attachmentInputId) {
    const button = event.target;
    button.disabled = true;
    button.textContent = '⏳ Préparation...';
    
    try {";
        
        $html .= $this->generateClientEmailJS($requestId, $hasAttachments ? $attachmentInputId : null);
        
        $html .= "
    } finally {
        button.disabled = false;
        button.textContent = '📤 Ouvrir dans mon client de messagerie';
    }
}

// Fonction pour vérifier la compatibilité
function checkCompatibility() {
    const compatibility = window.checkEmailClientCompatibility();
    const infoDiv = document.getElementById('compatibility-info');
    
    let html = '<h4>🔧 Compatibilité du navigateur</h4>';
    html += '<ul>';
    html += '<li><strong>File System Access API:</strong> ' + (compatibility.fileSystemAccess ? '✅ Supporté' : '❌ Non supporté') + '</li>';
    html += '<li><strong>Protocole mailto:</strong> ' + (compatibility.mailto ? '✅ Supporté' : '❌ Non supporté') + '</li>';
    html += '</ul>';
    html += '<p><strong>Recommandation:</strong> ' + compatibility.recommendation + '</p>';
    
    if (!compatibility.fileSystemAccess) {
        html += '<div class=\"warning\">⚠️ Pour une meilleure expérience avec les pièces jointes, utilisez Chrome, Edge ou un autre navigateur compatible avec File System Access API.</div>';
    }
    
    infoDiv.innerHTML = html;
    infoDiv.style.display = 'block';
}
</script>

<style>
.email-client-sender {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    background: #f9f9f9;
}

.email-preview {
    background: white;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.email-details p {
    margin: 5px 0;
}

.message-preview {
    background: #f8f8f8;
    padding: 10px;
    border-radius: 3px;
    max-height: 150px;
    overflow-y: auto;
}

.attachment-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.attachment-input {
    width: 100%;
    padding: 8px;
    margin-top: 10px;
}

.attachment-note {
    color: #666;
    font-size: 0.9em;
    margin: 10px 0;
}

.email-actions {
    text-align: center;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin: 0 5px;
    font-size: 16px;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    opacity: 0.8;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.compatibility-info {
    margin-top: 15px;
    padding: 15px;
    background: white;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.warning {
    background: #fff3cd;
    color: #856404;
    padding: 10px;
    border-radius: 3px;
    margin-top: 10px;
}
</style>";
        
        return $html;
    }

    /**
     * Génère une page complète pour l'envoi d'email via le client de messagerie
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $message Corps du message
     * @param array $options Options supplémentaires
     * @return string HTML de la page complète
     */
    public function generateEmailClientPage($to, $subject, $message, $options = []) {
        // Préparer l'envoi
        $result = $this->envoyerReellement($to, $subject, $message, array_merge($options, ['sendMode' => 'client']));
        
        if (!$result['success']) {
            return "<div class='error'>Erreur lors de la préparation de l'email: " . $result['message'] . "</div>";
        }
        
        $requestId = $result['requestId'];
        
        $html = "
<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Envoi d'email - Client de messagerie</title>
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
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #bee5eb;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📧 Envoi d'email via votre client de messagerie</h1>
        
        <div class='info'>
            <strong>ℹ️ Comment ça marche :</strong><br>
            Votre email sera préparé et ouvert dans votre client de messagerie par défaut (Outlook, Thunderbird, Mail, etc.). 
            Si vous avez des pièces jointes, elles seront incluses automatiquement.
        </div>
        
        " . $this->generateClientEmailHTML($requestId) . "
        
        <div style='margin-top: 30px; text-align: center; color: #666; font-size: 0.9em;'>
            <p>Powered by Centre La Grange - EmailService</p>
        </div>
    </div>
    
    <!-- Script EmailClientSender à inclure -->
    <script src='EmailClientSender.js'></script>
</body>
</html>";
        
        return $html;
    }

    /**
     * Envoie un email en utilisant la fonction mail() de PHP
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $message Corps du message
     * @param string $from Expéditeur
     * @param string $replyTo Adresse de réponse
     * @param array $options Options supplémentaires
     * @return bool Succès de l'envoi
     */
    private function envoyerDirect($to, $subject, $message, $from, $replyTo, $options = []) {
        // NOUVEAU: Vérifier le bypass de capture
        $bypassCapture = $options['bypassCapture'] ?? false;
        
        // MODIFICATION: En mode développement avec bypass, forcer l'envoi direct
        $shouldSendDirect = !$this->isDevelopment || ($this->isDevelopment && $bypassCapture);
        
        if (!$shouldSendDirect) {
            // Mode développement normal - capturer l'email
            $email = [
                'to' => $to,
                'subject' => $subject,
                'message' => $message,
                'headers' => 'Sent via mail() function',
                'from' => $from,
                'replyTo' => $replyTo,
                'timestamp' => date('Y-m-d H:i:s'),
                'options' => $options
            ];
            
            return $this->captureEmail($email);
        }
        
        // Envoi direct (production ou développement avec bypass)
        if (isset($options['attachments']) && !empty($options['attachments'])) {
            // Utiliser la même logique que dans envoyerAvecPieceJointe
            $boundary = md5(time());
            
            $headers = "From: $from\r\n";
            $headers .= "Reply-To: $replyTo\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $message . "\r\n\r\n";
            
            foreach ($options['attachments'] as $attachment) {
                if (empty($attachment['path']) || !file_exists($attachment['path'])) {
                    continue;
                }
                
                $filename = $attachment['name'] ?? basename($attachment['path']);
                $filetype = $attachment['type'] ?? 'application/octet-stream';
                
                $fileContent = file_get_contents($attachment['path']);
                $fileContentEncoded = chunk_split(base64_encode($fileContent));
                
                $body .= "--$boundary\r\n";
                $body .= "Content-Type: $filetype; name=\"$filename\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
                $body .= $fileContentEncoded . "\r\n";
            }
            
            $body .= "--$boundary--\r\n";
            
            $sent = mail($to, $subject, $body, $headers);
        } else {
            // Email sans pièce jointe
            $headers = 'From: ' . $from . "\r\n" .
                    'Reply-To: ' . $replyTo . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();
            
            $sent = mail($to, $subject, $message, $headers);
        }
        
        // MODIFICATION: Log adapté selon le mode
        if ($sent) {
            $logMessage = "Email envoyé avec succès à $to en mode direct";
            if ($this->isDevelopment && $bypassCapture) {
                $logMessage .= " (BYPASS ACTIVÉ)";
            }
            error_log($logMessage);
        } else {
            $logMessage = "Échec d'envoi d'email à $to en mode direct";
            if ($this->isDevelopment && $bypassCapture) {
                $logMessage .= " (BYPASS ACTIVÉ)";
            }
            error_log($logMessage);
        }
        
        return $sent;
    }

    /**
     * Génère un lien mailto: pour ouvrir un client de messagerie avec l'email prérempli
     * 
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $message Corps du message
     * @param string $from Expéditeur
     * @param string $replyTo Adresse de réponse
     * @param array $options Options supplémentaires
     * @return array Informations sur le lien mailto généré
     */
    private function envoyerViaClientEmail($to, $subject, $message, $from, $replyTo, $options = []) {
        // Encoder les paramètres pour l'URL
        $encodedSubject = rawurlencode($subject);
        $encodedBody = rawurlencode($message);
        $encodedCc = isset($options['cc']) ? rawurlencode($options['cc']) : '';
        $encodedBcc = isset($options['bcc']) ? rawurlencode($options['bcc']) : '';
        
        // Construire l'URL mailto:
        $mailtoUrl = "mailto:$to?subject=$encodedSubject&body=$encodedBody";
        
        if (!empty($encodedCc)) {
            $mailtoUrl .= "&cc=$encodedCc";
        }
        
        if (!empty($encodedBcc)) {
            $mailtoUrl .= "&bcc=$encodedBcc";
        }
        
        // Note: les pièces jointes ne sont pas supportées par le protocole mailto:
        if (isset($options['attachments']) && !empty($options['attachments'])) {
            error_log("ATTENTION: Le protocole mailto: ne supporte pas les pièces jointes. Utilisez le mode d'envoi 'direct' pour les emails avec pièces jointes.");
        }
        
        // Comme nous ne pouvons pas réellement "envoyer" via mailto:, nous retournons les informations nécessaires
        return [
            'success' => true,
            'message' => 'Lien mailto: généré avec succès',
            'mailtoUrl' => $mailtoUrl,
            'info' => 'Le client doit ouvrir ce lien pour compléter l\'envoi de l\'email'
        ];
    }

    /**
     * Getter pour l'expéditeur par défaut
     * @return string Email d'expédition par défaut
     */
    public function getDefaultSender() {
        return $this->defaultSender;
    }

    /**
     * Getter pour l'email de réponse par défaut
     * @return string Email de réponse par défaut
     */
    public function getDefaultReplyTo() {
        return $this->defaultReplyTo;
    }

    /**
     * Getter pour le mode d'envoi par défaut
     * @return string Mode d'envoi par défaut ('direct' ou 'client')
     */
    public function getDefaultSendMode() {
        return $this->defaultSendMode;
    }

    /**
     * Vérifie si MailHog est accessible
     * 
     * @return bool True si MailHog est accessible, false sinon
     */
    public function isMailHogRunning() {
        $host = 'localhost';
        $port = 1025; // Port SMTP de MailHog
        $timeout = 2; // Timeout en secondes
        
        // Tenter d'établir une connexion socket à MailHog
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        
        return false;
    }
}
?>