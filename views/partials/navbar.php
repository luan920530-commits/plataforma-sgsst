<?php $criticas = class_exists('\\App\\Sgsst') ? \App\Sgsst::conteoAlertasCriticas((int)\App\Auth::id()) : 0; ?>
<nav class="mac-glass px-4 md:px-6 py-2.5 flex justify-between items-center sticky top-0 z-40">
    <div class="flex items-center gap-3">
        <span class="hidden sm:flex gap-2 mr-1" aria-hidden="true">
            <span class="traffic" style="background:#ff5f57"></span>
            <span class="traffic" style="background:#febc2e"></span>
            <span class="traffic" style="background:#28c840"></span>
        </span>
        <a href="<?= base_url('dashboard') ?>" class="flex items-center gap-2.5 font-bold text-lg" style="color:var(--mac-ink)">
            <span class="w-9 h-9 rounded-[10px] flex items-center justify-center text-white shadow" style="background:linear-gradient(180deg,#0a84ff,#0040dd)">
                <i class="fa-solid fa-shield-halved"></i>
            </span>
            <span>SG&#8209;SST</span>
        </a>
        <span class="hidden lg:inline text-xs mac-muted border-l pl-4" style="border-color:var(--mac-stroke)">
            Administración · Gestión · Creación · Auditoría
        </span>
    </div>

    <div class="flex items-center gap-2 md:gap-3" x-data="{ abierto: false }">
        <a href="<?= base_url('sgsst') ?>" title="Alertas críticas de vencimiento y riesgo"
           class="relative w-9 h-9 rounded-full flex items-center justify-center hover:bg-black/5 transition" style="color:#3a3a3c">
            <i class="fa-regular fa-bell text-lg"></i>
            <?php if ($criticas > 0): ?>
            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full text-white text-[10px] font-bold flex items-center justify-center" style="background:var(--mac-red)"><?= $criticas > 99 ? '99+' : $criticas ?></span>
            <?php endif; ?>
        </a>
        <button @click="abierto = !abierto"
                class="flex items-center gap-2 text-sm hover:bg-black/5 px-2.5 py-1.5 rounded-full transition">
            <span class="w-8 h-8 rounded-full text-white flex items-center justify-center font-semibold text-xs shadow" style="background:linear-gradient(180deg,#bf5af2,#8e2de0)">
                <?= strtoupper(substr(\App\Auth::user()['nombre'] ?? 'U', 0, 1)) ?>
            </span>
            <span class="hidden md:inline font-medium"><?= e(\App\Auth::user()['nombre'] ?? '') ?></span>
            <i class="fa-solid fa-chevron-down text-[10px] mac-muted"></i>
        </button>

        <div x-show="abierto" x-cloak @click.outside="abierto = false"
             class="absolute right-4 top-[60px] w-60 py-2 z-50 mac-card" style="border-radius:14px">
            <div class="px-4 py-2 border-b" style="border-color:var(--mac-stroke)">
                <div class="text-[11px] mac-muted uppercase tracking-wide">Sesión iniciada</div>
                <div class="text-sm font-semibold truncate"><?= e(\App\Auth::user()['email'] ?? '') ?></div>
                <div class="text-xs mac-muted mt-0.5">🎛 Rol: <?= e(ucfirst(\App\Auth::user()['rol'] ?? '')) ?></div>
            </div>
            <a href="<?= base_url('cambiar-password') ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-black/5 transition">
                <i class="fa-solid fa-key" style="color:var(--mac-blue)"></i> Cambiar contraseña
            </a>
            <form method="POST" action="<?= base_url('logout') ?>">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm hover:bg-red-50 transition text-left" style="color:var(--mac-red)">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</nav>
