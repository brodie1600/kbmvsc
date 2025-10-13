// script.js - site interactions that rely on Alerts

// Ensure Alerts.js is loaded before this file

document.addEventListener('DOMContentLoaded', () => {
  if (window.serverAlerts) {
    const payload = window.serverAlerts;
    const keys = Array.isArray(payload.keys) ? payload.keys : [];
    const mode = payload.mode === 'modal' ? 'modal' : 'inline';
    const openAuthModal = Boolean(payload.openAuthModal);

    if (openAuthModal && window.bootstrap && window.bootstrap.Modal) {
      const modalEl = document.getElementById('authModal');
      if (modalEl) {
        const modalInstance = window.bootstrap.modal.getOrCreateInstance(modalEl);
        modalInstance.show();
      }
    }

    if (keys.length) {
      if (mode === 'modal') {
        if (keys.length === 1) {
          Alerts.showModal(keys[0]);
        } else {
          Alerts.showFromKeys(keys, { modal: true });
        }
      } else {
        if (keys.length === 1) {
          Alerts.show(keys[0]);
        } else {
          Alerts.showFromKeys(keys);
        }
      }
    }
  }
  
  // 1) Block Gmail addresses on manual login/register
  const authForm  = document.getElementById('authForm');
  const emailInput = document.getElementById('authEmail');
  if (authForm && emailInput) {
    authForm.addEventListener('submit', e => {
      const email = emailInput.value.trim().toLowerCase();
      if (email.endsWith('@gmail.com')) {
        e.preventDefault();
        Alerts.showModal('loginGmailBlock');
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
        return Alerts.showModal('forgotInvalidEmail');
      }
      if (email.toLowerCase().endsWith('@gmail.com')) {
        return Alerts.showModal('forgotGmailAccount');
      }
      // send the reset request
      fetch('forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      })
      .then(res => res.json())
      .then(() => {
        Alerts.showModal('forgotSuccess');
      })
      .catch(() => {
        Alerts.showModal('forgotNetworkError');
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
          Alerts.show('voteNotLoggedIn');
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
            Alerts.show('voteRateLimit');
            throw new Error('rate_limit');
          }
          return res.json();
        })
        .then(data => {
          if (!data.success) {
            const alertKey = data.alertKey || 'voteError';
            Alerts.show(alertKey);
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
          Alerts.show('voteNetworkError');
        });
      });
    });
  });
});
