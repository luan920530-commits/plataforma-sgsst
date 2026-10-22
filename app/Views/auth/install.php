<main class="min-h-screen px-5 py-8 sm:px-8 lg:py-12">
    <div class="mx-auto max-w-5xl">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 font-semibold tracking-tight text-slate-950">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-sm"><?= icon('shield', 'h-6 w-6') ?></span>
                <span class="text-xl">Normia <span class="font-normal text-slate-400">SST</span></span>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">Instalación sin comandos</span>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
            <section class="ios-card p-6 sm:p-8">
                <span class="eyebrow">Paso único</span>
                <h1 class="mt-3 text-3xl font-semibold tracking-[-0.035em] text-slate-950"><?= $databaseReady ? 'Finaliza la instalación' : 'Todo listo para arrancar' ?></h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">El sistema creará la base SQLite y los catálogos. Solo necesitas definir el acceso principal; no se instalarán datos demo.</p>

                <div class="mt-7 space-y-3">
                    <?php foreach ($checks as $check): ?>
                        <div class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-100">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full <?= $check['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>"><?= icon($check['ok'] ? 'check' : 'alert', 'h-3.5 w-3.5') ?></span>
                            <div>
                                <p class="text-xs font-semibold text-slate-800"><?= e($check['label']) ?></p>
                                <p class="mt-1 text-[11px] leading-5 text-slate-500"><?= e($check['detail']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-6 rounded-2xl bg-blue-50 p-4 text-[11px] leading-5 text-blue-800 ring-1 ring-blue-100">
                    <p class="font-semibold">URL detectada</p>
                    <p class="mt-1 break-all font-mono"><?= e($detectedUrl) ?></p>
                    <p class="mt-2">Se usará automáticamente para los enlaces y QR. No necesitas crear ni editar <code>.env</code>.</p>
                </div>
                <?php if (!$secureConnection): ?>
                    <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100">Puedes completar la instalación, pero activa HTTPS antes de registrar información real o compartir accesos.</div>
                <?php endif; ?>
            </section>

            <section class="ios-card p-6 sm:p-8">
                <span class="metric-icon bg-blue-50 text-blue-600"><?= icon('lock', 'h-6 w-6') ?></span>
                <h2 class="mt-5 text-xl font-semibold tracking-tight text-slate-950">Crea el SuperAdmin</h2>
                <p class="mt-2 text-xs leading-5 text-slate-500">Esta cuenta administrará empresas y usuarios. La instalación quedará bloqueada después de crearla.</p>

                <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
                <?php if (!empty($errors['general'])): ?>
                    <div class="mt-5 rounded-2xl bg-rose-50 p-4 text-xs leading-5 text-rose-700 ring-1 ring-rose-100"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/instalar')) ?>" class="mt-7 space-y-5" novalidate>
                    <?= csrf_field() ?>
                    <div>
                        <label for="nombre" class="label">Nombre del administrador *</label>
                        <input id="nombre" name="nombre" class="input" value="<?= e(old('nombre')) ?>" maxlength="120" autocomplete="name" required autofocus>
                        <?= field_error('nombre') ?>
                    </div>
                    <div>
                        <label for="email" class="label">Correo de acceso *</label>
                        <input id="email" name="email" type="email" class="input" value="<?= e(old('email')) ?>" maxlength="190" autocomplete="username" required>
                        <?= field_error('email') ?>
                    </div>
                    <div>
                        <label for="password" class="label">Contraseña *</label>
                        <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                        <p class="mt-1.5 text-[10px] text-slate-400">Mínimo 10 caracteres, con mayúsculas, minúsculas y números.</p>
                        <?= field_error('password') ?>
                    </div>
                    <div>
                        <label for="password_confirmation" class="label">Confirmar contraseña *</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
                        <?= field_error('password_confirmation') ?>
                    </div>
                    <button type="submit" class="btn-primary h-12 w-full" <?= $canInstall ? '' : 'disabled' ?>><?= icon('database', 'h-4 w-4') ?> Instalar y crear acceso</button>
                </form>

                <?php if (!$canInstall): ?>
                    <p class="mt-4 text-center text-[11px] leading-5 text-rose-600">Corrige los requisitos marcados en rojo y recarga la página para habilitar la instalación.</p>
                <?php endif; ?>
                <p class="mt-6 text-center text-[10px] leading-5 text-slate-400">SQLite privado · contraseña con hash seguro · protección CSRF · sin cuentas de demostración</p>
            </section>
        </div>
    </div>
</main>
