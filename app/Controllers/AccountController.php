<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\SecretBox;
use App\Support\Totp;
use App\Support\Validator;

final class AccountController extends BaseController
{
    public function security(): void
    {
        $account = User::find(Auth::id()) ?: $this->notFound();
        $pendingSecret = null;$uri = null;$scripts = [];
        if (!$account['totp_enabled'] && $account['totp_secret'] !== '') {
            $pendingSecret = SecretBox::decrypt((string) $account['totp_secret']);
            $uri = Totp::provisioningUri($pendingSecret, (string) $account['email']);
            $scripts = ['js/vendor/qrcode.js'];
        }
        $recovery = [];
        if ($account['totp_enabled'] && !empty($_SESSION['_totp_recovery']) && is_array($_SESSION['_totp_recovery'])) {
            $recovery = $_SESSION['_totp_recovery'];unset($_SESSION['_totp_recovery']);
        }
        $this->view('auth/security', ['title' => 'Seguridad de la cuenta', 'account' => $account, 'pendingSecret' => $pendingSecret, 'provisioningUri' => $uri, 'recoveryCodes' => $recovery, 'extraScripts' => $scripts]);
    }

    public function startTwoFactor(): void
    {
        $account = $this->confirmedAccount((string) $this->input('current_password_2fa'));
        if ($account['totp_enabled']) { flash('warning', 'El segundo factor ya está activo.'); redirect('/cuenta/seguridad'); }
        try {
            $secret = Totp::secret();$recovery = Totp::recoveryCodes();
            $hashes = array_map(static fn(string $code): string => password_hash($code, PASSWORD_DEFAULT), $recovery);
            User::prepareTotp(Auth::id(), SecretBox::encrypt($secret), $hashes);
            $_SESSION['_totp_recovery'] = $recovery;$_SESSION['_totp_started'] = time();
        } catch (\RuntimeException $e) { flash('error', $e->getMessage()); redirect('/cuenta/seguridad'); }
        Audit::record('Enrolamiento 2FA preparado', 'User', Auth::id());
        flash('success', 'Escanea el QR o copia la clave y valida un código para activar 2FA.');redirect('/cuenta/seguridad#2fa');
    }

    public function activateTwoFactor(): void
    {
        $account = User::find(Auth::id()) ?: $this->notFound();
        if ($account['totp_enabled'] || $account['totp_secret'] === '') { flash('warning', 'Primero inicia el enrolamiento de 2FA.'); redirect('/cuenta/seguridad'); }
        if (time() - (int) ($_SESSION['_totp_started'] ?? 0) > 600) { flash('error', 'El enrolamiento venció. Genera una nueva clave.'); redirect('/cuenta/seguridad'); }
        try { $valid = Totp::verify(SecretBox::decrypt((string) $account['totp_secret']), (string) $this->input('code')); }
        catch (\RuntimeException $e) { fail_validation(['code' => $e->getMessage()], [], '/cuenta/seguridad#2fa'); }
        if (!$valid) fail_validation(['code' => 'El código no coincide. Revisa la hora del dispositivo y vuelve a intentarlo.'], [], '/cuenta/seguridad#2fa');
        User::enableTotp(Auth::id());unset($_SESSION['_totp_started']);
        Audit::record('Segundo factor activado', 'User', Auth::id());
        flash('success', '2FA activado. Guarda ahora los códigos de recuperación en un lugar seguro; no volverán a mostrarse.');redirect('/cuenta/seguridad#2fa');
    }

    public function disableTwoFactor(): void
    {
        $this->confirmedAccount((string) $this->input('current_password_2fa'));
        User::disableTotp(Auth::id());unset($_SESSION['_totp_recovery'], $_SESSION['_totp_started']);
        Audit::record('Segundo factor desactivado', 'User', Auth::id());
        flash('success', 'El segundo factor fue desactivado y sus secretos eliminados de la cuenta.');redirect('/cuenta/seguridad');
    }

    public function updatePassword(): void
    {
        $data = ['current_password' => (string) $this->input('current_password'), 'password' => (string) $this->input('password'), 'password_confirmation' => (string) $this->input('password_confirmation')];
        $validator = new Validator($data);
        $validator->required('current_password', 'contraseña actual')->required('password', 'nueva contraseña')->password('password', 'nueva contraseña')->confirmed('password', 'password_confirmation');
        $errors = $validator->errors();$account = User::find(Auth::id());
        if ($account === null || !password_verify($data['current_password'], $account['password'])) $errors['current_password'] = 'La contraseña actual no coincide.';
        if ($data['current_password'] !== '' && hash_equals($data['current_password'], $data['password'])) $errors['password'] = 'La nueva contraseña debe ser diferente a la actual.';
        if ($errors !== []) fail_validation($errors, [], '/cuenta/seguridad');
        User::updatePassword(Auth::id(), password_hash($data['password'], PASSWORD_DEFAULT));session_regenerate_id(true);
        Audit::record('Contraseña actualizada', 'User', Auth::id());flash('success', 'Tu contraseña fue actualizada correctamente.');redirect('/cuenta/seguridad');
    }

    private function confirmedAccount(string $password): array
    {
        $account = User::find(Auth::id()) ?: $this->notFound();
        if ($password === '' || !password_verify($password, $account['password'])) fail_validation(['current_password_2fa' => 'Confirma tu contraseña actual para modificar 2FA.'], [], '/cuenta/seguridad#2fa');
        return $account;
    }
}
