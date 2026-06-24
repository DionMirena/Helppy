<?php
declare(strict_types=1);

final class SubscriptionController extends Controller {

    /** GET /subscribe — pricing page */
    public function index(array $params = []): void {
        Auth::require();
        if (Auth::role() !== 'provider' && Auth::role() !== 'admin') {
            $this->flash('info', 'Vetëm punëtorët dhe kompanitë mund të abonohen.');
            $this->redirect('/');
            return;
        }
        $uid = (int)Auth::user()['id'];
        $current = Subscription::activeFor($uid);
        $latest  = Subscription::latestFor($uid);

        $this->render('subscriptions/index', [
            'title'                => 'Abonohu',
            'current'              => $current,
            'latest'               => $latest,
            'stripe_enabled'       => Stripe::isConfigured(),
            'enabled_banks'        => BankGateway::enabledBanks(),
            'plans'                => Subscription::PLANS,
        ]);
    }

    /** POST /subscribe/checkout — start Stripe Checkout */
    public function checkout(array $params = []): void {
        Auth::require();
        if (Auth::role() !== 'provider' && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }
        $uid     = (int)Auth::user()['id'];
        $planKey = (string)Request::post('plan', '');
        if (!Subscription::isValidPlan($planKey)) {
            $this->flash('danger', 'Plan i pavlefshëm.');
            $this->redirect('/subscribe');
            return;
        }
        if (!Stripe::isConfigured()) {
            $this->flash('danger', 'Pagesa me kartë nuk është konfiguruar. Përdor transferin bankar.');
            $this->redirect('/subscribe');
            return;
        }

        $subId = Subscription::createPending($uid, $planKey, 'stripe');
        $tier  = Subscription::tierForPlan($planKey);
        $plan  = Subscription::planFor($planKey);

        try {
            $session = Stripe::createCheckoutSession(
                $tier,
                Subscription::priceForPlan($planKey),
                (string)Auth::user()['email'],
                CONFIG['base_url'] . '/subscribe/success?sub=' . $subId . '&session={CHECKOUT_SESSION_ID}',
                CONFIG['base_url'] . '/subscribe?cancelled=1',
                ['subscription_id' => $subId, 'provider_id' => $uid, 'tier' => $tier, 'plan' => $planKey],
                (string)($plan['name'] ?? ucfirst($tier))
            );
        } catch (Throwable $e) {
            error_log('[SubscriptionController::checkout] ' . $e->getMessage());
            Subscription::cancel($subId);
            $this->flash('danger', 'Nuk munda të krijoj sesionin e pagesës. ' . $e->getMessage());
            $this->redirect('/subscribe');
            return;
        }

        // Persist the Stripe session id on the pending row
        DB::q('UPDATE subscriptions SET stripe_session_id = ? WHERE id = ?', [$session['id'], $subId]);

        // Redirect to Stripe-hosted Checkout page
        header('Location: ' . $session['url']);
        exit;
    }

    /** GET /subscribe/success — return from Stripe Checkout */
    public function success(array $params = []): void {
        Auth::require();
        $subId     = (int)Request::get('sub', 0);
        $sessionId = (string)Request::get('session', '');
        $sub = Subscription::find($subId);
        if (!$sub || (int)$sub['provider_id'] !== (int)Auth::user()['id']) {
            $this->notFound(); return;
        }

        // Verify with Stripe (defense in depth — webhook is the source of truth)
        if ($sessionId !== '' && Stripe::isConfigured()) {
            try {
                $session = Stripe::retrieveCheckoutSession($sessionId);
                if (($session['payment_status'] ?? '') === 'paid' && $sub['status'] !== 'active') {
                    Subscription::activate($subId, $session['payment_intent'] ?? null);
                    $sub = Subscription::find($subId);
                }
            } catch (Throwable $e) {
                error_log('[SubscriptionController::success] ' . $e->getMessage());
            }
        }

        $this->render('subscriptions/success', [
            'title' => 'Abonimi u aktivizua',
            'sub'   => $sub,
        ]);
    }

