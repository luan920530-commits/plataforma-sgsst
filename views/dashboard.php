<div class="space-y-6" x-data="dashboardApp()" x-init="cargar()">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
            <p class="text-slate-500 text-sm mt-0.5">Resumen general de tu actividad</p>
        </div>
        <a href="<?= base_url('empresas/nueva') ?>"
           class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium shadow-sm transition">
            <i class="fa-solid fa-plus mr-2"></i> Nueva empresa
        </a>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-building"></i>
                </div>
                <span class="text-xs text-slate-400">empresas</span>
            </div>
            <div class="text-3xl font-bold text-slate-800 mt-3"><?= $totalEmpresas ?></div>
            <div class="text-xs text-slate-500 mt-1">Registradas</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <span class="text-xs text-slate-400">análisis</span>
            </div>
            <div class="text-3xl font-bold text-slate-800 mt-3"><?= $totalAnalisis ?></div>
            <div class="text-xs text-slate-500 mt-1">Generados</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center">
                    <i class="fa-solid fa-users"></i>
                </div>
                <span class="text-xs text-slate-400">personal</span>
            </div>
            <div class="text-3xl font-bold text-slate-800 mt-3"><?= $totalPersonal ?></div>
            <div class="text-xs text-slate-500 mt-1">Registrado</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <span class="text-xs text-slate-400">brigada</span>
            </div>
            <div class="text-3xl font-bold text-slate-800 mt-3"><?= $totalBrigadistas ?></div>
            <div class="text-xs text-slate-500 mt-1">Brigadistas</div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-brand-600"></i>
                Distribución por nivel de riesgo
            </h3>
            <div class="h-64">
                <canvas id="chartNiveles"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-brand-600"></i>
                Análisis por mes (últimos 6)
            </h3>
            <div class="h-64">
                <canvas id="chartMeses"></canvas>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-bar text-brand-600"></i>
            Vulnerabilidad promedio por amenaza
        </h3>
        <div class="h-72">
            <canvas id="chartAmenazas"></canvas>
        </div>
    </div>

    <!-- Análisis recientes -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock text-brand-600"></i>
                Análisis recientes
            </h3>
            <a href="<?= base_url('analisis') ?>" class="text-sm text-brand-600 hover:underline">
                Ver todos <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>

        <?php if (empty($analisisRecientes)): ?>
            <div class="p-8 text-center text-slate-500">
                <i class="fa-solid fa-inbox text-3xl text-slate-300 mb-2"></i>
                <p class="text-sm">No hay análisis registrados aún</p>
            </div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-2 font-semibold text-slate-700">Empresa</th>
                        <th class="text-left px-4 py-2 font-semibold text-slate-700">NIT</th>
                        <th class="text-left px-4 py-2 font-semibold text-slate-700">Versión</th>
                        <th class="text-left px-4 py-2 font-semibold text-slate-700">Fecha</th>
                        <th class="text-left px-4 py-2 font-semibold text-slate-700">Estado</th>
                        <th class="text-right px-4 py-2 font-semibold text-slate-700"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($analisisRecientes as $a): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800"><?= e($a['razon_social']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($a['nit']) ?></td>
                            <td class="px-4 py-3 text-slate-600">v<?= e($a['version']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= e($a['fecha_elaboracion']) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?= $a['estado'] === 'generado' ? 'bg-green-100 text-green-700'
                                        : ($a['estado'] === 'completo' ? 'bg-blue-100 text-blue-700'
                                        : 'bg-yellow-100 text-yellow-700') ?>">
                                    <?= e($a['estado']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= base_url("analisis/{$a['id']}/wizard/paso1") ?>"
                                   class="text-brand-600 hover:underline">
                                    Abrir <i class="fa-solid fa-arrow-right ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function dashboardApp() {
    return {
        async cargar() {
            try {
                const res = await fetch('<?= base_url('api/dashboard/stats') ?>');
                const data = await res.json();

                this.renderNiveles(data.niveles);
                this.renderMeses(data.porMes);
                this.renderAmenazas(data.porAmenaza);
            } catch (e) {
                console.error('Error cargando stats:', e);
            }
        },

        renderNiveles(niveles) {
            const ctx = document.getElementById('chartNiveles');
            if (!ctx) return;

            const map = { 'BAJO': 0, 'MEDIO': 0, 'ALTO': 0 };
            niveles.forEach(n => {
                if (map[n.nivel_riesgo] !== undefined) map[n.nivel_riesgo] = n.c;
            });

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Bajo', 'Medio', 'Alto'],
                    datasets: [{
                        data: [map.BAJO, map.MEDIO, map.ALTO],
                        backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                },
            });
        },

        renderMeses(porMes) {
            const ctx = document.getElementById('chartMeses');
            if (!ctx) return;

            const labels = porMes.map(m => m.mes);
            const data   = porMes.map(m => m.c);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels.length ? labels : ['Sin datos'],
                    datasets: [{
                        label: 'Análisis',
                        data: data.length ? data : [0],
                        backgroundColor: '#2563EB',
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            });
        },

        renderAmenazas(porAmenaza) {
            const ctx = document.getElementById('chartAmenazas');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: porAmenaza.map(a => a.nombre),
                    datasets: [
                        {
                            label: 'Personas',
                            data: porAmenaza.map(a => parseFloat(a.personas || 0).toFixed(2)),
                            backgroundColor: '#2563EB',
                            borderRadius: 4,
                        },
                        {
                            label: 'Recursos',
                            data: porAmenaza.map(a => parseFloat(a.recursos || 0).toFixed(2)),
                            backgroundColor: '#10B981',
                            borderRadius: 4,
                        },
                        {
                            label: 'Sistemas',
                            data: porAmenaza.map(a => parseFloat(a.sistemas || 0).toFixed(2)),
                            backgroundColor: '#F59E0B',
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, max: 3 } },
                },
            });
        },
    };
}
</script>
