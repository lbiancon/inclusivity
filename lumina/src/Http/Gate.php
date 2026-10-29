<?php

declare(strict_types=1);

namespace Lumina\Http;

use Lumina\App;
use Lumina\Auth;
use Lumina\Category;
use Lumina\Db;
use Lumina\DomainException;
use Lumina\Input;
use Lumina\Mailer;
use Lumina\Oauth;
use Lumina\Passcode;
use Lumina\Plan;
use Lumina\View;
use PDOException;

final class Gate
{
    public function show(): void
    {
        if (Auth::user(App::$pdo) !== null) {
            go('/app');
            exit;
        }
        $this->render('register');
    }

    public function enter(): void
    {
        if (Auth::user(App::$pdo) !== null) {
            go('/app');
            exit;
        }
        $this->render('login');
    }

    public function sendCode(): void
    {
        $this->trap();
        $email = Input::email('email');
        Passcode::issue(App::$pdo, App::$clock, $email, $this->ip());
        $_SESSION['gate_email'] = $email;
        unset($_SESSION['gate_ok']);
        go('/registrati/codice');
        exit;
    }

    public function codeForm(): void
    {
        $email = $this->pendingEmail();
        $letter = null;
        if (Mailer::isLocal() && isset($_SESSION['gate_letter'])) {
            $letter = Mailer::letter(App::$pdo, (int) $_SESSION['gate_letter'], $email);
        }
        View::render('auth/code', [
            'title' => 'Codice',
            'email' => $email,
            'letter' => $letter,
            'localMail' => Mailer::isLocal(),
        ]);
    }

