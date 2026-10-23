<header class="sticky top-0 z-20 border-b border-slate-200/50 bg-ios/80 backdrop-blur-2xl">
    <div class="mx-auto flex h-[72px] max-w-[1540px] items-center gap-3 px-4 sm:px-6 lg:px-9">
        <button type="button" id="sidebar-toggle" class="btn-icon lg:hidden" aria-label="Abrir menú" aria-controls="app-sidebar" aria-expanded="false"><?= icon('menu', 'h-5 w-5') ?></button>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 text-[11px] font-medium text-slate-400">
                <span><?= $user['rol'] === 'SuperAdmin' ? 'Plataforma' : e(App\Services\ModuleAccess::shortLabel(App\Services\ModuleAccess::current())) ?></span>
                <?= icon('chevron-right', 'h-3 w-3') ?>
                <span class="truncate text-slate-500"><?= e($title ?? 'Inicio') ?></span>
            </div>
            <p class="mt-0.5 hidden text-xs font-medium text-slate-500 sm:block"><?= format_date(date('Y-m-d'), true) ?> · Colombia</p>
        </div>
        <?php if ($config['demo']): ?><span class="hidden rounded-full bg-violet-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-violet-600 ring-1 ring-violet-100 sm:inline-flex">Entorno demo</span><?php endif; ?>
        <div class="relative">
            <button type="button" class="flex items-center gap-2 rounded-2xl p-1.5 pr-2 transition hover:bg-white hover:shadow-sm sm:gap-3" data-menu-toggle="user-menu" aria-expanded="false">
                <span class="avatar"><?= e(initials($user['nombre'])) ?></span>
                <span class="hidden text-left md:block">
                    <span class="block max-w-[170px] truncate text-xs font-semibold text-slate-800"><?= e($user['nombre']) ?></span>
                    <span class="mt-0.5 block text-[10px] text-slate-400"><?= e(role_label($user['rol'])) ?></span>
                </span>
                <?= icon('chevron-down', 'hidden h-3.5 w-3.5 text-slate-400 sm:block') ?>
            </button>
            <div id="user-menu" class="menu-popover hidden" data-menu>
                <div class="border-b border-slate-100 px-4 py-3 md:hidden">
                    <p class="truncate text-sm font-semibold"><?= e($user['nombre']) ?></p>
                    <p class="mt-1 text-xs text-slate-400"><?= e(role_label($user['rol'])) ?></p>
                </div>
                <div class="p-1.5">
                    <div class="px-3 py-2 text-xs text-slate-400"><?= e($user['email']) ?></div>
                    <a href="<?= e(url('/cuenta/seguridad')) ?>" class="menu-item"><?= icon('lock', 'h-4 w-4') ?> Seguridad de la cuenta</a>
                    <?php if ($user['rol'] === 'AdminEmpresa'): ?>
                        <a href="<?= e(url('/empresa/configuracion')) ?>" class="menu-item"><?= icon('settings', 'h-4 w-4') ?> Configuración</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="menu-item w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700"><?= icon('logout', 'h-4 w-4') ?> Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
