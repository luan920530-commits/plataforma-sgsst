<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;

final class AuthController extends BaseController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        Auth::cancelTwoFactor();
        $this->view('auth/login', ['title' => 'Iniciar sesión'], 'layouts/auth');
    }

    public function login(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        $email = text_lower((string) $this->input('email'));
        $password = (string) $this->input('password');
        $validator = new Validator(['email' => $email, 'password' => $password]);
        $validator->required('email', 'correo electrónico')->email('email')->required('password', 'contraseña');
        if ($validator->fails()) {
            fail_validation($validator->errors(), ['email' => $email], '/login');
        }

        if (Auth::lockedSeconds() > 0) {
            $seconds = Auth::lockedSeconds();
            fail_validation(['email' => "Demasiados intentos. Espera {$seconds} segundos antes de volver a intentarlo."], ['email' => $email], '/login');
        }

        if (!Auth::attempt($email, $password)) {
            Audit::record('Inicio de sesión fallido', 'Auth', null, ['email' => $email]);
            $remaining = max(0, 5 - (int) ($_SESSION['_login_attempts'] ?? 0));
            $message = Auth::lockedSeconds() > 0
                ? 'Cuenta temporalmente bloqueada por 5 minutos debido a intentos fallidos.'
                : 'Las credenciales no son válidas o la cuenta está inactiva.' . ($remaining > 0 ? " Te quedan {$remaining} intentos." : '');
            fail_validation(['email' => $message], ['email' => $email], '/login');
        }

        if (Auth::pendingTwoFactor()) {
            redirect('/login/2fa');
        }

        Audit::record('Inicio de sesión', 'Auth', Auth::id());
        if (!empty(Auth::user()['must_change_password'])) {
            flash('warning', 'Cambia la contraseña temporal antes de ingresar a los módulos.');
            redirect('/cuenta/seguridad');
        }
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && $intended !== '/login') {
            redirect($intended);
        }
        redirect('/dashboard');
    }

    public function showTwoFactor(): void
    {
        if (Auth::check()) redirect('/dashboard');
        if (!Auth::pendingTwoFactor()) { flash('warning', 'Inicia con tu correo y contraseña para solicitar un nuevo código.'); redirect('/login'); }
        $this->view('auth/two-factor', ['title' => 'Verificación en dos pasos'], 'layouts/auth');
    }

    public function verifyTwoFactor(): void
    {
        if (Auth::check()) redirect('/dashboard');
        if (!Auth::pendingTwoFactor()) { flash('warning', 'El reto de verificación venció. Inicia sesión nuevamente.'); redirect('/login'); }
        $code = trim((string) $this->input('code'));
        if ($code === '') fail_validation(['code' => 'Ingresa el código de tu aplicación o un código de recuperación.'], [], '/login/2fa');
        try { $valid = Auth::completeTwoFactor($code); }
        catch (\RuntimeException $e) { fail_validation(['code' => $e->getMessage()], [], '/login/2fa'); }
        if (!$valid) {
            if (!Auth::pendingTwoFactor()) { flash('error', 'Se superó el número de intentos. Espera cinco minutos e inicia sesión nuevamente.'); redirect('/login'); }
            fail_validation(['code' => 'El código no es válido o ya fue utilizado.'], [], '/login/2fa');
        }
        Audit::record('Inicio de sesión con segundo factor', 'Auth', Auth::id());
        if (!empty(Auth::user()['must_change_password'])) redirect('/cuenta/seguridad');
        $intended = $_SESSION['_intended'] ?? null;unset($_SESSION['_intended']);
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && $intended !== '/login') redirect($intended);
        redirect('/dashboard');
    }

    public function logout(): void
    {
        Audit::record('Cierre de sesión', 'Auth', Auth::id());
        Auth::logout();
        flash('success', 'Tu sesión se cerró de forma segura.');
        redirect('/login');
    }
}
