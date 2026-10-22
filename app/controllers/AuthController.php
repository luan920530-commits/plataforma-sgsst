<?php
namespace App\Controllers;

use App\Auth;
use App\Auditoria;
use App\Database;

class AuthController extends BaseController
{
    public function showLogin(): void
    {
        if (Auth::check()) $this->redirect(base_url('dashboard'));
        $this->view('login', ['titulo' => 'Iniciar sesión', 'csrf' => $this->csrfToken()]);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (Auth::login($email, $pass)) {
            Auditoria::registrar('auth.login', null, 'usuario', Auth::id(), $email);
            $this->redirect(base_url(Auth::mustChangePassword() ? 'cambiar-password' : 'dashboard'));
        }

        flash('error', 'Credenciales incorrectas');
        $this->redirect(base_url('login'));
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auditoria::registrar('auth.logout', null, 'usuario', Auth::id());
        Auth::logout();
        $this->redirect(base_url('login'));
    }

    public function showCambiarPassword(): void
    {
        $this->view('cambiar_password', ['titulo' => 'Cambiar contraseña', 'csrf' => $this->csrfToken()]);
    }

    public function cambiarPassword(): void
    {
        $this->verifyCsrf();
        $nueva = $_POST['password'] ?? '';
        if (strlen($nueva) < 10 || $nueva !== ($_POST['password_confirm'] ?? '')) {
            flash('error', 'La contraseña debe tener mínimo 10 caracteres y coincidir con la confirmación.');
            $this->redirect(base_url('cambiar-password'));
        }
        Database::execute(
            'UPDATE usuarios SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            [password_hash($nueva, PASSWORD_DEFAULT), Auth::id()]
        );
        Auditoria::registrar('auth.password_cambiada', null, 'usuario', Auth::id());
        flash('success', 'Contraseña actualizada');
        $this->redirect(base_url('dashboard'));
    }
}
