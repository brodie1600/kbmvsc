// script.js

// keep references so we never create more than one
let currentAlert = null;
let alertTimer   = null;

function showAlert(idOrMsg, opts = {}) {
  const container = document.getElementById('alert-container');
  if (!container) return;

  const base = window.alertConfigs && window.alertConfigs[idOrMsg];
  const defaults = { text: '', type: 'danger', fade: true, autoHide: true, timeout: 5000, dismissible: true };
  const cfg = Object.assign({}, defaults, base ? base : { text: idOrMsg }, opts);

  if (currentAlert) {
    currentAlert.querySelector('.alert-body').textContent = cfg.text;
    resetTimer();
    return;
  }

  const alertDiv = document.createElement('div');
  let classes = `alert alert-${cfg.type}`;
  if (cfg.dismissible) classes += ' alert-dismissible';
  if (cfg.fade) classes += ' fade';
  alertDiv.className = classes;
  alertDiv.setAttribute('role', 'alert');
  alertDiv.setAttribute('data-bs-theme', 'dark');
  let html = `<span class="alert-body">${cfg.text}</span>`;
  if (cfg.dismissible) {
    html += '<button type="button" class="btn-close btn-close-white" aria-label="Close"></button>';
  }
  alertDiv.innerHTML = html;
  container.append(alertDiv);
  currentAlert = alertDiv;

  if (cfg.fade) requestAnimationFrame(() => alertDiv.classList.add('show'));

  function dismiss() {
    if (cfg.fade) {
      alertDiv.classList.remove('show');
      alertDiv.addEventListener('transitionend', () => {
        if (alertDiv.parentElement) alertDiv.remove();
        if (currentAlert === alertDiv) currentAlert = null;
      }, { once: true });
    } else {
      if (alertDiv.parentElement) alertDiv.remove();
      if (currentAlert === alertDiv) currentAlert = null;
    }
    clearTimeout(alertTimer);
  }

  if (cfg.dismissible) alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);

  function resetTimer() {
    clearTimeout(alertTimer);
    if (cfg.autoHide && cfg.timeout > 0) alertTimer = setTimeout(dismiss, cfg.timeout);
  }
  resetTimer();
}

// ensures only one modal-alert exists at a time
let currentModalAlert = null;
let modalAlertTimer   = null;

function showModalAlert(idOrMsg, opts = {}) {
  const base = window.alertConfigs && window.alertConfigs[idOrMsg];
  const defaults = { text: '', type: 'danger', fade: true, autoHide: true, timeout: 5000, dismissible: true };
  const cfg = Object.assign({}, defaults, base ? base : { text: idOrMsg }, opts);

  // If one is already up, dismiss it immediately
  if (currentModalAlert) {
    clearTimeout(modalAlertTimer);
    currentModalAlert.remove();
    currentModalAlert = null;
  }

  // find modal-dialog and position
  const dialog = document.querySelector('#authModal .modal-dialog');
  if (!dialog) return;
  const rect = dialog.getBoundingClientRect();

  // create alert DIV
  const alertDiv = document.createElement('div');
  let classes = `alert alert-${cfg.type}`;
  if (cfg.dismissible) classes += ' alert-dismissible';
  if (cfg.fade) classes += ' fade';
  alertDiv.className = classes;
  alertDiv.setAttribute('role', 'alert');
  alertDiv.setAttribute('data-bs-theme', 'dark');
  alertDiv.style.position = 'absolute';
  alertDiv.style.top      = `${rect.bottom + 8}px`;
  alertDiv.style.left     = `${rect.left}px`;
  alertDiv.style.width    = `${rect.width}px`;
  alertDiv.style.zIndex   = '1060';
  let html = `${cfg.text}`;
  if (cfg.dismissible) {
    html += '<button type="button" class="btn-close btn-close-white" aria-label="Close"></button>';
  }
  alertDiv.innerHTML = html;
  document.body.append(alertDiv);
  currentModalAlert = alertDiv;

  if (cfg.fade) requestAnimationFrame(() => alertDiv.classList.add('show'));

  // helper to fade-out then remove
  function dismiss() {
    if (cfg.fade) {
      alertDiv.classList.remove('show');
      alertDiv.addEventListener('transitionend', () => {
        if (alertDiv.parentElement) alertDiv.remove();
      }, { once: true });
    } else {
      if (alertDiv.parentElement) alertDiv.remove();
    }
    currentModalAlert = null;
    clearTimeout(modalAlertTimer);
  }

  if (cfg.dismissible) alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);

  if (cfg.autoHide && cfg.timeout > 0) modalAlertTimer = setTimeout(dismiss, cfg.timeout);
}

