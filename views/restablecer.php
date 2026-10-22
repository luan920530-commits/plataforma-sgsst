<div class="w-full max-w-md mac-card p-8" style="border-radius:22px">
    <div class="text-center mb-6">
        <div class="w-14 h-14 mx-auto rounded-2xl flex items-center justify-center text-white text-2xl shadow" style="background:linear-gradient(180deg,#34c759,#248a3d)">
            <i class="fa-solid fa-lock-open"></i>
        </div>
        <h1 class="text-xl font-bold mt-3">Crea tu nueva contraseña</h1>
        <p class="text-sm mac-muted">Identidad verificada ✅ Escribe tu nueva contraseña.</p>
    </div>
    <form method="POST" action="<?= base_url('recuperar/restablecer') ?>" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div>
            <label class="block text-sm font-medium mb-1">Nueva contraseña</label>
            <input type="password" name="password" required minlength="10" autofocus class="mac-input w-full" style="padding:.7rem" placeholder="Mínimo 10 caracteres">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Confirmar contraseña</label>
            <input type="password" name="password_confirm" required minlength="10" class="mac-input w-full" style="padding:.7rem" placeholder="Repite la contraseña">
        </div>
        <button class="mac-btn w-full justify-center" style="padding:.75rem">Guardar y entrar</button>
    </form>
</div>
