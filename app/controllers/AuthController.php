<?php
namespace App\Controllers;

use App\Auth;

class AuthController extends BaseController
{
    public function showLogin(): void
    {
        if (Auth::check()) $this->redirect(base_url('dashboard'));
        $this->view('login', ['titulo' => 'Iniciar sesión']);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (Auth::login($email, $pass)) {
            $this->redirect(base_url('dashboard'));
        }

        flash('error', 'Credenciales incorrectas');
        $this->redirect(base_url('login'));
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect(base_url('login'));
    }
}
