/* Helppy.com — small client-side helpers.
 * - Polls /api/notifications/unread.json every 15s to update the nav badges.
 * - On chat pages, polls for new messages every 3s.
 *
 * No build step; vanilla JS only.
 */
(function () {
  'use strict';

  function setBadge(el, count) {
    if (!el) return;
    if (count > 0) {
      el.textContent = count > 99 ? '99+' : String(count);
      el.hidden = false;
    } else {
      el.hidden = true;
    }
  }

  function refreshBadges() {
    var bellBadge = document.querySelector('[data-helppy-badge="notifications"]');
    var chatBadge = document.querySelector('[data-helppy-badge="chat"]');
    if (!bellBadge && !chatBadge) return Promise.resolve();
    return fetch(window.HELPPY_BASE + '/api/notifications/unread.json', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;
        setBadge(bellBadge, data.count || 0);
        setBadge(chatBadge, data.chat_unread || 0);
      })
      .catch(function () { /* silent */ });
  }

  function autoHideFlashes() {
    var flashes = document.querySelectorAll('.helppy-flash[data-helppy-autohide]');
    flashes.forEach(function (el) {
      var ms = parseInt(el.getAttribute('data-helppy-autohide'), 10) || 3000;
      setTimeout(function () {
        el.classList.add('helppy-flash-leaving');
        // Match the CSS transition duration; then remove from DOM.
        setTimeout(function () {
          if (el.parentNode) el.parentNode.removeChild(el);
        }, 350);
      }, ms);
    });
  }

  function wireCopyButtons() {
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = document.getElementById(btn.dataset.copyTarget);
        if (!target) return;
        var text = (target.innerText || target.textContent || '').trim();
        var done = function () {
          var label = btn.querySelector('.copy-label');
          var icon  = btn.querySelector('i');
          if (label) label.textContent = 'U kopjua!';
          if (icon)  icon.className = 'bi bi-check2';
          setTimeout(function () {
            if (label) label.textContent = 'Kopjo';
            if (icon)  icon.className = 'bi bi-clipboard';
          }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, function () {});
        } else {
          var range = document.createRange();
          range.selectNode(target);
          window.getSelection().removeAllRanges();
          window.getSelection().addRange(range);
          try { document.execCommand('copy'); done(); } catch (e) {}
          window.getSelection().removeAllRanges();
        }
      });
    });
  }

  function wireProvidersScroller() {
    var scroller = document.querySelector('[data-providers-scroller]');
    if (!scroller) return;

    var grid    = scroller.querySelector('[data-providers-grid]');
    var btn     = scroller.querySelector('[data-providers-load-more]');
    var endEl   = scroller.querySelector('[data-providers-end]');
    var shown   = document.querySelector('[data-providers-shown]');

    var nextOffset = parseInt(scroller.getAttribute('data-next-offset'), 10) || 0;
    var total      = parseInt(scroller.getAttribute('data-total'), 10) || 0;
    var filterType = scroller.getAttribute('data-type') || '';
    var loading    = false;
    var done       = nextOffset >= total;

    function setDone() {
      done = true;
      if (btn) btn.hidden = true;
      if (endEl) endEl.hidden = false;
    }

    if (done) { setDone(); return; }
    if (!btn) return;

    function loadMore() {
      if (loading || done) return;
      loading = true;
      btn.classList.add('is-loading');
      btn.disabled = true;

      // Fetch + 1s timer in parallel — only swap the UI once BOTH resolve,
      // so the loading state stays visible at least a second.
      var typeQs  = filterType ? '&type=' + encodeURIComponent(filterType) : '';
      var fetched = fetch((window.HELPPY_BASE || '') + '/api/providers.json?offset=' + nextOffset + typeQs, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      }).then(function (r) { return r.ok ? r.json() : null; });

      var minDelay = new Promise(function (resolve) { setTimeout(resolve, 1000); });

      Promise.all([fetched, minDelay]).then(function (results) {
        var data = results[0];
        if (!data || !data.ok) {
          btn.classList.remove('is-loading');
          btn.disabled = false;
          loading = false;
          return;
        }

        var tmp = document.createElement('div');
        tmp.innerHTML = data.html;
        var added = [];
        while (tmp.firstChild) {
          var node = tmp.firstChild;
          tmp.removeChild(node);
          if (node.nodeType === 1) {
            var card = node.querySelector ? node.querySelector('.provider-card') : null;
            if (card) card.classList.add('provider-card-new');
            added.push(node);
          }
          grid.appendChild(node);
        }
        setTimeout(function () {
          added.forEach(function (n) {
            var c = n.querySelector && n.querySelector('.provider-card-new');
            if (c) c.classList.remove('provider-card-new');
          });
        }, 500);

        nextOffset = data.next;
        if (shown) shown.textContent = grid.children.length;
        loading = false;
        btn.classList.remove('is-loading');
        btn.disabled = false;
        if (!data.has_more) setDone();
      }).catch(function () {
        loading = false;
        btn.classList.remove('is-loading');
        btn.disabled = false;
      });
    }

    // Click still works as a manual fallback.
    btn.addEventListener('click', loadMore);

    // Primary trigger: auto-load when the button enters the scroller's viewport.
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) loadMore(); });
      }, {
        root: scroller,
        rootMargin: '120px 0px',
        threshold: 0
      });
      io.observe(btn);
    } else {
      // Older browsers: scroll-based fallback.
      scroller.addEventListener('scroll', function () {
        if (scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 200) {
          loadMore();
        }
      });
    }
  }

  function wireChatPanel() {
    var triggers = document.querySelectorAll('[data-helppy-chat]');
    if (!triggers.length) return;

    var BASE = window.HELPPY_BASE || '';

    function escapeHTML(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmtTime(iso) {
      var d = new Date(String(iso).replace(' ', 'T'));
      if (isNaN(d.getTime())) return '';
      var pad = function (n) { return n.toString().padStart(2, '0'); };
      return pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function nowIsoLocal() {
      var d = new Date(), pad = function (n) { return n.toString().padStart(2, '0'); };
      return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
           + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    // ----- single shared panel + backdrop -----
    var backdrop = document.createElement('div');
    backdrop.className = 'helppy-chat-backdrop';
    backdrop.hidden = true;

    var panel = document.createElement('aside');
    panel.className = 'helppy-chat-panel';
    panel.hidden = true;
    panel.innerHTML =
      '<div class="helppy-chat-panel-header">' +
        '<div class="chat-peer-avatar"><i class="bi bi-person-circle"></i></div>' +
        '<div class="meta">' +
          '<div class="name">Bisedo</div>' +
          '<div class="sub">online</div>' +
        '</div>' +
        '<a class="expand" href="#" title="Hap të plotë" aria-label="Hap të plotë">' +
          '<i class="bi bi-box-arrow-up-right"></i>' +
        '</a>' +
        '<button type="button" class="close" title="Mbyll" aria-label="Mbyll">' +
          '<i class="bi bi-x-lg"></i>' +
        '</button>' +
      '</div>' +
      '<div class="chat-thread" data-thread></div>' +
      '<form class="chat-composer" data-composer>' +
        '<textarea name="body" rows="1" maxlength="4000" placeholder="Shkruaj një mesazh..." required></textarea>' +
        '<button type="submit" class="btn btn-helppy" aria-label="Dërgo">' +
          '<i class="bi bi-send-fill"></i>' +
        '</button>' +
      '</form>';
    document.body.appendChild(backdrop);
    document.body.appendChild(panel);

    var nameEl    = panel.querySelector('.name');
    var threadEl  = panel.querySelector('[data-thread]');
    var composer  = panel.querySelector('[data-composer]');
    var taEl      = composer.querySelector('textarea');
    var sendBtn   = composer.querySelector('button[type=submit]');
    var btnClose  = panel.querySelector('.close');
    var btnExpand = panel.querySelector('.expand');

    var state = {
      convId: 0, viewerId: 0, otherId: 0, lastId: 0,
      sending: false, pollTimer: null
    };

    function scrollBottom() { threadEl.scrollTop = threadEl.scrollHeight; }

    function appendBubble(m) {
      if (m.id && !String(m.id).startsWith('tmp-')) {
        if (threadEl.querySelector('[data-msg-id="' + m.id + '"]')) return null;
      }
      var div = document.createElement('div');
      div.className = 'chat-bubble ' + (m.is_mine ? 'is-mine' : 'is-theirs');
      if (m.pending) div.classList.add('is-pending');
      div.dataset.msgId = m.id;
      div.innerHTML =
        '<div class="chat-text">' + escapeHTML(m.body).replace(/\n/g, '<br>') + '</div>' +
        '<div class="chat-meta">' + fmtTime(m.created_at) + '</div>';
      var empty = threadEl.querySelector('.chat-empty');
      if (empty) empty.remove();
      threadEl.appendChild(div);
      if (typeof m.id === 'number') state.lastId = Math.max(state.lastId, m.id);
      scrollBottom();
      return div;
    }

    function setLoading(label) {
      threadEl.innerHTML =
        '<div class="chat-loading"><i class="bi bi-arrow-clockwise"></i> ' +
        escapeHTML(label || 'Po hapem bisedën...') + '</div>';
    }

    function setError(label) {
      threadEl.innerHTML =
        '<div class="chat-empty"><i class="bi bi-exclamation-triangle"></i>' +
        '<p>' + escapeHTML(label) + '</p></div>';
    }

    function open(userId, userName) {
      // Reset state for fresh open.
      state = { convId: 0, viewerId: 0, otherId: 0, lastId: 0, sending: false, pollTimer: null };
      nameEl.textContent = userName || 'Bisedo';
      setLoading('Po hapem bisedën...');
      btnExpand.setAttribute('href', '#');

      panel.hidden = false;
      backdrop.hidden = false;
      requestAnimationFrame(function () {
        panel.classList.add('is-open');
        backdrop.classList.add('is-open');
        document.body.classList.add('helppy-chat-open');
      });

      fetch(BASE + '/api/chat/with/' + userId + '.json', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) {
          if (r.status === 401) { window.location.href = BASE + '/login'; return null; }
          return r.ok ? r.json() : null;
        })
        .then(function (data) {
          if (!data || !data.ok) { setError('Bisedën nuk munda ta hap.'); return; }
          state.convId   = data.conv_id;
          state.viewerId = data.viewer_id;
          state.otherId  = data.other_id;
          nameEl.textContent = data.other_name || userName;
          btnExpand.setAttribute('href', BASE + '/chat/' + data.conv_id);

          threadEl.innerHTML = '';
          if (!data.messages.length) {
            threadEl.innerHTML =
              '<div class="chat-empty"><i class="bi bi-chat-dots"></i>' +
              '<p>Nis bisedën — shkruaj mesazhin tënd më poshtë.</p></div>';
          } else {
            data.messages.forEach(appendBubble);
          }
          startPolling();
          setTimeout(function () { taEl.focus(); }, 200);
        })
        .catch(function () { setError('Lidhja dështoi.'); });
    }

    function startPolling() {
      if (state.pollTimer) return;
      state.pollTimer = setInterval(function () {
        if (!state.convId) return;
        fetch(BASE + '/api/chat/' + state.convId + '/messages.json?after=' + state.lastId, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (!data || !data.messages) return;
            data.messages.forEach(appendBubble);
          })
          .catch(function () {});
      }, 3000);
    }

    function close() {
      panel.classList.remove('is-open');
      backdrop.classList.remove('is-open');
      document.body.classList.remove('helppy-chat-open');
      if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
      setTimeout(function () {
        panel.hidden = true;
        backdrop.hidden = true;
        threadEl.innerHTML = '';
      }, 250);
    }

    function send() {
      if (!state.convId || state.sending) return;
      var body = taEl.value.replace(/\s+$/, '');
      if (!body) return;
      state.sending = true;
      if (sendBtn) sendBtn.disabled = true;

      var tempId = 'tmp-' + Date.now();
      var optimistic = appendBubble({
        id: tempId, sender_id: state.viewerId, is_mine: true,
        body: body, created_at: nowIsoLocal(), pending: true
      });

      var sentBody = taEl.value;
      taEl.value = '';
      autoGrow();
      taEl.focus();

      var form = new FormData();
      form.set('_csrf', getCsrfToken());
      form.set('body', body);

      fetch(BASE + '/chat/' + state.convId + '/message', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: form
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (resp) {
          if (resp && resp.message) {
            var realId = resp.message.id;
            if (threadEl.querySelector('[data-msg-id="' + realId + '"]') && optimistic) {
              optimistic.remove();
            } else if (optimistic) {
              optimistic.dataset.msgId = realId;
              optimistic.classList.remove('is-pending');
              var meta = optimistic.querySelector('.chat-meta');
              if (meta) meta.textContent = fmtTime(resp.message.created_at);
            }
            if (typeof realId === 'number') state.lastId = Math.max(state.lastId, realId);
          } else {
            if (optimistic) optimistic.classList.add('chat-bubble-failed');
            taEl.value = sentBody;
          }
        })
        .catch(function () {
          if (optimistic) optimistic.classList.add('chat-bubble-failed');
          taEl.value = sentBody;
        })
        .finally(function () {
          state.sending = false;
          if (sendBtn) sendBtn.disabled = false;
        });
    }

    function autoGrow() {
      taEl.style.height = 'auto';
      taEl.style.height = Math.min(taEl.scrollHeight, 120) + 'px';
    }

    function getCsrfToken() {
      // Reuse any existing CSRF token from any form on the page.
      var input = document.querySelector('input[name="_csrf"]');
      return input ? input.value : '';
    }

    // Wire up
    triggers.forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var uid = a.getAttribute('data-user-id');
        var name = a.getAttribute('data-user-name') || '';
        if (!uid) return;
        open(uid, name);
      });
    });
    btnClose.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && panel.classList.contains('is-open')) close();
    });
    composer.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    taEl.addEventListener('input', autoGrow);
    taEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });
  }

  function wireLightbox() {
    // Build a per-gallery list of images, then enable click-to-open.
    var galleries = document.querySelectorAll('.gallery-grid');
    if (!galleries.length) return;

    // Single shared overlay for the whole page.
    var overlay = document.createElement('div');
    overlay.className = 'helppy-lightbox';
    overlay.setAttribute('hidden', '');
    overlay.innerHTML =
      '<button type="button" class="helppy-lightbox-close" aria-label="Mbyll">' +
        '<i class="bi bi-x-lg"></i>' +
      '</button>' +
      '<button type="button" class="helppy-lightbox-nav helppy-lightbox-prev" aria-label="Para">' +
        '<i class="bi bi-chevron-left"></i>' +
      '</button>' +
      '<button type="button" class="helppy-lightbox-nav helppy-lightbox-next" aria-label="Pas">' +
        '<i class="bi bi-chevron-right"></i>' +
      '</button>' +
      '<figure class="helppy-lightbox-figure">' +
        '<img class="helppy-lightbox-img" alt="">' +
        '<figcaption class="helppy-lightbox-caption"></figcaption>' +
        '<div class="helppy-lightbox-counter"></div>' +
      '</figure>';
    document.body.appendChild(overlay);

    var imgEl     = overlay.querySelector('.helppy-lightbox-img');
    var capEl     = overlay.querySelector('.helppy-lightbox-caption');
    var counterEl = overlay.querySelector('.helppy-lightbox-counter');
    var btnPrev   = overlay.querySelector('.helppy-lightbox-prev');
    var btnNext   = overlay.querySelector('.helppy-lightbox-next');
    var btnClose  = overlay.querySelector('.helppy-lightbox-close');

    var currentList = [];
    var currentIdx  = 0;

    function show(list, idx) {
      currentList = list;
      currentIdx  = idx;
      render();
      overlay.removeAttribute('hidden');
      // Tiny delay so the transition runs from the hidden state.
      requestAnimationFrame(function () { overlay.classList.add('is-open'); });
      document.body.classList.add('helppy-lightbox-open');
    }

    function render() {
      var item = currentList[currentIdx];
      if (!item) return;
      imgEl.src = item.src;
      imgEl.alt = item.alt || '';
      capEl.textContent = item.caption || '';
      capEl.hidden = !item.caption;
      counterEl.textContent = (currentIdx + 1) + ' / ' + currentList.length;
      var multi = currentList.length > 1;
      btnPrev.hidden = !multi;
      btnNext.hidden = !multi;
    }

    function close() {
      overlay.classList.remove('is-open');
      document.body.classList.remove('helppy-lightbox-open');
      setTimeout(function () {
        overlay.setAttribute('hidden', '');
        imgEl.src = '';
      }, 180);
    }

    function step(delta) {
      if (!currentList.length) return;
      currentIdx = (currentIdx + delta + currentList.length) % currentList.length;
      render();
    }

    btnClose.addEventListener('click', close);
    btnPrev.addEventListener('click', function () { step(-1); });
    btnNext.addEventListener('click', function () { step(1); });
    overlay.addEventListener('click', function (e) {
      // Clicking the backdrop (anything that isn't the image or a button) closes.
      if (e.target === overlay || e.target === overlay.querySelector('.helppy-lightbox-figure')) {
        close();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (overlay.hasAttribute('hidden')) return;
      if (e.key === 'Escape')      close();
      else if (e.key === 'ArrowLeft')  step(-1);
      else if (e.key === 'ArrowRight') step(1);
    });

    // Wire each gallery — build its image list and bind click on each <img>.
    galleries.forEach(function (grid) {
      var items = Array.prototype.map.call(
        grid.querySelectorAll('.gallery-item img'),
        function (img) {
          return {
            el: img,
            src: img.getAttribute('data-full') || img.src,
            alt: img.alt || '',
            caption: img.alt || ''
          };
        }
      );
      items.forEach(function (it, i) {
        it.el.style.cursor = 'zoom-in';
        it.el.addEventListener('click', function (e) {
          // Don't open if the click happened on/inside a delete button overlay.
          if (e.target.closest('.gallery-delete')) return;
          show(items, i);
        });
      });
    });
  }

  function wireBackButton() {
    var btn = document.querySelector('[data-helppy-back]');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      // If the referrer came from this same site, the browser history is
      // meaningful — go back one step. Otherwise (direct hit, refresh, new
      // tab) fall back to the homepage so the button never feels "dead".
      var ref = document.referrer;
      var sameOrigin = false;
      try {
        sameOrigin = !!ref && new URL(ref).origin === window.location.origin;
      } catch (_) {}
      if (sameOrigin && window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = (window.HELPPY_BASE || '') + '/';
      }
    });
  }

  function wireThemeToggle() {
    var btn = document.querySelector('[data-theme-toggle]');
    if (!btn) return;
    var icon = btn.querySelector('[data-theme-icon]');
    function syncIcon() {
      if (!icon) return;
      var t = document.documentElement.getAttribute('data-theme') || 'light';
      icon.className = 'bi ' + (t === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill');
    }
    syncIcon();
    btn.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-theme') || 'light';
      var next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem('helppy-theme', next); } catch (e) {}
      syncIcon();
    });
  }

  function wireShareButtons() {
    var btns = document.querySelectorAll('[data-helppy-share]');
    if (!btns.length) return;
    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var data = {
          title: btn.getAttribute('data-share-title') || document.title,
          text:  btn.getAttribute('data-share-text')  || '',
          url:   btn.getAttribute('data-share-url')   || window.location.href,
        };
        if (navigator.share) {
          navigator.share(data).catch(function () { /* user cancelled */ });
          return;
        }
        // Desktop fallback: copy URL + show "Linku u kopjua".
        var toCopy = (data.text ? data.text + ' ' : '') + data.url;
        var original = btn.innerHTML;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(toCopy).then(function () {
            btn.innerHTML = '<i class="bi bi-check2"></i> Linku u kopjua';
            setTimeout(function () { btn.innerHTML = original; }, 2000);
          });
        } else {
          window.prompt('Kopjo linkun:', toCopy);
        }
      });
    });
  }

  function wireCityPickers() {
    var pickers = document.querySelectorAll('[data-citypicker]');
    if (!pickers.length) return;

    pickers.forEach(function (root) {
      var toggle = root.querySelector('[data-citypicker-toggle]');
      var panel  = root.querySelector('[data-citypicker-panel]');
      var label  = root.querySelector('[data-citypicker-label]');
      var hidden = root.querySelector('[data-citypicker-value]');
      var search = root.querySelector('[data-citypicker-search]');
      var empty  = root.querySelector('[data-citypicker-empty]');
      var items  = Array.prototype.slice.call(root.querySelectorAll('[data-citypicker-option]'));
      var activeIdx = -1;

      function open() {
        if (!panel.hidden) return;
        panel.hidden = false;
        root.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        if (search) { search.value = ''; filter(''); search.focus(); }
        activeIdx = -1;
      }
      function close() {
        if (panel.hidden) return;
        panel.hidden = true;
        root.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        items.forEach(function (it) { it.classList.remove('is-active'); });
      }
      function pick(item) {
        var val  = item.getAttribute('data-value') || '';
        var name = item.textContent.trim();
        hidden.value = val;
        // Always use the picked item's own text — works for both real options
        // and the "is-clear" reset option, regardless of any initial label.
        label.textContent = name;
        root.classList.toggle('is-selected', !!val);
        items.forEach(function (it) {
          it.classList.toggle('is-selected', it === item && !!val);
        });
        close();
      }
      function filter(q) {
        q = q.toLowerCase().trim();
        var visibleCount = 0;
        items.forEach(function (it) {
          if (it.classList.contains('is-clear')) {
            it.classList.toggle('is-hidden', q.length > 0);
            if (q.length === 0) visibleCount++;
            return;
          }
          var name = it.getAttribute('data-name') || it.textContent.toLowerCase();
          var match = !q || name.indexOf(q) !== -1;
          it.classList.toggle('is-hidden', !match);
          if (match) visibleCount++;
        });
        if (empty) empty.hidden = visibleCount > 0;
        activeIdx = -1;
        items.forEach(function (it) { it.classList.remove('is-active'); });
      }
      function visibleItems() {
        return items.filter(function (it) { return !it.classList.contains('is-hidden'); });
      }
      function move(delta) {
        var vis = visibleItems();
        if (!vis.length) return;
        activeIdx = (activeIdx + delta + vis.length) % vis.length;
        items.forEach(function (it) { it.classList.remove('is-active'); });
        var target = vis[activeIdx];
        target.classList.add('is-active');
        target.scrollIntoView({ block: 'nearest' });
      }

      toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.hidden) open(); else close();
      });
      toggle.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          open();
        }
      });
      items.forEach(function (item) {
        item.addEventListener('click', function (e) {
          e.stopPropagation();
          pick(item);
        });
      });
      if (search) {
        search.addEventListener('input', function () { filter(search.value); });
        search.addEventListener('keydown', function (e) {
          if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
          else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
          else if (e.key === 'Enter') {
            e.preventDefault();
            var vis = visibleItems();
            if (activeIdx >= 0 && vis[activeIdx]) pick(vis[activeIdx]);
            else if (vis.length) pick(vis[0]);
          } else if (e.key === 'Escape') {
            e.preventDefault();
            close();
            toggle.focus();
          }
        });
      }
      document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) close();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { close(); toggle.focus(); }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    autoHideFlashes();
    wireCopyButtons();
    wireBackButton();
    wireLightbox();
    wireChatPanel();
    wireProvidersScroller();
    wireCityPickers();
    wireShareButtons();
    wireThemeToggle();
    if (!window.HELPPY_BASE) return;
    refreshBadges();
    setInterval(refreshBadges, 25000);
  });

  // Expose for the chat page to call after sending/refreshing
  window.HELPPY = window.HELPPY || {};
  window.HELPPY.refreshBadges = refreshBadges;
})();
