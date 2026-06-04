<?php
declare(strict_types=1);

final class HomeController extends Controller {
    public function index(array $params = []): void {
        $this->render('home/index', [
            'title'      => 'Helppy.com',
            'cities'     => City::all(),
            'categories' => Category::all(),
            'featured'   => Provider::featured(8),
        ]);
    }
}
