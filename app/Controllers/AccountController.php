<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;

final class AccountController extends BaseController
{
    public function security(): void
    {
        $this->view('auth/security', ['title' => 'Seguridad de la cuenta']);
    }

    public function updatePassword(): void
    {
        $data = [
            'current_password' => (string) $this->input('current_password'),
            'password' => (string) $this->input('password'),
            'password_confirmation' => (string) $this->input('password_confirmation'),
        ];
        $validator = new Validator($data);
        $validator->required('current_password', 'contraseña actual')->required('password', 'nueva contraseña')
            ->password('password', 'nueva contraseña')->confirmed('password', 'password_confirmation');
        $errors = $validator->errors();
        $account = User::find(Auth::id());
        if ($account === null || !password_verify($data['current_password'], $account['password'])) {
            $errors['current_password'] = 'La contraseña actual no coincide.';
        }
        if ($data['current_password'] !== '' && hash_equals($data['current_password'], $data['password'])) {
            $errors['password'] = 'La nueva contraseña debe ser diferente a la actual.';
        }
        if ($errors !== []) {
            fail_validation($errors, [], '/cuenta/seguridad');
        }
        User::updatePassword(Auth::id(), password_hash($data['password'], PASSWORD_DEFAULT));
        session_regenerate_id(true);
        Audit::record('Contraseña actualizada', 'User', Auth::id());
        flash('success', 'Tu contraseña fue actualizada correctamente.');
        redirect('/cuenta/seguridad');
    }
}
