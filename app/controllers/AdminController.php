<?php
declare(strict_types=1);

final class AdminController extends Controller {
    public function index(array $params = []): void {
        Auth::require('admin');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $this->render('admin/index', [
            'title'  => 'Admin',
            'counts' => User::counts(),
        ]);
    }

    public function providers(array $params = []): void {
        Auth::require('admin');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $this->render('admin/providers', [
            'title'     => 'Punetoret',
            'providers' => Provider::allWithStatus(),
        ]);
    }

    public function toggleActive(array $params = []): void {
        Auth::require('admin');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin/providers'); }
        User::toggleActive($id);
        $this->flash('info', 'Statusi u perditesua.');
        $this->redirect('/admin/providers');
    }

    public function togglePremium(array $params = []): void {
        Auth::require('admin');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin/providers'); }
        Provider::togglePremium($id);
        $this->flash('info', 'Premium u perditesua.');
        $this->redirect('/admin/providers');
    }

    public function categories(array $params = []): void {
        Auth::require('admin');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $this->render('admin/categories', [
            'title'      => 'Kategorite',
            'categories' => Category::all(),
        ]);
    }

    public function createCategory(array $params = []): void {
        Auth::require('admin');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
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
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
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
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin'); }
        $r = Review::find($id);
        if (!$r) { $this->flash('danger', 'Nuk u gjet.'); $this->redirect('/admin'); }
        Review::delete($id);
        $this->flash('info', 'Vleresimi u fshi.');
        $this->redirect('/provider/' . (int)$r['provider_id']);
    }

    public function posts(array $params = []): void {
        Auth::require('admin');
        $posts = Post::allForAdmin(200);
        $this->render('admin/posts', [
            'title' => 'Postimet — Admin',
            'posts' => $posts,
        ]);
    }

    public function hidePost(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0 || !Post::find($id)) { $this->notFound(); return; }
        Post::hide($id);
        $this->flash('success', 'Postimi u fshi nga publiku.');
        $this->redirect('/admin/posts');
    }

    public function subscriptions(array $params = []): void {
        Auth::require('admin');
        $this->render('admin/subscriptions', [
            'title'   => 'Abonimet — Admin',
            'pending' => Subscription::pendingForAdmin(),
            'all'     => Subscription::allForAdmin(),
        ]);
    }

    public function activateSubscription(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        $sub = Subscription::find($id);
        if (!$sub) { $this->notFound(); return; }
        if ($sub['status'] === 'active') {
            $this->flash('info', 'Tashmë është aktiv.');
            $this->redirect('/admin/subscriptions');
            return;
        }
        Subscription::activate($id, null);
        Notification::create((int)$sub['provider_id'], 'subscription.activated',
            'Abonimi yt u aktivizua', 'Tier ' . $sub['tier'] . '. Tani mund të postosh oferta.',
            '/subscribe');
        $email = (string)DB::q('SELECT email FROM users WHERE id=?', [$sub['provider_id']])->fetchColumn();
        if ($email) {
            Helpers::sendEmailSafe($email, 'Abonimi yt në Helppy.com u aktivizua',
                "Pagesa u konfirmua. Tier: {$sub['tier']}. Tani mund të postosh oferta.\n\nFalemnderit!");
        }
        $this->flash('success', 'Abonimi u aktivizua.');
        $this->redirect('/admin/subscriptions');
    }

    public function cancelSubscription(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        $sub = Subscription::find($id);
        if (!$sub) { $this->notFound(); return; }
        Subscription::cancel($id);
        $this->flash('info', 'Abonimi u anulua.');
        $this->redirect('/admin/subscriptions');
    }

    /* ===========================================================
       FULL ADMIN POWERS — users, photos, bookings, conversations
       =========================================================== */

    public function users(array $params = []): void {
        Auth::require('admin');
        $this->render('admin/users', [
            'title' => 'Përdoruesit — Admin',
            'users' => User::allForAdmin(),
        ]);
    }

    public function toggleUserActive(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0 || !User::find($id)) { $this->notFound(); return; }
        $me = (int)Auth::user()['id'];
        if ($id === $me) {
            $this->flash('danger', 'Nuk mund të çaktivizosh vetveten.');
            $this->redirect('/admin/users');
            return;
        }
        User::toggleActive($id);
        $this->flash('info', 'Statusi u ndryshua.');
        $this->redirect('/admin/users');
    }

    public function setUserRole(array $params = []): void {
        Auth::require('admin');
        $id   = (int)($params['id'] ?? 0);
        $role = (string)Request::post('role', '');
        if ($id <= 0 || !User::find($id)) { $this->notFound(); return; }
        $me = (int)Auth::user()['id'];
        if ($id === $me) {
            $this->flash('danger', 'Nuk mund ta ndryshosh rolin tënd.');
            $this->redirect('/admin/users');
            return;
        }
        if (!in_array($role, ['client','provider','admin'], true)) {
            $this->flash('danger', 'Rol i pavlefshëm.');
            $this->redirect('/admin/users');
            return;
        }
        User::setRole($id, $role);
        $this->flash('success', 'Roli u ndryshua në ' . $role . '.');
        $this->redirect('/admin/users');
    }

    public function deleteUser(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        $u  = User::find($id);
        if (!$u) { $this->notFound(); return; }
        $me = (int)Auth::user()['id'];
        if ($id === $me) {
            $this->flash('danger', 'Nuk mund ta fshish vetveten.');
            $this->redirect('/admin/users');
            return;
        }
        User::deleteFully($id);
        $this->flash('success', 'Përdoruesi u fshi përgjithmonë.');
        $this->redirect('/admin/users');
    }

    /** Delete the provider's profile photo. The provider's row stays. */
    public function deleteProviderPhoto(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        $row = DB::q('SELECT photo FROM providers WHERE user_id = ?', [$id])->fetch();
        if (!$row) { $this->notFound(); return; }
        if (!empty($row['photo'])) {
            $path = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $row['photo'];
            if (is_file($path)) @unlink($path);
            DB::q('UPDATE providers SET photo = NULL WHERE user_id = ?', [$id]);
        }
        $this->flash('success', 'Foto u fshi.');
        $this->redirect('/provider/' . $id);
    }

    /** Delete a single photo from a post (leaves the post + other photos in place). */
    public function deletePostPhoto(array $params = []): void {
        Auth::require('admin');
        $photoId = (int)($params['photo_id'] ?? 0);
        $row = DB::q('SELECT id, post_id, filename FROM post_photos WHERE id = ?', [$photoId])->fetch();
        if (!$row) { $this->notFound(); return; }
        $fn = PostPhoto::removeOne((int)$row['id'], (int)$row['post_id']);
        if ($fn) {
            $path = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $fn;
            if (is_file($path)) @unlink($path);
        }
        $this->flash('success', 'Foto u fshi.');
        $this->redirect('/posts/' . (int)$row['post_id']);
    }

    public function deleteBooking(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0 || !Booking::find($id)) { $this->notFound(); return; }
        DB::q('DELETE FROM bookings WHERE id = ?', [$id]);
        $this->flash('success', 'Rezervimi u fshi.');
        $this->redirect('/bookings');
    }

    public function deleteConversation(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0 || !Conversation::find($id)) { $this->notFound(); return; }
        DB::q('DELETE FROM conversations WHERE id = ?', [$id]);  // CASCADE deletes messages
        $this->flash('success', 'Biseda u fshi.');
        $this->redirect('/chat');
    }
}
