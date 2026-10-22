<div class="w-full max-w-md mac-card p-8" style="border-radius:22px">
    <div class="text-center mb-6">
        <div class="w-14 h-14 mx-auto rounded-2xl flex items-center justify-center text-white text-2xl shadow" style="background:linear-gradient(180deg,#ff9500,#c93400)">
            <i class="fa-solid fa-key"></i>
        </div>
        <h1 class="text-xl font-bold mt-3">Recuperar contraseña</h1>
        <p class="text-sm mac-muted">Verifica tu identidad con tu pregunta secreta o tu PIN único.</p>
    </div>

    <?php if (($paso ?? 1) === 1): ?>
    <form method="POST" action="<?= base_url('recuperar') ?>" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div>
            <label class="block text-sm font-medium mb-1">Correo de tu cuenta</label>
            <input type="email" name="email" required autofocus class="mac-input w-full" style="padding:.7rem" placeholder="tucorreo@ejemplo.com">
        </div>
        <button class="mac-btn w-full justify-center" style="padding:.75rem">Continuar</button>
    </form>
    <?php else: ?>
    <div class="space-y-5">
        <?php if (!empty($tienePregunta)): ?>
        <form method="POST" action="<?= base_url('recuperar/verificar') ?>" class="space-y-3">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="metodo" value="pregunta">
            <p class="text-sm font-semibold">❓ <?= e($pregunta ?? 'Pregunta secreta') ?></p>
            <input type="text" name="respuesta" required class="mac-input w-full" style="padding:.7rem" placeholder="Tu respuesta (sin importar tildes ni mayúsculas)">
            <button class="mac-btn w-full justify-center" style="padding:.7rem">Verificar respuesta</button>
        </form>
        <?php endif; ?>

        <?php if (!empty($tienePregunta) && !empty($tienePin)): ?><p class="text-center text-xs mac-muted">— o usa tu PIN —</p><?php endif; ?>

        <?php if (!empty($tienePin)): ?>
        <form method="POST" action="<?= base_url('recuperar/verificar') ?>" class="space-y-3">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="metodo" value="pin">
            <p class="text-sm font-semibold">🔢 PIN único de recuperación (6 dígitos)</p>
            <input type="password" name="pin" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                   class="mac-input w-full text-center text-2xl tracking-[.5em]" style="padding:.7rem" placeholder="••••••">
            <button class="mac-btn w-full justify-center" style="padding:.7rem">Verificar PIN</button>
        </form>
        <?php endif; ?>

        <form method="POST" action="<?= base_url('recuperar/cancelar') ?>" class="text-center">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="text-sm underline mac-muted">Cancelar y volver al inicio de sesión</button>
        </form>
        <p class="text-[11px] mac-muted text-center">Tienes 5 intentos y 10 minutos. Los datos de recuperación se guardan cifrados (hash), nadie puede verlos.</p>
    </div>
    <?php endif; ?>

    <p class="text-center mt-5"><a href="<?= base_url('login') ?>" class="text-sm underline" style="color:var(--mac-blue)">← Volver a iniciar sesión</a></p>
</div>
