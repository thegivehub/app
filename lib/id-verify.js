  // Function to mark step as complete and activate next step
  function goToStep(stepNumber) {
    console.log(`Going to step ${stepNumber}`);
    
    // Update step indicators
    document.querySelectorAll('.step').forEach(step => {
      const stepNum = parseInt(step.dataset.step);
      
      // Reset all steps
      step.classList.remove('active');
      
      // Mark previous steps as completed
      if (stepNum < stepNumber) {
        step.classList.add('completed');
      }
      
      // Mark current step as active
      if (stepNum === stepNumber) {
        step.classList.add('active');
      }
    });
    
    // Update header title
    const stepTitles = [
      'Personal Information',
      'Upload ID Document',
      'Take Selfie',
      'Review Information'
    ];
    document.querySelector('.card-header h2').textContent = stepTitles[stepNumber - 1];
    
    // Hide all step content
    document.querySelectorAll('.step-content').forEach(content => {
      content.classList.remove('active');
    });
    
    // Show current step content
    document.getElementById(`step${stepNumber}-content`).classList.add('active');
    
    // Special handling for steps
    if (stepNumber === 3) {
      // If going to selfie step, initialize webcam
      initializeWebcam();
    } else {
      // For any step other than selfie, stop the webcam
      stopWebcam();
      
      if (stepNumber === 4) {
        // Update review content in step 4
        document.getElementById('review-name').textContent = 
          `${sessionStorage.getItem('firstName') || ''} ${sessionStorage.getItem('lastName') || ''}`;
        document.getElementById('review-dob').textContent = sessionStorage.getItem('dateOfBirth') || '';
        document.getElementById('review-address').textContent = 
        `${sessionStorage.getItem('address') || ''}, ${sessionStorage.getItem('city') || ''}, ${sessionStorage.getItem('state') || ''} ${sessionStorage.getItem('postalCode') || ''}`;
        
        // Update document preview images
        updateDocumentPreviews();
      }
    }
  }
  
  /**
   * Display verification report with all details and status
   * @param {string} verificationId - ID of the verification to display
   */
  async function showVerificationReport(verificationId) {
    try {
      console.log('Showing verification report for ID:', verificationId);
      
      // Fetch verification details from API
      const response = await fetch(`/api/verifications/${verificationId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch verification details');
      }
      
      // Get the response text first for debugging
      const responseText = await response.text();
      console.log('Raw verification response:', responseText);
      
      // Try to parse response as JSON
      let verification;
      try {
        verification = JSON.parse(responseText);
      } catch (e) {
        console.error('Failed to parse verification response:', e);
        throw new Error('Invalid server response');
      }
      
      console.log('Parsed verification details:', verification);
      
      if (!verification) {
        throw new Error(verification.error || 'Failed to retrieve verification');
      }
      
      // Extract the verification data, handling different response structures
      let data;
      if (verification.verification) {
        data = verification.verification;
      } else if (verification.data) {
        data = verification.data;
      } else if (verification.personalInfo) {
        // Direct verification object
        data = verification;
      } else {
        console.error('Unexpected verification data structure:', verification);
        throw new Error('Invalid verification data format');
      }
      
      console.log('Extracted verification data:', data);
      
      // Extract personal info if nested
      let personalInfo = data.personalInfo || data;
      let firstName = personalInfo.firstName || data.firstName || '';
      let lastName = personalInfo.lastName || data.lastName || '';
      let dateOfBirth = personalInfo.dateOfBirth || data.dateOfBirth || null;
      let address = personalInfo.address || data.address || 'N/A';
      let city = personalInfo.city || data.city || '';
      let state = personalInfo.state || data.state || '';
      let postalCode = personalInfo.postalCode || data.postalCode || '';
      let country = personalInfo.country || data.country || 'N/A';
      
      // Determine verification status
      let status = data.status || 'PENDING';
      let statusClass = '';
      let statusMessage = '';
      
      switch (status.toUpperCase()) {
        case 'APPROVED':
          statusClass = 'status-approved';
          statusMessage = 'Verified';
          break;
        case 'REJECTED':
          statusClass = 'status-rejected';
          statusMessage = 'Rejected';
          break;
        case 'PENDING':
        case 'SUBMITTED':
          statusClass = 'status-pending';
          statusMessage = 'Under Review';
          break;
        default:
          statusClass = 'status-pending';
          statusMessage = 'Processing';
      }
      
      // Determine face verification status
      let faceVerificationStatus = 'Not Available';
      let faceVerificationClass = 'status-pending';
      
      if (data.verificationResults) {
        if (data.verificationResults.success) {
          faceVerificationStatus = 'Successful';
          faceVerificationClass = 'status-approved';
        } else if (data.verificationResults.similarity) {
          // If there's a similarity score but verification wasn't successful
          if (data.verificationResults.similarity < 0.7) {
            faceVerificationStatus = 'Low Match';
            faceVerificationClass = 'status-rejected';
          } else {
            faceVerificationStatus = 'In Manual Review';
            faceVerificationClass = 'status-pending';
          }
        } else {
          faceVerificationStatus = 'In Manual Review';
          faceVerificationClass = 'status-pending';
        }
      }
      
      // Create the report card body
      const cardBody = document.querySelector('.card-body');
      
      // Clear existing content
      cardBody.innerHTML = '';
      
      // Create verification report container
      const reportContainer = document.createElement('div');
      reportContainer.className = 'verification-report';
      
      // Create the report header
      const reportHeader = document.createElement('div');
      reportHeader.className = 'report-header';
      reportHeader.innerHTML = `
        <div class="status-badge ${statusClass}">
          <span class="status-text">${statusMessage}</span>
        </div>
        <div class="success-icon">✓</div>
        <h3>Verification Submitted</h3>
        <p>Thank you for completing the verification process. Your information is being reviewed.</p>
      `;
      
      // Create the report content
      const reportContent = document.createElement('div');
      reportContent.className = 'report-content';
      
      // Format the data for display
      const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        
        try {
          let date;
          
          // Handle MongoDB date format (various possibilities)
          if (typeof dateStr === 'object') {
            if (dateStr.$date) {
              if (typeof dateStr.$date === 'string') {
                date = new Date(dateStr.$date);
              } else if (typeof dateStr.$date === 'object' && dateStr.$date.$numberLong) {
                date = new Date(parseInt(dateStr.$date.$numberLong));
              } else {
                date = new Date(dateStr.$date);
              }
            } else {
              // Try to serialize and parse it
              date = new Date(JSON.parse(JSON.stringify(dateStr)));
            }
          } else {
            date = new Date(dateStr);
          }
          
          if (isNaN(date.getTime())) return 'N/A';
          
          return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          });
        } catch (e) {
          console.error('Error formatting date:', e, 'value:', dateStr);
          return 'N/A';
        }
      };
      
      // Build the report sections
      const personalInfoSection = `
        <div class="report-section">
          <h4>Personal Information</h4>
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">Name</span>
              <span class="info-value">${firstName} ${lastName}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Date of Birth</span>
              <span class="info-value">${formatDate(dateOfBirth)}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Address</span>
              <span class="info-value">${address}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Location</span>
              <span class="info-value">${city}, ${state} ${postalCode}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Country</span>
              <span class="info-value">${country}</span>
            </div>
          </div>
        </div>
      `;
      
      // Add verification status section
      const verificationStatus = `
        <div class="report-section">
          <h4>Verification Status</h4>
          <div class="status-grid">
            <div class="status-item">
              <span class="status-label">Overall Status</span>
              <span class="status-value ${statusClass}">${statusMessage}</span>
            </div>
            <div class="status-item">
              <span class="status-label">Face Verification</span>
              <span class="status-value ${faceVerificationClass}">${faceVerificationStatus}</span>
            </div>
            <div class="status-item">
              <span class="status-label">Submission Date</span>
              <span class="status-value">${formatDate(data.submittedAt || data.createdAt)}</span>
            </div>
          </div>
        </div>
      `;
      
      // Extract document sources, checking for both IDs and direct URLs
      let documents = data.documents || {};
      let primaryId = documents.primaryId;
      let selfieId = documents.selfie;
      let documentImageUrl = data.documentImageUrl || data.verification?.documentImageUrl;
      let selfieImageUrl = data.selfieImageUrl || data.verification?.selfieImageUrl;
      
      // Debug document information
      console.log('Document information:', {
        documents,
        primaryId,
        selfieId,
        documentImageUrl,
        selfieImageUrl
      });
      
      // Add document images section
      let documentImages = `
        <div class="report-section">
          <h4>Your Verification Documents</h4>
          <div class="review-documents">`;
      
      // Add ID document if available - try multiple possible sources
      const timestamp = new Date().getTime(); // Force reload to bypass caching
      let idImageUrl;
      
      if (documentImageUrl) {
        // Direct URL in verification record
        idImageUrl = `${documentImageUrl}?_t=${timestamp}`;
      } else if (primaryId) {
        // Document ID in documents collection
        idImageUrl = `/api/documents/${primaryId}/file?_t=${timestamp}`;
      } else {
        // Direct filename based on verification ID
        idImageUrl = `/uploads/documents/primaryId-${verificationId}.png?_t=${timestamp}`;
      }
      
      documentImages += `
        <div class="document-preview">
          <h5>ID Document</h5>
          <div class="document-image">
            <img src="${idImageUrl}" alt="ID Document" 
                 onerror="if(this.src.indexOf('.png') > -1) { this.src = this.src.replace('.png', '.jpg'); } 
                         else if(this.src.indexOf('/api/') > -1) { this.src = '/uploads/documents/primaryId-${verificationId}.png'; }
                         else { this.src='/img/placeholder.jpg'; this.alt='Document preview unavailable'; }">
          </div>
        </div>`;
      
      // Add selfie if available - try multiple possible sources
      let selfieImageSrc;
      
      if (selfieImageUrl) {
        // Direct URL in verification record
        selfieImageSrc = `${selfieImageUrl}?_t=${timestamp}`;
      } else if (selfieId) {
        // Selfie ID in documents collection
        selfieImageSrc = `/api/documents/${selfieId}/file?_t=${timestamp}`;
      } else {
        // Direct filename based on verification ID
        selfieImageSrc = `/uploads/selfies/selfie-${verificationId}.png?_t=${timestamp}`;
      }
      
      documentImages += `
        <div class="document-preview">
          <h5>Selfie Photo</h5>
          <div class="document-image">
            <img src="${selfieImageSrc}" alt="Selfie"
                 onerror="if(this.src.indexOf('.png') > -1) { this.src = this.src.replace('.png', '.jpg'); } 
                         else if(this.src.indexOf('/api/') > -1) { this.src = '/uploads/selfies/selfie-${verificationId}.png'; }
                         else { this.src='/img/placeholder.jpg'; this.alt='Selfie preview unavailable'; }">
          </div>
        </div>`;
      
      documentImages += `</div>
        </div>`;
      
      // Combine all sections
      reportContent.innerHTML = personalInfoSection + verificationStatus + documentImages;
      
      // Add buttons for actions (return to dashboard and start new verification)
      const actions = document.createElement('div');
      actions.className = 'report-actions';
      actions.innerHTML = `
        <button class="btn btn-primary" onclick="window.location.href='/pages/dashboard.html'">Return to Dashboard</button>
        <button class="btn btn-secondary" onclick="startNewVerification()">Start New Verification</button>
      `;
      
      // Assemble the report
      reportContainer.appendChild(reportHeader);
      reportContainer.appendChild(reportContent);
      reportContainer.appendChild(actions);
      
      // Add the report to the page
      cardBody.appendChild(reportContainer);
      
      // Add debugging information for support
      const debugInfo = document.createElement('div');
      debugInfo.className = 'debug-info';
      debugInfo.style.display = 'none';
      debugInfo.innerHTML = `
        <details>
          <summary>Debug Information</summary>
          <pre>Verification ID: ${verificationId}</pre>
          <pre>Status: ${status}</pre>
          <pre>Documents: ${JSON.stringify(documents, null, 2)}</pre>
        </details>
      `;
      reportContainer.appendChild(debugInfo);
      
      return true;
    } catch (error) {
      console.error('Error showing verification report:', error);
      
      // Show a fallback success message if report fails
      const cardBody = document.querySelector('.card-body');
      const successMessage = document.createElement('div');
      successMessage.className = 'verification-success';
      successMessage.innerHTML = `
        <div class="success-icon">✓</div>
        <h3>Verification Submitted Successfully!</h3>
        <p>Your identity will be verified shortly. You will be notified when the verification is complete.</p>
        <button class="btn btn-primary" onclick="window.location.href='/pages/dashboard.html'">Return to Dashboard</button>
      `;
      
      // Replace card content with success message
      cardBody.innerHTML = '';
      cardBody.appendChild(successMessage);
      
      return false;
    }
  }
  
  // Function to handle the final submission
  async function submitVerification() {
    const verificationId = sessionStorage.getItem('verificationId');
    if (!verificationId) {
      alert('Verification session not found. Please start over.');
      goToStep(1);
      return;
    }
    
    try {
      // Disable submit button
      const button = document.getElementById('submitVerification');
      if (button) {
        button.disabled = true;
        button.textContent = 'Sending...';
      }
      
      // Get document info from session storage
      const documentType = sessionStorage.getItem('documentType');
      const documentNumber = sessionStorage.getItem('documentNumber');
      const documentExpiry = sessionStorage.getItem('documentExpiry');
      
      debugger;
      // Submit the verification for processing with document info
      const response = await fetch(`/api/verifications/${verificationId}/submit`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('accessToken')}`,
          'Content-Type': 'application/json'
        },
        // Include document info in the submission
        body: JSON.stringify({
          documentType,
          documentNumber,
          documentExpiry
        })
      });
      
      const result = await response.json();
      console.log('Verification submission result:', result);
      
      if (!result.success) {
        throw new Error(result.error || 'Failed to submit verification');
      }
      
      // Show success message
      console.log('Verification submitted successfully');
      
      // Clear verification ID from session storage
      sessionStorage.removeItem('verificationId');
      
      // Create verification report page
      await showVerificationReport(verificationId);
      
      // Update the header title
      document.querySelector('.card-header h2').textContent = 'Verification Report';
      
      // Optionally redirect to dashboard or show a success page
      // window.location.href = '/pages/dashboard.html';
    } catch (error) {
      console.error('Error submitting verification:', error);
      alert(error.message || 'Failed to submit verification');
    } finally {
      // Re-enable button
      const button = document.getElementById('submitVerification');
      if (button) {
        button.disabled = false;
        button.textContent = 'Send Verification';
      }
    }
  }
  
  // Store form data in session storage for later use
  function saveFormData() {
    sessionStorage.setItem('firstName', document.getElementById('firstName').value);
    sessionStorage.setItem('lastName', document.getElementById('lastName').value);
    sessionStorage.setItem('dateOfBirth', document.getElementById('dateOfBirth').value);
    sessionStorage.setItem('address', document.getElementById('address').value);
    sessionStorage.setItem('city', document.getElementById('city').value);
    sessionStorage.setItem('state', document.getElementById('state').value);
    sessionStorage.setItem('postalCode', document.getElementById('postalCode').value);
    sessionStorage.setItem('country', document.getElementById('country').value);
  }
  
  // Form submission handler
  document.getElementById('verification-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Save form data to session storage
    saveFormData();
    
    // Get form data for API submission
    const formData = new FormData(this);
    const jsonData = {};
    
    // Convert form data to JSON and log for debugging
    for (const [key, value] of formData.entries()) {
      jsonData[key] = value.trim();
    }
    
    console.log('Form data being submitted:', jsonData);
    
    try {
      // Get access token
      const token = localStorage.getItem('accessToken');
      console.log('Retrieved token:', token ? 'Found token' : 'No token found');
      
      if (!token) {
        alert('Please log in to continue');
        window.location.href = '/pages/login.html';
        return;
      }
      
      // Check if we have an existing verification ID
      const existingVerificationId = sessionStorage.getItem('verificationId');
      let apiUrl = '/api/verifications';
      let apiMethod = 'POST';
      
      // If we have an existing verification ID, update it instead of creating a new one
      if (existingVerificationId) {
        apiUrl = `/api/verifications/${existingVerificationId}`;
        apiMethod = 'PUT';
        console.log(`Updating existing verification: ${existingVerificationId}`);
      } else {
        console.log('Creating new verification');
      }
      
      // Submit the form
      const response = await fetch(apiUrl, {
        method: apiMethod,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(jsonData)
      });
      
      // Check if response is OK
      if (!response.ok) {
        console.error('API response error:', response.status, response.statusText);
        throw new Error(`API error: ${response.status} ${response.statusText}`);
      }
      
      const result = await response.json();
      console.log('API response:', result);
      
      // Process the API response - always proceed to the next step 
      // even if there was an error (for this demo)
      const verificationId = result.verificationId || ('temp-' + Date.now());
      sessionStorage.setItem('verificationId', verificationId);
      
      // Go to step 2
      console.log('Moving to step 2');
      goToStep(2);
      
    } catch (error) {
      console.error('Error submitting form:', error);
      alert('An error occurred. Please try again later.');
    }
  });
  
  // File Upload Handling
  function setupFileUpload(inputId, containerId, previewId, previewImageId, btnId) {
    const fileInput = document.getElementById(inputId);
    const container = document.getElementById(containerId);
    const preview = document.getElementById(previewId);
    const previewImage = document.getElementById(previewImageId);
    const button = document.getElementById(btnId);
    
    // Handle click on container
    container.addEventListener('click', () => {
      fileInput.click();
    });
    
    // Handle file selection
    fileInput.addEventListener('change', (e) => {
      if (fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        
        // Only handle images
        if (file.type.match('image.*')) {
          const reader = new FileReader();
          
          reader.onload = (e) => {
            // Show preview
            previewImage.src = e.target.result;
            preview.style.display = 'block';
            
            // Enable button
            button.disabled = false;
            
            // Update container style
            container.style.borderColor = '#4CAF50';
          };
          
          reader.readAsDataURL(file);
        } else if (inputId === 'id-document' && file.type === 'application/pdf') {
          // For PDFs, just show a generic image or icon
          previewImage.src = '/img/pdf-icon.png';
          preview.style.display = 'block';
          button.disabled = false;
          container.style.borderColor = '#4CAF50';
        }
      }
    });
    
    // Handle drag and drop
    container.addEventListener('dragover', (e) => {
      e.preventDefault();
      container.style.background = '#e6f2ff';
    });
    
    container.addEventListener('dragleave', (e) => {
      e.preventDefault();
      container.style.background = '#f8f9fa';
    });
    
    container.addEventListener('drop', (e) => {
      e.preventDefault();
      
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        // Trigger change event
        const event = new Event('change');
        fileInput.dispatchEvent(event);
      }
    });
  }
  
  // Function to upload ID document
  async function uploadIdDocument() {
    const idDocumentFile = document.getElementById('id-document').files[0];
    if (!idDocumentFile) {
      alert('Please select an ID document');
      return;
    }
    
    // Directly get the values from the form elements to avoid any reference issues
    const documentType = document.querySelector('#documentType').value;
    if (!documentType) {
      alert('Please select a document type');
      return;
    }
    
    const documentNumber = document.querySelector('#documentNumber').value;
    if (!documentNumber) {
      alert('Please enter the document number');
      return;
    }
    
    const documentExpiry = document.querySelector('#documentExpiry').value;
    if (!documentExpiry) {
      alert('Please enter the document expiry date');
      return;
    }
    
    // Log the raw values for debugging
    console.log("Raw form values:", {
      documentType,
      documentNumber,
      documentExpiry,
      documentTypeElement: document.querySelector('#documentType'),
      documentNumberElement: document.querySelector('#documentNumber'),
      documentExpiryElement: document.querySelector('#documentExpiry')
    });
    
    // Get verification ID from session storage
    const verificationId = sessionStorage.getItem('verificationId');
    if (!verificationId) {
      alert('Verification session not found. Please start over.');
      goToStep(1);
      return;
    }
    
    try {
      // Disable button and show loading state
      const button = document.getElementById('id-upload-btn');
      button.disabled = true;
      button.textContent = 'Uploading...';
      
      // Store document info in session storage for use in other steps
      sessionStorage.setItem('documentType', documentType);
      sessionStorage.setItem('documentNumber', documentNumber);
      sessionStorage.setItem('documentExpiry', documentExpiry);
      
      // Create a fresh FormData instance
      const formData = new FormData();
      
      // Add each field separately and verify it was added
      formData.append('document', idDocumentFile);
      formData.append('verificationId', verificationId);
      formData.append('documentType', documentType);
      formData.append('documentNumber', documentNumber);
      formData.append('documentExpiry', documentExpiry);
      
      // Also add with underscores as a backup in case server expects different format
      formData.append('document_type', documentType);
      formData.append('document_number', documentNumber);
      formData.append('document_expiry', documentExpiry);
      
      // Additional debugging to verify the form field values
      console.log('Form field values:');
      console.log('Document Type Element:', documentTypeElement);
      console.log('Document Type Value:', documentType);
      console.log('Document Number Element:', documentNumberElement);
      console.log('Document Number Value:', documentNumber);
      console.log('Document Expiry Element:', documentExpiryElement);
      console.log('Document Expiry Value:', documentExpiry);
      
      // Debug what's actually in the FormData
      console.log('FormData contents:');
      for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + (pair[1] instanceof File ? 
          pair[1].name + ' (' + pair[1].size + ' bytes, ' + pair[1].type + ')' : 
          pair[1]));
      }
      
      console.log('Uploading document with data:', {
        fileName: idDocumentFile.name,
        fileSize: idDocumentFile.size,
        fileType: idDocumentFile.type,
        verificationId,
        documentType,
        documentNumber,
        documentExpiry
      });
      
      // Try uploading to both endpoints to ensure compatibility
      let result = null;
      let response = null;
      
      try {
        // First try the new Documents API endpoint
        response = await fetch('/api/documents/upload', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
          },
          body: formData
        });
        
        // Get text response for debugging
        const responseText = await response.text();
        console.log('Document upload raw response:', responseText);
        
        // Parse JSON response
        try {
          result = JSON.parse(responseText);
          console.log('Parsed response from documents API:', result);
        } catch (e) {
          console.error('Failed to parse API response:', e);
        }
        
        // If successful, we're done
        if (result && result.success) {
          console.log('Document uploaded successfully via /api/documents/upload');
        } else {
          throw new Error('Document upload failed, trying alternate endpoint');
        }
      } catch (apiError) {
        console.log('First API attempt failed, trying verification API endpoint:', apiError);
        
        // Reset the FormData since it might have been consumed
        formData.delete('document');
        formData.append('document', idDocumentFile);
        
        // Try the verification API endpoint as fallback
        response = await fetch('/verification-api.php/document/upload', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
          },
          body: formData
        });
        
        // Get text response for debugging
        const responseText = await response.text();
        console.log('Document upload raw response from verification API:', responseText);
        
        // Parse JSON response
        try {
          result = JSON.parse(responseText);
          console.log('Parsed response from verification API:', result);
        } catch (e) {
          console.error('Failed to parse verification API response:', e);
          throw new Error('Failed to parse server response');
        }
        
        if (!result || !result.success) {
          throw new Error(result?.error || 'Failed to upload document via both APIs');
        }
        
        console.log('Document uploaded successfully via /verification-api.php/document/upload');
      }
      
      // Show success indicator and move to next step
      console.log('Document uploaded successfully');
      
      // Create a temporary success indicator
      // Also save the preview for the review step
      const previewImage = document.getElementById('id-preview-image');
      if (previewImage && previewImage.src) {
        // Store the document image URL for review step
        sessionStorage.setItem('idDocumentPreview', previewImage.src);
      }
    
      const successIndicator = document.createElement('div');
      successIndicator.className = 'success-message';
      successIndicator.textContent = 'Document uploaded successfully!';
      successIndicator.style.marginBottom = '15px';
      document.getElementById('id-preview').insertAdjacentElement('afterend', successIndicator);
      
      // Remove indicator after a brief delay
      setTimeout(() => {
        if (successIndicator.parentNode) {
          successIndicator.parentNode.removeChild(successIndicator);
        }
        // Move to next step
        goToStep(3);
      }, 1000);
    } catch (error) {
      console.error('Error uploading document:', error);
      alert(error.message || 'Failed to upload document');
    } finally {
      // Reset button state
      const button = document.getElementById('id-upload-btn');
      button.disabled = false;
      button.textContent = 'Next Step';
    }
  }
  
  // Function to upload selfie
  async function uploadSelfie() {
    // Check for captured selfie first, then check for uploaded file
    let selfieFile;
    let selfieSource = '';
    
    if (window.app && window.app.data && window.app.data.capturedSelfie) {
      selfieFile = window.app.data.capturedSelfie;
      selfieSource = 'webcam';
      console.log('Using captured selfie from webcam');
    } else if (document.getElementById('selfie').files[0]) {
      selfieFile = document.getElementById('selfie').files[0];
      selfieSource = 'upload';
      console.log('Using uploaded selfie file');
    } else {
      alert('Please take a selfie or upload a photo');
      return;
    }
    
    // Get verification ID from session storage
    const verificationId = sessionStorage.getItem('verificationId');
    if (!verificationId) {
      alert('Verification session not found. Please start over.');
      goToStep(1);
      return;
    }
    
    try {
      // Disable button and show loading state
      const button = document.getElementById('selfie-upload-btn');
      button.disabled = true;
      button.textContent = 'Uploading...';
      
      // Create form data
      const formData = new FormData();
      formData.append('selfie', selfieFile);
      formData.append('verificationId', verificationId);
      formData.append('documentType', 'selfie');

      // Debug what's actually in the FormData
      console.log('FormData contents for selfie:');
      for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + (pair[1] instanceof File ? 
          pair[1].name + ' (' + pair[1].size + ' bytes, ' + pair[1].type + ')' : 
          pair[1]));
      }
      
      console.log('Uploading selfie with data:', {
        fileName: selfieFile.name,
        fileSize: selfieFile.size,
        fileType: selfieFile.type,
        verificationId
      });
      
      // Try uploading to both endpoints to ensure compatibility
      let result = null;
      let response = null;
      
      try {
        // First try the new Documents API endpoint
        response = await fetch('/api/documents/upload', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
          },
          body: formData
        });
        
        // Get text response for debugging
        const responseText = await response.text();
        console.log('Selfie upload raw response:', responseText);
        
        // Parse JSON response
        try {
          result = JSON.parse(responseText);
          console.log('Parsed response from documents API:', result);
        } catch (e) {
          console.error('Failed to parse API response:', e);
        }
        
        // If successful, we're done
        if (result && result.success) {
          console.log('Selfie uploaded successfully via /api/documents/upload');
        } else {
          throw new Error('Selfie upload failed, trying alternate endpoint');
        }
      } catch (apiError) {
        console.log('First API attempt failed, trying verification API endpoint:', apiError);
        
        // Reset the FormData since it might have been consumed
        formData.delete('selfie');
        formData.append('selfie', selfieFile);
        
        // Try the verification API endpoint as fallback
        response = await fetch('/verification-api.php/selfie/upload', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
          },
          body: formData
        });
        
        // Get text response for debugging
        const responseText = await response.text();
        console.log('Selfie upload raw response from verification API:', responseText);
        
        // Parse JSON response
        try {
          result = JSON.parse(responseText);
          console.log('Parsed response from verification API:', result);
        } catch (e) {
          console.error('Failed to parse verification API response:', e);
          throw new Error('Failed to parse server response');
        }
        
        if (!result || !result.success) {
          throw new Error(result?.error || 'Failed to upload selfie via both APIs');
        }
        
        console.log('Selfie uploaded successfully via /verification-api.php/selfie/upload');
      }
      
      // Show success indicator and move to next step
      console.log('Selfie uploaded successfully');
      
      // Also save the preview for the review step
      const previewImage = document.getElementById('selfie-preview-image');
      if (previewImage && previewImage.src) {
        // Store the selfie image URL for review step
        sessionStorage.setItem('selfiePreview', previewImage.src);
      }
      
      // Create a temporary success indicator
      const successIndicator = document.createElement('div');
      successIndicator.className = 'success-message';
      successIndicator.textContent = 'Selfie uploaded successfully!';
      successIndicator.style.marginBottom = '15px';
      document.getElementById('selfie-preview').insertAdjacentElement('afterend', successIndicator);
      
      // Remove indicator after a brief delay
      setTimeout(() => {
        if (successIndicator.parentNode) {
          successIndicator.parentNode.removeChild(successIndicator);
        }
        // Move to next step
        goToStep(4);
      }, 1000);
    } catch (error) {
      console.error('Error uploading selfie:', error);
      alert(error.message || 'Failed to upload selfie');
    } finally {
      // Reset button state
      const button = document.getElementById('selfie-upload-btn');
      button.disabled = false;
      button.textContent = 'Next Step';
    }
  }
  
  // Function to explicitly stop the webcam
  function stopWebcam() {
    if (document.getElementById('selfieVideo') && 
        document.getElementById('selfieVideo').srcObject) {
      try {
        const stream = document.getElementById('selfieVideo').srcObject;
        const tracks = stream.getTracks();
        
        tracks.forEach(track => {
          track.stop();
        });
        
        document.getElementById('selfieVideo').srcObject = null;
        console.log('Webcam stopped successfully');
      } catch (error) {
        console.error('Error stopping webcam:', error);
      }
    }
  }
  
  // Initialize webcam
  async function initializeWebcam() {
    try {
      // Check if we're actually on the selfie step
      const selfieStep = document.getElementById('step3-content');
      if (!selfieStep || !selfieStep.classList.contains('active')) {
        console.log('Not initializing webcam because we are not on the selfie step');
        return false;
      }
      
      const video = document.getElementById('selfieVideo');
      if (!video) {
        console.error('Selfie video element not found');
        return false;
      }
      
      // Check if webcam is already initialized
      if (video.srcObject) {
        console.log('Webcam already initialized');
        return true;
      }
      
      // Check if browser supports getUserMedia
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.warn('Browser does not support getUserMedia');
        document.getElementById('videoContainer').style.display = 'none';
        document.getElementById('selfie-container').style.marginTop = '20px';
        return false;
      }
      
      // Request camera access
      const stream = await navigator.mediaDevices.getUserMedia({ 
        video: { facingMode: "user" },
        audio: false
      });
      
      // Set the video source to the camera stream
      video.srcObject = stream;
      console.log('Webcam initialized successfully');
      
      // Add event listener for capturing selfie
      document.getElementById('captureSelfie').addEventListener('click', () => {
        // Create a canvas element
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // Draw the video frame to the canvas
        canvas.getContext('2d').drawImage(video, 0, 0);
        
        // Convert the canvas to a data URL
        const dataUrl = canvas.toDataURL('image/png');
        
        // Display the captured image in the preview
        const previewImage = document.getElementById('selfie-preview-image');
        previewImage.src = dataUrl;
        document.getElementById('selfie-preview').style.display = 'block';
        
        // Convert the data URL to a Blob and create a File object
        canvas.toBlob((blob) => {
          // Create a File object from the blob
          const selfieFile = new File([blob], "selfie.png", { type: "image/png" });
          
          // Store the file in the app data object
          if (!window.app) window.app = {};
          if (!window.app.data) window.app.data = {};
          window.app.data.capturedSelfie = selfieFile;
          
          // Enable the continue button
          document.getElementById('selfie-upload-btn').disabled = false;
          
          // Hide the video container
          document.getElementById('videoContainer').style.display = 'none';
          
          console.log('Selfie captured successfully:', selfieFile.size, 'bytes');
        }, 'image/png');
      });
      
      return true;
    } catch (error) {
      console.error('Error initializing webcam:', error);
      document.getElementById('videoContainer').style.display = 'none';
      document.getElementById('selfie-container').style.marginTop = '20px';
      return false;
    }
  }
  
  // Update document previews in the review step
  function updateDocumentPreviews() {
    console.log('Updating document previews for review step');
    
    // Get verification ID
    const verificationId = sessionStorage.getItem('verificationId');
    if (!verificationId) {
      console.log('No verification ID found, cannot show document previews');
      return;
    }
    
    // Update document info in the review section
    const documentType = sessionStorage.getItem('documentType') || 'Unknown';
    const documentNumber = sessionStorage.getItem('documentNumber') || 'Unknown';
    const documentExpiry = sessionStorage.getItem('documentExpiry') || 'Unknown';
    
    // Add document info to the review display if we have such elements
    const reviewDocType = document.getElementById('review-doc-type');
    const reviewDocNumber = document.getElementById('review-doc-number');
    const reviewDocExpiry = document.getElementById('review-doc-expiry');
    
    if (reviewDocType) reviewDocType.textContent = documentType;
    if (reviewDocNumber) reviewDocNumber.textContent = documentNumber;
    if (reviewDocExpiry) reviewDocExpiry.textContent = documentExpiry;
    
    // Check for ID document preview in session storage first
    const idPreviewSrc = sessionStorage.getItem('idDocumentPreview');
    if (idPreviewSrc) {
      document.getElementById('review-id-image').src = idPreviewSrc;
      document.getElementById('review-id-preview').style.display = 'flex';
    } else {
      // Get ID document preview from the upload step if available
      const idPreviewImage = document.getElementById('id-preview-image');
      if (idPreviewImage && idPreviewImage.src && idPreviewImage.src !== '#') {
        document.getElementById('review-id-image').src = idPreviewImage.src;
        document.getElementById('review-id-preview').style.display = 'flex';
      } else {
        // Use direct URL based on verification ID
        const directIdUrl = `/uploads/documents/primaryId-${verificationId}.png`;
        console.log('Using direct primaryId URL:', directIdUrl);
        
        // Try to load the image
        const idImage = document.getElementById('review-id-image');
        idImage.onerror = () => {
          console.log('Primary ID image not found at .png path, trying .jpg');
          idImage.onerror = () => {
            console.log('Primary ID image not found at .jpg path either');
            // Try API fallback
            fetch(`/api/verifications/${verificationId}`)
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Check if there's a direct document URL
                  if (data.documentImageUrl) {
                    idImage.src = data.documentImageUrl;
                    document.getElementById('review-id-preview').style.display = 'flex';
                  } else if (data.verification && data.verification.documentImageUrl) {
                    idImage.src = data.verification.documentImageUrl;
                    document.getElementById('review-id-preview').style.display = 'flex';
                  } else if (data.verification && data.verification.documents && data.verification.documents.primaryId) {
                    const docId = data.verification.documents.primaryId;
                    idImage.src = `/api/documents/${docId}/file`;
                    document.getElementById('review-id-preview').style.display = 'flex';
                  }
                }
              })
              .catch(error => {
                console.error('Error fetching document details:', error);
              });
          };
          idImage.src = `/uploads/documents/document_${verificationId}_drivers_license.jpg`;
        };
        idImage.src = directIdUrl;
        document.getElementById('review-id-preview').style.display = 'flex';
      }
    }
    
    // Check for selfie preview in session storage first
    const selfiePreviewSrc = sessionStorage.getItem('selfiePreview');
    if (selfiePreviewSrc) {
      document.getElementById('review-selfie-image').src = selfiePreviewSrc;
      document.getElementById('review-selfie-preview').style.display = 'flex';
    } else {
      // Get selfie preview from the upload step if available
      const selfiePreviewImage = document.getElementById('selfie-preview-image');
      if (selfiePreviewImage && selfiePreviewImage.src && selfiePreviewImage.src !== '#') {
        document.getElementById('review-selfie-image').src = selfiePreviewImage.src;
        document.getElementById('review-selfie-preview').style.display = 'flex';
      } else {
        // Use direct URL based on verification ID
        const directSelfieUrl = `/uploads/selfies/selfie-${verificationId}.png`;
        console.log('Using direct selfie URL:', directSelfieUrl);
        
        // Try to load the image
        const selfieImage = document.getElementById('review-selfie-image');
        selfieImage.onerror = () => {
          console.log('Selfie image not found at .png path, trying .jpg');
          selfieImage.onerror = () => {
            console.log('Selfie image not found at .jpg path either');
            // Try API fallback
            fetch(`/api/verifications/${verificationId}`)
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Check if there's a direct selfie URL
                  if (data.selfieImageUrl) {
                    selfieImage.src = data.selfieImageUrl;
                    document.getElementById('review-selfie-preview').style.display = 'flex';
                  } else if (data.verification && data.verification.selfieImageUrl) {
                    selfieImage.src = data.verification.selfieImageUrl;
                    document.getElementById('review-selfie-preview').style.display = 'flex';
                  } else if (data.verification && data.verification.documents && data.verification.documents.selfie) {
                    const selfieId = data.verification.documents.selfie;
                    selfieImage.src = `/api/documents/${selfieId}/file`;
                    document.getElementById('review-selfie-preview').style.display = 'flex';
                  }
                }
              })
              .catch(error => {
                console.error('Error fetching selfie details:', error);
              });
          };
          selfieImage.src = `/uploads/selfies/selfie-${verificationId}.jpg`;
        };
        selfieImage.src = directSelfieUrl;
        document.getElementById('review-selfie-preview').style.display = 'flex';
      }
    }
  }
  
  // Set up file uploads when the DOM is loaded
  document.addEventListener('DOMContentLoaded', () => {
    // Set up ID document upload
    setupFileUpload(
      'id-document', 
      'id-document-container', 
      'id-preview', 
      'id-preview-image',
      'id-upload-btn'
    );
    
    // Set up selfie upload
    setupFileUpload(
      'selfie', 
      'selfie-container', 
      'selfie-preview', 
      'selfie-preview-image',
      'selfie-upload-btn'
    );
    
    // Add event listeners with inline handlers to ensure scope and context are correct
    document.getElementById('id-upload-btn').addEventListener('click', async function() {
      const fileInput = document.getElementById('id-document');
      const idDocumentFile = fileInput.files[0];
      if (!idDocumentFile) {
        alert('Please select an ID document');
        return;
      }
      
      // Get form values directly
      const documentType = document.getElementById('documentType').value;
      const documentNumber = document.getElementById('documentNumber').value;
      const documentExpiry = document.getElementById('documentExpiry').value;
      
      console.log('Direct DOM elements:', {
        documentTypeEl: document.getElementById('documentType'),
        documentNumberEl: document.getElementById('documentNumber'),
        documentExpiryEl: document.getElementById('documentExpiry')
      });
      
      console.log('Direct form values:', {
        documentType,
        documentNumber,
        documentExpiry
      });
      
      if (!documentType) {
        alert('Please select a document type');
        return;
      }
      if (!documentNumber) {
        alert('Please enter the document number');
        return;
      }
      if (!documentExpiry) {
        alert('Please enter the document expiry date');
        return;
      }
      
      // Save to session storage
      sessionStorage.setItem('documentType', documentType);
      sessionStorage.setItem('documentNumber', documentNumber);
      sessionStorage.setItem('documentExpiry', documentExpiry);
      
      // Get verification ID
      const verificationId = sessionStorage.getItem('verificationId');
      if (!verificationId) {
        alert('Verification session not found. Please start over.');
        goToStep(1);
        return;
      }
      
      try {
        // Disable button
        this.disabled = true;
        this.textContent = 'Uploading...';
        
        // Create FormData and use HTML form directly to ensure we're capturing what the browser actually has
        const form = document.createElement('form');
        
        // Add document input manually
        const docInput = document.createElement('input');
        docInput.type = 'file';
        docInput.name = 'document';
        // Create a DataTransfer object to set files
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(idDocumentFile);
        docInput.files = dataTransfer.files;
        form.appendChild(docInput);
        
        // Add other fields directly from real DOM elements
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'verificationId';
        idInput.value = verificationId;
        form.appendChild(idInput);
        
        // Clone the actual select element for document type
        const typeSelect = document.getElementById('documentType').cloneNode(true);
        form.appendChild(typeSelect);
        
        // Clone the actual input for document number
        const numberInput = document.getElementById('documentNumber').cloneNode(true);
        form.appendChild(numberInput);
        
        // Clone the actual input for document expiry
        const expiryInput = document.getElementById('documentExpiry').cloneNode(true);
        form.appendChild(expiryInput);
        
        console.log('Form structure:', form.innerHTML);
        
        // Create FormData from the actual form to ensure proper field capture
        const formData = new FormData(form);
        
        // Also manually set the values to be extra sure
        formData.set('document', idDocumentFile);
        formData.set('verificationId', verificationId);
        formData.set('documentType', documentType);
        formData.set('documentNumber', documentNumber);
        formData.set('documentExpiry', documentExpiry);
        
        // Log FormData entries to verify contents
        console.log('FormData entries:');
        for (const pair of formData.entries()) {
          console.log(pair[0], ':', pair[1] instanceof File ? 
                      `File: ${pair[1].name} (${pair[1].type})` : pair[1]);
        }
        
        // Upload document
        const response = await fetch('/api/documents/upload', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
          },
          body: formData
        });
        
        const result = await response.json();
        if (!result.success) {
          throw new Error(result.error || 'Failed to upload document');
        }
        
        // Save preview image
        const previewImage = document.getElementById('id-preview-image');
        if (previewImage && previewImage.src) {
          sessionStorage.setItem('idDocumentPreview', previewImage.src);
        }
        
        // Show success message
        const successMessage = document.createElement('div');
        successMessage.className = 'success-message';
        successMessage.textContent = 'Document uploaded successfully!';
        document.getElementById('id-preview').insertAdjacentElement('afterend', successMessage);
        
        // Move to next step after a delay
        setTimeout(() => {
          if (successMessage.parentNode) {
            successMessage.parentNode.removeChild(successMessage);
          }
          goToStep(3);
        }, 1000);
        
      } catch (error) {
        console.error('Error uploading document:', error);
        alert('Error uploading document: ' + error.message);
      } finally {
        // Re-enable button
        this.disabled = false;
        this.textContent = 'Next Step';
      }
    });
    
    document.getElementById('selfie-upload-btn').addEventListener('click', uploadSelfie);
    
    // Do not initialize webcam here - we'll do it only when actually on the selfie step
  });
  
  // Check for existing verification and determine which step to start from
  async function checkExistingVerification() {
    try {
      // Check if we're starting a new verification (from the 'Start New Verification' button)
      const forceNewVerification = sessionStorage.getItem('forceNewVerification');
      if (forceNewVerification === 'true') {
        console.log('Force new verification flag found, starting from step 1');
        // Clear the flag so it doesn't affect future page loads
        sessionStorage.removeItem('forceNewVerification');
        goToStep(1);
        return;
      }
      
      // Get access token
      const token = localStorage.getItem('accessToken');
      if (!token) {
        console.log('No token found, starting from step 1');
        goToStep(1);
        return;
      }
      
      // Call API to check existing verification status
      const response = await fetch('/api/verifications/status/status-for-user', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      
      if (!response.ok) {
        console.error('Failed to get verification status:', response.status, response.statusText);
        goToStep(1); // Default to step 1
        return;
      }
      
      const result = await response.json();
      console.log('Verification status:', result);
      
      if (result.success) {
        // If verification has been submitted or approved, show the report directly
        if (result.status === 'SUBMITTED' || result.status === 'APPROVED' || result.status === 'PENDING') {
          console.log('User has already submitted verification, showing report directly');
          
          if (result.verificationId) {
            // Make sure to stop any webcam that might be running
            stopWebcam();
            
            // Show verification report directly
            await showVerificationReport(result.verificationId);
            
            // Update the header title
            document.querySelector('.card-header h2').textContent = 'Verification Report';
            
            // Remove step indicators since we're just showing the report
            const stepsContainer = document.querySelector('.steps');
            if (stepsContainer) {
              stepsContainer.style.display = 'none';
            }
            
            return;
          }
        } else if (result.status === 'REJECTED') {
          // Previous verification was rejected, show message and start again
          alert(`Your previous verification was rejected. Reason: ${result.rejectionReason || 'Not specified'}. Please try again.`);
          goToStep(1);
          return;
        }
        
        // Store verification ID if it exists
        if (result.verificationId) {
          sessionStorage.setItem('verificationId', result.verificationId);
        }
        
        // Pre-fill form data if available
        if (result.personalInfo) {
          const info = result.personalInfo;
          // Convert MongoDB date format to HTML date input format if needed
          const dob = info.dateOfBirth && info.dateOfBirth.$date ? 
            new Date(info.dateOfBirth.$date.$numberLong) : 
            new Date(info.dateOfBirth || '');
            
          if (dob && !isNaN(dob.getTime())) {
            const year = dob.getFullYear();
            const month = String(dob.getMonth() + 1).padStart(2, '0');
            const day = String(dob.getDate()).padStart(2, '0');
            document.getElementById('dateOfBirth').value = `${year}-${month}-${day}`;
          }
          
          // Fill other fields
          if (info.firstName) document.getElementById('firstName').value = info.firstName;
          if (info.lastName) document.getElementById('lastName').value = info.lastName;
          if (info.address) document.getElementById('address').value = info.address;
          if (info.city) document.getElementById('city').value = info.city;
          if (info.state) document.getElementById('state').value = info.state;
          if (info.postalCode) document.getElementById('postalCode').value = info.postalCode;
          if (info.country) document.getElementById('country').value = info.country;
          
          // Save to session storage
          saveFormData();
        }
        
        // Start from the appropriate step
        if (result.startAtStep > 0) {
          console.log(`Continuing from step ${result.startAtStep}`);
          goToStep(result.startAtStep);
        } else {
          goToStep(1); // Default to step 1
        }
      } else {
        console.error('Error checking verification status:', result.error);
        goToStep(1); // Default to step 1
      }
    } catch (error) {
      console.error('Error checking existing verification:', error);
      goToStep(1); // Default to step 1
    }
  }
  
  /**
   * Starts a new verification process by clearing stored verification data
   * and reloading the page to begin from step 1
   */
  function startNewVerification() {
    console.log('Starting new verification process');
    
    // Set flag in sessionStorage to indicate we're starting a new verification
    sessionStorage.setItem('forceNewVerification', 'true');
    
    // Clear any verification data from localStorage and sessionStorage
    sessionStorage.removeItem('verificationId');
    sessionStorage.removeItem('idDocumentPreview');
    sessionStorage.removeItem('selfiePreview');
    
    // Clear any form data from sessionStorage
    sessionStorage.removeItem('firstName');
    sessionStorage.removeItem('lastName');
    sessionStorage.removeItem('dateOfBirth');
    sessionStorage.removeItem('address');
    sessionStorage.removeItem('city');
    sessionStorage.removeItem('state');
    sessionStorage.removeItem('postalCode');
    sessionStorage.removeItem('country');
    
    // Clear any application data
    if (window.app && window.app.data) {
      window.app.data.verificationId = null;
      window.app.data.capturedSelfie = null;
    }
    
    // Make direct API call to reset verification status for the user
    fetch('/verification-api.php/status/reset', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('accessToken')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ force: true })
    })
    .then(response => response.json())
    .then(data => {
      console.log('Verification status reset:', data);
      // Reload the page to start fresh
      window.location.reload();
    })
    .catch(error => {
      console.error('Error resetting verification status:', error);
      // Reload anyway
      window.location.reload();
    });
  }

  // Call this function when the page loads
  checkExistingVerification();

