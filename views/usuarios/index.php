<div class="space-y-6">
  <div><h1 class="text-2xl font-bold">Usuarios y roles</h1>
  <p class="text-sm text-slate-500">Roles: <b>superadmin</b> (administra todo), <b>consultor</b> (gestiona sus empresas), <b>cliente</b> (consulta sus empresas). Los datos siempre quedan aislados por propietario.</p></div>

  <form method="POST" action="<?= base_url('usuarios') ?>" class="bg-white border rounded-xl p-4 grid md:grid-cols-5 gap-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input name="nombre" required placeholder="Nombre" class="border rounded-lg px-3 py-2">
    <input name="email" type="email" required placeholder="Correo" class="border rounded-lg px-3 py-2">
    <input name="password" type="password" required minlength="10" placeholder="Contraseña temporal (mín. 10)" class="border rounded-lg px-3 py-2">
    <select name="rol" class="border rounded-lg px-3 py-2"><option value="consultor">Consultor</option><option value="cliente">Cliente</option><option value="superadmin">Superadmin</option></select>
    <button class="bg-brand-600 text-white rounded-lg px-4 py-2">Crear usuario</button>
  </form>

  <div class="bg-white border rounded-xl overflow-hidden"><table class="w-full text-sm">
    <thead class="bg-slate-50"><tr><th class="text-left px-4 py-2">Usuario</th><th class="text-left px-4 py-2">Rol</th><th class="text-left px-4 py-2">Activo</th><th class="text-left px-4 py-2">Nueva contraseña (opcional)</th><th></th></tr></thead>
    <tbody class="divide-y"><?php foreach ($usuarios as $u): ?>
      <tr><td class="px-4 py-3"><b><?= e($u['nombre']) ?></b><br><span class="text-slate-500"><?= e($u['email']) ?></span></td>
      <td colspan="4" class="px-4 py-3"><form method="POST" action="<?= base_url("usuarios/{$u['id']}") ?>" class="flex flex-wrap gap-2 items-center">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <select name="rol" class="border rounded-lg px-2 py-1.5"><?php foreach (['superadmin','consultor','cliente'] as $r): ?><option value="<?= $r ?>" <?= $u['rol']===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select>
        <label class="flex gap-1 items-center"><input type="checkbox" name="activo" value="1" <?= (int)$u['activo']?'checked':'' ?>> Activo</label>
        <input name="password" type="password" minlength="10" placeholder="Nueva contraseña" class="border rounded-lg px-2 py-1.5">
        <button class="bg-slate-800 text-white rounded-lg px-3 py-1.5">Guardar</button>
      </form></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
