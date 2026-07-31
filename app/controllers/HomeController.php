<?php
declare(strict_types=1);

final class HomeController extends Controller {
    public const PAGE_SIZE = 10;

    public function index(array $params = []): void {
        $type = self::normalizeType((string)Request::get('type', ''));
        $first = Provider::listPaged(0, self::PAGE_SIZE, $type);
        $total = Provider::listCount($type);

        // Category chip drill-down: when ?cat=ID, show that branch alone
        // (parent + its children) instead of the whole top-level list.
        $openCatId = (int)Request::get('cat', 0);
        $openCat   = $openCatId > 0 ? Category::find($openCatId) : null;
        // Strip shows all top-level categories; if a chip has children it drills
        // down, if not it links directly to search results.
        $topLevel  = Category::topLevel();
        $children  = $openCat ? Category::children((int)$openCat['id']) : [];

        $this->render('home/index', [
            'title'      => 'Helppy.com',
            'cities'     => City::all(),
            'categories' => Category::all(),
            'topCategories' => $topLevel,
            'openCat'    => $openCat,
            'openCatChildren' => $children,
            'featured'   => $first,
            'totalCount' => $total,
            'pageSize'   => self::PAGE_SIZE,
            'activeType' => $type,
        ]);
    }

    /**
     * GET /api/providers.json?offset=N&type=person|company
     * Returns the next $pageSize providers as ready-rendered card HTML so
     * the home page can append them without re-implementing the partial.
     */
    public function providersJson(array $params = []): void {
        $offset = (int)Request::get('offset', 0);
        $type   = self::normalizeType((string)Request::get('type', ''));
        $limit  = self::PAGE_SIZE;
        $rows   = Provider::listPaged($offset, $limit, $type);
        $total  = Provider::listCount($type);
        $hasMore = ($offset + count($rows)) < $total;

        ob_start();
        foreach ($rows as $p) {
            echo '<div class="col-12 col-sm-6 col-lg-4">';
            View::partial('provider-card', ['p' => $p]);
            echo '</div>';
        }
        $html = ob_get_clean();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'        => true,
            'html'      => $html,
            'next'      => $offset + count($rows),
            'has_more'  => $hasMore,
            'returned'  => count($rows),
            'total'     => $total,
        ]);
    }

    /** GET /si-funksionon — video tutorial page */
    public function howItWorks(array $params = []): void {
        $this->render('home/how-it-works', [
            'title'  => 'Si funksionon – Helppy.com',
            'videos' => TutorialVideo::allIndexed(),
        ]);
    }

    public function about(array $params = []): void {
        $this->render('home/about', ['title' => 'Rreth nesh – Helppy.com']);
    }

    public function contact(array $params = []): void {
        $this->render('home/contact', ['title' => 'Kontakt – Helppy.com']);
    }

    /** GET /map — full-page map of all providers with a GPS pin. */
    public function map(array $params = []): void {
        $this->render('home/map', [
            'title'      => 'Harta e punëtorëve – Helppy.com',
            'cities'     => City::all(),
            'categories' => Category::all(),
        ]);
    }

    /** GET /api/providers/map.json — provider pins as JSON for the map page. */
    public function mapJson(array $params = []): void {
        $rows = Provider::allWithLocation();
        $base = CONFIG['base_url'];
        $uploadUrl = CONFIG['upload_url'];

        $pins = [];
        foreach ($rows as $r) {
            $pins[] = [
                'id'         => (int)$r['id'],
                'name'       => $r['name'],
                'profession' => $r['profession'],
                'city'       => $r['city'] ?? '',
                'photo'      => !empty($r['photo']) ? $uploadUrl . '/' . rawurlencode($r['photo']) : $base . '/assets/img/default-avatar.svg',
                'lat'        => (float)$r['latitude'],
                'lng'        => (float)$r['longitude'],
                'avg_rating' => $r['avg_rating'] !== null ? round((float)$r['avg_rating'], 1) : null,
                'is_premium' => (bool)$r['is_premium'],
                'url'        => $base . '/provider/' . (int)$r['id'],
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'pins' => $pins]);
    }

    /** Only 'person'/'company' are valid; anything else means "all". */
    private static function normalizeType(string $t): string {
        return in_array($t, ['person', 'company'], true) ? $t : '';
    }
}
