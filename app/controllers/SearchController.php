<?php
declare(strict_types=1);

final class SearchController extends Controller {
    public function results(array $params = []): void {
        $cityId     = Request::get('city');
        $categoryId = Request::get('category');
        $cityId     = is_numeric($cityId) ? (int)$cityId : null;
        $categoryId = is_numeric($categoryId) ? (int)$categoryId : null;

        $providers = Provider::search($cityId, $categoryId);
        $city      = $cityId     ? City::find($cityId)         : null;
        $category  = $categoryId ? Category::find($categoryId) : null;

        $this->render('search/results', [
            'title'      => 'Rezultatet',
            'providers'  => $providers,
            'city'       => $city,
            'category'   => $category,
            'cities'     => City::all(),
            'categories' => Category::all(),
        ]);
    }
}
