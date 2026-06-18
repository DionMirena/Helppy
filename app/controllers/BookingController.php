<?php
declare(strict_types=1);

final class BookingController extends Controller {

    /** GET /provider/{id}/book — client picks date/time/notes */
    public function createForm(array $params = []): void {
        Auth::require();
        if (Auth::role() !== 'client') {
            $this->flash('danger', 'Vetëm klientët mund të rezervojnë.');
            $this->redirect('/provider/' . (int)($params['id'] ?? 0));
            return;
        }
        $providerId = (int)($params['id'] ?? 0);
        $provider = Provider::find($providerId);
        if (!$provider || empty($provider['is_active']) || empty($provider['email_verified'])) {
            $this->notFound();
            return;
        }
        $this->render('bookings/create', [
            'title'    => 'Rezervo — ' . ($provider['name'] ?? ''),
            'provider' => $provider,
            'old'      => [],
            'errors'   => [],
        ]);
    }

    /** POST /provider/{id}/book */
    public function store(array $params = []): void {
        Auth::require();
        if (Auth::role() !== 'client') {
            http_response_code(403);
            View::render('errors/403', []);
            return;
        }
        $providerId = (int)($params['id'] ?? 0);
        $provider = Provider::find($providerId);
        if (!$provider) { $this->notFound(); return; }

        $clientId = (int)Auth::user()['id'];
        if ($clientId === $providerId) {
            $this->flash('danger', 'Nuk mund të rezervosh vetes.');
            $this->redirect('/provider/' . $providerId);
            return;
        }

        $date     = trim((string)Request::post('date', ''));
        $time     = trim((string)Request::post('time', ''));
        $duration = Request::post('duration_hours');
        $duration = ($duration === '' || $duration === null || !is_numeric($duration)) ? null : (float)$duration;
        $notes    = trim((string)Request::post('notes', ''));

        $errors = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['date'] = 'Datë e pavlefshme.';
        } elseif ($date < date('Y-m-d')) {
            $errors['date'] = 'Data duhet të jetë sot ose më vonë.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $errors['time'] = 'Orë e pavlefshme (përdor HH:MM).';
        }
        if ($duration !== null && ($duration <= 0 || $duration > 24)) {
            $errors['duration_hours'] = 'Kohëzgjatja: 0–24 orë.';
        }
        if (mb_strlen($notes) > 2000) {
            $errors['notes'] = 'Shënimet deri 2000 karaktere.';
        }

        if ($errors) {
            $this->render('bookings/create', [
                'title'    => 'Rezervo — ' . ($provider['name'] ?? ''),
                'provider' => $provider,
                'old'      => $_POST,
                'errors'   => $errors,
            ]);
            return;
        }

        $scheduledAt = $date . ' ' . $time . ':00';
        $bookingId = Booking::create($clientId, $providerId, $scheduledAt, $duration, $notes !== '' ? $notes : null);

        // Notify provider in-app + email
        $clientName = Auth::user()['name'];
        $whenLabel = date('d M Y, H:i', strtotime($scheduledAt));
        Notification::create(
            $providerId,
            'booking.requested',
            'Rezervim i ri nga ' . $clientName,
            'Data: ' . $whenLabel . ($notes !== '' ? "\n\nShënime: " . mb_substr($notes, 0, 240) : ''),
            '/bookings/' . $bookingId
        );
        Helpers::sendEmailSafe(
            (string)$provider['email'],
            'Rezervim i ri në Helppy.com',
            "Klienti {$clientName} ka kërkuar një rezervim për {$whenLabel}.\n\nHyni në https://helppy.com.loc/bookings/{$bookingId} për të pranuar ose refuzuar."
        );

