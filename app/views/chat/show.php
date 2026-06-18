<section class="container py-4">
  <div class="chat-shell">
    <div class="chat-header">
      <a class="chat-back" href="<?= e(CONFIG['base_url']) ?>/chat" title="Mbrapa"><i class="bi bi-arrow-left"></i></a>
      <div class="chat-peer-avatar"><i class="bi bi-person-circle"></i></div>
      <div class="chat-peer-meta">
        <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$otherId ?>" class="chat-peer-name">
          <?= e($otherName) ?>
        </a>
        <div class="chat-peer-status" data-helppy-typing="">online</div>
      </div>
      <?php if (Auth::role() === 'admin'): ?>
        <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/conversations/<?= (int)$conv['id'] ?>/delete"
              onsubmit="return confirm('FSHI të gjithë bisedën përgjithmonë?');">
          <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
          <button class="btn btn-sm btn-outline-danger" type="submit" title="Fshi bisedën (admin)">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <div id="chat-thread" class="chat-thread"
         data-conv-id="<?= (int)$conv['id'] ?>"
         data-viewer-id="<?= (int)$viewerId ?>">
      <?php foreach ($messages as $m): ?>
        <div class="chat-bubble <?= (int)$m['sender_id'] === (int)$viewerId ? 'is-mine' : 'is-theirs' ?>"
             data-msg-id="<?= (int)$m['id'] ?>">
          <div class="chat-text"><?= nl2br(e($m['body'])) ?></div>
          <div class="chat-meta"><?= e(date('H:i', strtotime((string)$m['created_at']))) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$messages): ?>
        <div class="chat-empty">
          <i class="bi bi-chat-dots"></i>
          <p>Nis bisedën — shkruaj mesazhin tënd më poshtë.</p>
        </div>
      <?php endif; ?>
    </div>

    <form class="chat-composer" method="post" action="<?= e(CONFIG['base_url']) ?>/chat/<?= (int)$conv['id'] ?>/message">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
      <textarea name="body" rows="1" maxlength="4000" placeholder="Shkruaj një mesazh..." required></textarea>
      <button type="submit" class="btn btn-helppy" aria-label="Dërgo"><i class="bi bi-send-fill"></i></button>
    </form>
  </div>
</section>

<script>
(function () {
  var thread = document.getElementById('chat-thread');
  if (!thread) return;
  var convId   = parseInt(thread.dataset.convId, 10);
  var viewerId = parseInt(thread.dataset.viewerId, 10);
  var bubbles  = thread.querySelectorAll('[data-msg-id]');
  var lastId   = bubbles.length ? parseInt(bubbles[bubbles.length - 1].dataset.msgId, 10) : 0;

  function scrollBottom() { thread.scrollTop = thread.scrollHeight; }
  scrollBottom();

  function fmtTime(iso) {
    var d = new Date(iso.replace(' ', 'T'));
    return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
  }
  function escapeHTML(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function appendMessage(m) {
    var div = document.createElement('div');
    div.className = 'chat-bubble ' + (m.is_mine ? 'is-mine' : 'is-theirs');
    div.dataset.msgId = m.id;
    div.innerHTML = '<div class="chat-text">' + escapeHTML(m.body).replace(/\n/g, '<br>') + '</div>'
                  + '<div class="chat-meta">' + fmtTime(m.created_at) + '</div>';
    var empty = thread.querySelector('.chat-empty');
    if (empty) empty.remove();
    thread.appendChild(div);
    lastId = Math.max(lastId, m.id);
    scrollBottom();
  }

  function poll() {
    fetch(window.HELPPY_BASE + '/api/chat/' + convId + '/messages.json?after=' + lastId, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.messages) return;
        data.messages.forEach(appendMessage);
      })
      .catch(function () { /* silent */ });
  }
  setInterval(poll, 3000);

  // ===== AJAX send =====
  var form = document.querySelector('.chat-composer');
  var ta   = form ? form.querySelector('textarea') : null;
  var btn  = form ? form.querySelector('button[type=submit]') : null;
  var sending = false;

  function doSend() {
    if (!form || sending) return;
    var body = ta.value.replace(/\s+$/, '');
    if (!body) return;
    sending = true;
    if (btn) btn.disabled = true;

    var data = new FormData(form);
    fetch(form.action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: data
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (resp) {
        if (resp && resp.message) {
          appendMessage(resp.message);
          ta.value = '';
          autoGrow();
          ta.focus();
        } else {
          // Server didn't return JSON — fall back to a normal POST.
          form.submit();
        }
      })
      .catch(function () {
        // Network failure → fallback
        form.submit();
      })
      .finally(function () {
        sending = false;
        if (btn) btn.disabled = false;
      });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      doSend();
    });
  }

  // Enter to send, Shift+Enter for newline
  if (ta) {
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        doSend();
      }
    });
  }

  // Auto-grow the textarea up to the CSS max-height
  function autoGrow() {
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
  }
  if (ta) {
    ta.addEventListener('input', autoGrow);
    autoGrow();
  }
})();
</script>
