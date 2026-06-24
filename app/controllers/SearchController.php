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

        // District fallback: a city was picked but nobody operates there →
        // look in the rest of the same Kosovo district so we can suggest
        // someone nearby instead of an empty page.
        $nearbyProviders = [];
        $nearbyDistrict  = null;
        if ($city && !$providers) {
            $siblingIds = Geography::siblingCityIds((int)$city['id']);
            if ($siblingIds) {
                $nearbyProviders = Provider::searchInCities($siblingIds, $categoryId);
                $nearbyDistrict  = Geography::districtOfCityName((string)$city['name']);
            }
        }

        $this->render('search/results', [
            'title'            => 'Rezultatet',
            'providers'        => $providers,
            'city'             => $city,
            'category'         => $category,
            'cities'           => City::all(),
            'categories'       => Category::all(),
            'nearby_providers' => $nearbyProviders,
            'nearby_district'  => $nearbyDistrict,
        ]);
    }
}