    /** POST /subscribe/bank — start the manual bank-transfer flow */
    public function bank(array $params = []): void {
        Auth::require();
        if (Auth::role() !== 'provider' && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }
        $uid     = (int)Auth::user()['id'];
        $planKey = (string)Request::post('plan', '');
        $bankKey = (string)Request::post('bank', '');
        if (!Subscription::isValidPlan($planKey)) {
            $this->flash('danger', 'Plan i pavlefshëm.');
            $this->redirect('/subscribe');
            return;
        }
        $bank = Payments::findBank($bankKey);
        if (!$bank) {
            $this->flash('danger', 'Banka e zgjedhur nuk është e vlefshme.');
            $this->redirect('/subscribe');
            return;
        }
        $plan  = Subscription::planFor($planKey);
        $ref   = Subscription::generateBankRef($uid);
        $subId = Subscription::createPending($uid, $planKey, 'bank', $ref);
        DB::q('UPDATE subscriptions SET bank_chosen = ? WHERE id = ?', [$bank['key'], $subId]);

        // Notify every admin so they don't miss the inbound transfer.
        $providerName = Auth::user()['name'];
        $amount = (float)$plan['price'];
        $planName = (string)$plan['name'];
        $title  = "Pagesë e re në pritje (" . $bank['short'] . ")";
        $body   = "{$providerName} ka nisur një transfer për planin {$planName} (€{$amount}).\n"
                . "Kodi i referencës: {$ref}\n"
                . "Banka: {$bank['name']}\n\n"
                . "Kontrollo llogarinë bankare dhe aktivizo te /admin/subscriptions sapo paratë të mbërrijnë.";
        $admins = DB::q("SELECT id, email FROM users WHERE role='admin' AND is_active=1")->fetchAll();
        foreach ($admins as $a) {
            Notification::create((int)$a['id'], 'subscription.pending',
                $title, $body, '/admin/subscriptions');
            if (!empty($a['email'])) {
                Helpers::sendEmailSafe((string)$a['email'],
                    'Helppy.com — ' . $title, $body);
            }
        }

        $this->redirect('/subscribe/bank/' . $subId);
    }

    /**
     * POST /subscribe/card-bank — start the picked bank's 3D Secure card flow.
     * Requires both plan and bank keys. Creates a pending subscription stamped
     * with bank_chosen, then auto-submits to that bank's hosted page.
     */
    public function cardBank(array $params = []): void {
        Auth::require();
        if (Auth::role() !== 'provider' && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }
        $uid     = (int)Auth::user()['id'];
        $planKey = (string)Request::post('plan', '');
        $bankKey = (string)Request::post('bank', '');

        if (!Subscription::isValidPlan($planKey)) {
            $this->flash('danger', 'Plan i pavlefshëm.');
            $this->redirect('/subscribe');
            return;
        }
        if (!BankGateway::isConfigured($bankKey)) {
            $this->flash('danger', 'Banka e zgjedhur nuk është konfiguruar për pagesë me kartë.');
            $this->redirect('/subscribe');
            return;
        }

        $subId = Subscription::createPending($uid, $planKey, 'bank', null);
        DB::q('UPDATE subscriptions SET bank_chosen = ? WHERE id = ?', [$bankKey, $subId]);

        $plan   = Subscription::planFor($planKey);
        $shared = (array)(CONFIG['payments']['bank_gateway'] ?? []);

        $successUrl = CONFIG['base_url'] . (string)($shared['callback_path'] ?? '/subscribe/card-bank/callback');
        $failUrl    = CONFIG['base_url'] . (string)($shared['fail_path']     ?? '/subscribe?cardbank=fail');

        $fields = BankGateway::buildAuthRequest(
            $bankKey,
            (string)$subId,
            (float)$plan['price'],
            $successUrl,
            $failUrl,
            (string)Auth::user()['email']
        );

        $this->render('subscriptions/bank-redirect', [
            'title'   => 'Po të dërgojmë te banka…',
            'action'  => BankGateway::apiUrl($bankKey),
            'fields'  => $fields,
        ]);
    }

