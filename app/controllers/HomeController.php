<?php
declare(strict_types=1);

final class HomeController extends Controller {
    public function index(array $params = []): void {
        $this->render('home/index', ['title' => 'Helppy.com - Punues per shtepi']);
    }
}
