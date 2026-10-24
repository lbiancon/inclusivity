<?php

declare(strict_types=1);

namespace Lumina;

use Lumina\Http\Booking;
use Lumina\Http\Catalog;
use Lumina\Http\Desk;
use Lumina\Http\Front;
use Lumina\Http\Gate;
use Lumina\Http\People;
use Lumina\Http\Studio;
use Lumina\Schedule\Bookings;
use Lumina\Schedule\Engine;
use PDO;
use Throwable;

final class App
{
    public static PDO $pdo;
    public static Clock $clock;
    public static Engine $engine;
    public static Bookings $bookings;
    public static bool $debug = false;

    public static function run(): void
    {
        self::boot();
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('LUMINASESS');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        ini_set('display_errors', self::$debug ? '1' : '0');

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        try {
            self::routes()->dispatch($method, rawurldecode(request_path()));
        } catch (HttpException $exception) {
            http_response_code($exception->status);
            View::render('error', [
                'title' => 'Errore',
                'message' => $exception->getMessage(),
                'status' => $exception->status,
            ]);
        } catch (DomainException $exception) {
            if ($method === 'POST') {
                $old = $_POST;
                unset($old['password'], $old['password_confirm'], $old['_csrf']);
                $_SESSION['old'] = $old;
                flash('err', $exception->getMessage());
                $back = safe_path((string) ($_POST['redirect'] ?? ''));
                go($back ?? '/');
            }
            http_response_code(422);
            View::render('error', [
                'title' => 'Richiesta non valida',
                'message' => $exception->getMessage(),
                'status' => 422,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->__toString());
            http_response_code(500);
            View::render('error', [
                'title' => 'Errore',
                'message' => self::$debug ? $exception->getMessage() : 'Qualcosa non ha funzionato. Riprova tra un momento.',
                'status' => 500,
            ]);
        }
    }

    public static function boot(): void
    {
        self::$debug = getenv('LUMINA_DEBUG') === '1';
        self::$pdo = Db::connect();
        Upgrade::apply(self::$pdo);
        self::$clock = new Clock();
        self::$engine = new Engine(self::$pdo, self::$clock);
        self::$bookings = new Bookings(self::$pdo, self::$clock, self::$engine);
    }

    private static function routes(): Router
    {
        $router = new Router();
        $front = new Front();
        $booking = new Booking();
        $desk = new Desk();
        $catalog = new Catalog();
        $people = new People();
        $studio = new Studio();

        $gate = new Gate();
        $router->get('/', $front->home(...));
        $router->get('/prezzi', $gate->plans(...));
        $router->get('/accedi', $gate->enter(...));
        $router->post('/accedi', $front->login(...));
        $router->get('/registrati', $gate->show(...));
        $router->post('/registrati/codice/invia', $gate->sendCode(...));
        $router->get('/registrati/codice', $gate->codeForm(...));
        $router->post('/registrati/codice', $gate->verifyCode(...));
        $router->get('/registrati/profilo', $gate->profileForm(...));
        $router->post('/registrati/profilo', $gate->profile(...));
        $router->get('/auth/google', $gate->google(...));
        $router->get('/auth/google/ritorno', $gate->googleReturn(...));
        $router->get('/auth/apple', $gate->apple(...));
        $router->post('/auth/apple/ritorno', $gate->appleReturn(...), false);
        $router->post('/esci', $front->logout(...));
        $router->get('/portale', $front->portal(...));
        $router->post('/portale/appuntamenti/{id}/annulla', $front->portalCancel(...));
        $router->post('/portale/offerte/{id}/accetta', $front->portalAccept(...));
        $router->get('/portale/appuntamenti/{id}.ics', $front->portalIcs(...));

        $router->get('/b/{slug}/marchio', $booking->logo(...));
        $router->get('/b/{slug}', $booking->show(...));
        $router->post('/b/{slug}', $booking->book(...));
        $router->post('/b/{slug}/corso', $booking->bookClass(...));
        $router->post('/b/{slug}/attesa', $booking->wait(...));
        $router->get('/b/{slug}/ok/{token}', $booking->done(...));
        $router->get('/p/{token}', $booking->guestAppointment(...));
        $router->post('/p/{token}/annulla', $booking->guestCancel(...));
        $router->get('/p/{token}.ics', $booking->guestIcs(...));
        $router->get('/c/{token}', $booking->guestClass(...));
        $router->post('/c/{token}/annulla', $booking->guestClassCancel(...));
        $router->get('/w/{token}', $booking->guestWait(...));
        $router->post('/w/{token}/accetta', $booking->guestWaitAccept(...));
        $router->post('/w/{token}/annulla', $booking->guestWaitCancel(...));

        $router->get('/app', $desk->dashboard(...));
        $router->get('/app/agenda', $desk->agenda(...));
        $router->get('/app/giornata', $desk->day(...));
        $router->get('/app/appuntamenti/{id}', $desk->appointment(...));
        $router->post('/app/appuntamenti', $desk->create(...));
        $router->post('/app/appuntamenti/{id}/stato', $desk->status(...));
        $router->post('/app/appuntamenti/{id}/annulla', $desk->cancel(...));
        $router->post('/app/appuntamenti/{id}/rimborsa', $desk->refund(...));
        $router->get('/app/esporta.csv', $desk->export(...));
        $router->get('/app/notifiche', $desk->notifications(...));
        $router->post('/app/notifiche/lette', $desk->readNotifications(...));

        $router->get('/app/servizi', $catalog->services(...));
        $router->post('/app/servizi', $catalog->saveService(...));
        $router->post('/app/servizi/{id}', $catalog->saveService(...));
        $router->get('/app/staff', $catalog->staffPage(...));
        $router->post('/app/staff', $catalog->saveStaff(...));
        $router->post('/app/staff/{id}', $catalog->saveStaff(...));
        $router->post('/app/staff/{id}/orari', $catalog->saveHours(...));
        $router->post('/app/risorse', $catalog->saveResource(...));
        $router->post('/app/risorse/{id}', $catalog->saveResource(...));

        $router->get('/app/clienti', $people->index(...));
        $router->post('/app/clienti', $people->save(...));
        $router->get('/app/clienti/{id}', $people->show(...));
        $router->post('/app/clienti/{id}', $people->save(...));
        $router->post('/app/clienti/{id}/tessera', $people->sell(...));

        $router->get('/app/classi', $studio->classes(...));
        $router->post('/app/classi', $studio->saveSession(...));
        $router->get('/app/classi/{id}', $studio->session(...));
        $router->post('/app/classi/{id}/prenota', $studio->bookSession(...));
        $router->post('/app/classi/{id}/stato', $studio->sessionStatus(...));
        $router->post('/app/classi/iscrizioni/{id}', $studio->bookingStatus(...));
        $router->post('/app/classi/iscrizioni/{id}/rimborsa', $studio->refundBooking(...));
        $router->get('/app/pacchetti', $studio->packages(...));
        $router->post('/app/pacchetti', $studio->savePackage(...));
        $router->post('/app/pacchetti/{id}', $studio->savePackage(...));
        $router->get('/app/attesa', $studio->waitlist(...));
        $router->post('/app/attesa/{id}/accetta', $studio->accept(...));
        $router->post('/app/attesa/{id}/annulla', $studio->cancelWait(...));
        $router->get('/app/prezzi', $studio->pricing(...));
        $router->post('/app/prezzi', $studio->saveRule(...));
        $router->post('/app/prezzi/{id}', $studio->toggleRule(...));
        $router->get('/app/analisi', $studio->analytics(...));
        $router->get('/app/piano', $gate->planPage(...));
        $router->post('/app/piano', $gate->choosePlan(...));
        $router->get('/app/impostazioni', $studio->settings(...));
        $router->post('/app/impostazioni', $studio->saveSettings(...));
        $router->post('/app/chiusure', $studio->saveClosure(...));
        $router->post('/app/chiusure/{id}/elimina', $studio->deleteClosure(...));

        return $router;
    }
}
