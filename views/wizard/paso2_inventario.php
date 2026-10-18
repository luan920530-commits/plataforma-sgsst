<?php
require BASE_PATH . '/views/partials/wizard_steps.php';

$extintores = $extintores ?? [];
$botiquin   = $botiquin ?? [];
$otros      = $otros ?? [];

// Convertir "otros" a mapa [categoria => fila]
$otrosMap = [];
foreach ($otros as $o) {
    $otrosMap[$o['categoria']] = $o;
}

// Categorías fijas
$categoriasOtros = [
    'Camillas',
    'Gabinetes contra incendio',
    'Luces de emergencia',
    'Radios',
    'Paletas de pare y siga',
    'Silbatos',
    'Linternas',
    'Megáfonos',
    'Cuerdas de rescate',
    'Inmovilizadores cervicales',
];
?>

<form method="POST"
      action="<?= base_url("analisis/{$analisisId}/wizard/paso2") ?>"
      x-data="inventario()">

    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="bg-brand-600 text-white px-6 py-4">
            <h2 class="text-xl font-semibold flex items-center gap-3">
                <i class="fa-solid fa-boxes-stacked"></i>
                Paso 2 — Inventario de recursos
            </h2>
            <p class="text-brand-100 text-sm mt-1">
                Registra extintores, botiquín y otros recursos para emergencias
            </p>
        </div>

        <div class="p-6 space-y-6">

            <!-- ============ EXTINTORES ============ -->
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div class="bg-slate-50 px-4 py-3 flex justify-between items-center">
                    <h3 class="font-semibold text-slate-800">
                        <i class="fa-solid fa-fire-extinguisher text-red-500 mr-2"></i>
                        Extintores
                        <span class="text-xs font-normal text-slate-500 ml-2"
                              x-text="`(${extintores.length} registrados)`"></span>
                    </h3>
                    <button type="button" @click="agregarExtintor()"
                            class="px-3 py-1.5 text-sm bg-brand-600 hover:bg-brand-700 text-white rounded-lg">
                        <i class="fa-solid fa-plus mr-1"></i> Añadir
                    </button>
                </div>

                <div class="p-4">
                    <template x-if="extintores.length === 0">
                        <p class="text-slate-400 text-sm text-center py-6">
                            Aún no hay extintores registrados
                        </p>
                    </template>

                    <div class="overflow-x-auto" x-show="extintores.length > 0">
                        <table class="w-full text-sm">
                            <thead class="text-slate-500 text-xs uppercase">
                                <tr>
                                    <th class="text-left pb-2 pr-2">N°</th>
                                    <th class="text-left pb-2 pr-2">Ubicación</th>
                                    <th class="text-left pb-2 pr-2">Tipo</th>
                                    <th class="text-left pb-2 pr-2">Peso</th>
                                    <th class="text-left pb-2 pr-2">Recarga</th>
                                    <th class="text-left pb-2 pr-2">Capacidad</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(e, i) in extintores" :key="i">
                                    <tr class="border-t border-slate-100">
                                        <td class="py-1 pr-2">
                                            <input type="text" :name="`extintores[${i}][numero]`"
                                                   x-model="e.numero"
                                                   class="w-16 px-2 py-1 border border-slate-200 rounded text-sm">
                                        </td>
                                        <td class="py-1 pr-2">
                                            <input type="text" :name="`extintores[${i}][ubicacion]`"
                                                   x-model="e.ubicacion"
                                                   placeholder="Ej: Entrada"
                                                   class="w-full px-2 py-1 border border-slate-200 rounded text-sm">
                                        </td>
                                        <td class="py-1 pr-2">
                                            <select :name="`extintores[${i}][tipo]`" x-model="e.tipo"
                                                    class="w-24 px-2 py-1 border border-slate-200 rounded text-sm">
                                                <option value="">—</option>
                                                <option>PQS</option>
                                                <option>CO2</option>
                                                <option>Agua</option>
                                                <option>Espuma</option>
                                                <option>Clase K</option>
                                            </select>
                                        </td>
                                        <td class="py-1 pr-2">
                                            <input type="text" :name="`extintores[${i}][peso]`"
                                                   x-model="e.peso" placeholder="10 lb"
                                                   class="w-20 px-2 py-1 border border-slate-200 rounded text-sm">
                                        </td>
                                        <td class="py-1 pr-2">
                                            <input type="date" :name="`extintores[${i}][fecha_recarga]`"
                                                   x-model="e.fecha_recarga"
                                                   class="px-2 py-1 border border-slate-200 rounded text-sm">
                                        </td>
                                        <td class="py-1 pr-2">
                                            <input type="text" :name="`extintores[${i}][capacidad]`"
                                                   x-model="e.capacidad" placeholder="10 lb"
                                                   class="w-20 px-2 py-1 border border-slate-200 rounded text-sm">
                                        </td>
                                        <td class="py-1">
                                            <button type="button" @click="extintores.splice(i, 1)"
                                                    class="text-red-500 hover:bg-red-50 p-1.5 rounded"
                                                    title="Eliminar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============ BOTIQUÍN ============ -->
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div class="bg-slate-50 px-4 py-3 flex justify-between items-center">
                    <h3 class="font-semibold text-slate-800">
                        <i class="fa-solid fa-briefcase-medical text-red-500 mr-2"></i>
                        Botiquín de primeros auxilios
                        <span class="text-xs font-normal text-slate-500 ml-2"
                              x-text="`(${botiquin.length} registrados)`"></span>
                    </h3>
                    <button type="button" @click="agregarBotiquin()"
                            class="px-3 py-1.5 text-sm bg-brand-600 hover:bg-brand-700 text-white rounded-lg">
                        <i class="fa-solid fa-plus mr-1"></i> Añadir
                    </button>
                </div>

                <div class="p-4">
                    <template x-if="botiquin.length === 0">
                        <p class="text-slate-400 text-sm text-center py-6">
                            Aún no hay elementos registrados
                        </p>
                    </template>

                    <table class="w-full text-sm" x-show="botiquin.length > 0">
                        <thead class="text-slate-500 text-xs uppercase">
                            <tr>
                                <th class="text-left pb-2 pr-2">Elemento</th>
                                <th class="text-left pb-2 pr-2 w-24">Cantidad</th>
                                <th class="text-left pb-2 pr-2 w-40">Vencimiento</th>
                                <th class="text-left pb-2 pr-2 w-24">Apto</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(b, i) in botiquin" :key="i">
                                <tr class="border-t border-slate-100">
                                    <td class="py-1 pr-2">
                                        <input type="text" :name="`botiquin[${i}][elemento]`"
                                               x-model="b.elemento"
                                               placeholder="Ej: Gasas"
                                               class="w-full px-2 py-1 border border-slate-200 rounded text-sm">
                                    </td>
                                    <td class="py-1 pr-2">
                                        <input type="number" :name="`botiquin[${i}][cantidad]`"
                                               x-model.number="b.cantidad" min="1"
                                               class="w-full px-2 py-1 border border-slate-200 rounded text-sm">
                                    </td>
                                    <td class="py-1 pr-2">
                                        <input type="date" :name="`botiquin[${i}][fecha_vencimiento]`"
                                               x-model="b.fecha_vencimiento"
                                               class="w-full px-2 py-1 border border-slate-200 rounded text-sm">
                                    </td>
                                    <td class="py-1 pr-2">
                                        <select :name="`botiquin[${i}][apto_para_uso]`"
                                                x-model.number="b.apto_para_uso"
                                                class="w-full px-2 py-1 border border-slate-200 rounded text-sm">
                                            <option :value="1">Sí</option>
                                            <option :value="0">No</option>
                                        </select>
                                    </td>
                                    <td class="py-1">
                                        <button type="button" @click="botiquin.splice(i, 1)"
                                                class="text-red-500 hover:bg-red-50 p-1.5 rounded">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============ OTROS ============ -->
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div class="bg-slate-50 px-4 py-3">
                    <h3 class="font-semibold text-slate-800">
                        <i class="fa-solid fa-toolbox text-slate-600 mr-2"></i>
                        Otros recursos
                    </h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Registra la cantidad disponible de cada recurso
                    </p>
                </div>
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($categoriasOtros as $cat):
                        $fila = $otrosMap[$cat] ?? null;
                        $cantidad = (int) ($fila['cantidad'] ?? 0);
                        $desc     = $fila['descripcion'] ?? '';
                        $slug     = preg_replace('/[^a-z0-9]+/i', '_', strtolower($cat));
                    ?>
                        <div class="p-3 border border-slate-200 rounded-lg">
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                <?= e($cat) ?>
                            </label>
                            <input type="number"
                                   name="otros[<?= e($cat) ?>][cantidad]"
                                   min="0" value="<?= $cantidad ?>"
                                   class="w-full px-2 py-1 border border-slate-200 rounded text-sm mb-1">
                            <input type="text"
                                   name="otros[<?= e($cat) ?>][descripcion]"
                                   value="<?= e($desc) ?>"
                                   placeholder="Observaciones (opcional)"
                                   class="w-full px-2 py-1 border border-slate-200 rounded text-xs">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-between pt-6 border-t border-slate-200">
                <a href="<?= base_url("analisis/{$analisisId}/wizard/paso1") ?>"
                   class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Anterior
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                    Guardar y continuar <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function inventario() {
    return {
        extintores: <?= json_encode(array_values($extintores)) ?>,
        botiquin:   <?= json_encode(array_values($botiquin)) ?>,

        agregarExtintor() {
            this.extintores.push({
                numero: '',
                ubicacion: '',
                tipo: '',
                peso: '',
                fecha_recarga: '',
                capacidad: '',
            });
        },
        agregarBotiquin() {
            this.botiquin.push({
                elemento: '',
                cantidad: 1,
                fecha_vencimiento: '',
                apto_para_uso: 1,
            });
        },
    };
}
</script>
