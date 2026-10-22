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
            $this->redirect(base_url(Auth::mustChangePassword() ? 'cambiar-password' : (Auth::hasRole('cliente') ? 'mi-empresa' : 'dashboard')));
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

    // ================= Recuperación de contraseña =================

    public static function preguntasSecretas(): array
    {
        return [
            '¿Cuál es el nombre de tu primera mascota?',
            '¿En qué ciudad naciste?',
            '¿Cuál es el segundo nombre de tu madre?',
            '¿Cómo se llamaba tu primera escuela?',
            '¿Cuál es tu comida favorita?',
            '¿Cuál es el nombre de tu mejor amigo de la infancia?',
        ];
    }

    private function normalizarRespuesta(string $r): string
    {
        $r = mb_strtolower(trim($r));
        $r = strtr($r, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return preg_replace('/\s+/', ' ', $r) ?? $r;
    }

    /** POST /mi-seguridad/recuperacion — cada usuario configura su pregunta/PIN (logueado) */
    public function guardarRecuperacion(): void
    {
        $this->verifyCsrf();
        $pregunta = trim($_POST['pregunta'] ?? '');
        $respuesta = trim($_POST['respuesta'] ?? '');
        $pin = trim($_POST['pin'] ?? '');
        if (!in_array($pregunta, self::preguntasSecretas(), true)) $pregunta = '';
        if (($pregunta !== '' && strlen($respuesta) < 3) || ($pin !== '' && !preg_match('/^\d{6}$/', $pin)) || ($pregunta === '' && $pin === '')) {
            flash('error', 'Configura al menos un método válido: pregunta secreta (respuesta de 3+ caracteres) y/o un PIN de exactamente 6 dígitos.');
            $this->redirect(base_url('cambiar-password'));
        }
        $sets = []; $vals = [];
        if ($pregunta !== '') {
            $sets[] = 'recuperacion_pregunta = ?'; $vals[] = $pregunta;
            $sets[] = 'recuperacion_respuesta_hash = ?'; $vals[] = password_hash($this->normalizarRespuesta($respuesta), PASSWORD_DEFAULT);
        }
        if ($pin !== '') {
            $sets[] = 'recuperacion_pin_hash = ?'; $vals[] = password_hash($pin, PASSWORD_DEFAULT);
        }
        $vals[] = Auth::id();
        Database::execute('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
        Auditoria::registrar('auth.recuperacion_configurada', null, 'usuario', Auth::id());
        flash('success', 'Datos de recuperación guardados. Ya puedes usar «¿Olvidaste tu contraseña?» en el login si la olvidas.');
        $this->redirect(base_url('cambiar-password'));
    }

    /** GET /recuperar — paso 1: correo */
    public function showRecuperar(): void
    {
        if (Auth::check()) $this->redirect(base_url('dashboard'));
        $paso = isset($_SESSION['recuperacion_user_id']) ? 2 : 1;
        $u = $paso === 2 ? Database::first('SELECT recuperacion_pregunta, recuperacion_respuesta_hash, recuperacion_pin_hash FROM usuarios WHERE id = ?', [(int) $_SESSION['recuperacion_user_id']]) : null;
        $this->view('recuperar', [
            'titulo' => 'Recuperar contraseña', 'csrf' => $this->csrfToken(), 'paso' => $paso,
            'pregunta' => $u['recuperacion_pregunta'] ?? null,
            'tienePregunta' => !empty($u['recuperacion_respuesta_hash']),
            'tienePin' => !empty($u['recuperacion_pin_hash']),
        ]);
    }

    /** POST /recuperar — identifica la cuenta y pasa a la verificación */
    public function recuperarIdentificar(): void
    {
        $this->verifyCsrf();
        $email = trim($_POST['email'] ?? '');
        $u = Database::first('SELECT id, recuperacion_respuesta_hash, recuperacion_pin_hash FROM usuarios WHERE email = ? AND activo = 1', [$email]);
        if (!$u || (empty($u['recuperacion_respuesta_hash']) && empty($u['recuperacion_pin_hash']))) {
            // Mismo mensaje exista o no la cuenta (no revelar correos registrados)
            flash('error', 'Si ese correo tiene configurada la recuperación, continúa con la verificación. Si no configuraste pregunta secreta ni PIN, pide al administrador que restablezca tu contraseña.');
            $this->redirect(base_url('recuperar'));
        }
        $_SESSION['recuperacion_user_id'] = (int) $u['id'];
        $_SESSION['recuperacion_intentos'] = 0;
        $_SESSION['recuperacion_inicio'] = time();
        $this->redirect(base_url('recuperar'));
    }

    /** POST /recuperar/verificar — valida pregunta secreta o PIN único */
    public function recuperarVerificar(): void
    {
        $this->verifyCsrf();
        $uid = (int) ($_SESSION['recuperacion_user_id'] ?? 0);
        if (!$uid || time() - (int) ($_SESSION['recuperacion_inicio'] ?? 0) > 600) {
            unset($_SESSION['recuperacion_user_id']);
            flash('error', 'La sesión de recuperación venció (10 minutos). Vuelve a empezar.');
            $this->redirect(base_url('recuperar'));
        }
        $_SESSION['recuperacion_intentos'] = (int) ($_SESSION['recuperacion_intentos'] ?? 0) + 1;
        if ($_SESSION['recuperacion_intentos'] > 5) {
            unset($_SESSION['recuperacion_user_id'], $_SESSION['recuperacion_verificado']);
            flash('error', 'Demasiados intentos fallidos. Espera y vuelve a intentarlo, o pide ayuda al administrador.');
            $this->redirect(base_url('login'));
        }
        $u = Database::first('SELECT recuperacion_respuesta_hash, recuperacion_pin_hash FROM usuarios WHERE id = ? AND activo = 1', [$uid]);
        $ok = false;
        if (($_POST['metodo'] ?? '') === 'pin' && !empty($u['recuperacion_pin_hash'])) {
            $ok = password_verify(trim($_POST['pin'] ?? ''), $u['recuperacion_pin_hash']);
        } elseif (!empty($u['recuperacion_respuesta_hash'])) {
            $ok = password_verify($this->normalizarRespuesta($_POST['respuesta'] ?? ''), $u['recuperacion_respuesta_hash']);
        }
        if (!$ok) {
            flash('error', 'El dato no coincide. Revisa e intenta de nuevo (' . $_SESSION['recuperacion_intentos'] . '/5 intentos).');
            $this->redirect(base_url('recuperar'));
        }
        $_SESSION['recuperacion_verificado'] = true;
        Auditoria::registrar('auth.recuperacion_verificada', null, 'usuario', $uid);
        $this->redirect(base_url('recuperar/restablecer'));
    }

    /** GET /recuperar/restablecer — paso 3: nueva contraseña */
    public function showRestablecer(): void
    {
        if (empty($_SESSION['recuperacion_verificado']) || empty($_SESSION['recuperacion_user_id'])) {
            $this->redirect(base_url('recuperar'));
        }
        $this->view('restablecer', ['titulo' => 'Nueva contraseña', 'csrf' => $this->csrfToken()]);
    }

    /** POST /recuperar/restablecer */
    public function restablecer(): void
    {
        $this->verifyCsrf();
        $uid = (int) ($_SESSION['recuperacion_user_id'] ?? 0);
        if (!$uid || empty($_SESSION['recuperacion_verificado']) || time() - (int) ($_SESSION['recuperacion_inicio'] ?? 0) > 600) {
            unset($_SESSION['recuperacion_user_id'], $_SESSION['recuperacion_verificado']);
            flash('error', 'La sesión de recuperación venció. Vuelve a empezar.');
            $this->redirect(base_url('recuperar'));
        }
        $nueva = $_POST['password'] ?? '';
        if (strlen($nueva) < 10 || $nueva !== ($_POST['password_confirm'] ?? '')) {
            flash('error', 'La contraseña debe tener mínimo 10 caracteres y coincidir con la confirmación.');
            $this->redirect(base_url('recuperar/restablecer'));
        }
        Database::execute('UPDATE usuarios SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            [password_hash($nueva, PASSWORD_DEFAULT), $uid]);
        unset($_SESSION['recuperacion_user_id'], $_SESSION['recuperacion_verificado'], $_SESSION['recuperacion_intentos'], $_SESSION['recuperacion_inicio']);
        Auditoria::registrar('auth.password_restablecida', null, 'usuario', $uid);
        flash('success', 'Contraseña restablecida. Ya puedes iniciar sesión con la nueva.');
        $this->redirect(base_url('login'));
    }

    /** POST /recuperar/cancelar */
    public function recuperarCancelar(): void
    {
        $this->verifyCsrf();
        unset($_SESSION['recuperacion_user_id'], $_SESSION['recuperacion_verificado'], $_SESSION['recuperacion_intentos'], $_SESSION['recuperacion_inicio']);
        $this->redirect(base_url('login'));
    }
}
