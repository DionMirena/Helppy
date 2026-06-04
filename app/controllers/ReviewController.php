<?php
declare(strict_types=1);

final class ReviewController extends Controller {
    public function store(array $params = []): void {
        Auth::require('client');
        $providerId = (int)($params['id'] ?? 0);
        $clientId   = (int)Auth::user()['id'];
        $rating     = (int)Request::post('rating', 0);
        $comment    = trim((string)Request::post('comment', ''));

        if ($rating < 1 || $rating > 5) {
            $this->flash('danger', 'Vleresimi duhet te jete 1-5.');
            $this->redirect('/provider/' . $providerId);
        }

        $provider = Provider::find($providerId);
        if (!$provider) {
            $this->flash('danger', 'Punetori nuk u gjet.');
            $this->redirect('/');
        }

        if (Review::existsFor($providerId, $clientId)) {
            $this->flash('danger', 'Keni vleresuar tashme kete punetor.');
            $this->redirect('/provider/' . $providerId);
        }

        Review::create($providerId, $clientId, $rating, $comment !== '' ? $comment : null);
        $this->flash('success', 'Faleminderit per vleresimin!');
        $this->redirect('/provider/' . $providerId);
    }

    public function destroy(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $r  = Review::find($id);
        if (!$r) {
            $this->flash('danger', 'Vleresimi nuk u gjet.');
            $this->redirect('/');
        }
        $uid = (int)Auth::user()['id'];
        if ($uid !== (int)$r['client_id'] && Auth::role() !== 'admin') {
            http_response_code(403);
            View::render('errors/403', []);
            exit;
        }
        Review::delete($id);
        $this->flash('info', 'Vleresimi u fshi.');
        if (Auth::role() === 'admin') $this->redirect('/provider/' . (int)$r['provider_id']);
        else                          $this->redirect('/client/dashboard');
    }
}
