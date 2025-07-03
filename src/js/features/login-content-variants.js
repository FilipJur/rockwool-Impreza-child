/**
 * Login Content Variants
 * Simplified - content is now handled by PHP output buffering
 * Domain: Authentication/Frontend
 * Design: w6FJkgIwZWiFb2nYyDbzaj
 */

/**
 * Initialize login content variants
 * Note: Content modification is now handled by PHP output buffering in functions.php
 * This JavaScript is kept minimal for any future client-side enhancements
 */
function initLoginContentVariants() {
  // Check if we're on the login page
  if (!document.querySelector('.lwp_forms_login')) {
    return;
  }

  // Prevent multiple initializations
  if (window.loginContentVariantsInitialized) {
    return;
  }
  window.loginContentVariantsInitialized = true;

  // Watch for activation form appearing
  watchForActivationForm();

  console.log('Login content variants: Initialized with activation form watcher');
}

function watchForActivationForm() {
  // Use MutationObserver to watch for activation form
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === 1) { // Element node
          // Check if activation form appeared
          const activationForm = node.querySelector ? node.querySelector('#lwp_activate[style*="display: block"]') :
                                 (node.id === 'lwp_activate' && node.style.display === 'block') ? node : null;

          if (activationForm) {
            console.log('Activation form detected, updating content...');
            updateActivationFormContent();
          }
        }
      });

      // Also check for style changes on existing activation form
      if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
        const target = mutation.target;
        if (target.id === 'lwp_activate' && target.style.display === 'block') {
          console.log('Activation form became visible, updating content...');
          updateActivationFormContent();
        }
      }
    });
  });

  // Start observing
  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['style']
  });

  // Also check immediately in case
  // form is already there
  const existingForm = document.querySelector('#lwp_activate[style*="display: block"]');
  if (existingForm) {
    console.log('Activation form already exists, updating content...');
    updateActivationFormContent();
  }
}

function updateActivationFormContent() {
  const activationForm = document.querySelector('#lwp_activate');
  if (!activationForm || activationForm.style.display !== 'block') {
    return;
  }

  // Update title
  const title = activationForm.querySelector('.lh1');
  if (title) {
    title.textContent = 'Zadejte kód, který jsme vám poslali';
  }

  // Update subtitle with link - place after title
  const subtitle = activationForm.querySelector('.lwp_labels');
  if (subtitle) {
    subtitle.innerHTML = 'Nic vám nepřišlo? Stačí kliknout a <a href="#" class="lwp_didnt_r_c">kód pošleme znovu</a>.';
    // Move subtitle after title, before input
    const title = activationForm.querySelector('.lh1');
    if (title && title.parentNode) {
      title.parentNode.insertBefore(subtitle, title.nextSibling);
    }
  }

  // Update main button text
  const mainButton = activationForm.querySelector('.submit_button.auth_secCode');
  if (mainButton) {
    mainButton.textContent = 'Registrovat';
  }

  // Update resend button text
  const resendButton = activationForm.querySelector('.submit_button.lwp_didnt_r_c');
  if (resendButton) {
    resendButton.textContent = 'Znovu odeslat kód';
  }

  // Remove the "Registraci už mám" link completely
  const changePhoneLink = activationForm.querySelector('.lwp_change_pn');
  if (changePhoneLink) {
    changePhoneLink.style.display = 'none';
  }

  // Add terms of service text if not exists
  addTermsOfServiceToActivation(activationForm);

  // Style the input to look like 6 individual boxes
  styleActivationInput();

  console.log('Activation form content updated successfully');
}

function addTermsOfServiceToActivation(form) {
  // Check if terms already exist
  if (form.querySelector('.activation-terms')) {
    return;
  }

  // Create terms element
  const termsDiv = document.createElement('div');
  termsDiv.className = 'activation-terms';
  termsDiv.innerHTML = '<span class="activation-terms-text">Telefonní číslo slouží k ověření totožnosti. Registrací souhlasíte s podmínkami programu.</span>';

  // Insert before the bottom activation section
  const bottomSection = form.querySelector('.lwp_bottom_activation');
  if (bottomSection) {
    form.insertBefore(termsDiv, bottomSection);
  } else {
    // Fallback: add at the end
    form.appendChild(termsDiv);
  }
}

function styleActivationInput() {
  const codeInput = document.querySelector('#lwp_activate .lwp_scode');
  if (!codeInput) {
    return;
  }

  // Create 6 individual input fields
  createSixDigitInputs(codeInput);
}

/**
 * Creates 6 individual OTP input fields to replace the single input
 * @param {HTMLElement} originalInput - The original lwp_scode input element
 */
