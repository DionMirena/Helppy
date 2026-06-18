<?php
declare(strict_types=1);

final class NotificationController extends Controller {

    /** GET /notifications */
    public function index(array $params = []): void {
        Auth::require();
        $uid = (int)Auth::user()['id'];
        $notes = Notification::forUser($uid, 100);
        // Mark all read once the user views the list
        Notification::markAllRead($uid);
        $this->render('notifications/index', [
            'title' => 'Njoftimet',
            'notes' => $notes,
        ]);
    }

    /** POST /notifications/read-all */
    public function readAll(array $params = []): void {
        Auth::require();
        $uid = (int)Auth::user()['id'];
        Notification::markAllRead($uid);
        $this->flash('success', 'Të gjitha njoftimet u shënuan si të lexuara.');
        $this->redirect('/notifications');
    }

    /** GET /api/notifications/unread.json — used by the bell badge poll */
    public function unreadJson(array $params = []): void {
        if (!Auth::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['count' => 0]);
            return;
        }
        $uid = (int)Auth::user()['id'];
        $count = Notification::unreadCount($uid);
        $chatUnread = Conversation::totalUnreadForUser($uid);
        header('Content-Type: application/json');
        echo json_encode(['count' => $count, 'chat_unread' => $chatUnread]);
    }
}
