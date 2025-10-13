// search.js - handles server-side search with pagination

document.addEventListener('DOMContentLoaded', () => {
  const searchInput   = document.getElementById('gameSearch');
  const listContainer = document.querySelector('.games-list');
  const loadMoreBtn   = document.getElementById('loadMoreBtn');
  if (!searchInput || !listContainer || !loadMoreBtn) return;

  const limit = 20;
  let offset  = 0;
  let query   = '';
  let fetching = false;
  let done     = false;
  let debTimer;

  function initGameBlock(block) {
    const gameId = block.dataset.gameId;
    const header = block.querySelector('.header-row');
    if (!header) return;

    header.addEventListener('click', e => {
      if (e.target.closest('.vote-icon')) return;
      block.classList.toggle('expanded');
      block.classList.toggle('collapsed');
    });

    block.querySelectorAll('.vote-icon').forEach(icon => {
      icon.addEventListener('click', e => {
        e.stopPropagation();
        if (!window.isLoggedIn) {
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
            csrf_token: window.csrfToken
          })
        })
        .then(res => res.status === 429 ? Promise.reject('rate_limit') : res.json())
        .then(data => {
          if (!data.success) {
            const alertKey = data.alertKey || 'voteError';
            Alerts.show(alertKey);
            return;
          }
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
          if (err === 'rate_limit') {
            Alerts.show('voteRateLimit');
          } else {
            Alerts.show('voteNetworkError');
          }
        });
      });
    });
  }

  function fetchGames(reset = false) {
    if (fetching || (done && !reset)) return;
    fetching = true;
    loadMoreBtn.disabled = true;
    const params = new URLSearchParams({ q: query, offset: offset, limit: limit });
    fetch('search_games.php?' + params.toString(), { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => {
        if (reset) {
          listContainer.innerHTML = '';
          offset = 0;
          done = false;
        }
        if (!html.trim()) {
          done = true;
          loadMoreBtn.style.display = 'none';
          return;
        }
        const temp = document.createElement('div');
        temp.innerHTML = html;
        temp.querySelectorAll('.game-block').forEach(el => {
          listContainer.appendChild(el);
          initGameBlock(el);
          offset++;
        });
        if (offset % limit !== 0) done = true;
        loadMoreBtn.style.display = done ? 'none' : 'inline-block';
      })
      .catch(() => {})
      .finally(() => {
        fetching = false;
        loadMoreBtn.disabled = false;
      });
  }

  function doSearch() {
    query = searchInput.value.trim();
    offset = 0;
    fetchGames(true);
  }

  searchInput.addEventListener('input', () => {
    clearTimeout(debTimer);
    debTimer = setTimeout(doSearch, 300);
  });

  loadMoreBtn.addEventListener('click', () => fetchGames());

  window.addEventListener('scroll', () => {
    if (done || fetching) return;
    const rect = loadMoreBtn.getBoundingClientRect();
    if (rect.top < window.innerHeight) fetchGames();
  });

  fetchGames();
});
