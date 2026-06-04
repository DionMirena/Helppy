<?php
declare(strict_types=1);

final class HomeController extends Controller {
    public function index(array $params = []): void {
        echo "Home route works.";
    }
}
