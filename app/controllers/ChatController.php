<?php
declare(strict_types=1);

final class ChatController extends Controller {

    /** GET /chat — list conversations */
    public function index(array $params = []): void {
        Auth::require();
        $uid = (int)Auth::user()['id'];
        $conversations = Conversation::forUser($uid);
        $this->render('chat/index', [
            'title'         => 'Bisedat',
            'conversations' => $conversations,
        ]);
    }

    /** GET /chat/with/{userId} — open or create a conversation, then redirect to /chat/{id} */
    public function start(array $params = []): void {
        Auth::require();
        $otherId = (int)($params['user_id'] ?? 0);
        $uid     = (int)Auth::user()['id'];
        if ($otherId <= 0 || $otherId === $uid) { $this->notFound(); return; }
        // Confirm the other user exists
        $r = DB::q('SELECT id FROM users WHERE id = ? AND is_active = 1', [$otherId])->fetch();
        if (!$r) { $this->notFound(); return; }
        $convId = Conversation::findOrCreate($uid, $otherId);
        $this->redirect('/chat/' . $convId);
    }

    /** GET /chat/{id} — show a conversation thread */
    public function show(array $params = []): void {
        Auth::require();
        $id  = (int)($params['id'] ?? 0);
        $uid = (int)Auth::user()['id'];
        if (!Conversation::userIn($id, $uid)) { http_response_code(403); View::render('errors/403', []); return; }
        $conv = Conversation::find($id);
        if (!$conv) { $this->notFound(); return; }
        $otherId   = Conversation::otherUserId($conv, $uid);
        $otherName = (int)$conv['user_a_id'] === $uid ? $conv['user_b_name'] : $conv['user_a_name'];

        Message::markReadFor($id, $uid);
        $messages = Message::forConversation($id);

        $this->render('chat/show', [
            'title'      => 'Bisedo me ' . $otherName,
            'conv'       => $conv,
            'otherId'    => $otherId,
            'otherName'  => $otherName,
            'viewerId'   => $uid,
            'messages'   => $messages,
        ]);
    }

    /** POST /chat/{id}/message — send a message. Returns JSON when AJAX. */
    public function send(array $params = []): void {
        Auth::require();
        $id    = (int)($params['id'] ?? 0);
        $uid   = (int)Auth::user()['id'];
        $isAjax = self::wantsJson();

        if (!Conversation::userIn($id, $uid)) {
            if ($isAjax) { self::jsonError(403, 'forbidden'); return; }
            http_response_code(403); View::render('errors/403', []); return;
        }

        $body = trim((string)Request::post('body', ''));
        if ($body === '' || mb_strlen($body) > 4000) {
            if ($isAjax) { self::jsonError(422, 'invalid_body'); return; }
            $this->flash('danger', 'Mesazhi është bosh ose tepër i gjatë.');
            $this->redirect('/chat/' . $id);
            return;
        }

        $msgId = Message::send($id, $uid, $body);

        // Notify the other participant
        $conv = Conversation::find($id);
        if ($conv) {
            $otherId    = Conversation::otherUserId($conv, $uid);
            $senderName = Auth::user()['name'];
            $preview    = mb_substr($body, 0, 160);
            Notification::create(
                $otherId,
                'message.new',
                'Mesazh i ri nga ' . $senderName,
                $preview,
                '/chat/' . $id
            );
            $otherEmail = DB::q('SELECT email FROM users WHERE id = ?', [$otherId])->fetchColumn();
            if ($otherEmail) {
                Helpers::sendEmailSafe(
                    (string)$otherEmail,
                    "Mesazh i ri nga {$senderName} në Helppy.com",
                    "{$senderName} të ka shkruar:\n\n{$preview}\n\nPërgjigju në " . CONFIG['base_url'] . "/chat/{$id}"
                );
            }
        }

        if ($isAjax) {
            $row = DB::q('SELECT id, sender_id, body, created_at FROM messages WHERE id=?', [$msgId])->fetch();
            header('Content-Type: application/json');
            echo json_encode([
                'ok'      => true,
                'message' => [
                    'id'         => (int)$row['id'],
                    'sender_id'  => (int)$row['sender_id'],
                    'is_mine'    => true,
                    'body'       => (string)$row['body'],
                    'created_at' => (string)$row['created_at'],
                ],
            ]);
            return;
        }

        $this->redirect('/chat/' . $id);
    }

    /** True when the client asked for JSON (fetch/XHR). */
    private static function wantsJson(): bool {
        $xrw    = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        return strcasecmp($xrw, 'XMLHttpRequest') === 0
            || stripos($accept, 'application/json') !== false;
    }

    private static function jsonError(int $code, string $msg): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $msg]);
    }

    /** GET /api/chat/{id}/messages.json?after=N — poll for new messages */
    public function pollMessages(array $params = []): void {
        if (!Auth::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['messages' => []]);
            return;
        }
        $id  = (int)($params['id'] ?? 0);
        $uid = (int)Auth::user()['id'];
        if (!Conversation::userIn($id, $uid)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['messages' => []]);
            return;
        }
        $afterId = (int)Request::get('after', 0);
        $msgs = Message::afterId($id, $afterId);

        // Any incoming messages are now considered read by the viewer
        if ($msgs) Message::markReadFor($id, $uid);

        header('Content-Type: application/json');
        echo json_encode([
            'messages' => array_map(function ($m) use ($uid) {
                return [
                    'id'         => (int)$m['id'],
                    'sender_id'  => (int)$m['sender_id'],
                    'is_mine'    => (int)$m['sender_id'] === $uid,
                    'body'       => $m['body'],
                    'created_at' => $m['created_at'],
                ];
            }, $msgs),
        ]);
    }
}
