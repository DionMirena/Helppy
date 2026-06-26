<?php
declare(strict_types=1);

final class SearchController extends Controller {
    public function results(array $params = []): void {
        $cityId     = Request::get('city');
        $categoryId = Request::get('category');
        $cityId     = is_numeric($cityId) ? (int)$cityId : null;
        $categoryId = is_numeric($categoryId) ? (int)$categoryId : null;
        $query      = trim((string)Request::get('q', ''));
        $queryArg   = $query !== '' ? $query : null;

        $providers = Provider::search($cityId, $categoryId, $queryArg);
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
                $nearbyProviders = Provider::searchInCities($siblingIds, $categoryId, $queryArg);
                $nearbyDistrict  = Geography::districtOfCityName((string)$city['name']);
            }
        }

        // Drill-down state for the chip strip: if the picked category has a
        // parent, that parent is "open"; if the category IS the parent,
        // open is itself. Otherwise no drill-down (top-level chips shown).
        $openCat = null;
        if ($category) {
            if (!empty($category['parent_id'])) {
                $openCat = Category::find((int)$category['parent_id']);
            } else {
                // Top-level — check if it has children
                $childs = Category::children((int)$category['id']);
                if ($childs) $openCat = $category;
            }
        }
        $openCatChildren = $openCat ? Category::children((int)$openCat['id']) : [];

        $this->render('search/results', [
            'title'            => 'Rezultatet',
            'providers'        => $providers,
            'city'             => $city,
            'category'         => $category,
            'query'            => $query,
            'cities'           => City::all(),
            'categories'       => Category::all(),
            'topCategories'    => Category::topLevelWithChildren(),
            'openCat'          => $openCat,
            'openCatChildren'  => $openCatChildren,
            'nearby_providers' => $nearbyProviders,
            'nearby_district'  => $nearbyDistrict,
        ]);
    }
}
