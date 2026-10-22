<div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 border border-slate-200">
    <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-brand-600 flex items-center justify-center text-white text-2xl mb-3">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">Plataforma SG-SST</h1>
        <p class="text-slate-500 text-sm mt-1">Administración, gestión, creación y auditoría del SG-SST</p>
    </div>

    <form method="POST" action="<?= base_url('login') ?>" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= e($csrf ?? '') ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Correo</label>
            <input type="email" name="email" required autofocus
                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none transition"
                   placeholder="admin@ppprae.local">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label>
            <input type="password" name="password" required
                   class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none transition"
                   placeholder="••••••••">
        </div>

        <button type="submit"
                class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-lg transition-colors">
            Iniciar sesión
        </button>
    </form>

    <p class="text-center text-xs text-slate-400 mt-6">
        Decreto 1072/2015 · Resolución 0312/2019
    </p>
</div>
