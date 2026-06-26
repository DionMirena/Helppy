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
        // Strip only shows umbrella categories (those with children) — the rest
        // are still reachable via the "Kërko kategori" search panel.
        $topLevel  = Category::topLevelWithChildren();
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

    /** Only 'person'/'company' are valid; anything else means "all". */
    private static function normalizeType(string $t): string {
        return in_array($t, ['person', 'company'], true) ? $t : '';
    }
}
