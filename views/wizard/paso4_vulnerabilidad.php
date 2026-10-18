<?php
require BASE_PATH . '/views/partials/wizard_steps.php';

// Fallbacks
$datos           = $datos ?? null;
$amenazaActivaId = $amenazaActivaId ?? 0;
$amenazas        = $amenazas ?? [];
?>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden"
     x-data="vulnerabilidad(<?= htmlspecialchars(json_encode($datos), ENT_QUOTES, 'UTF-8') ?>)"
     x-cloak>

    <div class="bg-brand-600 text-white px-6 py-4">
        <h2 class="text-xl font-semibold flex items-center gap-3">
            <i class="fa-solid fa-chart-simple"></i>
            Paso 4 — Análisis de vulnerabilidad
        </h2>
        <p class="text-brand-100 text-sm mt-1">
            Selecciona cada amenaza y responde su cuestionario
        </p>
    </div>

    <!-- Tabs de amenazas -->
    <div class="border-b border-slate-200 px-4 pt-4">
        <div class="flex flex-wrap gap-2 pb-3">
            <?php foreach ($amenazas as $a): ?>
                <a href="<?= base_url("analisis/{$analisisId}/wizard/paso4?amenaza={$a['id']}") ?>"
                   class="px-3 py-1.5 text-sm rounded-lg transition
                          <?= ((int)$a['id'] === $amenazaActivaId)
                              ? 'bg-brand-600 text-white font-medium'
                              : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    <?= e($a['nombre']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="p-6">
        <?php if (!$datos || empty($datos['categorias'])): ?>
            <div class="p-8 text-center border border-dashed border-slate-300 rounded-xl">
                <i class="fa-solid fa-clipboard-question text-3xl text-slate-300 mb-3"></i>
                <p class="text-slate-500">No hay cuestionario cargado para esta amenaza</p>
            </div>
        <?php else: ?>

            <!-- Info amenaza -->
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 mb-6">
                <div class="flex justify-between items-center flex-wrap gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-800 text-lg"><?= e($datos['nombre']) ?></h3>
                        <p class="text-sm text-slate-500 mt-0.5">
                            Responde marcando SI / NO / PARCIAL / N/A. Se guarda automáticamente.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <div class="text-xs text-slate-500 uppercase">Promedio</div>
                            <div class="text-2xl font-bold"
                                 :class="colorClase" x-text="promedio.toFixed(2)"></div>
                        </div>
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center"
                             :class="colorFondo"
                             :title="colorTexto">
                            <i class="fa-solid fa-circle text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categorías -->
            <div class="space-y-4">
                <template x-for="(cat, idx) in categorias" :key="idx">
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <button type="button"
                                class="w-full bg-slate-50 px-4 py-3 flex justify-between items-center hover:bg-slate-100 transition"
                                @click="cat.abierta = !cat.abierta">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid" :class="cat.abierta ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                <span class="font-semibold text-slate-800 text-left"
                                      x-text="cat.nombre"></span>
                            </div>
                            <span class="text-xs text-slate-500">
                                <span x-text="cat.preguntas.filter(p => p.calificacion !== null).length"></span>
                                / <span x-text="cat.preguntas.length"></span>
                            </span>
                        </button>

                        <div x-show="cat.abierta" x-transition class="divide-y divide-slate-100">
                            <template x-for="(preg, pidx) in cat.preguntas" :key="preg.id">
                                <div class="p-4 grid grid-cols-12 gap-3 items-start hover:bg-slate-50">
                                    <span class="col-span-12 md:col-span-5 text-sm text-slate-700 leading-snug"
                                          x-text="preg.texto"></span>

                                    <div class="col-span-12 md:col-span-3 flex gap-1 flex-wrap">
                                        <template x-for="op in ['SI','NO','PARCIAL','N/A']" :key="op">
                                            <button type="button"
                                                    class="px-2 py-1 text-xs rounded border font-medium transition"
                                                    :class="preg.respuesta === op
                                                        ? (op === 'SI'      ? 'bg-green-600 text-white border-green-600'
                                                         : op === 'NO'      ? 'bg-red-600 text-white border-red-600'
                                                         : op === 'PARCIAL' ? 'bg-yellow-500 text-white border-yellow-500'
                                                         : 'bg-slate-600 text-white border-slate-600')
                                                        : 'bg-white text-slate-600 border-slate-300 hover:border-slate-400'"
                                                    @click="setRespuesta(preg, op)"
                                                    x-text="op"></button>
                                        </template>
                                    </div>

                                    <input type="text"
                                           placeholder="Observación"
                                           class="col-span-12 md:col-span-3 px-2 py-1 text-sm border border-slate-300 rounded focus:ring-1 focus:ring-brand-500 outline-none"
                                           x-model="preg.observacion"
                                           @change="guardar(preg)">

                                    <span class="col-span-12 md:col-span-1 text-center font-mono font-semibold text-sm"
                                          :class="colorCalificacion(preg.calificacion)"
                                          x-text="preg.calificacion !== null ? preg.calificacion.toFixed(1) : '—'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        <?php endif; ?>

        <!-- Botones -->
        <div class="flex justify-between pt-6 mt-6 border-t border-slate-200">
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso3") ?>"
               class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left mr-2"></i> Anterior
            </a>
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso5") ?>"
               class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                Siguiente <i class="fa-solid fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</div>

<script>
function vulnerabilidad(data) {
    return {
        categorias: (data?.categorias || []).map(c => ({ ...c, abierta: true })),

        get total() {
            return this.categorias.reduce((s, c) => s + c.preguntas.length, 0);
        },
        get respondidas() {
            return this.categorias.reduce((s, c) =>
                s + c.preguntas.filter(p => p.calificacion !== null).length, 0);
        },
        get promedio() {
            const vals = this.categorias
                .flatMap(c => c.preguntas)
                .filter(p => p.calificacion !== null)
                .map(p => p.calificacion);
            return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : 0;
        },
        get colorTexto() {
            const p = this.promedio;
            if (p <= 0.5) return 'ROJO — Alta vulnerabilidad';
            if (p <= 0.7) return 'AMARILLO — Media';
            return 'VERDE — Baja';
        },
        get colorClase() {
            const p = this.promedio;
            if (p <= 0.5) return 'text-red-600';
            if (p <= 0.7) return 'text-yellow-600';
            return 'text-green-600';
        },
        get colorFondo() {
            const p = this.promedio;
            if (p <= 0.5) return 'bg-red-600';
            if (p <= 0.7) return 'bg-yellow-500';
            return 'bg-green-600';
        },
        colorCalificacion(c) {
            if (c === null) return 'text-slate-300';
            if (c >= 1) return 'text-green-600';
            if (c >= 0.5) return 'text-yellow-600';
            return 'text-red-600';
        },
        setRespuesta(preg, valor) {
            const mapa = { 'SI': 1, 'NO': 0, 'PARCIAL': 0.5, 'N/A': 1 };
            preg.respuesta    = valor;
            preg.calificacion = mapa[valor];
            this.guardar(preg);
        },
        guardar(preg) {
            clearTimeout(this._t);
            this._t = setTimeout(() => {
                fetch('<?= base_url('api/respuestas') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: preg.id,
                        respuesta: preg.respuesta,
                        observacion: preg.observacion,
                    }),
                });
            }, 400);
        }
    };
}
</script>
