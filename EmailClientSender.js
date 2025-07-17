/**
 * EmailClientSender.js - Version complète avec système de préférence utilisateur
 * 
 * Gestionnaire pour l'envoi d'emails via le client de messagerie de l'utilisateur
 * Utilise le File System Access API pour créer des fichiers .eml
 * Optimisé pour Thunderbird avec compatibilité universelle + préférences utilisateur
 */

/**
 * Système de préférence utilisateur pour le client de messagerie
 */
class EmailClientPreference {
    constructor() {
        this.storageKey = 'email-client-preference';
        this.detectedKey = 'email-client-detected';
    }
    
    /**
     * Sauvegarde la préférence utilisateur
     */
    savePreference(clientType) {
        try {
            localStorage.setItem(this.storageKey, clientType);
            localStorage.setItem(this.detectedKey + '-override', 'true');
            localStorage.setItem(this.detectedKey + '-timestamp', Date.now().toString());
            console.log('✅ Préférence email client sauvegardée:', clientType);
        } catch (e) {
            console.warn('⚠️ Impossible de sauvegarder la préférence:', e);
        }
    }
    
    /**
     * Récupère la préférence utilisateur
     */
    getPreference() {
        try {
            return localStorage.getItem(this.storageKey);
        } catch (e) {
            return null;
        }
    }
    
    /**
     * Vérifie si l'utilisateur a défini une préférence
     */
    hasUserPreference() {
        try {
            const hasOverride = localStorage.getItem(this.detectedKey + '-override') === 'true';
            const preference = localStorage.getItem(this.storageKey);
            return hasOverride && preference;
        } catch (e) {
            return false;
        }
    }
    
    /**
     * Efface la préférence utilisateur
     */
    clearPreference() {
        try {
            localStorage.removeItem(this.storageKey);
            localStorage.removeItem(this.detectedKey + '-override');
            localStorage.removeItem(this.detectedKey + '-timestamp');
            console.log('🗑️ Préférence email client effacée');
        } catch (e) {
            console.warn('⚠️ Impossible d\'effacer la préférence:', e);
        }
    }
    
