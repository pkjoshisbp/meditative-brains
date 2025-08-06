/**
 * SSML Preview Utility for Admin Interface
 * This module provides functionality to preview messages in SSML format
 * Compatible with your existing admin interface
 */

class SSMLPreviewManager {
    constructor(apiBaseUrl = '') {
        this.apiBaseUrl = apiBaseUrl;
        this.modal = null;
        this.initialized = false;
    }

    /**
     * Initialize the SSML preview system
     * Call this once when your page loads
     */
    init() {
        if (this.initialized) return;
        
        this.createModal();
        this.setupEventListeners();
        this.initialized = true;
    }

    /**
     * Add a "Preview SSML" button to your existing admin form
     * @param {string} buttonContainerId - ID of the container where button should be added
     * @param {string} textareaId - ID of the textarea containing messages
     */
    addPreviewButton(buttonContainerId, textareaId) {
        const container = document.getElementById(buttonContainerId);
        if (!container) {
            console.error('Button container not found:', buttonContainerId);
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-info ssml-preview-btn';
        button.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 5px;">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            Preview SSML
        `;
        button.onclick = () => this.showPreview(textareaId);
        
        container.appendChild(button);
    }

    /**
     * Show SSML preview for messages in specified textarea
     * @param {string} textareaId - ID of textarea containing messages
     */
    async showPreview(textareaId) {
        const textarea = document.getElementById(textareaId);
        if (!textarea) {
            this.showError('Textarea not found');
            return;
        }

        const messages = this.parseMessages(textarea.value);
        if (messages.length === 0) {
            this.showError('Please enter at least one message to preview.');
            return;
        }

        this.showModal();
        this.showLoading();

        try {
            const ssmlMessages = await this.generateSSMLPreview(messages);
            this.displayPreview(ssmlMessages);
        } catch (error) {
            this.showError('Failed to generate SSML preview: ' + error.message);
        }
    }

    /**
     * Parse messages from textarea content
     * @param {string} content - Raw textarea content
     * @returns {Array} Array of individual messages
     */
    parseMessages(content) {
        if (!content || typeof content !== 'string') return [];
        
        return content.trim()
            .split('\n')
            .map(line => line.trim())
            .filter(line => line.length > 0);
    }

    /**
     * Generate SSML preview by calling backend API
     * @param {Array} messages - Array of message strings
     * @returns {Promise<Array>} Promise resolving to SSML formatted messages
     */
    async generateSSMLPreview(messages) {
        const response = await fetch(`${this.apiBaseUrl}/api/motivationMessage/preview-ssml`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // Add authentication headers if needed
                // 'Authorization': 'Bearer ' + getAuthToken()
            },
            body: JSON.stringify({ messages })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Unknown error occurred');
        }

        return data.ssmlMessages;
    }

    /**
     * Create the modal HTML structure
     */
    createModal() {
        const modalHTML = `
            <div id="ssmlPreviewModal" class="ssml-modal" style="display: none;">
                <div class="ssml-modal-content">
                    <div class="ssml-modal-header">
                        <h2>🎭 SSML Preview</h2>
                        <span class="ssml-modal-close">&times;</span>
                    </div>
                    <div class="ssml-modal-body">
                        <div id="ssmlPreviewContent">
                            <!-- Content will be injected here -->
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('ssmlPreviewModal');
        
        // Add CSS styles
        this.addStyles();
    }

    /**
     * Add required CSS styles for the modal
     */
    addStyles() {
        const styles = `
            .ssml-modal {
                position: fixed;
                z-index: 10000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                backdrop-filter: blur(5px);
            }
            
            .ssml-modal-content {
                background-color: #fff;
                margin: 5% auto;
                border-radius: 15px;
                width: 90%;
                max-width: 900px;
                max-height: 80vh;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                animation: ssmlModalSlideIn 0.3s ease-out;
            }
            
            @keyframes ssmlModalSlideIn {
                from { opacity: 0; transform: translateY(-50px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .ssml-modal-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px 25px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .ssml-modal-header h2 {
                margin: 0;
                font-size: 1.5em;
            }
            
            .ssml-modal-close {
                color: white;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                transition: opacity 0.3s ease;
            }
            
            .ssml-modal-close:hover {
                opacity: 0.7;
            }
            
            .ssml-modal-body {
                padding: 25px;
                max-height: 60vh;
                overflow-y: auto;
            }
            
            .ssml-message-item {
                margin-bottom: 20px;
                position: relative;
            }
            
            .ssml-message {
                padding: 15px;
                border-left: 4px solid #667eea;
                background: #f8f9fa;
                border-radius: 0 8px 8px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .ssml-preview-content {
                background: white;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                padding: 15px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                white-space: pre-wrap;
                color: #333;
                margin-top: 10px;
            }
            
            .ssml-markup {
                color: #d73a49;
                font-weight: 600;
            }
            
            .ssml-emphasis {
                color: #6f42c1;
                font-weight: bold;
            }
            
            .ssml-copy-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                background: #667eea;
                color: white;
                border: none;
                padding: 5px 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                z-index: 1;
            }
            
            .ssml-copy-btn:hover {
                background: #5a6fd8;
            }
            
            .ssml-loading {
                text-align: center;
                padding: 40px;
                color: #666;
            }
            
            .ssml-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #667eea;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: ssmlSpin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            @keyframes ssmlSpin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .ssml-error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #f5c6cb;
            }
            
            .ssml-preview-btn {
                margin-left: 10px;
                display: inline-flex;
                align-items: center;
            }
        `;

        const styleSheet = document.createElement('style');
        styleSheet.textContent = styles;
        document.head.appendChild(styleSheet);
    }

    /**
     * Setup event listeners for modal
     */
    setupEventListeners() {
        // Close modal when clicking close button
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('ssml-modal-close')) {
                this.hideModal();
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.id === 'ssmlPreviewModal') {
                this.hideModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal.style.display === 'block') {
                this.hideModal();
            }
        });
    }

    /**
     * Show the modal
     */
    showModal() {
        if (this.modal) {
            this.modal.style.display = 'block';
        }
    }

    /**
     * Hide the modal
     */
    hideModal() {
        if (this.modal) {
            this.modal.style.display = 'none';
        }
    }

    /**
     * Show loading state in modal
     */
    showLoading() {
        const content = document.getElementById('ssmlPreviewContent');
        if (content) {
            content.innerHTML = `
                <div class="ssml-loading">
                    <div class="ssml-spinner"></div>
                    <p>Generating SSML preview...</p>
                </div>
            `;
        }
    }

    /**
     * Show error message in modal
     * @param {string} message - Error message to display
     */
    showError(message) {
        const content = document.getElementById('ssmlPreviewContent');
        if (content) {
            content.innerHTML = `
                <div class="ssml-error">
                    <strong>Error:</strong> ${message}
                </div>
            `;
        }
    }

    /**
     * Display SSML preview in modal
     * @param {Array} ssmlMessages - Array of SSML formatted messages
     */
    displayPreview(ssmlMessages) {
        const content = document.getElementById('ssmlPreviewContent');
        if (!content) return;

        let html = '';
        
        ssmlMessages.forEach((ssml, index) => {
            const escapedSSML = this.escapeHtml(ssml);
            html += `
                <div class="ssml-message-item">
                    <div class="ssml-message">
                        <button class="ssml-copy-btn" onclick="ssmlPreviewManager.copyToClipboard('${escapedSSML}')">
                            📋 Copy
                        </button>
                        <strong>Message ${index + 1}:</strong>
                        <div class="ssml-preview-content">${this.formatSSMLDisplay(ssml)}</div>
                    </div>
                </div>
            `;
        });
        
        content.innerHTML = html;
    }

    /**
     * Format SSML for display with syntax highlighting
     * @param {string} ssml - SSML content
     * @returns {string} Formatted HTML
     */
    formatSSMLDisplay(ssml) {
        return ssml
            .replace(/\[([^\]]+)\]/g, '<span class="ssml-markup">[$1]</span>')
            .replace(/\*\*([^*]+)\*\*/g, '<span class="ssml-emphasis">**$1**</span>')
            .replace(/\*([^*]+)\*/g, '<span class="ssml-emphasis">*$1*</span>');
    }

    /**
     * Escape HTML for safe insertion
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/'/g, "\\'");
    }

    /**
     * Copy text to clipboard
     * @param {string} text - Text to copy
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            // Show success message if you have a notification system
            console.log('SSML copied to clipboard');
        } catch (err) {
            console.error('Failed to copy to clipboard:', err);
            // Fallback for older browsers
            this.fallbackCopyToClipboard(text);
        }
    }

    /**
     * Fallback copy method for older browsers
     * @param {string} text - Text to copy
     */
    fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            console.log('SSML copied to clipboard (fallback)');
        } catch (err) {
            console.error('Fallback copy failed:', err);
        }
        
        document.body.removeChild(textArea);
    }
}

// Create global instance
window.ssmlPreviewManager = new SSMLPreviewManager();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.ssmlPreviewManager.init();
    });
} else {
    window.ssmlPreviewManager.init();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SSMLPreviewManager;
}
