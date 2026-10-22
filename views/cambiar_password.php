<div class="max-w-md mx-auto bg-white border rounded-2xl p-6 space-y-4">
  <h1 class="text-xl font-bold">Cambiar contraseña</h1>
  <?php if (\App\Auth::mustChangePassword()): ?><p class="text-sm bg-yellow-50 border border-yellow-200 rounded-lg p-3">Por seguridad debes cambiar la contraseña temporal antes de continuar usando la plataforma.</p><?php endif; ?>
  <form method="POST" action="<?= base_url('cambiar-password') ?>" class="space-y-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="password" name="password" required minlength="10" placeholder="Nueva contraseña (mín. 10 caracteres)" class="w-full border rounded-lg px-3 py-2">
    <input type="password" name="password_confirm" required minlength="10" placeholder="Confirmar contraseña" class="w-full border rounded-lg px-3 py-2">
    <button class="w-full bg-brand-600 text-white rounded-lg py-2">Guardar contraseña</button>
  </form>
</div>

<div class="max-w-md mx-auto bg-white border rounded-2xl p-6 space-y-4 mt-5">
  <h2 class="text-lg font-bold">🔐 Recuperación de contraseña</h2>
  <p class="text-sm text-slate-500">Configura tu <b>pregunta secreta</b> y/o tu <b>PIN único de 6 dígitos</b>. Si olvidas tu contraseña podrás restablecerla desde el login sin llamar a nadie. Los datos se guardan cifrados: nadie puede verlos.</p>
  <form method="POST" action="<?= base_url('mi-seguridad/recuperacion') ?>" class="space-y-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="block text-sm font-medium">Pregunta secreta
      <select name="pregunta" class="w-full border rounded-lg px-3 py-2 mt-1">
        <option value="">— Elegir pregunta —</option>
        <?php foreach (\App\Controllers\AuthController::preguntasSecretas() as $q): ?>
        <option value="<?= e($q) ?>"><?= e($q) ?></option>
        <?php endforeach; ?>
      </select></label>
    <input type="text" name="respuesta" placeholder="Tu respuesta secreta" class="w-full border rounded-lg px-3 py-2">
    <label class="block text-sm font-medium">PIN único (6 dígitos)
      <input type="password" name="pin" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Ej: 482916 (no lo compartas)" class="w-full border rounded-lg px-3 py-2 mt-1"></label>
    <button class="w-full text-white rounded-lg py-2" style="background:linear-gradient(180deg,#ff9500,#e07b00)">Guardar datos de recuperación</button>
    <p class="text-[11px] text-slate-400">Puedes configurar uno solo o los dos. Si vuelves a guardar, se reemplazan los anteriores.</p>
  </form>
</div>
