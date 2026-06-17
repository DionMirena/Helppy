<?php
declare(strict_types=1);

final class PostController extends Controller {
    public function index(array $params = []): void {
        $filters = [
            'type'        => Request::get('type'),
            'category_id' => Request::get('category') !== null ? (int)Request::get('category') : null,
            'city_id'     => Request::get('city') !== null ? (int)Request::get('city') : null,
        ];

        $posts = Post::feed($filters, 60);

        $this->render('posts/index', [
            'title'      => 'Postimet',
            'posts'      => $posts,
            'filters'    => $filters,
            'categories' => Category::all(),
            'cities'     => City::all(),
        ]);
    }
}