function createSixDigitInputs(originalInput) {
  // Check if already transformed
  if (originalInput.dataset.transformed === 'true') {
    return;
  }

  // Hide the original input but keep it for form submission
  originalInput.style.display = 'none';
  originalInput.dataset.transformed = 'true';

  // Create container for the 6 individual inputs
  const container = document.createElement('div');
  container.className = 'otp-input-container';
  container.setAttribute('data-otp-container', 'true');

  // Create 6 individual input fields
  const inputs = [];
  for (let i = 0; i < 6; i++) {
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'otp-digit-input';
    input.setAttribute('maxlength', '1');
    input.setAttribute('pattern', '[0-9]');
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('autocomplete', 'one-time-code');
    input.setAttribute('data-index', i);
    
    inputs.push(input);
    container.appendChild(input);
  }

  // Insert the container after the original input
  originalInput.parentNode.insertBefore(container, originalInput.nextSibling);

  // Add event listeners for enhanced UX
  setupOTPInputEvents(inputs, originalInput);

  // Focus the first input
  inputs[0].focus();
}

/**
 * Sets up event listeners for the OTP input fields
 * @param {HTMLElement[]} inputs - Array of the 6 input elements
 * @param {HTMLElement} originalInput - The original hidden input for form submission
 */
function setupOTPInputEvents(inputs, originalInput) {
  inputs.forEach((input, index) => {
    // Handle digit input and auto-advance
    input.addEventListener('input', (e) => {
      const value = e.target.value;
      
      // Only allow numbers
      if (!/^\d*$/.test(value)) {
        e.target.value = '';
        return;
      }

      // Move to next input if digit entered
      if (value.length === 1 && index < 5) {
        inputs[index + 1].focus();
      }

      // Update the original input with combined value
      updateOriginalInput(inputs, originalInput);
    });

    // Handle backspace to move to previous input
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace') {
        // If current input is empty, move to previous input
        if (e.target.value === '' && index > 0) {
          inputs[index - 1].focus();
          inputs[index - 1].select();
        }
      }
      
      // Handle left/right arrow keys
      if (e.key === 'ArrowLeft' && index > 0) {
        inputs[index - 1].focus();
      } else if (e.key === 'ArrowRight' && index < 5) {
        inputs[index + 1].focus();
      }
      
      // Handle home/end keys
      if (e.key === 'Home') {
        inputs[0].focus();
      } else if (e.key === 'End') {
        inputs[5].focus();
      }
    });

    // Handle paste functionality
    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasteData = e.clipboardData.getData('text');
      
      // Only process if it's a 6-digit code
      if (/^\d{6}$/.test(pasteData)) {
        const digits = pasteData.split('');
        digits.forEach((digit, i) => {
          if (inputs[i]) {
            inputs[i].value = digit;
          }
        });
        
        // Update the original input and focus the last input
        updateOriginalInput(inputs, originalInput);
        inputs[5].focus();
        
        // Auto-submit if all fields are filled
        if (inputs.every(input => input.value !== '')) {
          // Small delay to ensure visual feedback
          setTimeout(() => {
            const submitButton = document.querySelector('#lwp_activate .submit_button.auth_secCode');
            if (submitButton) {
              submitButton.click();
            }
          }, 100);
        }
      }
    });

    // Handle focus events for better UX
    input.addEventListener('focus', (e) => {
      e.target.select();
    });
  });
}

/**
 * Updates the original hidden input with the combined value from all 6 inputs
 * @param {HTMLElement[]} inputs - Array of the 6 input elements
 * @param {HTMLElement} originalInput - The original hidden input for form submission
 */
function updateOriginalInput(inputs, originalInput) {
  const combinedValue = inputs.map(input => input.value).join('');
  originalInput.value = combinedValue;
  
  // Trigger change event on original input for any listeners
  originalInput.dispatchEvent(new Event('change', { bubbles: true }));
  
  // Auto-submit when all 6 digits are entered
  if (combinedValue.length === 6 && /^\d{6}$/.test(combinedValue)) {
    // Small delay to ensure visual feedback
    setTimeout(() => {
      const submitButton = document.querySelector('#lwp_activate .submit_button.auth_secCode');
      if (submitButton) {
        submitButton.click();
      }
    }, 100);
  }
}

/**
 * Setup function for the login content variants feature
 */
export function setupLoginContentVariants() {
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLoginContentVariants);
  } else {
    initLoginContentVariants();
  }
}

// Auto-initialize if not using module system
if (typeof window !== 'undefined' && !window.mistrFachmanModules) {
  setupLoginContentVariants();
}