// Single DOMContentLoaded listener
document.addEventListener('DOMContentLoaded', () => {
  // 1) Block Gmail addresses on manual login/register
  const authForm  = document.getElementById('authForm');
  const emailInput = document.getElementById('authEmail');
  if (authForm && emailInput) {
    authForm.addEventListener('submit', e => {
      const email = emailInput.value.trim().toLowerCase();
      if (email.endsWith('@gmail.com')) {
        e.preventDefault();
        showModalAlert('gmailAuth');
      }
    });
  }

  // 2) Forgot Password Handler
  const forgotLink = document.getElementById('forgotLink');
  if (forgotLink) {
    forgotLink.addEventListener('click', e => {
      e.preventDefault();
      const email = emailInput.value.trim();
      const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (!validEmail) {
        return showModalAlert('invalidEmail');
      }
      if (email.toLowerCase().endsWith('@gmail.com')) {
        return showModalAlert('gmailReset');
      }
      // send the reset request
      fetch('forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      })
      .then(res => res.json())
      .then(() => {
        showModalAlert('resetNotice');
      })
      .catch(() => {
        showModalAlert('networkError', { type: 'danger' });
      });
    });
  }

  // 3) Voting icons and expand/collapse
  document.querySelectorAll('.game-block').forEach(block => {
    const gameId = block.dataset.gameId;
    const header = block.querySelector('.header-row');

    header.addEventListener('click', e => {
      if (e.target.closest('.vote-icon')) return;
      block.classList.toggle('expanded');
      block.classList.toggle('collapsed');
    });

    block.querySelectorAll('.vote-icon').forEach(icon => {
      icon.addEventListener('click', e => {
        e.stopPropagation();
        if (!isLoggedIn) {
          showAlert('notLoggedIn');
          return;
        }
        const type      = icon.dataset.voteType;
        const wasActive = icon.classList.contains('active');

        fetch('vote.php', {
          method:      'POST',
          credentials: 'same-origin',
          headers:     { 'Content-Type': 'application/json' },
          body:        JSON.stringify({
            game_id:    gameId,
            vote_type:  type,
            csrf_token: csrfToken
          })
        })
        .then(res => {
          if (res.status === 429) {
            // Rate-limit hit
            showAlert('rateLimit');
            throw new Error('rate_limit');
          }
          return res.json();
        })
        .then(data => {
          if (!data.success) {
            // other errors (e.g. db_error)
            showAlert('submitError', { text: data.error || window.alertConfigs.submitError.text });
            return;
          }
          // --- success UI update ---
          const k     = data.kbm;
          const c     = data.controller;
          const total = k + c;
          const pctK  = total ? (k/total)*100 : 50;

          block.querySelectorAll('.vote-icon').forEach(i => i.classList.remove('active'));
          if (!wasActive) icon.classList.add('active');

          block.querySelector('.count-label-inner').textContent = `${k} — ${total} — ${c}`;
          block.querySelector('.kbm-bar').style.width        = pctK + '%';
          block.querySelector('.controller-bar').style.width = (100 - pctK) + '%';
          block.querySelector('.tick').style.left            = pctK + '%';

          let iconSrc = 'icons/question.svg';
          if (k > c)      iconSrc = 'icons/kbm-drkong.svg';
          else if (c > k) iconSrc = 'icons/controller-lgtong.svg';
          block.querySelector('.majority-icon').src = iconSrc;
        })
        .catch(err => {
          // suppress the thrown rate_limit error
          if (err.message === 'rate_limit') return;
          // network or other exception
          showAlert('networkError');
        });
      });
    });    
  });
});
