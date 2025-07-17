(function(){
  const defaultConfig = {
    message: '',
    type: 'danger',
    dismissable: true,
    autoDismiss: true,
    timeout: 5000,
    fade: true
  };

  const configs = {
    loginGmailBlock: {
      message: 'You cannot log in or register with a Gmail account using this form. Please use the "Continue with Google" button above.',
      type: 'warning'
    },
    forgotInvalidEmail: {
      message: 'Please enter a valid email address above first.',
      type: 'warning'
    },
    forgotGmailAccount: {
      message: 'You cannot reset the password of a Gmail account using this form.',
      type: 'danger'
    },
    forgotSuccess: {
      message: 'If the email address is registered, please check your inbox for a link to reset your password.',
      type: 'success'
    },
    forgotNetworkError: {
      message: 'Network error. Please try again.',
      type: 'danger'
    },
    voteNotLoggedIn: {
      message: 'You must be logged in to vote!',
      type: 'warning'
    },
    voteRateLimit: {
      message: "You're voting too fast! Please wait a moment.",
      type: 'primary'
    },
    voteError: {
      message: 'Error submitting vote.',
      type: 'warning'
    },
    voteNetworkError: {
      message: 'Network error—please try again.',
      type: 'warning'
    },
    googleSigninError: {
      message: 'Google sign-in failed.',
      type: 'danger'
    },
    googleSigninNetworkError: {
      message: 'Network error during Google sign-in.',
      type: 'danger'
    }
  };

  let currentAlert = null;
  let alertTimer   = null;
  function showAlert(key, overrides={}){
    const cfg = { ...defaultConfig, ...(configs[key]||{}), ...overrides };
    const container = document.getElementById('alert-container');
    if(!container || !cfg.message) return;

    if(currentAlert){
      currentAlert.querySelector('.alert-body').innerHTML = cfg.message;
      resetTimer();
      return;
    }
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${cfg.type}` +
      (cfg.dismissable ? ' alert-dismissible' : '') +
      (cfg.fade ? ' fade' : '');
    alertDiv.setAttribute('role','alert');
    alertDiv.setAttribute('data-bs-theme','dark');
    alertDiv.innerHTML = `<span class="alert-body">${cfg.message}</span>`;
    if(cfg.dismissable){
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
    if(cfg.dismissable){
      alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);
    }
    function resetTimer(){
      clearTimeout(alertTimer);
      if(cfg.autoDismiss) alertTimer=setTimeout(dismiss,cfg.timeout);
    }
    resetTimer();
  }

  let currentModalAlert = null;
  let modalAlertTimer   = null;
  function showModalAlert(key, overrides={}){
    const cfg = { ...defaultConfig, ...(configs[key]||{}), ...overrides };
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
      (cfg.dismissable ? ' alert-dismissible' : '') +
      (cfg.fade ? ' fade' : '');
    alertDiv.setAttribute('role','alert');
    alertDiv.setAttribute('data-bs-theme','dark');
    alertDiv.style.position='absolute';
    alertDiv.style.top=`${rect.bottom + 8}px`;
    alertDiv.style.left=`${rect.left}px`;
    alertDiv.style.width=`${rect.width}px`;
    alertDiv.style.zIndex='1060';
    alertDiv.innerHTML = cfg.message;
    if(cfg.dismissable){
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
    if(cfg.dismissable){
      alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);
    }
    if(cfg.autoDismiss){
      modalAlertTimer=setTimeout(dismiss,cfg.timeout);
    }
  }

  window.Alerts = {
    show: showAlert,
    showModal: showModalAlert,
    config: configs
  };
  // Backward compatibility
  window.showAlert = showAlert;
  window.showModalAlert = showModalAlert;
})();
