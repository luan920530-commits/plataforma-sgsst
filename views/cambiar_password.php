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