    /**
     * Affiche un sélecteur pour que l'utilisateur choisisse son client
     */
    showClientSelector(currentDetection, onSelectionCallback) {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: flex; align-items: center;
            justify-content: center; z-index: 10000;
        `;
        
        modal.innerHTML = `
            <div style="
                background: white; padding: 30px; border-radius: 12px; max-width: 500px;
                text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            ">
                <h2 style="color: #333; margin-bottom: 20px;">
                    🎯 Quel est votre client de messagerie principal ?
                </h2>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #666;">
                        <strong>Détection automatique :</strong> ${this.getClientDisplayName(currentDetection.primary)}<br>
                        <small>Cette détection semble incorrecte ? Choisissez votre client ci-dessous.</small>
                    </p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                    <button data-client="thunderbird" style="
                        padding: 15px; border: 2px solid #0066cc; border-radius: 8px;
                        background: ${currentDetection.primary === 'thunderbird' ? '#0066cc' : 'white'};
                        color: ${currentDetection.primary === 'thunderbird' ? 'white' : '#0066cc'};
                        cursor: pointer; font-weight: bold; transition: all 0.3s ease;
                    ">
                        🦅 Thunderbird
                    </button>
                    
                    <button data-client="outlook" style="
                        padding: 15px; border: 2px solid #0078d4; border-radius: 8px;
                        background: ${currentDetection.primary === 'outlook' ? '#0078d4' : 'white'};
                        color: ${currentDetection.primary === 'outlook' ? 'white' : '#0078d4'};
                        cursor: pointer; font-weight: bold; transition: all 0.3s ease;
                    ">
                        📧 Outlook
                    </button>
                    
                    <button data-client="apple-mail" style="
                        padding: 15px; border: 2px solid #007bff; border-radius: 8px;
                        background: ${currentDetection.primary === 'apple-mail' ? '#007bff' : 'white'};
                        color: ${currentDetection.primary === 'apple-mail' ? 'white' : '#007bff'};
                        cursor: pointer; font-weight: bold; transition: all 0.3s ease;
                    ">
                        📮 Apple Mail
                    </button>
                    
                    <button data-client="other" style="
                        padding: 15px; border: 2px solid #6c757d; border-radius: 8px;
                        background: white; color: #6c757d; cursor: pointer; font-weight: bold;
                        transition: all 0.3s ease;
                    ">
                        📬 Autre
                    </button>
                </div>
                
                <div style="display: flex; justify-content: center; gap: 10px;">
                    <button id="cancelSelection" style="
                        background: #6c757d; color: white; border: none;
                        padding: 10px 20px; border-radius: 5px; cursor: pointer;
                    ">
                        Ignorer
                    </button>
                    <button id="resetDetection" style="
                        background: #ffc107; color: #212529; border: none;
                        padding: 10px 20px; border-radius: 5px; cursor: pointer;
                    ">
                        Effacer préférence
                    </button>
                </div>
                
                <p style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    Cette préférence sera sauvegardée pour les prochaines fois.
                </p>
            </div>
        `;
        
        let selectedClient = null;
        
        // Gestionnaires d'événements
        const clientButtons = modal.querySelectorAll('button[data-client]');
        clientButtons.forEach(button => {
            button.addEventListener('click', () => {
                selectedClient = button.getAttribute('data-client');
                
                // Mettre à jour visuellement la sélection
                clientButtons.forEach(btn => {
                    const isSelected = btn.getAttribute('data-client') === selectedClient;
                    const borderColor = btn.style.borderColor;
                    
                    if (isSelected) {
                        btn.style.background = borderColor;
                        btn.style.color = 'white';
                        btn.style.transform = 'scale(1.05)';
                    } else {
                        btn.style.background = 'white';
                        btn.style.color = borderColor;
                        btn.style.transform = 'scale(1)';
                    }
                });
                
                // Sauvegarder et fermer après un délai
                setTimeout(() => {
                    this.savePreference(selectedClient);
                    modal.remove();
                    
                    if (onSelectionCallback) {
                        onSelectionCallback(selectedClient);
                    }
                }, 500);
            });
        });
        
        modal.querySelector('#cancelSelection').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('#resetDetection').addEventListener('click', () => {
            this.clearPreference();
            modal.remove();
            if (onSelectionCallback) {
                onSelectionCallback('reset');
            }
        });
        
        document.body.appendChild(modal);
    }
    
    /**
     * Obtient le nom d'affichage du client
     */
    getClientDisplayName(client) {
        const names = {
            'thunderbird': 'Thunderbird',
            'outlook': 'Outlook',
            'apple-mail': 'Apple Mail',
            'evolution': 'Evolution',
            'other': 'Autre',
            'unknown': 'Inconnu'
        };
        return names[client] || client;
    }
}

class EmailClientSender {
    constructor() {
        this.isFileSystemAccessSupported = 'showSaveFilePicker' in window;
        this.preference = new EmailClientPreference();
        this.detectedEmailClient = this.detectEmailClientWithPreference();
        console.log('File System Access API supporté:', this.isFileSystemAccessSupported);
        console.log('Client de messagerie détecté:', this.detectedEmailClient);
    }

    /**
     * Vérifie si le File System Access API est supporté
     * @returns {boolean}
     */
    isSupported() {
        return this.isFileSystemAccessSupported;
    }

    /**
     * Détection avec prise en compte des préférences utilisateur
     */
    detectEmailClientWithPreference() {
        // 1. Vérifier si l'utilisateur a une préférence sauvegardée
        if (this.preference.hasUserPreference()) {
            const userPreference = this.preference.getPreference();
            if (userPreference && userPreference !== 'other') {
                console.log('🎯 Utilisation de la préférence utilisateur:', userPreference);
                return {
                    primary: userPreference,
                    secondary: this.getSecondaryClient(userPreference),
                    os: this.detectOS(),
                    source: 'user-preference',
                    confidence: 'user-defined'
                };
            }
        }
        
        // 2. Sinon, utiliser la détection automatique améliorée
        const autoDetected = this.detectEmailClientAdvanced();
        autoDetected.source = 'auto-detection';
        
        // 3. Si la confiance est faible, proposer à l'utilisateur de choisir
        if (autoDetected.confidence === 'low' || autoDetected.confidence === 'very-low') {
            setTimeout(() => {
                this.preference.showClientSelector(autoDetected, (selectedClient) => {
                    if (selectedClient === 'reset') {
                        // Recharger la détection après reset
                        this.detectedEmailClient = this.detectEmailClientWithPreference();
                        console.log('🔄 Détection réinitialisée:', this.detectedEmailClient);
                    } else if (selectedClient) {
                        // Recharger la détection après sélection
                        this.detectedEmailClient = this.detectEmailClientWithPreference();
                        console.log('🔄 Client mis à jour:', this.detectedEmailClient);
                        
                        // Rafraîchir l'interface si nécessaire
                        this.updateInterfaceForNewClient();
                    }
                });
            }, 2000); // Attendre 2 secondes après le chargement
        }
        
        return autoDetected;
    }
    
    /**
     * Détection avancée du client de messagerie
     */
    detectEmailClientAdvanced() {
        const userAgent = navigator.userAgent.toLowerCase();
        const platform = navigator.platform.toLowerCase();
        
        // Vérifier les indices spécifiques
        const thunderbirdIndicators = this.checkForThunderbirdIndicators();
        const outlookIndicators = this.checkForOutlookIndicators();
        
        console.log('🔍 Détection avancée:', {
            platform,
            thunderbirdIndicators,
            outlookIndicators
        });
        
        // Logique améliorée par OS
        if (platform.includes('win')) {
            if (thunderbirdIndicators.score > outlookIndicators.score) {
                return {
                    primary: 'thunderbird',
                    secondary: 'outlook',
                    os: 'windows',
                    confidence: thunderbirdIndicators.score > 2 ? 'high' : 'medium',
                    indicators: thunderbirdIndicators
                };
            } else if (outlookIndicators.score > 0) {
                return {
                    primary: 'outlook',
                    secondary: 'thunderbird',
                    os: 'windows',
                    confidence: outlookIndicators.score > 2 ? 'high' : 'medium',
                    indicators: outlookIndicators
                };
            } else {
                // ✅ Prioriser Thunderbird par défaut sur Windows
                return {
                    primary: 'thunderbird',
                    secondary: 'outlook',
                    os: 'windows',
                    confidence: 'low',
                    reason: 'default-thunderbird-priority'
                };
            }
        }
        
        if (platform.includes('mac')) {
            return {
                primary: 'apple-mail',
                secondary: 'thunderbird',
                os: 'mac',
                confidence: 'high'
            };
        }
        
        if (platform.includes('linux')) {
            return {
                primary: 'thunderbird',
                secondary: 'evolution',
                os: 'linux',
                confidence: 'high'
            };
        }
        
        return {
            primary: 'thunderbird',
            secondary: 'unknown',
            os: 'unknown',
            confidence: 'very-low'
        };
    }
    
    /**
     * Vérifie les indicateurs spécifiques à Thunderbird
     */
    checkForThunderbirdIndicators() {
        const indicators = {
            localStorage: false,
            userAgent: false,
            fileAssociations: false,
            score: 0
        };
        
        try {
            // Vérifier les préférences dans localStorage
            const keys = Object.keys(localStorage);
            if (keys.some(key => key.includes('thunderbird') || key.includes('mozilla-mail'))) {
                indicators.localStorage = true;
                indicators.score += 2;
            }
        } catch (e) {
            // Accès localStorage bloqué
        }
        
        // Vérifier l'user agent
        const ua = navigator.userAgent.toLowerCase();
        if (ua.includes('thunderbird') || (ua.includes('mozilla') && ua.includes('mail'))) {
            indicators.userAgent = true;
            indicators.score += 3;
        }
        
        return indicators;
    }
    
    /**
     * Vérifie les indicateurs spécifiques à Outlook
     */
    checkForOutlookIndicators() {
        const indicators = {
            localStorage: false,
            cookies: false,
            userAgent: false,
            score: 0
        };
        
        try {
            // Vérifier localStorage pour Office 365
            const keys = Object.keys(localStorage);
            if (keys.some(key => key.includes('outlook') || key.includes('office365'))) {
                indicators.localStorage = true;
                indicators.score += 2;
            }
        } catch (e) {
            // Accès bloqué
        }
        
        // Vérifier les cookies Office 365
        if (document.cookie.includes('office365') || 
            document.cookie.includes('outlook') ||
            document.cookie.includes('microsoftonline')) {
            indicators.cookies = true;
            indicators.score += 1;
        }
        
        return indicators;
    }
    
    /**
     * Détecte uniquement l'OS
     */
    detectOS() {
        const platform = navigator.platform.toLowerCase();
        if (platform.includes('win')) return 'windows';
        if (platform.includes('mac')) return 'mac';
        if (platform.includes('linux')) return 'linux';
        return 'unknown';
    }
    
    /**
     * Obtient le client secondaire selon le client principal
     */
    getSecondaryClient(primary) {
        const secondaries = {
            'thunderbird': 'outlook',
            'outlook': 'thunderbird',
            'apple-mail': 'thunderbird',
            'evolution': 'thunderbird'
        };
        return secondaries[primary] || 'thunderbird';
    }
    
    /**
     * Met à jour l'interface quand le client change
     */
    updateInterfaceForNewClient() {
        // Déclencher un événement personnalisé pour notifier le changement
        const event = new CustomEvent('emailClientChanged', {
            detail: {
                newClient: this.detectedEmailClient,
                timestamp: Date.now()
            }
        });
        window.dispatchEvent(event);
        
        console.log('🔄 Interface mise à jour pour nouveau client:', this.detectedEmailClient.primary);
    }

    /**
     * Génère un fichier .eml avec headers adaptatifs selon le client détecté
     * @param {Object} emailData - Données de l'email
     * @returns {string} - Contenu du fichier .eml
     */
    generateUniversalEmlContent(emailData) {
        const boundary = 'boundary_' + Math.random().toString(36).substr(2, 16);
        const date = new Date().toUTCString();
        
        let emlContent = '';
        
        // Headers de base (universels)
        emlContent += `MIME-Version: 1.0\r\n`;
        emlContent += `Date: ${date}\r\n`;
        emlContent += `From: ${emailData.from}\r\n`;
        emlContent += `To: ${emailData.to}\r\n`;
        emlContent += `Subject: ${emailData.subject}\r\n`;
        emlContent += `Message-ID: <${Date.now()}.${Math.random().toString(36).substr(2, 9)}@lagrange.ch>\r\n`;
        
        // Headers de brouillon universels
        emlContent += `X-Unsent: 1\r\n`;
        emlContent += `X-Priority: 3\r\n`;
        emlContent += `Status: \r\n`;
        
        // ✅ HEADERS SPÉCIFIQUES SELON LE CLIENT DÉTECTÉ
        switch (this.detectedEmailClient.primary) {
            case 'thunderbird':
                // Headers Mozilla/Thunderbird
                emlContent += `X-Mozilla-Status: 0000\r\n`;
                emlContent += `X-Mozilla-Status2: 00000000\r\n`;
                emlContent += `X-Mozilla-Draft-Info: internal/draft; vcard=0; receipt=0; DSN=0; uuencode=0; attachmentreminder=0; deliveryformat=4\r\n`;
                emlContent += `X-Mozilla-News-Host: \r\n`;
                emlContent += `X-Identity-Key: \r\n`;
                emlContent += `X-Account-Key: \r\n`;
                break;
                
            case 'outlook':
                // Headers Outlook/Exchange
                emlContent += `X-MS-Exchange-Organization-SCL: -1\r\n`;
                emlContent += `X-Outlook-Draft: true\r\n`;
                emlContent += `Importance: Normal\r\n`;
                break;
                
            case 'apple-mail':
                // Headers Apple Mail
                emlContent += `X-Mailer: Apple Mail\r\n`;
                break;
                
            default:
                // Headers universels pour clients inconnus
                emlContent += `X-Draft: true\r\n`;
                break;
        }
        
        // ✅ TOUJOURS AJOUTER LES HEADERS MOZILLA COMME FALLBACK
        // (même si ce n'est pas Thunderbird, ça ne fait pas de mal)
        if (this.detectedEmailClient.primary !== 'thunderbird') {
            emlContent += `X-Mozilla-Draft-Info: internal/draft; vcard=0; receipt=0; DSN=0; uuencode=0\r\n`;
        }
        
        if (emailData.cc) {
            emlContent += `Cc: ${emailData.cc}\r\n`;
        }
        
        if (emailData.bcc) {
            emlContent += `Bcc: ${emailData.bcc}\r\n`;
        }
        
        if (emailData.replyTo) {
            emlContent += `Reply-To: ${emailData.replyTo}\r\n`;
        }
        
        // Corps du message (identique pour tous)
        if (!emailData.attachments || emailData.attachments.length === 0) {
            emlContent += `Content-Type: text/plain; charset=UTF-8\r\n`;
            emlContent += `Content-Transfer-Encoding: 8bit\r\n`;
            emlContent += `\r\n`;
            emlContent += emailData.message;
            return emlContent;
        }
        
        // Avec pièces jointes
        emlContent += `Content-Type: multipart/mixed; boundary="${boundary}"\r\n`;
        emlContent += `\r\n`;
        emlContent += `This is a multi-part message in MIME format.\r\n`;
        emlContent += `\r\n`;
        
        // Corps du message
        emlContent += `--${boundary}\r\n`;
        emlContent += `Content-Type: text/plain; charset=UTF-8\r\n`;
        emlContent += `Content-Transfer-Encoding: 8bit\r\n`;
        emlContent += `\r\n`;
        emlContent += emailData.message + '\r\n';
        
        // Pièces jointes
        for (const attachment of emailData.attachments) {
            emlContent += `\r\n--${boundary}\r\n`;
            emlContent += `Content-Type: ${attachment.mimeType}; name="${attachment.name}"\r\n`;
            emlContent += `Content-Transfer-Encoding: base64\r\n`;
            emlContent += `Content-Disposition: attachment; filename="${attachment.name}"\r\n`;
            emlContent += `\r\n`;
            emlContent += attachment.base64Content + '\r\n';
        }
        
        emlContent += `\r\n--${boundary}--\r\n`;
        return emlContent;
    }

    /**
     * Génère un nom de fichier adapté au client détecté
     */
    generateFileName(emailData) {
        const cleanSubject = emailData.subject.replace(/[<>:"/\\|?*]/g, '_').substring(0, 40);
        const timestamp = new Date().toISOString().slice(0, 16).replace(/[:-]/g, '');
        
        let prefix = 'Brouillon';
        
        switch (this.detectedEmailClient.primary) {
            case 'thunderbird':
                prefix = 'TB_Brouillon';
                break;
            case 'outlook':
                prefix = 'OL_Brouillon';
                break;
            case 'apple-mail':
                prefix = 'Mail_Brouillon';
                break;
            default:
                prefix = 'Email_Brouillon';
                break;
        }
        
        return `${prefix}_${cleanSubject}_${timestamp}.eml`;
    }

    /**
     * Convertit un fichier en base64
     * @param {File} file - Fichier à convertir
     * @returns {Promise<string>} - Contenu base64
     */
    async fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                // Supprimer le préfixe "data:...;base64,"
                const base64 = reader.result.split(',')[1];
                // Diviser en lignes de 76 caractères comme requis par RFC 2045
                const chunked = base64.match(/.{1,76}/g).join('\r\n');
                resolve(chunked);
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    /**
     * Détermine le type MIME d'un fichier
     * @param {File} file - Fichier
     * @returns {string} - Type MIME
     */
    getMimeType(file) {
        if (file.type) {
            return file.type;
        }
        
        // Fallback basé sur l'extension si le type n'est pas disponible
        const extension = file.name.split('.').pop().toLowerCase();
        const mimeTypes = {
            'pdf': 'application/pdf',
            'doc': 'application/msword',
            'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls': 'application/vnd.ms-excel',
            'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg': 'image/jpeg',
            'jpeg': 'image/jpeg',
            'png': 'image/png',
            'gif': 'image/gif',
            'txt': 'text/plain',
            'csv': 'text/csv',
            'zip': 'application/zip'
        };
        
        return mimeTypes[extension] || 'application/octet-stream';
    }

    /**
     * Prépare les pièces jointes pour l'email
     * @param {FileList|Array} attachments - Liste des fichiers
     * @returns {Promise<Array>} - Pièces jointes préparées
     */
    async prepareAttachments(attachments) {
        if (!attachments || attachments.length === 0) {
            return [];
        }
        
        const preparedAttachments = [];
        
        for (const file of attachments) {
            try {
                const base64Content = await this.fileToBase64(file);
                const mimeType = this.getMimeType(file);
                
                preparedAttachments.push({
                    name: file.name,
                    mimeType: mimeType,
                    base64Content: base64Content,
                    size: file.size
                });
                
                console.log(`Pièce jointe préparée: ${file.name} (${mimeType})`);
            } catch (error) {
                console.error(`Erreur lors de la préparation de ${file.name}:`, error);
                throw new Error(`Impossible de traiter la pièce jointe ${file.name}`);
            }
        }
        
        return preparedAttachments;
    }

    /**
     * Crée et télécharge un fichier .eml via le File System Access API
     * @param {Object} emailData - Données de l'email
     * @param {FileList|Array} attachments - Pièces jointes (optionnel)
     * @returns {Promise<boolean>} - Succès de l'opération
     */
    async createAndDownloadEml(emailData, attachments = null) {
        if (!this.isSupported()) {
            throw new Error('File System Access API non supporté par ce navigateur');
        }

        try {
            // Préparer les pièces jointes si nécessaire
            let preparedAttachments = [];
            if (attachments && attachments.length > 0) {
                console.log('Préparation des pièces jointes...');
                preparedAttachments = await this.prepareAttachments(attachments);
            }

            // Créer les données complètes de l'email
            const completeEmailData = {
                ...emailData,
                attachments: preparedAttachments
            };

            // Générer le contenu .eml adaptatif
            const emlContent = this.generateUniversalEmlContent(completeEmailData);
            
            // Nom de fichier adaptatif
            const fileName = this.generateFileName(emailData);
            
            // Options pour le sélecteur de fichier avec nom suggéré
            const filePickerOptions = {
                types: [{
                    description: 'Fichier Email Brouillon (.eml)',
                    accept: {
                        'message/rfc822': ['.eml']
                    }
                }],
                suggestedName: fileName,
                excludeAcceptAllOption: false
            };
            
            // Ouvrir le sélecteur de fichier
            const fileHandle = await window.showSaveFilePicker(filePickerOptions);
            
            // Créer un flux d'écriture
            const writable = await fileHandle.createWritable();
            
            // Écrire le contenu
            await writable.write(emlContent);
            await writable.close();
            
            console.log(`✅ Fichier .eml créé avec succès: ${fileName}`);
            
            // Afficher des instructions adaptées au client détecté
            this.showAdaptiveInstructions(fileName);
            
            return true;
            
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Utilisateur a annulé la sauvegarde');
                return false;
            }
            
            console.error('Erreur lors de la création du fichier .eml:', error);
            throw error;
        }
    }

    /**
     * ✅ INSTRUCTIONS ADAPTÉES SELON LE CLIENT DÉTECTÉ
     */
    showAdaptiveInstructions(fileName) {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: flex; align-items: center;
            justify-content: center; z-index: 10000;
        `;
        
        // Contenu adapté au client principal détecté
        let primaryInstructions = '';
        let primaryColor = '#007bff';
        let primaryIcon = '📧';
        
        switch (this.detectedEmailClient.primary) {
            case 'thunderbird':
                primaryColor = '#0066cc';
                primaryIcon = '🦅';
                primaryInstructions = `
                    <h4 style="color: ${primaryColor}; margin-bottom: 8px;">${primaryIcon} Thunderbird (recommandé pour vous)</h4>
                    <ol style="margin: 0; padding-left: 20px; line-height: 1.6;">
                        <li><strong>Double-cliquez</strong> sur le fichier</li>
                        <li><strong>Clic droit</strong> → "Modifier en tant que nouveau message"</li>
                        <li>Ou menu <strong>Message → Modifier en tant que nouveau</strong></li>
                        <li>Cliquez sur <strong>"Envoyer"</strong> 📤</li>
                    </ol>
                `;
                break;
                
            case 'outlook':
                primaryColor = '#0078d4';
                primaryIcon = '📧';
                primaryInstructions = `
                    <h4 style="color: ${primaryColor}; margin-bottom: 8px;">${primaryIcon} Outlook (recommandé pour vous)</h4>
                    <ol style="margin: 0; padding-left: 20px; line-height: 1.6;">
                        <li><strong>Double-cliquez</strong> sur le fichier</li>
                        <li>Si il s'ouvre en lecture : <strong>Clic droit → "Modifier le message"</strong></li>
                        <li>Ou utilisez <strong>Ctrl+E</strong> pour passer en mode édition</li>
                        <li>Cliquez sur <strong>"Envoyer"</strong> 📤</li>
                    </ol>
                `;
                break;
                
            case 'apple-mail':
                primaryColor = '#007bff';
                primaryIcon = '📮';
                primaryInstructions = `
                    <h4 style="color: ${primaryColor}; margin-bottom: 8px;">${primaryIcon} Apple Mail (recommandé pour vous)</h4>
                    <ol style="margin: 0; padding-left: 20px; line-height: 1.6;">
                        <li><strong>Double-cliquez</strong> sur le fichier</li>
                        <li>Cliquez sur <strong>"Répondre"</strong> ou <strong>"Transférer"</strong></li>
                        <li>Modifiez les destinataires si nécessaire</li>
                        <li>Cliquez sur <strong>"Envoyer"</strong> 📤</li>
                    </ol>
                `;
                break;
                
            default:
                primaryInstructions = `
                    <h4 style="color: ${primaryColor}; margin-bottom: 8px;">${primaryIcon} Instructions générales</h4>
                    <ol style="margin: 0; padding-left: 20px; line-height: 1.6;">
                        <li><strong>Double-cliquez</strong> sur le fichier</li>
                        <li>Le fichier s'ouvre dans votre client de messagerie</li>
                        <li>Cherchez une option <strong>"Modifier"</strong> ou <strong>"Éditer"</strong></li>
                        <li>Cliquez sur <strong>"Envoyer"</strong> 📤</li>
                    </ol>
                `;
                break;
        }
        
        modal.innerHTML = `
            <div style="
                background: white; padding: 30px; border-radius: 10px; max-width: 650px;
                text-align: left; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                max-height: 85vh; overflow-y: auto;
            ">
                <div style="text-align: center; margin-bottom: 25px;">
                    <h2 style="color: ${primaryColor}; margin-bottom: 10px;">
                        ✅ Brouillon créé avec succès !
                    </h2>
                    <div style="background: #e8f4fd; padding: 10px; border-radius: 5px;">
                        <strong>📄 Fichier :</strong> <code style="font-size: 0.9em;">${fileName}</code>
                    </div>
                </div>
                
                <div style="background: #f0f8ff; border-left: 4px solid ${primaryColor}; padding: 15px; margin-bottom: 20px;">
                    ${primaryInstructions}
                </div>
                
                <details style="margin-bottom: 20px;">
                    <summary style="cursor: pointer; font-weight: bold; color: #666; padding: 10px 0;">
                        🔧 Instructions pour autres clients de messagerie
                    </summary>
                    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        
                        <div style="margin-bottom: 15px;">
                            <h4 style="color: #0066cc; margin-bottom: 8px;">🦅 Thunderbird</h4>
                            <p style="margin: 0; line-height: 1.6;">
                                Double-clic → Clic droit → "Modifier en tant que nouveau message"
                            </p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <h4 style="color: #0078d4; margin-bottom: 8px;">📧 Outlook</h4>
                            <p style="margin: 0; line-height: 1.6;">
                                Double-clic → Clic droit → "Modifier le message" ou Ctrl+E
                            </p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <h4 style="color: #007bff; margin-bottom: 8px;">📮 Apple Mail</h4>
                            <p style="margin: 0; line-height: 1.6;">
                                Double-clic → "Répondre" → Modifier les destinataires
                            </p>
                        </div>
                        
                        <div>
                            <h4 style="color: #28a745; margin-bottom: 8px;">🐧 Evolution (Linux)</h4>
                            <p style="margin: 0; line-height: 1.6;">
                                Double-clic → Menu "Message" → "Modifier en tant que nouveau"
                            </p>
                        </div>
                    </div>
                </details>
                
                <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #055160;">💡 Informations techniques :</h4>
                    <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                        <li>Le fichier contient des headers optimisés pour <strong>${this.detectedEmailClient.primary}</strong></li>
                        <li>Compatible avec tous les clients de messagerie standards</li>
                        <li>Les pièces jointes sont automatiquement incluses</li>
                        <li>Vous pouvez modifier le message avant l'envoi</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <button onclick="this.parentElement.parentElement.remove()" style="
                        background: ${primaryColor}; color: white; border: none;
                        padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;
                        margin-right: 10px;
                    ">
                        ✅ J'ai compris
                    </button>
                    <button onclick="this.showClientSpecificHelp('${this.detectedEmailClient.primary}')" style="
                        background: #6c757d; color: white; border: none;
                        padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;
                    ">
                        📚 Aide détaillée
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Fonction pour aide spécifique
        window.showClientSpecificHelp = (client) => {
            const helpUrls = {
                'thunderbird': 'https://support.mozilla.org/fr/kb/composer-messages-thunderbird',
                'outlook': 'https://support.microsoft.com/en-us/office/edit-a-message-that-hasn-t-been-sent-3d4ae5a0-bb2c-4d04-9e9a-2c1df3f6f3e8',
                'apple-mail': 'https://support.apple.com/guide/mail/send-emails-mlhlp1010/mac'
            };
            
            const url = helpUrls[client] || 'https://support.mozilla.org/fr/kb/composer-messages-thunderbird';
            window.open(url, '_blank');
        };
        
        setTimeout(() => {
            if (modal.parentElement) {
                modal.remove();
                delete window.showClientSpecificHelp;
            }
        }, 60000);
    }

    /**
     * Télécharge automatiquement les pièces jointes depuis le serveur
     * @param {Array} attachmentInfo - Informations sur les pièces jointes
     * @returns {Promise<Array>} - Pièces jointes téléchargées comme objets File
     */
    async downloadAttachmentsFromServer(attachmentInfo) {
        if (!attachmentInfo || attachmentInfo.length === 0) {
            return [];
        }

        const downloadedFiles = [];
        
        for (const attachment of attachmentInfo) {
            try {
                console.log(`Téléchargement de ${attachment.name} depuis le serveur...`);
                
                // Construire l'URL de téléchargement
                // Utiliser le chemin relatif depuis la racine web
                const fileName = attachment.path.split(/[\\\/]/).pop(); // Extraire juste le nom de fichier
                const downloadUrl = `storage/factures/${fileName}`;
                
                console.log(`URL de téléchargement: ${downloadUrl}`);
                
                // Télécharger le fichier
                const response = await fetch(downloadUrl);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Convertir en blob puis en File
                const blob = await response.blob();
                const file = new File([blob], attachment.name, { 
                    type: attachment.type || 'application/octet-stream' 
                });
                
                downloadedFiles.push(file);
                console.log(`✅ ${attachment.name} téléchargé avec succès (${file.size} octets)`);
                
            } catch (error) {
                console.error(`❌ Erreur téléchargement ${attachment.name}:`, error);
                // Continuer avec les autres fichiers même si un échoue
            }
        }
        
        return downloadedFiles;
    }

    /**
     * Génère un lien mailto: pour ouvrir un client de messagerie avec l'email prérempli
     * @param {Object} emailData - Données de l'email
     * @returns {string} - URL mailto:
     */
    generateMailtoLink(emailData) {
        const params = new URLSearchParams();
        
        if (emailData.subject) {
            params.append('subject', emailData.subject);
        }
        
        if (emailData.message) {
            params.append('body', emailData.message);
        }
        
        if (emailData.cc) {
            params.append('cc', emailData.cc);
        }
        
        if (emailData.bcc) {
            params.append('bcc', emailData.bcc);
        }
        
        const mailtoUrl = `mailto:${emailData.to}?${params.toString()}`;
        console.log('Lien mailto généré:', mailtoUrl);
        
        return mailtoUrl;
    }

    /**
     * Envoie un email via le client de messagerie (méthode principale)
     * @param {Object} emailData - Données de l'email
     * @param {FileList|Array} attachments - Pièces jointes (optionnel)
     * @returns {Promise<Object>} - Résultat de l'opération
     */
    async sendViaEmailClient(emailData, manualAttachments = null) {
        try {
            // Valider les données requises
            if (!emailData.to || !emailData.subject) {
                throw new Error('Destinataire et sujet sont requis');
            }

            console.log('=== DÉBUT sendViaEmailClient (VERSION UNIVERSELLE) ===');
            console.log('Client détecté:', this.detectedEmailClient);
            console.log('Données email:', emailData);
            console.log('Pièces jointes manuelles:', manualAttachments ? manualAttachments.length : 0);
            
            // Déterminer les pièces jointes à utiliser
            let attachmentsToUse = [];
            
            if (manualAttachments && manualAttachments.length > 0) {
                // Utiliser les pièces jointes sélectionnées manuellement
                attachmentsToUse = Array.from(manualAttachments);
                console.log('Utilisation des pièces jointes manuelles:', attachmentsToUse.length);
            } else if (window.attachmentInfo && window.attachmentInfo.length > 0) {
                // Télécharger automatiquement les pièces jointes depuis le serveur
                console.log('Téléchargement automatique des pièces jointes depuis le serveur...');
                attachmentsToUse = await this.downloadAttachmentsFromServer(window.attachmentInfo);
                console.log('Pièces jointes téléchargées:', attachmentsToUse.length);
            }

            // Si pas de pièces jointes et File System Access non supporté, utiliser mailto:
            if (attachmentsToUse.length === 0 && !this.isSupported()) {
                const mailtoUrl = this.generateMailtoLink(emailData);
                window.location.href = mailtoUrl;
                
                return {
                    success: true,
                    method: 'mailto',
                    message: 'Email ouvert dans le client de messagerie par défaut'
                };
            }

            // Si File System Access supporté, créer un fichier .eml
            if (this.isSupported()) {
                const success = await this.createAndDownloadEml(emailData, attachmentsToUse);
                
                if (success) {
                    return {
                        success: true,
                        method: 'eml_file_universal',
                        message: `Fichier .eml créé avec succès ! Optimisé pour ${this.detectedEmailClient.primary}.`,
                        instructions: 'Consultez les instructions dans la popup pour ouvrir le fichier en mode brouillon.',
                        attachmentCount: attachmentsToUse.length,
                        detectedClient: this.detectedEmailClient.primary
                    };
                } else {
                    return {
                        success: false,
                        method: 'eml_file_universal',
                        message: 'Création du fichier .eml annulée par l\'utilisateur'
                    };
                }
            }

            // Fallback: si on a des pièces jointes mais pas de File System Access
            if (attachmentsToUse.length > 0) {
                throw new Error('Les pièces jointes ne sont pas supportées sans File System Access API. Utilisez Chrome, Edge ou Firefox récent.');
            }

            // Dernière option: mailto: sans pièces jointes
            const mailtoUrl = this.generateMailtoLink(emailData);
            window.location.href = mailtoUrl;
            
            return {
                success: true,
                method: 'mailto_fallback',
                message: 'Email ouvert via mailto: (pièces jointes non supportées)'
            };

        } catch (error) {
            console.error('Erreur lors de l\'envoi via client email:', error);
            return {
                success: false,
                method: 'error',
                message: error.message
            };
        }
    }
}

// Fonction utilitaire pour l'intégration avec PHP
window.EmailClientSender = EmailClientSender;

/**
 * Fonction globale pour envoyer un email via le client de messagerie
 * Compatible avec l'appel depuis PHP
 */
window.sendEmailViaClient = async function(emailData, attachmentInputId = null) {
    const sender = new EmailClientSender();
    
    // Récupérer les pièces jointes manuelles si un ID d'input est fourni
    let manualAttachments = null;
    if (attachmentInputId) {
        const attachmentInput = document.getElementById(attachmentInputId);
        if (attachmentInput && attachmentInput.files && attachmentInput.files.length > 0) {
            manualAttachments = attachmentInput.files;
            console.log('Pièces jointes manuelles sélectionnées:', manualAttachments.length);
        }
    }
    
    // Envoyer l'email (avec téléchargement automatique si pas de sélection manuelle)
    const result = await sender.sendViaEmailClient(emailData, manualAttachments);
    
    // Afficher le résultat à l'utilisateur
    if (result.success) {
        console.log('✅ ' + result.message);
        if (result.attachmentCount > 0) {
            console.log(`📎 ${result.attachmentCount} pièce(s) jointe(s) incluse(s)`);
        }
        if (result.detectedClient) {
            console.log(`🎯 Optimisé pour: ${result.detectedClient}`);
        }
    } else {
        console.error('❌ ' + result.message);
        alert('Erreur: ' + result.message);
    }
    
    return result;
};

/**
 * Fonction globale pour permettre à l'utilisateur de changer sa préférence
 */
window.changeEmailClientPreference = function() {
    const sender = new EmailClientSender();
    const currentDetection = sender.detectedEmailClient;
    
    sender.preference.showClientSelector(currentDetection, (selectedClient) => {
        if (selectedClient === 'reset') {
            console.log('🔄 Préférence réinitialisée');
            window.location.reload(); // Recharger la page pour prendre en compte le changement
        } else if (selectedClient) {
            console.log('🎯 Nouveau client sélectionné:', selectedClient);
            window.location.reload(); // Recharger la page pour prendre en compte le changement
        }
    });
};

/**
 * Fonction pour vérifier la compatibilité du navigateur avec gestion des préférences
 */
window.checkEmailClientCompatibility = function() {
    const sender = new EmailClientSender();
    return {
        fileSystemAccess: sender.isSupported(),
        mailto: true, // Toujours supporté
        detectedClient: sender.detectedEmailClient.primary,
        detectedOS: sender.detectedEmailClient.os,
        detectionSource: sender.detectedEmailClient.source,
        detectionConfidence: sender.detectedEmailClient.confidence,
        hasUserPreference: sender.preference.hasUserPreference(),
        userPreference: sender.preference.getPreference(),
        recommendation: sender.isSupported() ? 
            `Toutes les fonctionnalités sont supportées. Optimisé pour ${sender.detectedEmailClient.primary}.` : 
            'Pièces jointes limitées - utilisez un navigateur récent pour toutes les fonctionnalités'
    };
};

/**
 * Fonction utilitaire pour afficher les informations de debug
 */
window.debugEmailClientSender = function() {
    const sender = new EmailClientSender();
    const compatibility = window.checkEmailClientCompatibility();
    
    console.log('=== DEBUG EmailClientSender ===');
    console.log('File System Access supporté:', sender.isSupported());
    console.log('Client détecté:', sender.detectedEmailClient);
    console.log('Compatibilité complète:', compatibility);
    console.log('User Agent:', navigator.userAgent);
    console.log('Platform:', navigator.platform);
    console.log('================================');
    
    return {
        sender,
        compatibility,
        userAgent: navigator.userAgent,
        platform: navigator.platform
    };
};

/**
 * Fonction pour tester la génération d'un fichier .eml
 */
window.testEmailGeneration = async function() {
    const testEmailData = {
        from: 'test@lagrange.ch',
        to: 'destinataire@example.com',
        subject: 'Test Email - ' + new Date().toLocaleTimeString(),
        message: 'Ceci est un test de génération d\'email.\n\nCordialement,\nCentre La Grange'
    };
    
    console.log('🧪 Test de génération d\'email...');
    console.log('Données de test:', testEmailData);
    
    const sender = new EmailClientSender();
    
    try {
        const result = await sender.sendViaEmailClient(testEmailData);
        console.log('✅ Test réussi:', result);
        return result;
    } catch (error) {
        console.error('❌ Test échoué:', error);
        return { success: false, error: error.message };
    }
};

/**
 * Fonction pour afficher une modal d'aide complète
 */
window.showEmailClientHelp = function() {
    const sender = new EmailClientSender();
    const compatibility = window.checkEmailClientCompatibility();
    
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); display: flex; align-items: center;
        justify-content: center; z-index: 10000;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white; padding: 30px; border-radius: 10px; max-width: 700px;
            text-align: left; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-height: 90vh; overflow-y: auto;
        ">
            <h2 style="color: #333; margin-bottom: 20px; text-align: center;">
                📧 Guide d'utilisation - Client de messagerie
            </h2>
            
            <div style="background: #e8f4fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px 0; color: #0066cc;">🎯 Votre configuration détectée :</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Client principal :</strong> ${compatibility.detectedClient}</li>
                    <li><strong>Système :</strong> ${compatibility.detectedOS}</li>
                    <li><strong>File System Access :</strong> ${compatibility.fileSystemAccess ? '✅ Supporté' : '❌ Non supporté'}</li>
                </ul>
            </div>
            
            <h3 style="color: #333; margin-bottom: 15px;">🔧 Instructions détaillées :</h3>
            
            <div style="margin-bottom: 20px;">
                <h4 style="color: #0066cc; margin-bottom: 8px;">🦅 Thunderbird</h4>
                <ol style="line-height: 1.6;">
                    <li>Double-cliquez sur le fichier .eml téléchargé</li>
                    <li>Dans Thunderbird, clic droit sur le message → "Modifier en tant que nouveau message"</li>
                    <li>Alternative : Menu "Message" → "Modifier en tant que nouveau"</li>
                    <li>Le message s'ouvre en mode composition</li>
                    <li>Vérifiez les destinataires et cliquez "Envoyer"</li>
                </ol>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4 style="color: #0078d4; margin-bottom: 8px;">📧 Outlook</h4>
                <ol style="line-height: 1.6;">
                    <li>Double-cliquez sur le fichier .eml téléchargé</li>
                    <li>Si le message s'ouvre en lecture : clic droit → "Modifier le message"</li>
                    <li>Alternative : Appuyez sur Ctrl+E pour passer en mode édition</li>
                    <li>Le message s'ouvre en mode composition</li>
                    <li>Cliquez sur "Envoyer"</li>
                </ol>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4 style="color: #007bff; margin-bottom: 8px;">📮 Apple Mail</h4>
                <ol style="line-height: 1.6;">
                    <li>Double-cliquez sur le fichier .eml téléchargé</li>
                    <li>Dans Mail, cliquez sur "Répondre" ou "Transférer"</li>
                    <li>Modifiez l'adresse de destination si nécessaire</li>
                    <li>Supprimez les préfixes "Re:" ou "Fwd:" du sujet</li>
                    <li>Cliquez sur "Envoyer"</li>
                </ol>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ Dépannage :</h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <li><strong>Fichier ne s'ouvre pas :</strong> Glissez-déposez le dans votre client de messagerie</li>
                    <li><strong>Pas de mode édition :</strong> Cherchez "Modifier", "Éditer" ou "Nouveau message" dans les menus</li>
                    <li><strong>Pièces jointes manquantes :</strong> Vérifiez qu'elles apparaissent avant d'envoyer</li>
                    <li><strong>Problème de navigateur :</strong> Utilisez Chrome, Edge ou Firefox récent</li>
                </ul>
            </div>
            
            <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #055160;">💡 Informations techniques :</h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <li>Le fichier .eml contient des headers RFC-2822 standards</li>
                    <li>Headers spécialisés selon votre client détecté</li>
                    <li>Pièces jointes encodées en base64</li>
                    <li>Format compatible avec tous les clients de messagerie</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 25px;">
                <button onclick="this.parentElement.parentElement.remove()" style="
                    background: #007bff; color: white; border: none;
                    padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;
                    margin-right: 10px;
                ">
                    ✅ Fermer
                </button>
                <button onclick="window.debugEmailClientSender()" style="
                    background: #6c757d; color: white; border: none;
                    padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;
                ">
                    🔍 Debug
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    setTimeout(() => {
        if (modal.parentElement) {
            modal.remove();
        }
    }, 120000); // 2 minutes
};

// ✅ INITIALISATION ET LOGS DE DÉMARRAGE
console.log('📧 EmailClientSender.js chargé avec succès');
console.log('🎯 Version : Universelle avec optimisation Thunderbird');

// Détecter et afficher la configuration au chargement
document.addEventListener('DOMContentLoaded', function() {
    const sender = new EmailClientSender();
    console.log('🔍 Configuration détectée:', sender.detectedEmailClient);
    console.log('📱 File System Access supporté:', sender.isSupported());
    
    // Ajouter les informations dans la console pour debug
    if (typeof window !== 'undefined') {
        window.emailClientInfo = {
            version: 'Universelle avec optimisation Thunderbird',
            detectedClient: sender.detectedEmailClient,
            fileSystemAccessSupported: sender.isSupported(),
            loadedAt: new Date().toISOString()
        };
        
        console.log('📋 Informations disponibles dans window.emailClientInfo');
    }
});

/**
 * Export pour utilisation en module (optionnel)
 */
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EmailClientSender;
}

/**
 * Export ES6 (optionnel)
 */
if (typeof exports !== 'undefined') {
    exports.EmailClientSender = EmailClientSender;
}