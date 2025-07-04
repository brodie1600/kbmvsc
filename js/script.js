// script.js

// keep references so we never create more than one
let currentAlert = null;
let alertTimer   = null;

function showAlert(message, type = 'danger') {
  const container = document.getElementById('alert-container');
  if (!container) return;

  // If already showing, update text & reset timer
  if (currentAlert) {
    currentAlert.querySelector('.alert-body').textContent = message;
    resetTimer();
    return;
  }

  // Create the alert with only .fade (no .show yet)
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} alert-dismissible fade`;
  alertDiv.setAttribute('role', 'alert');
  alertDiv.setAttribute('data-bs-theme', 'dark');
  alertDiv.innerHTML = `
    <span class="alert-body">${message}</span>
    <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
  `;
  container.append(alertDiv);
  currentAlert = alertDiv;

  // Trigger a reflow, then add .show for fade-in
  requestAnimationFrame(() => alertDiv.classList.add('show'));

  // Dismiss helper (fade-out then remove)
  function dismiss() {
    alertDiv.classList.remove('show');
    alertDiv.addEventListener('transitionend', () => {
      if (alertDiv.parentElement) alertDiv.remove();
      if (currentAlert === alertDiv) currentAlert = null;
    }, { once: true });
    clearTimeout(alertTimer);
  }

  // Wire the × button
  alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);

  // Auto-dismiss after 5 s
  function resetTimer() {
    clearTimeout(alertTimer);
    alertTimer = setTimeout(dismiss, 5000);
  }
  resetTimer();
}

// ensures only one modal-alert exists at a time
let currentModalAlert = null;
let modalAlertTimer   = null;

function showModalAlert(message, type = 'danger') {
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

  // create alert DIV with .fade only
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} alert-dismissible fade`;
  alertDiv.setAttribute('role', 'alert');
  alertDiv.setAttribute('data-bs-theme', 'dark');
  alertDiv.style.position = 'absolute';
  alertDiv.style.top      = `${rect.bottom + 8}px`;
  alertDiv.style.left     = `${rect.left}px`;
  alertDiv.style.width    = `${rect.width}px`;
  alertDiv.style.zIndex   = '1060';
  alertDiv.innerHTML = `
    ${message}
    <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
  `;
  document.body.append(alertDiv);
  currentModalAlert = alertDiv;

  // fade-in
  requestAnimationFrame(() => alertDiv.classList.add('show'));

  // helper to fade-out then remove
  function dismiss() {
    alertDiv.classList.remove('show');
    alertDiv.addEventListener('transitionend', () => {
      if (alertDiv.parentElement) alertDiv.remove();
    }, { once: true });
    currentModalAlert = null;
    clearTimeout(modalAlertTimer);
  }

  // close button wires into dismiss
  alertDiv.querySelector('.btn-close').addEventListener('click', dismiss);

  // auto-dismiss after 5 s
  modalAlertTimer = setTimeout(dismiss, 5000);
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
        showModalAlert(
          'You cannot log in or register with a Gmail account using this form. Please use the "Continue with Google" button above.',
          'warning'
        );
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
        return showModalAlert('Please enter a valid email address above first.', 'warning');
      }
      if (email.toLowerCase().endsWith('@gmail.com')) {
        return showModalAlert(
          'You cannot reset the password of a Gmail account using this form.', 'danger'
        );
      }
      // send the reset request
      fetch('forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      })
      .then(res => res.json())
      .then(() => {
        showModalAlert(
          'If the email address is registered, please check your inbox for a link to reset your password.',
          'success'
        );
      })
      .catch(() => {
        showModalAlert('Network error. Please try again.', 'danger');
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
          showAlert('You must be logged in to vote!', 'warning');
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
            showAlert("You're voting too fast! Please wait a moment.", 'primary');
            throw new Error('rate_limit');
          }
          return res.json();
        })
        .then(data => {
          if (!data.success) {
            // other errors (e.g. db_error)
            showAlert(data.error || 'Error submitting vote.', 'warning');
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
          showAlert('Network error—please try again.', 'warning');
        });
      });
    });    
  });
    // 4) Search/filter games
  const searchInput = document.getElementById('gameSearch');
  const listContainer = document.querySelector('.games-list');
  if (searchInput && listContainer) {
    const blocks = Array.from(listContainer.children);
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      if (!q) {
        // no query: restore original order & show all
        blocks.forEach(b => {
          b.style.display = '';
          listContainer.appendChild(b);
        });
        return;
      }

      // Partition: prefix-matches first, then substring-matches
      const prefixMatches    = [];
      const substringMatches = [];

      blocks.forEach(block => {
        const titleEl = block.querySelector('.title');
        const title = titleEl ? titleEl.textContent.trim().toLowerCase() : '';
        block.style.display = 'none';

        if (title.startsWith(q)) {
          prefixMatches.push(block);
        } else if (title.includes(q)) {
          substringMatches.push(block);
        }
      });

      // Show & re-append in order
      [...prefixMatches, ...substringMatches].forEach(b => {
        b.style.display = '';
        listContainer.appendChild(b);
      });
    });
  }
});
