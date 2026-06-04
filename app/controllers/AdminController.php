<?php
declare(strict_types=1);

final class AdminController extends Controller {
    public function index(array $params = []): void {
        Auth::require('admin');
        $this->render('admin/index', [
            'title'  => 'Admin',
            'counts' => User::counts(),
        ]);
    }

    public function providers(array $params = []): void {
        Auth::require('admin');
        $this->render('admin/providers', [
            'title'     => 'Punetoret',
            'providers' => Provider::allWithStatus(),
        ]);
    }

    public function toggleActive(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin/providers'); }
        User::toggleActive($id);
        $this->flash('info', 'Statusi u perditesua.');
        $this->redirect('/admin/providers');
    }

    public function togglePremium(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin/providers'); }
        Provider::togglePremium($id);
        $this->flash('info', 'Premium u perditesua.');
        $this->redirect('/admin/providers');
    }

    public function categories(array $params = []): void {
        Auth::require('admin');
        $this->render('admin/categories', [
            'title'      => 'Kategorite',
            'categories' => Category::all(),
        ]);
    }

    public function createCategory(array $params = []): void {
        Auth::require('admin');
        $name = trim((string)Request::post('name', ''));
        $slug = trim((string)Request::post('slug', ''));
        $icon = trim((string)Request::post('icon', '')) ?: null;

        if ($name === '' || $slug === '') {
            $this->flash('danger', 'Emri dhe slug-u jane te detyrueshem.');
            $this->redirect('/admin/categories');
        }
        if (Category::findBySlug($slug)) {
            $this->flash('danger', 'Slug-u ekziston.');
            $this->redirect('/admin/categories');
        }
        Category::create($name, $slug, $icon);
        $this->flash('success', 'Kategoria u shtua.');
        $this->redirect('/admin/categories');
    }

    public function deleteCategory(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin/categories'); }
        if (Category::hasProviders($id)) {
            $this->flash('danger', 'Nuk mund ta fshini: ka punetore te lidhur.');
            $this->redirect('/admin/categories');
        }
        Category::delete($id);
        $this->flash('info', 'Kategoria u fshi.');
        $this->redirect('/admin/categories');
    }

    public function deleteReview(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin'); }
        $r = Review::find($id);
        if (!$r) { $this->flash('danger', 'Nuk u gjet.'); $this->redirect('/admin'); }
        Review::delete($id);
        $this->flash('info', 'Vleresimi u fshi.');
        $this->redirect('/provider/' . (int)$r['provider_id']);
    }
}
