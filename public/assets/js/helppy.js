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

  document.addEventListener('DOMContentLoaded', function () {
    autoHideFlashes();
    wireCopyButtons();
    if (!window.HELPPY_BASE) return;
    refreshBadges();
    setInterval(refreshBadges, 25000);
  });

  // Expose for the chat page to call after sending/refreshing
  window.HELPPY = window.HELPPY || {};
  window.HELPPY.refreshBadges = refreshBadges;
})();
