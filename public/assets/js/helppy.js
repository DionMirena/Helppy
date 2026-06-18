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

  document.addEventListener('DOMContentLoaded', function () {
    autoHideFlashes();
    if (!window.HELPPY_BASE) return;
    refreshBadges();
    setInterval(refreshBadges, 15000);
  });

  // Expose for the chat page to call after sending/refreshing
  window.HELPPY = window.HELPPY || {};
  window.HELPPY.refreshBadges = refreshBadges;
})();
