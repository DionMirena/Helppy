<?php
declare(strict_types=1);

final class ProviderController extends Controller {
    public function show(array $params = []): void {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->notFound(); return; }

        $provider = Provider::find($id);
        if (!$provider || empty($provider['is_active'])) { $this->notFound(); return; }

        Provider::incrementViews($id);

        $reviews = Review::forProvider($id);

        $alreadyReviewed = false;
        if (Auth::check() && Auth::role() === 'client') {
            $alreadyReviewed = Review::existsFor($id, (int)Auth::user()['id']);
        }

        $this->render('provider/show', [
            'title'           => $provider['name'],
            'p'               => $provider,
            'reviews'         => $reviews,
            'alreadyReviewed' => $alreadyReviewed,
        ]);
    }

    public function dashboard(array $params = []): void   { echo 'TODO dash'; }
    public function update(array $params = []): void      { echo 'TODO update'; }
    public function uploadPhoto(array $params = []): void { echo 'TODO upload'; }
}
