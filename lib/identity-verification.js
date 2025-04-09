// Identity Verification Module
(function() {
    // Initialize verification system
    const verificationModule = {
        elements: {},
        data: {
            documentFile: null,
            documentType: '',
            documentId: null,
            selfieFile: null,
            selfieDataUrl: null,
            verificationStatus: 'pending'
        },
        state: {
            currentStep: 1,
            videoStream: null,
            loading: false
        },

        init() {
            this.cacheDOMElements();
            this.bindEvents();
            this.loadVerificationStatus();
        },

        cacheDOMElements() {
            // Main containers
            this.elements.verificationStatus = document.getElementById('verificationStatus');
            this.elements.verificationSteps = document.getElementById('verificationSteps');
            this.elements.stepContent = document.getElementById('verificationStepContent');
            
            // Step indicators
            this.elements.stepIndicators = this.elements.verificationSteps.querySelectorAll('.step-indicator');
            
            // Step content containers
            this.elements.stepContents = this.elements.stepContent.querySelectorAll('.step-content');
            
            // Document upload elements
            this.elements.idDocumentUpload = document.getElementById('idDocumentUpload');
            this.elements.idDocumentInput = document.getElementById('idDocumentInput');
            this.elements.idDocumentPreview = document.getElementById('idDocumentPreview');
            this.elements.idDocumentPreviewImg = this.elements.idDocumentPreview.querySelector('img');
            this.elements.removeIdDocument = document.getElementById('removeIdDocument');
            this.elements.documentType = document.getElementById('documentType');
            
            // Navigation buttons
            this.elements.nextToSelfie = document.getElementById('nextToSelfie');
            this.elements.backToDocument = document.getElementById('backToDocument');
            this.elements.nextToReview = document.getElementById('nextToReview');
            this.elements.backToSelfie = document.getElementById('backToSelfie');
            this.elements.submitVerification = document.getElementById('submitVerification');
            
            // Selfie capture elements
            this.elements.videoContainer = document.getElementById('videoContainer');
            this.elements.selfieVideo = document.getElementById('selfieVideo');
            this.elements.captureSelfie = document.getElementById('captureSelfie');
            this.elements.selfiePreview = document.getElementById('selfiePreview');
            this.elements.selfiePreviewImg = this.elements.selfiePreview.querySelector('img');
            this.elements.removeSelfie = document.getElementById('removeSelfie');
            this.elements.selfieUpload = document.getElementById('selfieUpload');
            this.elements.selfieInput = document.getElementById('selfieInput');
            
            // Review elements
            this.elements.reviewDocumentPreview = document.getElementById('reviewDocumentPreview');
            this.elements.reviewSelfiePreview = document.getElementById('reviewSelfiePreview');
            this.elements.reviewDocumentType = document.getElementById('reviewDocumentType');
        },

        bindEvents() {
            // Document upload events
            this.elements.idDocumentUpload.addEventListener('click', () => this.elements.idDocumentInput.click());
            this.elements.idDocumentInput.addEventListener('change', (e) => this.handleDocumentUpload(e));
            this.elements.idDocumentUpload.addEventListener('dragover', (e) => {
                e.preventDefault();
                this.elements.idDocumentUpload.classList.add('dragover');
            });
            this.elements.idDocumentUpload.addEventListener('dragleave', () => {
                this.elements.idDocumentUpload.classList.remove('dragover');
            });
            this.elements.idDocumentUpload.addEventListener('drop', (e) => {
                e.preventDefault();
                this.elements.idDocumentUpload.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    this.handleDocumentUpload({ target: { files: e.dataTransfer.files } });
                }
            });
            this.elements.removeIdDocument.addEventListener('click', () => this.removeDocument());
            
            // Document type selection
            this.elements.documentType.addEventListener('change', () => {
                this.data.documentType = this.elements.documentType.value;
                this.validateStep1();
            });
            
            // Navigation buttons
            this.elements.nextToSelfie.addEventListener('click', () => this.goToStep(2));
            this.elements.backToDocument.addEventListener('click', () => this.goToStep(1));
            this.elements.nextToReview.addEventListener('click', () => this.goToStep(3));
            this.elements.backToSelfie.addEventListener('click', () => this.goToStep(2));
            this.elements.submitVerification.addEventListener('click', () => this.submitVerification());
            
            // Selfie capture events
            this.elements.captureSelfie.addEventListener('click', () => this.captureSelfie());
            this.elements.removeSelfie.addEventListener('click', () => this.removeSelfie());
            this.elements.selfieUpload.addEventListener('click', () => this.elements.selfieInput.click());
            this.elements.selfieInput.addEventListener('change', (e) => this.handleSelfieUpload(e));
        },

        async loadVerificationStatus() {
            try {
                this.setLoading(true);
                
                // Call API to get verification status
                const response = await fetch('/api/user/verification-status', {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('accessToken')}`,
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) throw new Error('Failed to load verification status');
                
                const result = await response.json();
                
                if (result.success) {
                    this.data.verificationStatus = result.status || 'not_started';
                    this.updateVerificationStatusUI();
                }
            } catch (error) {
                console.error('Error loading verification status:', error);
                // Fallback to 'not_started' status
                this.data.verificationStatus = 'not_started';
                this.updateVerificationStatusUI();
            } finally {
                this.setLoading(false);
            }
        },

        updateVerificationStatusUI() {
            const statusContainer = this.elements.verificationStatus;
            const statusIcon = statusContainer.querySelector('.status-icon');
            const statusTitle = statusContainer.querySelector('h3');
            const statusText = statusContainer.querySelector('p');
            
            // Remove all status classes
            statusIcon.classList.remove('pending', 'verified', 'failed');
            
            switch (this.data.verificationStatus) {
                case 'verified':
                    statusIcon.classList.add('verified');
                    statusIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
                    statusTitle.textContent = 'Verification Complete';
                    statusText.textContent = 'Your identity has been verified successfully.';
                    break;
                
                case 'pending_review':
                    statusIcon.classList.add('pending');
                    statusIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>';
                    statusTitle.textContent = 'Under Review';
                    statusText.textContent = 'Your verification is being reviewed by our team.';
                    break;
                
                case 'failed':
                    statusIcon.classList.add('failed');
                    statusIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
                    statusTitle.textContent = 'Verification Failed';
                    statusText.textContent = 'Your verification could not be completed. Please try again.';
                    break;
                
                case 'not_started':
                case 'pending':
                default:
                    statusIcon.classList.add('pending');
                    statusIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>';
                    statusTitle.textContent = 'Verification Required';
                    statusText.textContent = 'Please complete the verification process below.';
                    break;
            }
            
            // Disable form if already verified or pending review
            if (this.data.verificationStatus === 'verified' || this.data.verificationStatus === 'pending_review') {
                this.elements.stepContent.querySelectorAll('button, input, select').forEach(el => {
                    el.disabled = true;
                });
            }
        },

        goToStep(step) {
            // Update current step
            this.state.currentStep = step;
            
            // Update step indicators
            this.elements.stepIndicators.forEach((indicator, index) => {
                indicator.classList.toggle('active', index + 1 === step);
                indicator.classList.toggle('completed', index + 1 < step);
            });
            
            // Update step dividers
            const dividers = this.elements.verificationSteps.querySelectorAll('.step-divider');
            dividers.forEach((divider, index) => {
                divider.classList.toggle('completed', index + 1 < step);
            });
            
            // Show corresponding step content
            this.elements.stepContents.forEach(content => {
                content.classList.toggle('active', content.dataset.step == step);
            });

            // Special handling for certain steps
            if (step === 2) {
                this.startCamera();
            } else {
                this.stopCamera();
            }

            // Prepare review step
            if (step === 3) {
                this.prepareReviewStep();
            }
        },

        async startCamera() {
            if (this.state.videoStream) return;

            try {
                const constraints = {
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'user'
                    }
                };

                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                this.elements.selfieVideo.srcObject = stream;
                this.state.videoStream = stream;
            } catch (error) {
                console.error('Error accessing camera:', error);
                // Show error message to user
                const errorMessage = document.createElement('div');
                errorMessage.className = 'camera-error';
                errorMessage.textContent = 'Unable to access camera. Please ensure camera permissions are granted or use the file upload option.';
                this.elements.videoContainer.appendChild(errorMessage);
            }
        },

        stopCamera() {
            if (this.state.videoStream) {
                this.state.videoStream.getTracks().forEach(track => track.stop());
                this.state.videoStream = null;
            }
        },

        captureSelfie() {
            if (!this.state.videoStream) return;

            // Create canvas to capture frame
            const canvas = document.createElement('canvas');
            const video = this.elements.selfieVideo;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');

            // Mirror the image (since video is mirrored)
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);

            // Draw video frame to canvas
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Convert to data URL
            this.data.selfieDataUrl = canvas.toDataURL('image/jpeg');

            // Convert data URL to File object
            this.data.selfieFile = this.dataURLtoFile(this.data.selfieDataUrl, 'selfie.jpg');

            // Display the selfie
            this.elements.selfiePreviewImg.src = this.data.selfieDataUrl;
            this.elements.selfiePreview.style.display = 'block';
            this.elements.videoContainer.style.display = 'none';

            // Enable next button
            this.elements.nextToReview.disabled = false;
        },

        dataURLtoFile(dataUrl, filename) {
            const arr = dataUrl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);

            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }

            return new File([u8arr], filename, { type: mime });
        },

        removeSelfie() {
            this.data.selfieFile = null;
            this.data.selfieDataUrl = null;

            // Hide preview and show video
            this.elements.selfiePreview.style.display = 'none';
            this.elements.videoContainer.style.display = 'block';

            // Restart camera if it was stopped
            if (!this.state.videoStream) {
                this.startCamera();
            }

            // Disable next button
            this.elements.nextToReview.disabled = true;
        },

        async handleSelfieUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            const fileType = file.type;
            if (!fileType.startsWith('image/')) {
                alert('Please upload an image file');
                return;
            }

            // Store file
            this.data.selfieFile = file;

            // Read file and display preview
            const reader = new FileReader();
            reader.onload = (e) => {
                this.data.selfieDataUrl = e.target.result;
                this.elements.selfiePreviewImg.src = this.data.selfieDataUrl;
                this.elements.selfiePreview.style.display = 'block';
                this.elements.videoContainer.style.display = 'none';

                // Stop camera
                this.stopCamera();

                // Enable next button
                this.elements.nextToReview.disabled = false;
            };
            reader.readAsDataURL(file);
        },

        async handleDocumentUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            const fileType = file.type;
            if (!fileType.startsWith('image/')) {
                alert('Please upload an image file');
                return;
            }

            // Store file
            this.data.documentFile = file;

            // Read file and display preview
            const reader = new FileReader();
            reader.onload = (e) => {
                this.elements.idDocumentPreviewImg.src = e.target.result;
                this.elements.idDocumentPreview.style.display = 'block';
                this.elements.idDocumentUpload.style.display = 'none';

                // Validate step 1
                this.validateStep1();
            };
            reader.readAsDataURL(file);
        },

        removeDocument() {
            this.data.documentFile = null;
            this.elements.idDocumentPreview.style.display = 'none';
            this.elements.idDocumentUpload.style.display = 'flex';
            this.elements.idDocumentInput.value = '';

            // Disable next button
            this.elements.nextToSelfie.disabled = true;
        },

        validateStep1() {
            const isValid = this.data.documentFile && this.elements.documentType.value;
            this.elements.nextToSelfie.disabled = !isValid;
            return isValid;
        },

        prepareReviewStep() {
            // Set document type text
            this.elements.reviewDocumentType.textContent = this.getDocumentTypeName(this.data.documentType);

            // Set preview images
            const documentClone = this.elements.idDocumentPreviewImg.cloneNode(true);
            const selfieClone = this.elements.selfiePreviewImg.cloneNode(true);

            // Clear previous previews
            this.elements.reviewDocumentPreview.innerHTML = '';
            this.elements.reviewSelfiePreview.innerHTML = '';

            // Add new previews
            this.elements.reviewDocumentPreview.appendChild(documentClone);
            this.elements.reviewSelfiePreview.appendChild(selfieClone);
        },

        getDocumentTypeName(documentType) {
            switch (documentType) {
                case 'passport':
                    return 'Passport';
                case 'drivers_license':
                    return 'Driver\'s License';
                case 'id_card':
                    return 'Government ID Card';
                default:
                    return documentType;
            }
        },

        async submitVerification() {
            try {
                this.setLoading(true);

                // Step 1: Upload document
                const documentFormData = new FormData();
                documentFormData.append('document', this.data.documentFile);
                documentFormData.append('type', this.data.documentType);
                
                // Upload document first
                const documentUploadResponse = await fetch('/api/documents/upload', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
                    },
                    body: documentFormData
                });

                if (!documentUploadResponse.ok) {
                    throw new Error('Failed to upload document');
                }

                const documentResult = await documentUploadResponse.json();

                if (!documentResult.success) {
                    throw new Error(documentResult.error || 'Failed to upload document');
                }

                // Save document ID for verification
                this.data.documentId = documentResult.documentId;

                // Step 2: Upload selfie and verify identity
                const selfieFormData = new FormData();
                selfieFormData.append('selfieFile', this.data.selfieFile);
                selfieFormData.append('documentId', this.data.documentId);
                
                const verificationResponse = await fetch(`/api/user/verify-identity`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
                    },
                    body: selfieFormData
                });

                if (!verificationResponse.ok) {
                    throw new Error('Failed to submit verification');
                }

                const verificationResult = await verificationResponse.json();

                if (!verificationResult.success) {
                    throw new Error(verificationResult.error || 'Failed to verify identity');
                }

                // Update status and show success message
                this.data.verificationStatus = 'pending_review';
                this.goToStep('success');

                // Update status UI
                setTimeout(() => {
                    this.updateVerificationStatusUI();
                }, 1000);

            } catch (error) {
                console.error('Error submitting verification:', error);
                alert(`Error: ${error.message}`);
            } finally {
                this.setLoading(false);
            }
        },

        setLoading(loading) {
            this.state.loading = loading;
            const buttons = document.querySelectorAll('#verificationStepContent button');

            buttons.forEach(button => {
                button.disabled = loading;
            });

            if (loading) {
                // Create loading overlay if it doesn't exist
                let loadingOverlay = document.querySelector('.loading-overlay');
                if (!loadingOverlay) {
                    loadingOverlay = document.createElement('div');
                    loadingOverlay.className = 'loading-overlay';
                    loadingOverlay.innerHTML = '<div class="spinner"></div>';
                    this.elements.stepContent.appendChild(loadingOverlay);
                }
            } else {
                // Remove loading overlay
                const loadingOverlay = document.querySelector('.loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.remove();
                }
            }
        }
    };

    // Add to global app object
    window.app = window.app || {};
    window.app.verificationModule = verificationModule;

    // Initialize if DOM is ready and element exists
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('verificationSteps')) {
            verificationModule.init();
        }
    });
})();
