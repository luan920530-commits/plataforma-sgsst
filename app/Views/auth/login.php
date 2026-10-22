<main class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
    <section class="relative hidden overflow-hidden bg-[#0a66d6] p-12 text-white lg:flex lg:flex-col xl:p-16">
        <div class="absolute inset-0 auth-grid opacity-30"></div>
        <div class="absolute -left-32 top-1/3 h-96 w-96 rounded-full bg-cyan-300/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-24 h-[430px] w-[430px] rounded-full bg-indigo-300/25 blur-3xl"></div>
        <a href="<?= e(url('/login')) ?>" class="relative flex items-center gap-3 font-semibold">
            <span class="flex h-11 w-11 items-center justify-center rounded-[15px] bg-white/15 ring-1 ring-white/25 backdrop-blur-xl"><?= icon('shield', 'h-6 w-6') ?></span>
            <span class="text-2xl font-bold tracking-[-0.04em]">normia<span class="ml-1.5 font-normal text-white/60">sst</span></span>
        </a>
        <div class="relative my-auto max-w-xl py-16">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium ring-1 ring-white/15 backdrop-blur-xl"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span> Seguridad y salud, mejor gestionadas</span>
            <h1 class="mt-7 text-[44px] font-semibold leading-[1.08] tracking-[-0.045em] xl:text-[54px]">El bienestar de tu equipo empieza con una buena gestión.</h1>
            <p class="mt-6 max-w-lg text-base leading-7 text-blue-100/75">Centraliza documentos, riesgos y acciones de mejora en una experiencia simple para toda tu organización.</p>
            <div class="mt-10 grid max-w-lg grid-cols-3 gap-3">
                <div class="auth-glass"><p class="text-2xl font-semibold">7 · 21 · 60</p><p class="mt-1 text-[11px] leading-4 text-blue-100/65">Estándares según tu clasificación</p></div>
                <div class="auth-glass"><p class="text-2xl font-semibold">PHVA</p><p class="mt-1 text-[11px] leading-4 text-blue-100/65">Mejora continua en un solo lugar</p></div>
                <div class="auth-glass"><p class="text-2xl font-semibold">100%</p><p class="mt-1 text-[11px] leading-4 text-blue-100/65">Información aislada por empresa</p></div>
            </div>
        </div>
        <div class="relative flex items-center gap-3 text-xs text-blue-100/60">
            <?= icon('shield-plain', 'h-4 w-4') ?>
            <span>Decreto 1072 de 2015 · Resolución 0312 de 2019</span>
        </div>
    </section>

    <section class="relative flex items-center justify-center px-5 py-10 sm:px-10 lg:px-12">
        <div class="absolute left-5 top-5 flex items-center gap-2 lg:hidden">
            <span class="brand-mark"><?= icon('shield', 'h-5 w-5') ?></span>
            <span class="text-lg font-bold tracking-tight">normia<span class="ml-1 font-medium text-slate-400">sst</span></span>
        </div>
        <div class="w-full max-w-[430px] pt-12 lg:pt-0">
            <div class="mb-8">
                <p class="text-sm font-semibold text-blue-600">Bienvenido de nuevo</p>
                <h2 class="mt-2 text-[34px] font-semibold tracking-[-0.04em] text-slate-950">Inicia sesión</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">Ingresa a tu espacio de trabajo para continuar gestionando lo importante.</p>
            </div>

            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
            <?php if (!empty($errors['general'])): ?><div class="mb-5 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-100"><?= e($errors['general']) ?></div><?php endif; ?>

            <form method="post" action="<?= e(url('/login')) ?>" class="space-y-5" novalidate>
                <?= csrf_field() ?>
                <div>
                    <label for="email" class="label">Correo electrónico</label>
                    <div class="input-with-icon">
                        <?= icon('mail', 'h-[18px] w-[18px]') ?>
                        <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" class="input pl-11" placeholder="nombre@empresa.co" autocomplete="username" required autofocus>
                    </div>
                    <?= field_error('email') ?>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between"><label for="password" class="label mb-0">Contraseña</label><span class="text-[11px] text-slate-400">Mínimo 10 caracteres</span></div>
                    <div class="input-with-icon">
                        <?= icon('lock', 'h-[18px] w-[18px]') ?>
                        <input id="password" name="password" type="password" class="input pl-11 pr-11" placeholder="Tu contraseña" autocomplete="current-password" required>
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1 text-slate-400 hover:text-blue-600" data-toggle-password="password" aria-label="Mostrar contraseña"><?= icon('eye', 'h-[18px] w-[18px]') ?></button>
                    </div>
                    <?= field_error('password') ?>
                </div>
                <button type="submit" class="btn-primary h-12 w-full text-[15px]">Ingresar de forma segura <?= icon('arrow-right', 'h-4 w-4') ?></button>
            </form>

            <?php if ($config['demo']): ?>
                <div class="my-7 flex items-center gap-3"><span class="h-px flex-1 bg-slate-200"></span><span class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Explora los perfiles demo</span><span class="h-px flex-1 bg-slate-200"></span></div>
                <div class="grid grid-cols-2 gap-2.5">
                    <?php foreach ([
                        ['SuperAdmin', 'superadmin@normia.co', 'shield-plain'],
                        ['Admin empresa', 'admin@andina.co', 'building'],
                        ['Responsable SST', 'sst@andina.co', 'clipboard'],
                        ['Trabajador', 'trabajador@andina.co', 'user'],
                    ] as $demo): ?>
                        <button type="button" class="demo-account" data-demo-email="<?= e($demo[1]) ?>" data-demo-password="Normia2026!">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition group-hover:bg-blue-50 group-hover:text-blue-600"><?= icon($demo[2], 'h-4 w-4') ?></span>
                            <span class="text-left"><span class="block text-xs font-semibold text-slate-700"><?= e($demo[0]) ?></span><span class="mt-0.5 block text-[10px] text-slate-400">Usar este acceso</span></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="mt-3 text-center text-[10px] leading-5 text-slate-400">Datos ficticios para evaluación local · No usar en producción.</p>
            <?php endif; ?>

            <div class="mt-8 flex items-center justify-center gap-2 text-[11px] text-slate-400"><?= icon('lock', 'h-3.5 w-3.5') ?><span>Sesión protegida · CSRF · Contraseñas con hash seguro</span></div>
        </div>
    </section>
</main>
