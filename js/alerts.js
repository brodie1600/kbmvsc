// alerts.js - centralized Bootstrap alert helpers and configuration

const AlertsConfig = {
  loginRequired: {
    text: 'You must be logged in to vote!',
    type: 'warning'
  },
  rateLimit: {
    text: "You're voting too fast! Please wait a moment.",
    type: 'primary'
  },
  submitError: {
    text: 'Error submitting vote.',
    type: 'warning'
  },
  networkError: {
    text: 'Network error. Please try again.',
    type: 'warning'
  },
  modalInvalidEmail: {
    text: 'Please enter a valid email address above first.',
    type: 'warning'
  },
  modalGmailReset: {
    text: 'You cannot reset the password of a Gmail account using this form.',
    type: 'danger'
  },
  modalResetSent: {
    text: 'If the email address is registered, please check your inbox for a link to reset your password.',
    type: 'success'
  },
  modalGmailLogin: {
    text: 'You cannot log in or register with a Gmail account using this form. Please use the "Continue with Google" button above.',
    type: 'warning'
  }
};

function buildAlertConfig(keyOrOptions, overrides = {}) {
  const base = typeof keyOrOptions === 'string'
    ? (AlertsConfig[keyOrOptions] || { text: keyOrOptions })
    : keyOrOptions;
  const cfg = Object.assign({
    text: '',
    type: 'danger',
    fade: true,
    dismissible: true,
    autoDismiss: true,
    timeout: 5000
  }, base, overrides);
  return cfg;
}

// ------- Toast-style alert -------
let currentAlert = null;
let alertTimer   = null;
function showAlert(key, overrides = {}) {
  const cfg = buildAlertConfig(key, overrides);
  const container = document.getElementById('alert-container');
  if (!container) return;

  if (currentAlert) {
    currentAlert.remove();
    clearTimeout(alertTimer);
    currentAlert = null;
  }

  const alertDiv = document.createElement('div');
  alertDiv.classList.add('alert', `alert-${cfg.type}`);
  if (cfg.dismissible) alertDiv.classList.add('alert-dismissible');
  if (cfg.fade) alertDiv.classList.add('fade');
  alertDiv.setAttribute('role', 'alert');
  alertDiv.setAttribute('data-bs-theme', 'dark');
  alertDiv.innerHTML = cfg.dismissible
    ? `<span class="alert-body">${cfg.text}</span><button type="button" class="btn-close btn-close-white" aria-label="Close"></button>`
    : `<span class="alert-body">${cfg.text}</span>`;
  container.append(alertDiv);
  currentAlert = alertDiv;

  if (cfg.fade) requestAnimationFrame(() => alertDiv.classList.add('show'));

  function remove() {
    if (alertDiv.parentElement) alertDiv.remove();
    if (currentAlert === alertDiv) currentAlert = null;
  }

  function dismiss() {
    if (cfg.fade) {
      alertDiv.classList.remove('show');
      alertDiv.addEventListener('transitionend', remove, { once: true });
    } else {
      remove();
    }
    clearTimeout(alertTimer);
  }

  if (cfg.dismissible) {
    alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);
  }

  if (cfg.autoDismiss) {
    alertTimer = setTimeout(dismiss, cfg.timeout);
  }
}

// ------- Modal relative alert -------
let currentModalAlert = null;
let modalAlertTimer   = null;
function showModalAlert(key, overrides = {}) {
  const cfg = buildAlertConfig(key, overrides);

  if (currentModalAlert) {
    currentModalAlert.remove();
    clearTimeout(modalAlertTimer);
    currentModalAlert = null;
  }

  const dialog = document.querySelector('#authModal .modal-dialog');
  if (!dialog) return;
  const rect = dialog.getBoundingClientRect();

  const alertDiv = document.createElement('div');
  alertDiv.classList.add('alert', `alert-${cfg.type}`);
  if (cfg.dismissible) alertDiv.classList.add('alert-dismissible');
  if (cfg.fade) alertDiv.classList.add('fade');
  alertDiv.setAttribute('role', 'alert');
  alertDiv.setAttribute('data-bs-theme', 'dark');
  alertDiv.style.position = 'absolute';
  alertDiv.style.top      = `${rect.bottom + 8}px`;
  alertDiv.style.left     = `${rect.left}px`;
  alertDiv.style.width    = `${rect.width}px`;
  alertDiv.style.zIndex   = '1060';
  alertDiv.innerHTML = cfg.dismissible
    ? `${cfg.text}<button type="button" class="btn-close btn-close-white" aria-label="Close"></button>`
    : cfg.text;
  document.body.append(alertDiv);
  currentModalAlert = alertDiv;

  if (cfg.fade) requestAnimationFrame(() => alertDiv.classList.add('show'));

  function remove() {
    if (alertDiv.parentElement) alertDiv.remove();
    currentModalAlert = null;
  }

  function dismiss() {
    if (cfg.fade) {
      alertDiv.classList.remove('show');
      alertDiv.addEventListener('transitionend', remove, { once: true });
    } else {
      remove();
    }
    clearTimeout(modalAlertTimer);
  }

  if (cfg.dismissible) {
    alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);
  }

  if (cfg.autoDismiss) {
    modalAlertTimer = setTimeout(dismiss, cfg.timeout);
  }
}

window.AlertsConfig  = AlertsConfig;
window.showAlert     = showAlert;
window.showModalAlert = showModalAlert;