    public function verifyCode(): void
    {
        $email = $this->pendingEmail();
        $code = Input::str('code', 6);
        if ($code === '') {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= Input::str('d' . $i, 1);
            }
        }
        if (!Passcode::check(App::$pdo, App::$clock, $email, $code)) {
            throw new DomainException('validation', 'Codice non valido o scaduto. Puoi chiederne uno nuovo.');
        }
        unset($_SESSION['gate_letter']);
        $_SESSION['gate_ok'] = true;
        $user = Db::one(App::$pdo, 'SELECT * FROM users WHERE email = :email', ['email' => $email]);
        if ($user === null) {
            go('/registrati/profilo');
            exit;
        }
        if ($user['email_verified_at'] === null) {
            Db::run(App::$pdo, 'UPDATE users SET email_verified_at = :now WHERE id = :id', [
                'now' => App::$clock->sql(App::$clock->now()),
                'id' => (int) $user['id'],
            ]);
        }
        $this->linkPending((int) $user['id'], $email);
        Auth::loginId((int) $user['id']);
        $this->clearGate();
        $this->linkClients($user);
        go($this->destination($user));
        exit;
    }

    public function profileForm(): void
    {
        if (empty($_SESSION['gate_ok']) || $this->pendingEmail() === '') {
            go('/registrati');
            exit;
        }
        $pending = $_SESSION['pending_oauth'] ?? null;
        View::render('auth/profile', [
            'title' => 'Apri l\'agenda',
            'email' => $this->pendingEmail(),
            'name' => is_array($pending) ? (string) ($pending['name'] ?? '') : '',
            'categories' => Category::cases(),
            'offers' => Plan::offers(),
        ]);
    }

    public function profile(): void
    {
        if (empty($_SESSION['gate_ok'])) {
            throw new DomainException('validation', 'Conferma prima l\'email.');
        }
        $email = $this->pendingEmail();
        $account = Input::choice('account', ['business', 'client']);
        $name = Input::str('name', 120, true);
        if (mb_strlen($name) < 2) {
            throw new DomainException('validation', 'Il nome è troppo corto.');
        }
        $plan = $account === 'business' ? Input::choice('plan', Plan::ids()) : 'salone';
        $interval = $account === 'business' ? Input::choice('plan_interval', ['month', 'year']) : 'month';
        $phone = Input::phone('phone');
        App::$pdo->beginTransaction();
        try {
            Db::run(App::$pdo, 'INSERT INTO users (name, email, password_hash, phone, role, email_verified_at) VALUES (:name, :email, NULL, :phone, :role, :verified)', [
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'role' => $account === 'business' ? 'owner' : 'client',
                'verified' => App::$clock->sql(App::$clock->now()),
            ]);
            $userId = Db::id(App::$pdo);
            if ($account === 'business') {
                $businessName = Input::str('business_name', 140, true);
                $category = Input::choice('category', array_map(static fn (Category $c): string => $c->value, Category::cases()));
                $city = Input::str('city', 80, true);
                $slug = $this->uniqueSlug(slugify($businessName));
                Db::run(App::$pdo, 'INSERT INTO businesses (
                    owner_user_id, name, slug, category, tagline, description, city, email, phone, timezone, plan, plan_interval
                ) VALUES (
                    :owner, :name, :slug, :category, :tagline, :description, :city, :email, :phone, :timezone, :plan, :interval
                )', [
                    'owner' => $userId,
                    'name' => $businessName,
                    'slug' => $slug,
                    'category' => $category,
                    'tagline' => 'Prenotazioni aperte',
                    'description' => '',
                    'city' => $city,
                    'email' => $email,
                    'phone' => $phone,
                    'timezone' => 'Europe/Rome',
                    'plan' => $plan,
                    'interval' => $interval,
                ]);
                $businessId = Db::id(App::$pdo);
                foreach ([1, 2, 3, 4, 5] as $day) {
                    foreach ([['09:00:00', '13:00:00'], ['15:00:00', '19:00:00']] as [$start, $end]) {
                        Db::run(App::$pdo, 'INSERT INTO weekly_hours (business_id, staff_id, weekday, start_time, end_time) VALUES (:business, NULL, :weekday, :start_time, :end_time)', [
                            'business' => $businessId,
                            'weekday' => $day,
                            'start_time' => $start,
                            'end_time' => $end,
                        ]);
                    }
                }
            }
            $this->linkPending($userId, $email);
            App::$pdo->commit();
        } catch (PDOException $exception) {
            if (App::$pdo->inTransaction()) {
                App::$pdo->rollBack();
            }
            if (Db::duplicate($exception)) {
                throw new DomainException('validation', 'Questa email è già registrata. Entra con il codice.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if (App::$pdo->inTransaction()) {
                App::$pdo->rollBack();
            }
            throw $exception;
        }
        Auth::loginId($userId);
        $this->clearGate();
        $user = ['id' => $userId, 'role' => $account === 'business' ? 'owner' : 'client', 'email' => $email];
        $this->linkClients($user);
        flash('ok', $account === 'business'
            ? 'Agenda aperta sul piano ' . Plan::offers()[$plan]['name'] . '. Nessun addebito: il pagamento non è collegato, i limiti sì.'
            : 'Accesso creato. Le prenotazioni con questa email compariranno qui.');
        go(($account === 'business' ? '/app/servizi' : '/portale'));
        exit;
    }

    public function google(): void
    {
        $this->redirectOut('google');
    }

    public function apple(): void
    {
        $this->redirectOut('apple');
    }

    public function googleReturn(): void
    {
        $this->finish('google', $_GET);
    }

    public function appleReturn(): void
    {
        $this->finish('apple', $_POST);
    }

    public function plans(): void
    {
        View::render('marketing/plans', [
            'title' => 'Piani',
            'offers' => Plan::offers(),
        ]);
    }

    public function planPage(): void
    {
        $workspace = Guard::workspace(true);
        Guard::view('app/plan', $workspace, [
            'title' => 'Piano',
            'offers' => Plan::offers(),
        ]);
    }

    public function choosePlan(): void
    {
        $workspace = Guard::workspace(true);
        $plan = Input::choice('plan', Plan::ids());
        $interval = Input::choice('plan_interval', ['month', 'year']);
        Db::run(App::$pdo, 'UPDATE businesses SET plan = :plan, plan_interval = :interval WHERE id = :id', [
            'plan' => $plan,
            'interval' => $interval,
            'id' => (int) $workspace['business']['id'],
        ]);
        flash('ok', 'Piano ' . Plan::offers()[$plan]['name'] . ' attivo. Nessuna carta è stata addebitata.');
        go('/app/piano');
        exit;
    }

    /** @param array<string, mixed> $request */
    private function finish(string $provider, array $request): void
    {
        try {
            $result = Oauth::finish(App::$pdo, App::$clock, $provider, $request);
        } catch (DomainException $exception) {
            flash('err', $exception->getMessage());
            go('/registrati');
            exit;
        }
        if ($result['kind'] === 'profile') {
            go('/registrati/profilo');
            exit;
        }
        $user = $result['user'] ?? [];
        $this->linkClients($user);
        $this->clearGate();
        go($this->destination($user));
        exit;
    }

    private function redirectOut(string $provider): void
    {
        $intent = request_path() === '/auth/apple' || request_path() === '/auth/google' ? 'enter' : 'enter';
        try {
            $url = Oauth::begin(App::$pdo, App::$clock, $provider, $intent);
        } catch (DomainException $exception) {
            flash('err', $exception->getMessage());
            go('/registrati');
            exit;
        }
        go($url);
        exit;
    }

    private function render(string $mode): void
    {
        View::render('auth/gate', [
            'title' => $mode === 'login' ? 'Accedi' : 'Apri l\'agenda',
            'mode' => $mode,
            'google' => Oauth::googleReady(),
            'apple' => Oauth::appleReady(),
            'localMail' => Mailer::isLocal(),
            'demo' => $mode === 'login' && Db::one(App::$pdo, 'SELECT id FROM businesses WHERE slug = :slug', ['slug' => 'atelier-luce']) !== null,
            'offers' => Plan::offers(),
        ]);
    }

    private function pendingEmail(): string
    {
        $email = $_SESSION['gate_email'] ?? '';
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            go('/registrati');
            exit;
        }

        return $email;
    }

    private function trap(): void
    {
        if (Input::str('company', 120) !== '') {
            go('/registrati/codice');
            exit;
        }
    }

    private function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return preg_match('/^[0-9a-fA-F:.]+$/', $ip) === 1 ? $ip : '0.0.0.0';
    }

    private function linkPending(int $userId, string $email): void
    {
        $pending = $_SESSION['pending_oauth'] ?? null;
        if (!is_array($pending) || ($pending['email'] ?? '') !== $email) {
            return;
        }
        $exists = Db::one(App::$pdo, 'SELECT id FROM oauth_accounts WHERE provider = :provider AND subject = :subject', [
            'provider' => (string) $pending['provider'],
            'subject' => (string) $pending['subject'],
        ]);
        if ($exists === null) {
            Oauth::link(App::$pdo, App::$clock, $userId, (string) $pending['provider'], (string) $pending['subject']);
        }
        unset($_SESSION['pending_oauth']);
    }

    private function clearGate(): void
    {
        unset($_SESSION['gate_email'], $_SESSION['gate_ok'], $_SESSION['gate_letter'], $_SESSION['pending_oauth']);
    }

    /** @param array<string, mixed> $user */
    private function destination(array $user): string
    {
        $next = safe_path((string) ($_SESSION['after_login'] ?? ''));
        unset($_SESSION['after_login']);
        if ($next === null || $next === '/accedi' || str_starts_with($next, '/registrati') || str_starts_with($next, '/auth/')) {
            return (string) ($user['role'] ?? '') === 'client' ? '/portale' : '/app';
        }

        return $next;
    }

    /** @param array<string, mixed> $user */
    private function linkClients(array $user): void
    {
        if (!isset($user['id'], $user['email'])) {
            return;
        }
        Db::run(
            App::$pdo,
            'UPDATE clients SET user_id = :user WHERE email = :email AND user_id IS NULL',
            ['user' => (int) $user['id'], 'email' => (string) $user['email']],
        );
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'agenda';
        for ($i = 2; $i < 40; $i++) {
            $found = Db::one(App::$pdo, 'SELECT id FROM businesses WHERE slug = :slug', ['slug' => $slug]);
            if ($found === null) {
                return $slug;
            }
            $slug = $base . '-' . $i;
        }
        throw new DomainException('conflict', 'Non riesco a creare un indirizzo univoco. Cambia il nome.');
    }
}
