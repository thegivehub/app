// Integration with registration flow
document.addEventListener('DOMContentLoaded', function() {
    // Find registration form in your existing page
    const registrationForm = document.getElementById('registrationForm');
    
    if (!registrationForm) {
        console.warn('Registration form not found, address validation integration skipped');
        return;
    }
    
    // Add address validation step to your flow if needed
    let addressValidationInitialized = false;
    let addressValidated = false;
    
    // Find which step in the registration process needs address validation
    const initAddressValidation = function() {
        if (addressValidationInitialized) return;
        
        // Look for the address fields container in your form
        const addressContainer = document.querySelector('.address-fields-container');
        if (!addressContainer) return;
        
        // Load the address form into the container
        fetch('/address-form.html')
            .then(response => response.text())
            .then(html => {
                // Insert the address form HTML
                addressContainer.innerHTML = html;
                
                // Initialize any scripts
                const script = document.createElement('script');
                script.src = '/js/address-validation.js';
                document.head.appendChild(script);
                
                // Listen for address validation success
                document.addEventListener('addressValidated', function(e) {
                    addressValidated = true;
                    
                    // Store the validated address in hidden fields
                    const address = e.detail.address;
                    
                    // Either update hidden fields in your form or store in session storage
                    for (const [field, value] of Object.entries(address)) {
                        const hiddenField = document.getElementById(`address_${field}`);
                        if (hiddenField) {
                            hiddenField.value = value;
                        } else {
                            // Create hidden field if it doesn't exist
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.id = `address_${field}`;
                            input.name = `address[${field}]`;
                            input.value = value;
                            registrationForm.appendChild(input);
                        }
                    }
                    
                    // Enable next button or form submission
                    const nextButton = document.querySelector('.next-button');
                    if (nextButton) {
                        nextButton.disabled = false;
                    }
                });
                
                addressValidationInitialized = true;
            })
            .catch(error => {
                console.error('Error loading address validation form:', error);
            });
    };
    
    // If using a multi-step form with steps navigation
    const stepIndicators = document.querySelectorAll('.step-indicator');
    
    if (stepIndicators.length) {
        // Find which step contains address fields
        const addressStep = Array.from(stepIndicators).find(
            step => step.textContent.toLowerCase().includes('address') || 
                  step.dataset.step === 'address'
        );
        
        if (addressStep) {
            // Initialize when that step becomes active
            const stepNumber = addressStep.dataset.step;
            
            // Watch for step changes
            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (mutation.attributeName === 'class' && 
                        mutation.target.classList.contains('active') && 
                        mutation.target.dataset.step === stepNumber) {
                        initAddressValidation();
                    }
                });
            });
            
            stepIndicators.forEach(step => {
                observer.observe(step, { attributes: true });
            });
        } else {
            // If we can't determine which step, initialize on load
            initAddressValidation();
        }
    } else {
        // Not a multi-step form, initialize on load
        initAddressValidation();
    }
    
    // Override form submission to validate address
    registrationForm.addEventListener('submit', function(e) {
        // If we're supposed to validate the address and haven't yet
        const addressValidationRequired = document.getElementById('addressForm') !== null;
        
        if (addressValidationRequired && !addressValidated) {
            e.preventDefault();
            
            // Trigger address validation if not already validated
            if (window.addressValidator) {
                window.addressValidator.validate();
            } else {
                console.error('Address validator not initialized');
            }
            
            return false;
        }
        
        // Proceed with normal form submission
        return true;
    });
});
