(function(){
  const defaultConfig = {
    message: '',
    type: 'danger',
    dismissible: true,
    autoDismiss: true,
    timeout: 5000,
    fade: true
  };

const configs = {
  
  // When a user attempts to register using an email address that already exists
  authEmailExists: {
    message: 'Email address already registered.',
    type: 'warning'
  },

  // When a user attempts to login but provides a bad email address and/or password
  authInvalidCredentials: {
    message: 'Invalid email/password combination. Please try again.',
    type: 'danger'
  },

  // When a user attempts to login or register but provides an invalid email address
  authInvalidEmail: {
    message: 'Please enter a valid email address.',
    type: 'warning'
  },

  // When a user attempts to login or register but does not complete the CAPTCHA
  authRecaptchaRequired: {
    message: 'Please complete the CAPTCHA to prove you are not a robot.',
    type: 'warning'
  },

  // When a user attempts to login or register but provides a password less than 8 characters
  authPasswordTooShort: {
    message: 'Password must be at least 8 characters.',
    type: 'warning'
  },

  // General failure on auth.php
  authUnrecognizedAction: {
    message: 'Unrecognized action.',
    type: 'danger'
  },

  // When a user has an email address ending in @gmail.com entered in the form and clicks the "Reset Password" link
  forgotGmailAccount: {
    message: 'You cannot reset the password of a Gmail account using this form.',
    type: 'warning'
  },

  // When a user clicks the "Reset Password" link but does not have a valid email address entered in the form
  forgotInvalidEmail: {
    message: 'Please enter a valid email address above first.',
    type: 'warning'
  },

  // When the "Reset Password" function fails due to a network error
  forgotNetworkError: {
    message: 'Network error. Please try again.',
    type: 'danger'
  },

  // When a user successfully initiates a password reset
  forgotSuccess: {
    message: 'If the email address is registered, please check your inbox for a link to reset your password.',
    type: 'success'
  },

  // Google sign-in failure
  googleSigninError: {
    message: 'Google sign-in failed. Please try again.',
    type: 'danger'
  },
  
  // Error with Google ID token when attempting to sign in
  googleSigninInvalidToken: {
    message: 'Invalid ID token.',
    type: 'danger'
  },

  // Network error during Google sign-in
  googleSigninNetworkError: {
    message: 'Network error during Google sign-in. Please try again.',
    type: 'danger'
  },

  // No token provided during Google sign-in
  googleSigninNoToken: {
    message: 'No token provided.',
    type: 'danger'
  },

  // Server error occurred during Google sign-in
  googleSigninServerError: {
    message: 'A server error occurred. Please try again.',
    type: 'danger'
  },

  // When a user has an email address ending in @gmail.com entered in the form and clicks the "Login" or "Register" buttons
  loginGmailBlock: {
    message: 'You cannot log in or register with a Gmail account using this form. Please use the "Continue with Google" button above.',
    type: 'warning'
  },

  // When a user visits reset_password.php and provides a valid, expired token
  resetExpiredToken: {
    message: 'This reset link has expired.',
    type: 'danger',
    dismissible: false,
    autoDismiss: false,
    timeout: 0
  },

  // When a user visits reset_password.php and provides an invalid token
  resetInvalidToken: {
    message: 'Invalid token.',
    type: 'danger',
    dismissible: false,
    autoDismiss: false,
    timeout: 0
  },

  // When a user visits reset_password.php and does not provide a token
  resetNoToken: {
    message: 'No token provided.',
    type: 'danger',
    dismissible: false,
    autoDismiss: false,
    timeout: 0
  },

  // When a user attempts to reset their password but provides a password less than 8 characters
  resetPasswordTooShort: {
    message: 'Password must be at least 8 characters.',
    type: 'warning'
  },

  // When a user attepts to reset their password but the provided passwords do not match each other
  resetPasswordMismatch: {
    message: 'Passwords do not match.',
    type: 'warning'
  },

  // When a user successfully resets their password
  resetSuccess: {
    message: 'Your password has been reset. You may now <a href="index.php" class="alert-link">return to the login page</a>.',
    type: 'success',
    dismissible: false,
    autoDismiss: false,
    timeout: 0
  },

  // When the application cannot record a vote
  voteError: {
    message: 'Error submitting vote. Please try again.',
    type: 'danger'
  },

  // Invalid CSRF token
  voteInvalidCsrf: {
    message: 'Invalid CSRF token. Please refresh the page and try again.',
    type: 'danger'
  },

  // When the server receives an invalid vote input
  voteInvalidInput: {
    message: 'Invalid vote request.',
    type: 'danger'
  },

  // Generic network error
  voteNetworkError: {
    message: 'Network error. Please try again.',
    type: 'danger'
  },

  // When a user attempts to submit a vote but is not logged in
  voteNotLoggedIn: {
    message: 'You must be logged in to vote!',
    type: 'warning'
  },

  // When a user attempts to submit multiple votes and exceeds the rate limit
  voteRateLimit: {
    message: "You're voting too fast! Please wait a moment.",
    type: 'primary'
  },

  // Server could not record a vote
  voteServerError: {
    message: 'A server error prevented your vote from being saved. Please try again.',
    type: 'danger'
  }
};

  let currentAlert = null;
  let alertTimer   = null;
  function showAlert(key, overrides={}){
    const cfg = { ...defaultConfig, ...(configs[key]||{}), ...overrides };
    if(Object.prototype.hasOwnProperty.call(cfg, 'dismissable')){
      cfg.dismissable = cfg.dismissible
      delete cfg.dismissable;
    }
    const container = document.getElementById('alert-container');
    if(!container || !cfg.message) return;

    if(currentAlert){
      currentAlert.querySelector('.alert-body').innerHTML = cfg.message;
      resetTimer();
      return;
    }
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${cfg.type}` +
      (cfg.dismissible ? ' alert-dismissible' : '') +
      (cfg.fade ? ' fade' : '');
    alertDiv.setAttribute('role','alert');
    alertDiv.setAttribute('data-bs-theme','dark');
    alertDiv.innerHTML = `<span class="alert-body">${cfg.message}</span>`;
    if(cfg.dismissible){
      alertDiv.innerHTML += '<button type="button" class="btn-close btn-close-white" aria-label="Close"></button>';
    }
    container.append(alertDiv);
    currentAlert = alertDiv;
    if(cfg.fade) requestAnimationFrame(()=>alertDiv.classList.add('show'));

    function dismiss(){
      if(cfg.fade){
        alertDiv.classList.remove('show');
        alertDiv.addEventListener('transitionend',()=>{
          if(alertDiv.parentElement) alertDiv.remove();
          if(currentAlert===alertDiv) currentAlert=null;
        },{once:true});
      } else {
        if(alertDiv.parentElement) alertDiv.remove();
        if(currentAlert===alertDiv) currentAlert=null;
      }
      clearTimeout(alertTimer);
    }
    if(cfg.dismissible){
      alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);
    }
    function resetTimer(){
      clearTimeout(alertTimer);
      if(cfg.autoDismiss && cfg.timeout > 0) alertTimer=setTimeout(dismiss,cfg.timeout);
    }
    resetTimer();
  }

  let currentModalAlert = null;
  let modalAlertTimer   = null;
  function showModalAlert(key, overrides={}){
    const cfg = { ...defaultConfig, ...(configs[key]||{}), ...overrides };
    if(Object.prototype.hasOwnProperty.call(cfg, 'dismissable')){
      cfg.dismissible = cfg.dismissable;
      delete cfg.dismissable;
    }
    if(!cfg.message) return;
    if(currentModalAlert){
      clearTimeout(modalAlertTimer);
      currentModalAlert.remove();
      currentModalAlert=null;
    }
    const dialog = document.querySelector('#authModal .modal-dialog');
    if(!dialog) return;
    const rect = dialog.getBoundingClientRect();
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${cfg.type}` +
      (cfg.dismissible ? ' alert-dismissible' : '') +
      (cfg.fade ? ' fade' : '');
    alertDiv.setAttribute('role','alert');
    alertDiv.setAttribute('data-bs-theme','dark');
    alertDiv.style.position='absolute';
    alertDiv.style.top=`${rect.bottom + 8}px`;
    alertDiv.style.left=`${rect.left}px`;
    alertDiv.style.width=`${rect.width}px`;
    alertDiv.style.zIndex='1060';
    alertDiv.innerHTML = cfg.message;
    if(cfg.dismissible){
      alertDiv.innerHTML += '<button type="button" class="btn-close btn-close-white" aria-label="Close"></button>';
    }
    document.body.append(alertDiv);
    currentModalAlert=alertDiv;
    if(cfg.fade) requestAnimationFrame(()=>alertDiv.classList.add('show'));

    function dismiss(){
      if(cfg.fade){
        alertDiv.classList.remove('show');
        alertDiv.addEventListener('transitionend',()=>{
          if(alertDiv.parentElement) alertDiv.remove();
        },{once:true});
      } else {
        if(alertDiv.parentElement) alertDiv.remove();
      }
      currentModalAlert=null;
      clearTimeout(modalAlertTimer);
    }
    if(cfg.dismissible){
      alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);
    }
    if(cfg.autoDismiss && cfg.timeout > 0){
      modalAlertTimer=setTimeout(dismiss,cfg.timeout);
    }
  }

  function showFromKeys(keys=[], options={}){
    if(!Array.isArray(keys) || !keys.length) return;
    const validKeys = keys.filter(key => configs[key] && configs[key].message);
    if(!validKeys.length) return;
    const combinedMessage = validKeys.map(key => configs[key].message).join('<br>');
    const baseKey = validKeys[0];
    const overrides = { message: combinedMessage };
    if(options.modal){
      showModalAlert(baseKey, overrides);
    } else {
      showAlert(baseKey, overrides);
    }
  }

  window.Alerts = {
    show: showAlert,
    showModal: showModalAlert,
    showFromKeys,
    config: configs
  };
  // Backward compatibility
  window.showAlert = showAlert;
  window.showModalAlert = showModalAlert;
})();