    /**
     * POST/GET /subscribe/card-bank/callback — bank returns here after payment.
     * Find the sub by oid, use its bank_chosen to pick the right store_key,
     * verify HMAC, activate on approval.
     */
    public function cardBankCallback(array $params = []): void {
        $post = $_POST ?: $_GET;
        $subId = (int)($post['oid'] ?? 0);
        $sub   = $subId ? Subscription::find($subId) : null;
        if (!$sub) { $this->notFound(); return; }

        $bankKey = (string)($sub['bank_chosen'] ?? '');
        $result  = BankGateway::verifyCallback($bankKey, $post);

        if ($result['ok']) {
            if ($sub['status'] !== 'active') {
                Subscription::activate($subId, (string)($post['TransId'] ?? $post['AuthCode'] ?? '') ?: null);
            }
            $this->redirect('/subscribe/success?sub=' . $subId);
            return;
        }

        error_log('[BankGateway callback] declined: ' . $result['reason'] . ' for sub=' . $subId . ' bank=' . $bankKey);
        Subscription::cancel($subId);
        $this->flash('danger', 'Pagesa nuk u aprovua: ' . $result['reason']);
        $this->redirect('/subscribe');
    }

    /** GET /subscribe/bank/{id} — show bank-transfer instructions for a pending row */
    public function bankInstructions(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $sub = Subscription::find($id);
        if (!$sub || (int)$sub['provider_id'] !== (int)Auth::user()['id']) {
            $this->notFound(); return;
        }
        $bank = Payments::findBank((string)($sub['bank_chosen'] ?? '')) ?? [
            'name'        => '—',
            'short'       => '—',
            'beneficiary' => 'Helppy SH.P.K.',
            'iban'        => 'XK00 0000 0000 0000 0000',
            'swift'       => '',
            'note'        => 'Përdor kodin e referencës si arsye e pagesës.',
        ];
        $this->render('subscriptions/bank', [
            'title' => 'Pagesë me transfer bankar — ' . $bank['short'],
            'sub'   => $sub,
            'bank'  => $bank,
        ]);
    }

    /**
     * POST /subscribe/webhook — Stripe webhook receiver.
     * Activates the matching subscription on successful payment.
     */
    public function webhook(array $params = []): void {
        $secret = Stripe::webhookSecret();
        $sig    = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        $raw    = file_get_contents('php://input');
        if ($raw === false) $raw = '';
        try {
            Stripe::verifyWebhook((string)$raw, $sig, $secret);
        } catch (Throwable $e) {
            error_log('[Stripe webhook] verify failed: ' . $e->getMessage());
            http_response_code(400);
            echo 'invalid';
            return;
        }
        $event = json_decode((string)$raw, true);
        if (!is_array($event) || empty($event['type'])) {
            http_response_code(400); echo 'malformed'; return;
        }

        if ($event['type'] === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $sessionId = (string)($session['id'] ?? '');
            $paid = ($session['payment_status'] ?? '') === 'paid';
            if ($sessionId && $paid) {
                $sub = Subscription::findByStripeSession($sessionId);
                if ($sub && $sub['status'] !== 'active') {
                    Subscription::activate((int)$sub['id'], $session['payment_intent'] ?? null);
                }
            }
        }

        http_response_code(200);
        echo 'ok';
    }

    /** POST /subscribe/cancel-current — provider cancels their own active sub */
    public function cancelMine(array $params = []): void {
        Auth::require();
        $uid = (int)Auth::user()['id'];
        $sub = Subscription::activeFor($uid);
        if (!$sub) { $this->flash('info', 'Nuk ke abonim aktiv.'); $this->redirect('/subscribe'); return; }
        Subscription::cancel((int)$sub['id']);
        // Keep is_premium if it was set; only future ones don't auto-set.
        $this->flash('success', 'Abonimi u anulua. Mund të rikthehesh kur të duash.');
        $this->redirect('/subscribe');
    }
}
