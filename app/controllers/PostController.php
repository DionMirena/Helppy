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

    public function createForm(array $params = []): void {
        Auth::require();
        $role = Auth::role();
        if ($role !== 'client' && $role !== 'provider' && $role !== 'admin') {
            http_response_code(403);
            View::render('errors/403', []);
            return;
        }

        // Type derives from role. Admin can post either; default to 'offer'.
        $type = $role === 'client' ? 'request' : 'offer';
        if ($role === 'admin' && in_array(Request::get('type'), ['offer','request'], true)) {
            $type = Request::get('type');
        }

        // SUBSCRIPTION GATE — providers need an active subscription to post 'offer' type.
        // Admins bypass; clients only post 'request' which is always free.
        if ($type === 'offer' && $role === 'provider') {
            $uid = (int)Auth::user()['id'];
            if (!Subscription::isActive($uid)) {
                $this->flash('info', 'Për të postuar oferta, aktivizo një abonim mujor.');
                $this->redirect('/subscribe');
                return;
            }
        }

        $this->render('posts/create', [
            'title'      => $type === 'offer' ? 'Posto ofertën tënde' : 'Posto kërkesën tënde',
            'type'       => $type,
            'categories' => Category::all(),
            'cities'     => City::all(),
            'old'        => [], // empty on first render; refilled on validation failure
            'errors'     => [],
        ]);
    }

    public function store(array $params = []): void {
        Auth::require();
        $role = Auth::role();
        $uid  = (int)Auth::user()['id'];

        // Type derives from role (admin chooses via hidden field).
        $type = $role === 'client' ? 'request' : 'offer';
        if ($role === 'admin' && in_array(Request::post('type'), ['offer','request'], true)) {
            $type = Request::post('type');
        } elseif ($role !== 'client' && $role !== 'provider' && $role !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        // SUBSCRIPTION GATE — enforce on POST as well so a hand-crafted request can't bypass.
        if ($type === 'offer' && $role === 'provider' && !Subscription::isActive($uid)) {
            $this->flash('info', 'Për të postuar oferta, aktivizo një abonim mujor.');
            $this->redirect('/subscribe');
            return;
        }

        // Collect raw input
        $title       = trim((string)Request::post('title', ''));
        $description = trim((string)Request::post('description', ''));
        $categoryId  = (int)Request::post('category_id', 0);
        $cityId      = (int)Request::post('city_id', 0);

        $priceFrom = self::numOrNull(Request::post('price_from'));
        $priceTo   = self::numOrNull(Request::post('price_to'));
        $workingHours       = trim((string)Request::post('working_hours', ''));
        $contactPreferences = trim((string)Request::post('contact_preferences', ''));

        $budgetFrom = self::numOrNull(Request::post('budget_from'));
        $budgetTo   = self::numOrNull(Request::post('budget_to'));
        $deadline   = trim((string)Request::post('deadline', ''));
        $urgency    = trim((string)Request::post('urgency', ''));

        // Validate
        $errors = [];
        if (mb_strlen($title) < 4 || mb_strlen($title) > 160)            $errors['title']       = 'Titulli duhet të jetë 4-160 karaktere.';
        if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) $errors['description'] = 'Përshkrimi duhet të jetë 20-5000 karaktere.';
        if ($categoryId <= 0 || !Category::find($categoryId))            $errors['category_id'] = 'Zgjidh një kategori.';
        if ($cityId <= 0     || !City::find($cityId))                    $errors['city_id']     = 'Zgjidh një qytet.';

        if ($priceFrom !== null && ($priceFrom < 0 || $priceFrom > 1000000)) $errors['price_from'] = 'Çmim i pavlefshëm.';
        if ($priceTo   !== null && ($priceTo   < 0 || $priceTo   > 1000000)) $errors['price_to']   = 'Çmim i pavlefshëm.';
        if ($priceFrom !== null && $priceTo !== null && $priceFrom > $priceTo) $errors['price_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';

        if ($budgetFrom !== null && ($budgetFrom < 0 || $budgetFrom > 1000000)) $errors['budget_from'] = 'Buxhet i pavlefshëm.';
        if ($budgetTo   !== null && ($budgetTo   < 0 || $budgetTo   > 1000000)) $errors['budget_to']   = 'Buxhet i pavlefshëm.';
        if ($budgetFrom !== null && $budgetTo !== null && $budgetFrom > $budgetTo) $errors['budget_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';

        if (mb_strlen($workingHours)       > 120) $errors['working_hours']       = 'Orari deri 120 karaktere.';
        if (mb_strlen($contactPreferences) > 200) $errors['contact_preferences'] = 'Preferencat e kontaktit deri 200 karaktere.';

        if ($type === 'request') {
            if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                $errors['deadline'] = 'Datë e pavlefshme.';
            } elseif ($deadline !== '' && $deadline < date('Y-m-d')) {
                $errors['deadline'] = 'Afati duhet të jetë sot ose më vonë.';
            }
            if ($urgency !== '' && !in_array($urgency, ['low','normal','high'], true)) {
                $errors['urgency'] = 'Urgjenca e pavlefshme.';
            }
        }

        // Photo validation (no save yet)
        $files = self::collectUploadedPhotos('photos');
        if (count($files) > 5) {
            $errors['photos'] = 'Maksimumi 5 foto për postim.';
        }
        foreach ($files as $idx => $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) { $errors['photos'] = 'Gabim në ngarkim të fotos.'; break; }
            if ($f['size'] > 5 * 1024 * 1024) { $errors['photos'] = 'Çdo foto duhet të jetë nën 5MB.'; break; }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
                $errors['photos'] = 'Formati i lejuar: JPG, PNG, WEBP.';
                break;
            }
        }

        if ($errors) {
            $this->render('posts/create', [
                'title'      => $type === 'offer' ? 'Posto ofertën tënde' : 'Posto kërkesën tënde',
                'type'       => $type,
                'categories' => Category::all(),
                'cities'     => City::all(),
                'old'        => $_POST,
                'errors'     => $errors,
            ]);
            return;
        }

        // Build clean data
        $data = [
            'title'         => $title,
            'description'   => $description,
            'category_id'   => $categoryId,
            'city_id'       => $cityId,
        ];
        if ($type === 'offer') {
            $data['price_from']          = $priceFrom;
            $data['price_to']            = $priceTo;
            $data['working_hours']       = $workingHours !== '' ? $workingHours : null;
            $data['contact_preferences'] = $contactPreferences !== '' ? $contactPreferences : null;
        } else {
            $data['budget_from'] = $budgetFrom;
            $data['budget_to']   = $budgetTo;
            $data['deadline']    = $deadline !== '' ? $deadline : null;
            $data['urgency']     = $urgency  !== '' ? $urgency  : null;
        }

        // Insert post + photos. Roll back files on DB failure.
        try {
            DB::pdo()->beginTransaction();
            $postId = Post::create($uid, $type, $data);

            $savedFilenames = [];
            foreach ($files as $idx => $f) {
                $ext  = self::extForMime((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']));
                $name = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $name;
                if (!is_dir(CONFIG['upload_dir'])) mkdir(CONFIG['upload_dir'], 0775, true);
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    throw new RuntimeException('Photo move failed');
                }
                $savedFilenames[] = $name;
                PostPhoto::add($postId, $name, $idx);
            }
            DB::pdo()->commit();
        } catch (Throwable $ex) {
            DB::pdo()->rollBack();
            foreach ($savedFilenames ?? [] as $n) {
                @unlink(CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $n);
            }
            $this->flash('danger', 'Gabim teknik gjatë ruajtjes së postimit. Provo përsëri.');
            $this->redirect('/posts/create');
            return;
        }

        $this->flash('success', 'Postimi u publikua.');
        $this->redirect('/posts/' . $postId);
    }

    public function show(array $params = []): void {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->notFound(); return; }

        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }

        $viewerIsOwner = Auth::check() && (int)Auth::user()['id'] === (int)$post['user_id'];
        $viewerIsAdmin = Auth::role() === 'admin';

        // Hidden posts: visible to owner + admin only.
        if ($post['status'] === 'hidden' && !$viewerIsOwner && !$viewerIsAdmin) {
            $this->notFound();
            return;
        }

        Post::incrementViews($id);
        $photos = PostPhoto::forPost($id);

        $this->render('posts/show', [
            'title'         => $post['title'],
            'p'             => $post,
            'photos'        => $photos,
            'viewerIsOwner' => $viewerIsOwner,
            'viewerIsAdmin' => $viewerIsAdmin,
        ]);
    }

    public function editForm(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }

        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        $this->render('posts/edit', [
            'title'      => 'Modifiko postimin',
            'p'          => $post,
            'photos'     => PostPhoto::forPost($id),
            'categories' => Category::all(),
            'cities'     => City::all(),
            'old'        => $post, // pre-fill from current row
            'errors'     => [],
        ]);
    }

    public function update(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }

        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        $type = $post['type']; // type cannot change after creation

        // Collect raw input (mirrors store(); same validation, no type switch)
        $title       = trim((string)Request::post('title', ''));
        $description = trim((string)Request::post('description', ''));
        $categoryId  = (int)Request::post('category_id', 0);
        $cityId      = (int)Request::post('city_id', 0);
        $priceFrom   = self::numOrNull(Request::post('price_from'));
        $priceTo     = self::numOrNull(Request::post('price_to'));
        $workingHours       = trim((string)Request::post('working_hours', ''));
        $contactPreferences = trim((string)Request::post('contact_preferences', ''));
        $budgetFrom  = self::numOrNull(Request::post('budget_from'));
        $budgetTo    = self::numOrNull(Request::post('budget_to'));
        $deadline    = trim((string)Request::post('deadline', ''));
        $urgency     = trim((string)Request::post('urgency', ''));

        $errors = [];
        if (mb_strlen($title) < 4 || mb_strlen($title) > 160)            $errors['title'] = 'Titulli duhet të jetë 4-160 karaktere.';
        if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) $errors['description'] = 'Përshkrimi duhet të jetë 20-5000 karaktere.';
        if ($categoryId <= 0 || !Category::find($categoryId))            $errors['category_id'] = 'Zgjidh një kategori.';
        if ($cityId <= 0     || !City::find($cityId))                    $errors['city_id'] = 'Zgjidh një qytet.';
        if ($priceFrom !== null && ($priceFrom < 0 || $priceFrom > 1000000)) $errors['price_from'] = 'Çmim i pavlefshëm.';
        if ($priceTo   !== null && ($priceTo   < 0 || $priceTo   > 1000000)) $errors['price_to']   = 'Çmim i pavlefshëm.';
        if ($priceFrom !== null && $priceTo !== null && $priceFrom > $priceTo) $errors['price_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';
        if ($budgetFrom !== null && ($budgetFrom < 0 || $budgetFrom > 1000000)) $errors['budget_from'] = 'Buxhet i pavlefshëm.';
        if ($budgetTo   !== null && ($budgetTo   < 0 || $budgetTo   > 1000000)) $errors['budget_to']   = 'Buxhet i pavlefshëm.';
        if ($budgetFrom !== null && $budgetTo !== null && $budgetFrom > $budgetTo) $errors['budget_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';
        if (mb_strlen($workingHours)       > 120) $errors['working_hours']       = 'Orari deri 120 karaktere.';
        if (mb_strlen($contactPreferences) > 200) $errors['contact_preferences'] = 'Preferencat deri 200 karaktere.';
        if ($type === 'request') {
            if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) $errors['deadline'] = 'Datë e pavlefshme.';
            if ($urgency !== '' && !in_array($urgency, ['low','normal','high'], true)) $errors['urgency'] = 'Urgjenca e pavlefshme.';
        }

        // Handle photo deletions (checkbox array: delete_photo[]=id)
        $deleteIds = array_map('intval', (array)Request::post('delete_photo', []));

        // Handle new photo uploads
        $files = self::collectUploadedPhotos('photos');
        $existingCount = count(PostPhoto::forPost($id)) - count($deleteIds);
        if ($existingCount + count($files) > 5) {
            $errors['photos'] = 'Maksimumi 5 foto për postim.';
        }
        foreach ($files as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) { $errors['photos'] = 'Gabim në ngarkim të fotos.'; break; }
            if ($f['size'] > 5 * 1024 * 1024) { $errors['photos'] = 'Çdo foto duhet të jetë nën 5MB.'; break; }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { $errors['photos'] = 'Formati i lejuar: JPG, PNG, WEBP.'; break; }
        }

        if ($errors) {
            $this->render('posts/edit', [
                'title'      => 'Modifiko postimin',
                'p'          => $post,
                'photos'     => PostPhoto::forPost($id),
                'categories' => Category::all(),
                'cities'     => City::all(),
                'old'        => array_merge($post, $_POST),
                'errors'     => $errors,
            ]);
            return;
        }

        // Build data
        $data = [
            'title'         => $title,
            'description'   => $description,
            'category_id'   => $categoryId,
            'city_id'       => $cityId,
        ];
        if ($type === 'offer') {
            $data['price_from']          = $priceFrom;
            $data['price_to']            = $priceTo;
            $data['working_hours']       = $workingHours !== '' ? $workingHours : null;
            $data['contact_preferences'] = $contactPreferences !== '' ? $contactPreferences : null;
        } else {
            $data['budget_from'] = $budgetFrom;
            $data['budget_to']   = $budgetTo;
            $data['deadline']    = $deadline !== '' ? $deadline : null;
            $data['urgency']     = $urgency  !== '' ? $urgency  : null;
        }

        // Apply changes
        try {
            DB::pdo()->beginTransaction();
            Post::update($id, $data);

            // Delete chosen photos
            foreach ($deleteIds as $pid) {
                $fn = PostPhoto::removeOne($pid, $id);
                if ($fn) {
                    $p = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $fn;
                    if (is_file($p)) @unlink($p);
                }
            }

            // Add new photos (continue sort_order numbering)
            $existing = PostPhoto::forPost($id);
            $nextOrder = $existing ? (max(array_column($existing, 'sort_order')) + 1) : 0;
            $savedFilenames = [];
            foreach ($files as $f) {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                $ext  = self::extForMime($mime);
                $name = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $name;
                if (!is_dir(CONFIG['upload_dir'])) mkdir(CONFIG['upload_dir'], 0775, true);
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    throw new RuntimeException('Photo move failed');
                }
                $savedFilenames[] = $name;
                PostPhoto::add($id, $name, $nextOrder++);
            }
            DB::pdo()->commit();
        } catch (Throwable $ex) {
            DB::pdo()->rollBack();
            foreach ($savedFilenames ?? [] as $n) @unlink(CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $n);
            $this->flash('danger', 'Gabim teknik gjatë ruajtjes së ndryshimeve.');
            $this->redirect('/posts/' . $id . '/edit');
            return;
        }

        $this->flash('success', 'Postimi u përditësua.');
        $this->redirect('/posts/' . $id);
    }

    public function close(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }
        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }
        Post::close($id);
        $this->flash('success', 'Postimi u mbyll.');
        $this->redirect('/posts/' . $id);
    }

    public function destroy(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }
        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        // Remove photo files from disk
        $filenames = PostPhoto::removeAllForPost($id);
        foreach ($filenames as $fn) {
            $p = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $fn;
            if (is_file($p)) @unlink($p);
        }
        Post::delete($id);

        $this->flash('success', 'Postimi u fshi.');
        $this->redirect('/posts');
    }

    /** Convert a stringy number to float or null. Empty => null. */
    private static function numOrNull($v): ?float {
        if ($v === null || $v === '' || $v === false) return null;
        if (!is_numeric($v)) return null;
        return (float)$v;
    }

    /** Normalize $_FILES['photos'] (multi-upload) to a list of single-file arrays. */
    private static function collectUploadedPhotos(string $key): array {
        if (empty($_FILES[$key]) || !is_array($_FILES[$key]['name'])) return [];
        $out = [];
        foreach ($_FILES[$key]['name'] as $i => $name) {
            if ($_FILES[$key]['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $out[] = [
                'name'     => $name,
                'type'     => $_FILES[$key]['type'][$i],
                'tmp_name' => $_FILES[$key]['tmp_name'][$i],
                'error'    => $_FILES[$key]['error'][$i],
                'size'     => $_FILES[$key]['size'][$i],
            ];
        }
        return $out;
    }

    private static function extForMime(string $mime): string {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'jpg';
    }
}
