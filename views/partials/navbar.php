<nav class="bg-white border-b border-slate-200 px-6 py-3 flex justify-between items-center sticky top-0 z-40 shadow-sm">
    <div class="flex items-center gap-4">
        <a href="<?= base_url('dashboard') ?>" class="flex items-center gap-2 font-bold text-brand-700 text-lg">
            <div class="w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center text-white">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <span>PPPRAE</span>
        </a>
        <span class="hidden md:inline text-xs text-slate-400 border-l border-slate-200 pl-4">
            Decreto 1072/2015
        </span>
    </div>

    <div class="flex items-center gap-4" x-data="{ abierto: false }">
        <button @click="abierto = !abierto"
                class="flex items-center gap-2 text-sm text-slate-700 hover:bg-slate-100 px-3 py-2 rounded-lg transition">
            <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-semibold text-xs">
                <?= strtoupper(substr(\App\Auth::user()['nombre'] ?? 'U', 0, 1)) ?>
            </div>
            <span class="hidden md:inline"><?= e(\App\Auth::user()['nombre'] ?? '') ?></span>
            <i class="fa-solid fa-chevron-down text-xs"></i>
        </button>

        <div x-show="abierto" x-cloak @click.outside="abierto = false"
             class="absolute right-6 top-16 bg-white border border-slate-200 rounded-lg shadow-lg w-56 py-2 z-50">
            <div class="px-4 py-2 border-b border-slate-100">
                <div class="text-xs text-slate-500">Sesión</div>
                <div class="text-sm font-medium text-slate-800 truncate">
                    <?= e(\App\Auth::user()['email'] ?? '') ?>
                </div>
                <div class="text-xs text-slate-400 mt-0.5">
                    <?= e(ucfirst(\App\Auth::user()['rol'] ?? '')) ?>
                </div>
            </div>
            <a href="<?= base_url('logout') ?>"
               class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </a>
        </div>
    </div>
</nav>
