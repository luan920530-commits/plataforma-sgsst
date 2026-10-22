<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Services\Installer;
use App\Support\Validator;
use Throwable;

final class InstallController extends BaseController
{
    public function show(): void
    {
        if (!Installer::needsInstallation()) {
            redirect('/login');
        }

        $this->view('auth/install', [
            'title' => 'Instalación inicial',
            'checks' => Installer::requirements(),
            'canInstall' => Installer::canInstall(),
            'databaseReady' => Database::isInstalled(),
            'detectedUrl' => absolute_url('/'),
            'secureConnection' => str_starts_with(absolute_url('/'), 'https://'),
        ], 'layouts/auth');
    }

    public function store(): void
    {
        if (!Installer::needsInstallation()) {
            redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            fail_validation(['general' => 'La sesión de instalación venció. Recarga la página e inténtalo nuevamente.'], [], '/instalar');
        }

        $data = [
            'nombre' => (string) $this->input('nombre'),
            'email' => text_lower((string) $this->input('email')),
            'password' => (string) $this->input('password'),
            'password_confirmation' => (string) $this->input('password_confirmation'),
        ];
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre')->max('nombre', 'nombre', 120)
            ->required('email', 'correo electrónico')->email('email')->max('email', 'correo electrónico', 190)
            ->required('password', 'contraseña')->password('password')
            ->confirmed('password', 'password_confirmation');
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/instalar');
        }
        if (!Installer::canInstall()) {
            fail_validation(['general' => 'El servidor todavía no cumple los requisitos señalados. Corrígelos y recarga esta página.'], $data, '/instalar');
        }

        try {
            $admin = Installer::installWithSuperAdmin($data['email'], $data['password'], $data['nombre']);
        } catch (Throwable $exception) {
            error_log((string) $exception);
            fail_validation([
                'general' => 'No se pudo completar la instalación. Revisa los requisitos, los permisos de database/storage y el registro storage/logs/app.log.',
            ], $data, '/instalar');
        }

        if (!$admin['created']) {
            redirect('/login');
        }
        session_regenerate_id(true);
        flash('success', 'Instalación completada. Ingresa con el correo ' . $admin['email'] . ' y la contraseña que acabas de crear.');
        redirect('/login');
    }
}
