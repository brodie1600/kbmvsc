// alertsConfig.js - central configuration for Bootstrap alerts
// Each key defines default properties for a named alert.
// text: message text
// type: Bootstrap contextual class suffix
// fade: whether to fade in/out
// autoHide: automatically dismiss after timeout (true/false)
// timeout: milliseconds before auto-dismiss (ignored if autoHide false or 0)
// dismissible: whether an X button is shown

window.alertConfigs = {
  notLoggedIn: {
    text: 'You must be logged in to vote!',
    type: 'warning',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  },
  rateLimit: {
    text: "You're voting too fast! Please wait a moment.",
    type: 'primary',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  },
  submitError: {
    text: 'Error submitting vote.',
    type: 'warning',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  },
  networkError: {
    text: 'Network error—please try again.',
    type: 'warning',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  },
  gmailAuth: {
    text: 'You cannot log in or register with a Gmail account using this form. Please use the "Continue with Google" button above.',
    type: 'warning',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  },
  invalidEmail: {
    text: 'Please enter a valid email address above first.',
    type: 'warning',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  },
  gmailReset: {
    text: 'You cannot reset the password of a Gmail account using this form.',
    type: 'danger',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  },
  resetNotice: {
    text: 'If the email address is registered, please check your inbox for a link to reset your password.',
    type: 'success',
    fade: true,
    autoHide: true,
    timeout: 5000,
    dismissible: true
  }
};
