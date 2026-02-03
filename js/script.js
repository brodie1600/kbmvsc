// script.js - site interactions that rely on Alerts

// Ensure Alerts.js is loaded before this file

document.addEventListener('DOMContentLoaded', () => {
  const processServerAlerts = () => {
    if (processServerAlerts.handled) return;
    const payload = window.serverAlerts;
    if (!payload) {
      processServerAlerts.handled = true;
      return;
    }

    const keys = Array.isArray(payload.keys) ? payload.keys : [];
    const mode = payload.mode === 'inline' ? 'inline' : 'modal';
    const openAuthModal = Boolean(payload.openAuthModal);
    const modalEl = document.getElementById('authModal');

    const showAlerts = () => {
      if (!keys.length) return;
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
    };

    if (mode === 'modal' && openAuthModal && modalEl) {
      if (window.bootstrap && window.bootstrap.Modal) {
        const instance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        if (keys.length) {
          const handleShown = () => {
            modalEl.removeEventListener('shown.bs.modal', handleShown);
            showAlerts();
          };
          modalEl.addEventListener('shown.bs.modal', handleShown, { once: true });
        }
        instance.show();
        processServerAlerts.handled = true;
        return;
      }

      if (document.readyState === 'complete') {
        modalEl.classListn.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop')) {
          const backdrop = document.createElement('div');
          backdrop.className = 'modal-backdrop fade show';
          document.body.appendChild(backdrop);
        }
        showAlerts();
        processServerAlerts.handled = true;
        return;
      }

      // Bootstrap not yet loaded; retry once page assets have finished loading
      window.addEventListener('load', () => {
        if (processServerAlerts.handled) return;
        processServerAlerts();
        if (!processServerAlerts.handled) {
          // Final fallback if Bootstrap is still unavailable
          modalEl.classList.add('show');
          modalEl.style.display = 'block';
          modalEl.removeAttribute('aria-hidden');
          document.body.classList.add('modal-open');
          if (!document.querySelector('.modal-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
          }
          showAlerts();
          processServerAlerts.handled = true;
        }
      }, { once: true });
      return;
    }

    showAlerts();
    processServerAlerts.handled = true;
  };

  processServerAlerts();

  window.addEventListener('load', processServerAlerts, { once: true });
  
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
      .then(data => {
        if (data && data.success === false && data.error === 'steam_linked') {
          Alerts.showModal('forgotSteamAccount');
          return;
        }
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
          let borderColor = '#aaa';
          if (k > c) {
            iconSrc = 'icons/kbm-drkong.svg';
            borderColor = '#ba6c06';
          } else if (c > k) {
            iconSrc = 'icons/controller-lgtong.svg';
            borderColor = '#fcba03';
          }
          block.querySelector('.majority-icon').src = iconSrc;
          block.style.borderColor = borderColor;
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
