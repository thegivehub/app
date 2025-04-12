(function () {
const $ = (str) => document.querySelector(str);
const $$ = (str) => document.querySelectorAll(str);

const app = {
  data: {
    user: null,
    preferences: null,
    paymentMethods: [],
    capturedSelfie: null,
    documentId: null,
    verificationComplete: false
  },
  state: {
    currentSection: 'verification',
    loading: false,
    saveStatus: null,
  },
  config: {
    apiBase: location.protocol + "//" + location.hostname + '/api',
  },
  init() {
    // First check if user is authenticated
    const token = localStorage.getItem('accessToken');
    if (!token) {
      window.location.href = '/pages/login.html';
      return;
    }

    this.bindEvents();
    this.loadUserData();
  },
  bindEvents() {
    // Navigation
    $$('.nav-item').forEach((item) => {
      item.addEventListener('click', () => {
        this.changeSection(item.dataset.section);
      });
    });

    // Remove the form event listener and just use the button click
    const saveButton = $('button#saveChangesBtn');
    if (saveButton) {
      saveButton.addEventListener('click', (e) => {
        e.preventDefault();
        this.saveProfile();
      });
    }
    // Notification toggles
    $$('input[type="checkbox"]').forEach((toggle) => {
      toggle.addEventListener('change', () => {
        this.savePreferences();
      });
    });

    // Identity verification file uploads
    $('#idDocumentUpload').addEventListener('click', function() {
      $('#idDocumentInput').click();
    });
    
    $('#selfieUpload').addEventListener('click', function() {
      $('#selfieInput').click();
    });
    
    // Handle file selection for ID document
    $('#idDocumentInput').addEventListener('change', function(e) {
      if (e.target.files.length > 0) {
        const file = e.target.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
          $('#idDocumentPreview').style.display = 'block';
          $('#idDocumentPreview img').src = e.target.result;
          $('#nextToSelfie').disabled = false;
        };
        
        reader.readAsDataURL(file);
      }
    });
    
    // Handle file selection for selfie
    $('#selfieInput').addEventListener('change', function(e) {
      if (e.target.files.length > 0) {
        const file = e.target.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
          $('#selfiePreview').style.display = 'block';
          $('#selfieVideo').style.display = 'none';
          $('#selfiePreview img').src = e.target.result;
          $('#nextToReview').disabled = false;
        };
        
        reader.readAsDataURL(file);
      }
    });
    
    // Handle remove buttons for uploaded files
    $('#removeIdDocument').addEventListener('click', function() {
      $('#idDocumentPreview').style.display = 'none';
      $('#idDocumentInput').value = '';
      $('#nextToSelfie').disabled = true;
    });
    
    $('#removeSelfie').addEventListener('click', function() {
      $('#selfiePreview').style.display = 'none';
      $('#selfieVideo').style.display = 'block';
      $('#selfieInput').value = '';
      $('#nextToReview').disabled = true;
    });
    
    // Add navigation between personal info and document upload
    $('#nextToDocument').addEventListener('click', async function() {
      // Validate personal information
      const requiredFields = ['firstName', 'lastName', 'dateOfBirth', 'address', 'city', 'state', 'postalCode', 'country'];
      const missingFields = requiredFields.filter(field => !$('#' + field).value);
      
      if (missingFields.length > 0) {
        alert('Please fill in all required fields: ' + missingFields.join(', '));
        return;
      }

      try {
        // Disable button while processing
        $('#nextToDocument').disabled = true;
        $('#nextToDocument').textContent = 'Processing...';
        
        // Collect personal information
        const personalInfo = {
          firstName: $('#firstName').value,
          lastName: $('#lastName').value,
          dateOfBirth: $('#dateOfBirth').value,
          address: $('#address').value,
          city: $('#city').value,
          state: $('#state').value,
          postalCode: $('#postalCode').value,
          country: $('#country').value,
          status: 'pending'
        };
        
        console.log('Submitting personal info:', personalInfo);
        
        // Upload personal info and get document ID
        const result = await app.uploadPersonalInfo(personalInfo);
        if (result.documentId) {
          app.data.documentId = result.documentId;
          console.log('Personal info saved, document ID:', app.data.documentId);
          
          // Show success message (optional)
          const statusMsg = document.createElement('div');
          statusMsg.className = 'success-message';
          statusMsg.textContent = 'Personal information saved successfully!';
          statusMsg.style.marginBottom = '15px';
          $('.step-content[data-step="1"]').appendChild(statusMsg);
          
          // Set a timeout to remove the message
          setTimeout(() => {
            if (statusMsg.parentNode) {
              statusMsg.parentNode.removeChild(statusMsg);
            }
          }, 3000);
        } else {
          throw new Error('Failed to get document ID from server');
        }
        
        // Proceed to next step
        $('.step-content[data-step="1"]').classList.remove('active');
        $('.step-content[data-step="2"]').classList.add('active');
        
        // Update step indicators
        $$('.step-indicator')[0].classList.add('completed');
        $$('.step-indicator')[1].classList.add('active');
        $$('.step-divider')[0].classList.add('completed');
      } catch (error) {
        console.error('Error saving personal information:', error);
        alert(error.message || 'Failed to save personal information');
      } finally {
        $('#nextToDocument').disabled = false;
        $('#nextToDocument').textContent = 'Continue';
      }
    });
    
    // Step navigation for verification process
    $('#nextToSelfie').addEventListener('click', async function() {
      // Validate document information
      const requiredFields = ['documentType', 'documentNumber', 'documentExpiry'];
      const missingFields = requiredFields.filter(field => !$('#' + field).value);
      
      if (missingFields.length > 0) {
        alert('Please fill in all required fields: ' + missingFields.join(', '));
        return;
      }

      if (!$('#idDocumentInput').files[0]) {
        alert('Please upload your ID document');
        return;
      }

      try {
        // Disable button while processing
        $('#nextToSelfie').disabled = true;
        
        // Upload the document
        const documentFile = $('#idDocumentInput').files[0];
        const documentInfo = {
          documentType: $('#documentType').value,
          documentNumber: $('#documentNumber').value,
          documentExpiry: $('#documentExpiry').value
        };
        
        const result = await app.uploadDocumentFile(documentFile, documentInfo);
        console.log('Document upload result:', result);
        
        // Proceed to next step
        $('.step-content[data-step="2"]').classList.remove('active');
        $('.step-content[data-step="3"]').classList.add('active');
        
        // Update step indicators
        $$('.step-indicator')[1].classList.add('completed');
        $$('.step-indicator')[2].classList.add('active');
        $$('.step-divider')[1].classList.add('completed');
        
        // Initialize webcam
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
          navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
              $('#selfieVideo').srcObject = stream;
            })
            .catch(function(error) {
              console.error("Could not access camera:", error);
            });
        }
      } catch (error) {
        console.error('Error uploading document:', error);
        alert(error.message || 'Failed to upload document');
      } finally {
        $('#nextToSelfie').disabled = false;
      }
    });
    
    $('#nextToReview').addEventListener('click', async function() {
      try {
        // Disable button while processing
        $('#nextToReview').disabled = true;
        
        // Check if we have a selfie to upload
        const selfieFile = app.data.capturedSelfie || $('#selfieInput').files[0];
        if (!selfieFile) {
          throw new Error('Please take or upload a selfie');
        }
        
        // Upload the selfie
        console.log('Uploading selfie...', selfieFile);
        const selfieResult = await app.uploadSelfie(selfieFile, app.data.documentId);
        console.log('Selfie upload result:', selfieResult);
        
        // Proceed to review step
        $('.step-content[data-step="3"]').classList.remove('active');
        $('.step-content[data-step="4"]').classList.add('active');
        
        // Update step indicators
        $$('.step-indicator')[2].classList.add('completed');
        $$('.step-indicator')[3].classList.add('active');
        $$('.step-divider')[2].classList.add('completed');
        
        // Stop webcam
        if ($('#selfieVideo').srcObject) {
          $('#selfieVideo').srcObject.getTracks().forEach(track => track.stop());
        }
        
        // Update review section with all collected information
        $('#reviewDocumentPreview').innerHTML = $('#idDocumentPreview').innerHTML;
        $('#reviewSelfiePreview').innerHTML = $('#selfiePreview').innerHTML;
        $('#reviewDocumentType').textContent = $('#documentType').value;

        // Add personal information to review
        const reviewDetails = document.createElement('div');
        reviewDetails.classList.add('review-details');
        reviewDetails.innerHTML = `
          <h4>Personal Information</h4>
          <p><strong>Name:</strong> ${$('#firstName').value} ${$('#lastName').value}</p>
          <p><strong>Date of Birth:</strong> ${$('#dateOfBirth').value}</p>
          <p><strong>Address:</strong> ${$('#address').value}</p>
          <p><strong>City:</strong> ${$('#city').value}</p>
          <p><strong>State:</strong> ${$('#state').value}</p>
          <p><strong>Postal Code:</strong> ${$('#postalCode').value}</p>
          <p><strong>Country:</strong> ${$('#country').value}</p>
          <h4>Document Information</h4>
          <p><strong>Document Type:</strong> ${$('#documentType').value}</p>
          <p><strong>Document Number:</strong> ${$('#documentNumber').value}</p>
          <p><strong>Expiry Date:</strong> ${$('#documentExpiry').value}</p>
        `;
        $('.review-container').appendChild(reviewDetails);
      } catch (error) {
        console.error('Error uploading selfie:', error);
        alert(error.message || 'Failed to upload selfie');
      } finally {
        $('#nextToReview').disabled = false;
      }
    });
    
    // Submit verification
    $('#submitVerification').addEventListener('click', async function() {
      try {
        $('#submitVerification').disabled = true;
        
        // Mark verification as reviewed/complete
        const result = await app.completeVerification(app.data.documentId);
        console.log('Verification completed:', result);
        
        // Show success state
        $('.step-content[data-step="4"]').classList.remove('active');
        $('.step-content[data-step="success"]').classList.add('active');
        
        app.data.verificationComplete = true;
        
      } catch (error) {
        console.error('Verification completion error:', error);
        alert(error.message || 'Failed to complete verification');
      } finally {
        $('#submitVerification').disabled = false;
      }
    });

    // Back navigation
    $('#backToDocument').addEventListener('click', function() {
      $('.step-content[data-step="3"]').classList.remove('active');
      $('.step-content[data-step="2"]').classList.add('active');
      
      // Update step indicators
      $$('.step-indicator')[1].classList.remove('completed');
      $$('.step-indicator')[2].classList.remove('active');
      $$('.step-divider')[1].classList.remove('completed');
      
      // Stop webcam
      if ($('#selfieVideo').srcObject) {
        $('#selfieVideo').srcObject.getTracks().forEach(track => track.stop());
      }
    });
    
    // Handle selfie capture button
    $('#captureSelfie').addEventListener('click', function() {
      const canvas = document.createElement('canvas');
      const video = $('#selfieVideo');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);
      
      // Convert the canvas to a blob and store globally
      canvas.toBlob(function(blob) {
        // Create a File object from the blob
        const capturedFile = new File([blob], "selfie.png", { type: "image/png" });
        
        // Store the file in the app data object instead of on the input element
        app.data.capturedSelfie = capturedFile;
        console.log("Selfie captured:", capturedFile);
        
        // Update the preview
        const dataUrl = canvas.toDataURL('image/png');
        $('#selfiePreview').style.display = 'block';
        $('#selfiePreview img').src = dataUrl;
        $('#videoContainer').style.display = 'none';
        $('#nextToReview').disabled = false;
      }, 'image/png');
    });
  },
  setupAvatarUpload() {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      input.onchange = (e) => this.handleAvatarUpload(e);
      input.click();
  },
  async handleAvatarUpload(event) {
      const file = event.target.files[0];
      if (!file) return;

      if (!file.type.startsWith('image/')) {
          this.showError('avatarError', 'Please select an image file');
          return;
      }

      console.log('handleAvatarUload');

      try {
          // Show preview
          const reader = new FileReader();
          reader.onload = (e) => {
              document.getElementById('avatarImg').src = e.target.result;
              document.getElementById('avatarImg').style.display = 'block';
              document.getElementById('avatarImg').value = e.target.result;
          };
          reader.readAsDataURL(file);
          console.log('prefetch1')

          // Upload to server
          const formData = new FormData();
          formData.append('avatar', file);
          formData.append('username', this.data.user.username);
          formData.append('email', this.data.user.email);

          console.log('prefetch')

          const response = await fetch('/api/auth/upload-avatar', {
              method: 'POST',
              headers: {
                  'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
              },
              body: formData
          });

          console.log('response', response);

          const result = await response.json();
          if (!result.success) {
              throw new Error(result.error || 'Failed to upload avatar');
          }
          
          console.log('Avatar uploaded successfully:', result);

          // Store the returned avatar URL
          // document.getElementById('avatarImg').value = result.filename || result.url;
          document.getElementById('avatarImg').src = result.url;
      } catch (error) {
          this.showError('avatarError', error.message);
      }
  },          
  async loadUserData() {
    try {
      this.state.loading = true;
      // this.render();

      // Use our manager classes for consistent SDK handling
      const [user] = await Promise.all([
        app.settingsManager.getUserProfile()
      ]);

      const [preferences] = await Promise.all([
        app.settingsManager.getPreferences()
      ]);

      if (!user) {
        throw new Error('Failed to load user data');
      }

      console.log('Loaded user data:', user);
      console.log('Loaded preferences:', preferences);

      this.data.user = user;
      this.data.preferences = preferences;

      this.state.loading = false;
      this.render();
    } catch (error) {
      console.error('Error loading user data:', error);
      this.showError('Failed to load user data');

      // If token is invalid/missing, redirect to login
      if (error.message.includes('No authentication token')) {
        window.location.href = '/pages/login.html';
      }
    }
  },

  async fetchData(collection, id) {
    const url = `${this.config.apiBase}/${collection}${
      id ? `?id=${id}` : ''
    }`;
    const response = await fetch(url, {
      headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
      }
    });
    if (!response.ok)
      throw new Error(`HTTP error! status: ${response.status}`);
    return await response.json();
  },

  async saveProfile(evt) {
    try {
      if (evt) evt.preventDefault();

      const formData = {
        displayName: $('#displayName').value,
        personalInfo: {
          firstName: $('#first_name').value,
          lastName: $('#last_name').value,
          phone: $('#phone').value,
          email: $('#email').value,
          location: $('#location').value,
        },
        email: $('#email').value,
        profile: {
          bio: $('#bio').value,
          avatar: $('#avatarImg').src.includes('color-logo.svg')
            ? null
            : $('#avatarImg').src.split('/').pop(), // Extract filename
        },
      };

      console.log('Saving profile:', formData);
      await app.settingsManager.updateProfile(formData);
      this.showSuccess('Profile updated successfully');

      // Reload user data to ensure we have latest state
      await this.loadUserData();
    } catch (error) {
      console.error('Error saving profile:', error);
      this.showError('Failed to save profile');
    }

    return false;
  },

  async savePreferences() {
    try {
      const preferences = {
        emailNotifications: {
          campaignUpdates: $('#notifCampaign').checked,
          newDonations: $('#notifDonations').checked,
          milestones: $('#notifMilestones').checked,
          marketing: $('#notifMarketing').checked,
        },
      };

      await app.settingsManager.updatePreferences(preferences);
      this.showSuccess('Preferences updated successfully');
    } catch (error) {
      console.error('Error saving preferences:', error);
      this.showError('Failed to save preferences');
    }
  },

  async updateData(collection, id, data) {
    const url = `${this.config.apiBase}/${collection}?id=${id}`;
    const response = await fetch(url, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
      },
      body: JSON.stringify(data),
    });
    if (!response.ok)
      throw new Error(`HTTP error! status: ${response.status}`);
    return await response.json();
  },

  changeSection(section) {
    this.state.currentSection = section;
    this.render();
  },

  getCurrentUserId() {
    const token = localStorage.getItem('accessToken');
    if (!token) {
      window.location.href = '/pages/login.html';
      return null;
    }

    const decoded = app.api.decodeToken(token);
    return decoded?.sub; // 'sub' is the standard JWT claim for user ID
  },
  showSuccess(message) {
    this.state.saveStatus = { type: 'success', message };
    this.render();
    setTimeout(() => {
      this.state.saveStatus = null;
      this.render();
    }, 3000);
  },
  showError(message) {
    this.state.saveStatus = { type: 'error', message };
    this.render();
  },
  render() {
    // Update active section
    $$('.nav-item').forEach((item) => {
      item.classList.toggle(
        'active',
        item.dataset.section === this.state.currentSection
      );
    });
    $$('.settings-section').forEach((section) => {
      section.classList.toggle(
        'active',
        section.id === this.state.currentSection
      );
    });

    // Update save status message
    const statusEl = $('#saveStatus');
    if (this.state.saveStatus) {
      statusEl.textContent = this.state.saveStatus.message;
      statusEl.className = `success-message ${this.state.saveStatus.type}`;
      statusEl.style.display = 'block';
    } else {
      statusEl.style.display = 'none';
    }

   // Update loading state
    document.body.classList.toggle('loading', this.state.loading);
  },
  // Check if KYC verification is required and show the reminder if needed
  async checkKycVerification() {
    try {
      // Only proceed if we have the KYC manager module
      if (!window.app || !window.app.kycManager) {
        return;
      }

      // Check if verification is required
      const isRequired =
        await window.app.kycManager.isVerificationRequired();

      if (isRequired) {
        document.getElementById(
          'kyc-verification-reminder'
        ).style.display = 'block';

        // Add event listener to the verification button
        document
          .getElementById('start-verification-btn')
          .addEventListener('click', function () {
            window.location.href = '/verification.html';
            // pages/kyc-verification.html  ??
          });
      }
    } catch (error) {
      console.error('Error checking KYC verification status:', error);
    }
  },
  async uploadPersonalInfo(personalData) {
    try {
      // Store personal data in the application state for later use
      Object.assign(this.data, personalData);
      
      // Format the date correctly for MongoDB
      const formattedData = {
        ...personalData,
        status: "pending"
      };

      console.log('Sending document data:', formattedData);

      // Send the data to the server
      const response = await fetch(this.config.apiBase + '/document/create', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
        },
        body: JSON.stringify(formattedData)
      });

      // Log the raw response for debugging
      const responseText = await response.text();
      console.log('Server response text:', responseText);
      
      // Parse the response as JSON
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (e) {
        console.error('Failed to parse response as JSON:', e);
        throw new Error(`Invalid server response: ${responseText}`);
      }

      if (!result.success) {
        throw new Error(result.error || 'Failed to save personal information');
      }

      // Store document ID for next steps
      const documentId = result.documentId;
      if (!documentId) {
        throw new Error('No document ID returned from server');
      }

      console.log('Personal info saved, document ID:', documentId);

      // Return object with documentId directly as required by nextToDocument handler
      return {
        success: true,
        documentId: documentId
      };
    } catch (error) {
      console.error('Error processing personal information:', error);
      throw error;
    }
  },

  async uploadDocumentFile(file, documentInfo) {
    const formData = new FormData();
    
    // Add the file
    formData.append('document', file);
    
    // Add document type parameter to match backend expectation
    formData.append('type', 'document');
    formData.append('documentType', documentInfo.documentType);
    
    // Add document ID from previous step 
    if (this.data.documentId) {
      formData.append('documentId', this.data.documentId);
      console.log('Including document ID in upload:', this.data.documentId);
    } else {
      console.error('No document ID available for document upload');
    }
    
    // Add document metadata
    if (documentInfo.documentNumber) formData.append('documentNumber', documentInfo.documentNumber);
    if (documentInfo.documentExpiry) formData.append('documentExpiry', documentInfo.documentExpiry);

    try {
      console.log('Uploading document with form data...');
      
      // Log FormData for debugging
      for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + (pair[1] instanceof File ? pair[1].name + ' (' + pair[1].size + ' bytes)' : pair[1]));
      }
      
      const response = await fetch(this.config.apiBase + '/document/upload', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
        },
        body: formData
      });

      // Get the full response text for debugging
      const responseText = await response.text();
      console.log('Document upload response:', responseText);
      
      // Try to parse as JSON
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (e) {
        console.error('Failed to parse response as JSON:', e);
        throw new Error(`Invalid server response: ${responseText}`);
      }

      if (!result.success) {
        throw new Error(result.error || 'Failed to upload document');
      }

      // Store document ID for selfie upload if not already set
      if (result.documentId && !this.data.documentId) {
        this.data.documentId = result.documentId;
        console.log('Document uploaded, ID:', this.data.documentId);
      }

      return result;
    } catch (error) {
      console.error('Error uploading document:', error);
      throw error;
    }
  },

  async uploadSelfie(file, documentId) {
    // Use document ID from parameter or app data
    const docId = documentId || this.data.documentId;
    if (!docId) {
      throw new Error('Document ID is required to upload selfie');
    }

    const formData = new FormData();
    
    // Debug the file object
    console.log("uploadSelfie received file:", file);
    console.log("File name:", file.name);
    console.log("File type:", file.type);
    console.log("File size:", file.size);
    
    // Use the correct field name that the server expects
    formData.append('selfie', file);
    
    // Set type to selfie for backend
    formData.append('type', 'selfie');
    
    // Add the document ID 
    formData.append('documentId', docId);

    try {
      // Log FormData for debugging
      console.log('FormData entries:');
      for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + (pair[1] instanceof File ? pair[1].name + ' (' + pair[1].size + ' bytes)' : pair[1]));
      }
      
      const response = await fetch(this.config.apiBase + '/document/upload', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
        },
        body: formData
      });
      
      // Get full response text for debugging
      const responseText = await response.text();
      console.log('Selfie upload response:', responseText);
      
      // Try to parse the response as JSON
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (e) {
        console.error('Failed to parse response as JSON:', e);
        throw new Error(`Invalid server response: ${responseText}`);
      }
      
      if (!result.success) {
        throw new Error(result.error || 'Failed to upload selfie');
      }

      console.log('Selfie upload successful:', result);
      return result;
    } catch (error) {
      console.error('Error uploading selfie:', error);
      throw error;
    }
  },
  
  async completeVerification(documentId) {
    try {
      if (!documentId) {
        throw new Error('Document ID is required to complete verification');
      }
      
      console.log('Completing verification for document ID:', documentId);
      
      // First try the reviewed parameter approach
      try {
        const response = await fetch(this.config.apiBase + '/document/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
          },
          body: JSON.stringify({
            documentId: documentId,
            reviewed: true
          })
        });

        // Get full response text for debugging
        const responseText = await response.text();
        console.log('Verification completion response:', responseText);
        
        // Try to parse response as JSON
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (e) {
          console.error('Failed to parse response as JSON:', e);
          throw new Error(`Invalid server response: ${responseText}`);
        }

        if (!result.success) {
          throw new Error(result.error || 'Failed to complete verification');
        }

        console.log('Verification completed successfully:', result);
        
        // Populate the verification report with user's data
        this.populateVerificationReport(documentId);
        
        return result;
      } catch (error) {
        console.error('First verification attempt failed:', error);
        
        // If the first approach fails, try the direct verification approach
        console.log('Trying direct verification approach');
        
        const response = await fetch(this.config.apiBase + '/document/verify/' + documentId, {
          method: 'GET',
          headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('accessToken')
          }
        });
        
        const responseText = await response.text();
        console.log('Direct verification response:', responseText);
        
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (e) {
          console.error('Failed to parse direct verification response as JSON:', e);
          throw new Error(`Invalid server response: ${responseText}`);
        }
        
        if (!result.success) {
          throw new Error(result.error || 'Failed to complete verification');
        }
        
        // Populate the verification report with user's data
        this.populateVerificationReport(documentId);
        
        return result;
      }
    } catch (error) {
      console.error('Error completing verification:', error);
      throw error;
    }
  },
  
  // New function to populate the verification report
  populateVerificationReport(documentId) {
    try {
      // Get the values from the form elements
      const firstName = $('#firstName').value;
      const lastName = $('#lastName').value;
      const dateOfBirth = $('#dateOfBirth').value;
      const address = $('#address').value;
      const city = $('#city').value;
      const state = $('#state').value;
      const postalCode = $('#postalCode').value;
      const country = $('#country').value;
      const documentType = $('#documentType').value;
      const documentNumber = $('#documentNumber').value;
      const documentExpiry = $('#documentExpiry').value;
      
      // Format the date of birth nicely
      const dob = new Date(dateOfBirth);
      const formattedDob = dob.toLocaleDateString('en-US', {
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
      });
      
      // Format document type to be more readable
      const documentTypeMap = {
        'passport': 'Passport',
        'drivers_license': 'Driver\'s License',
        'national_id': 'National ID Card',
        'residence_permit': 'Residence Permit'
      };
      
      const formattedDocType = documentTypeMap[documentType] || documentType;
      
      // Format document expiry date
      const expiry = new Date(documentExpiry);
      const formattedExpiry = expiry.toLocaleDateString('en-US', {
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
      });
      
      // Format submission date (current date)
      const now = new Date();
      const formattedSubmissionDate = now.toLocaleDateString('en-US', {
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      
      // Populate the report fields
      $('#report-name').textContent = `${firstName} ${lastName}`;
      $('#report-dob').textContent = formattedDob;
      $('#report-address').textContent = address;
      $('#report-location').textContent = `${city}, ${state} ${postalCode}, ${country}`;
      
      $('#report-doc-type').textContent = formattedDocType;
      $('#report-doc-number').textContent = documentNumber;
      $('#report-doc-expiry').textContent = formattedExpiry;
      
      $('#report-status').textContent = 'Submitted';
      $('#report-status').classList.add('pending');
      $('#report-date').textContent = formattedSubmissionDate;
      
      // Get the document and selfie image previews
      const docImgSrc = $('#idDocumentPreview img').src;
      const selfieImgSrc = $('#selfiePreview img').src;
      
      // Set the background images for the report
      $('#report-doc-image').style.backgroundImage = `url(${docImgSrc})`;
      $('#report-selfie-image').style.backgroundImage = `url(${selfieImgSrc})`;
      
      // Add face comparison results if available
      if (result.verificationResult) {
        const faceMatch = result.verificationResult.similarity 
          ? `${Math.round(result.verificationResult.similarity * 100)}%` 
          : 'N/A';
        const confidence = result.verificationResult.confidence 
          ? `${Math.round(result.verificationResult.confidence * 100)}%` 
          : 'N/A';
        const liveness = result.verificationResult.liveness 
          ? `${Math.round(result.verificationResult.liveness * 100)}%` 
          : 'N/A';
        
        $('#report-face-match').textContent = faceMatch;
        $('#report-confidence').textContent = confidence;
        $('#report-liveness').textContent = liveness;
      } else {
        $('#report-face-match').textContent = 'N/A';
        $('#report-confidence').textContent = 'N/A';
        $('#report-liveness').textContent = 'N/A';
      }
      
      console.log('Verification report populated successfully');
    } catch (error) {
      console.error('Error populating verification report:', error);
    }
  },
};

window.app = app;
document.addEventListener('DOMContentLoaded', () => app.init());
document.addEventListener('DOMContentLoaded', app.checkKycVerification);
})();