        $this->flash('success', 'Rezervimi u dërgua. Punëtori do të njoftohet.');
        $this->redirect('/bookings/' . $bookingId);
    }

    /** GET /bookings — list bookings depending on viewer role */
    public function index(array $params = []): void {
        Auth::require();
        $role = Auth::role();
        $uid  = (int)Auth::user()['id'];
        if ($role === 'client') {
            $bookings = Booking::forClient($uid);
            $perspective = 'client';
        } elseif ($role === 'provider') {
            $bookings = Booking::forProvider($uid);
            $perspective = 'provider';
        } else { // admin
            $bookings = array_merge(Booking::forClient($uid), Booking::forProvider($uid));
            $perspective = 'both';
        }
        $this->render('bookings/index', [
            'title'       => 'Rezervimet',
            'bookings'    => $bookings,
            'perspective' => $perspective,
        ]);
    }

    /** GET /bookings/{id} */
    public function show(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $b = Booking::find($id);
        if (!$b) { $this->notFound(); return; }
        $uid = (int)Auth::user()['id'];
        $isClient   = (int)$b['client_id']   === $uid;
        $isProvider = (int)$b['provider_id'] === $uid;
        $isAdmin    = Auth::role() === 'admin';
        if (!$isClient && !$isProvider && !$isAdmin) {
            http_response_code(403); View::render('errors/403', []); return;
        }
        $this->render('bookings/show', [
            'title'      => 'Rezervim #' . $id,
            'b'          => $b,
            'isClient'   => $isClient,
            'isProvider' => $isProvider,
            'isAdmin'    => $isAdmin,
        ]);
    }

    /** POST /bookings/{id}/accept|reject|cancel|complete */
    public function transition(array $params = []): void {
        Auth::require();
        $id     = (int)($params['id'] ?? 0);
        $action = (string)($params['action'] ?? '');
        $b = Booking::find($id);
        if (!$b) { $this->notFound(); return; }
        $uid = (int)Auth::user()['id'];
        $isClient   = (int)$b['client_id']   === $uid;
        $isProvider = (int)$b['provider_id'] === $uid;

        $allowed = [
            'accept'   => ['provider', ['pending'],            Booking::STATUS_ACCEPTED,  'U pranua.'],
            'reject'   => ['provider', ['pending'],            Booking::STATUS_REJECTED,  'U refuzua.'],
            'cancel'   => ['client',   ['pending','accepted'], Booking::STATUS_CANCELLED, 'U anulua.'],
            'complete' => ['provider', ['accepted'],           Booking::STATUS_COMPLETED, 'U shenua si i përfunduar.'],
        ];
        if (!isset($allowed[$action])) { $this->notFound(); return; }
        [$role, $fromStatuses, $newStatus, $flashMsg] = $allowed[$action];
        if (($role === 'client' && !$isClient) || ($role === 'provider' && !$isProvider)) {
            http_response_code(403); View::render('errors/403', []); return;
        }
        if (!in_array($b['status'], $fromStatuses, true)) {
            $this->flash('danger', 'Veprimi nuk është i lejuar nga ky status.');
            $this->redirect('/bookings/' . $id);
            return;
        }

        Booking::setStatus($id, $newStatus);

        // Notify the other party
        $otherId   = $role === 'provider' ? (int)$b['client_id'] : (int)$b['provider_id'];
        $otherName = $role === 'provider' ? (string)$b['client_name'] : (string)$b['provider_name'];
        $otherEmail = $role === 'provider' ? (string)$b['client_email'] : (string)$b['provider_email'];
        $myName    = Auth::user()['name'];
        $title     = "Rezervimi #{$id} — {$flashMsg}";
        $body      = "{$myName} {$flashMsg}";
        Notification::create($otherId, 'booking.' . $newStatus, $title, $body, '/bookings/' . $id);
        Helpers::sendEmailSafe($otherEmail, $title, "Përshëndetje {$otherName},\n\n{$body}\n\nDetajet: https://helppy.com.loc/bookings/{$id}");

        $this->flash('success', $flashMsg);
        $this->redirect('/bookings/' . $id);
    }
}
