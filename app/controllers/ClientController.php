<?php
declare(strict_types=1);

final class ClientController extends Controller {
    public function dashboard(array $params = []): void {
        Auth::require('client');
        $uid     = (int)Auth::user()['id'];
        $reviews = Review::byClient($uid);
        $this->render('client/dashboard', [
            'title'   => 'Llogaria ime',
            'user'    => Auth::user(),
            'reviews' => $reviews,
        ]);
    }
}
