<?php
namespace App\Controllers;

use App\Auditoria;
use App\Database;

/** Gestión de usuarios y roles — solo superadmin (Fase 1: roles funcionales). */
class UsuarioController extends BaseController
{
    public function index(): void
    {
        $this->requireRole('superadmin');
        $this->view('usuarios/index', [
            'titulo' => 'Usuarios y roles',
            'usuarios' => Database::query('SELECT id,email,nombre,rol,activo,created_at FROM usuarios ORDER BY nombre'),
            'csrf' => $this->csrfToken(),
        ]);
    }

    public function crear(): void
    {
        $this->requireRole('superadmin'); $this->verifyCsrf();
        $email = trim($_POST['email'] ?? ''); $nombre = trim($_POST['nombre'] ?? '');
        $rol = in_array($_POST['rol'] ?? '', ['superadmin','consultor','cliente'], true) ? $_POST['rol'] : 'consultor';
        $pass = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $nombre === '' || strlen($pass) < 10) {
            flash('error', 'Datos inválidos: la contraseña debe tener al menos 10 caracteres.');
            $this->redirect(base_url('usuarios'));
        }
        Database::execute(
            'INSERT INTO usuarios (email,password_hash,nombre,rol,must_change_password) VALUES (?,?,?,?,1)',
            [$email, password_hash($pass, PASSWORD_DEFAULT), $nombre, $rol]
        );
        Auditoria::registrar('usuario.creado', null, 'usuario', Database::lastId(), "{$email} ({$rol})");
        flash('success', 'Usuario creado. Deberá cambiar su contraseña al ingresar.');
        $this->redirect(base_url('usuarios'));
    }

    public function actualizar(string $id): void
    {
        $this->requireRole('superadmin'); $this->verifyCsrf();
        $rol = in_array($_POST['rol'] ?? '', ['superadmin','consultor','cliente'], true) ? $_POST['rol'] : 'consultor';
        $activo = isset($_POST['activo']) ? 1 : 0;
        Database::execute('UPDATE usuarios SET rol=?, activo=? WHERE id=?', [$rol, $activo, (int)$id]);
        $nueva = $_POST['password'] ?? '';
        if (strlen($nueva) >= 10) {
            Database::execute('UPDATE usuarios SET password_hash=?, must_change_password=1 WHERE id=?', [password_hash($nueva, PASSWORD_DEFAULT), (int)$id]);
        }
        Auditoria::registrar('usuario.actualizado', null, 'usuario', (int)$id, "rol={$rol} activo={$activo}");
        flash('success', 'Usuario actualizado');
        $this->redirect(base_url('usuarios'));
    }
}
