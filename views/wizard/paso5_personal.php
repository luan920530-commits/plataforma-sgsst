<?php
require BASE_PATH . '/views/partials/wizard_steps.php';

$personal            = $personal ?? [];
$minimoBrigadistas   = $minimoBrigadistas ?? 0;
$brigadistasActuales = $brigadistasActuales ?? 0;
$empresaId           = (int) $analisis['empresa_id_real'];
?>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="bg-brand-600 text-white px-6 py-4 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-semibold flex items-center gap-3">
                <i class="fa-solid fa-users"></i>
                Paso 5 — Personal / MEDEVAC
            </h2>
            <p class="text-brand-100 text-sm mt-1">
                Registra el personal y su información de emergencia
            </p>
        </div>
        <button type="button" @click="$dispatch('abrir-modal-personal')"
                onclick="document.dispatchEvent(new CustomEvent('abrir-modal-personal'))"
                class="px-4 py-2 bg-white text-brand-700 hover:bg-brand-50 rounded-lg font-medium shadow-sm transition">
            <i class="fa-solid fa-user-plus mr-2"></i> Agregar personal
        </button>
    </div>

    <div class="p-6 space-y-6">

        <!-- Alerta de brigadistas -->
        <div class="p-4 rounded-lg border flex items-start gap-3
            <?= $brigadistasActuales >= $minimoBrigadistas
                ? 'bg-green-50 border-green-200 text-green-800'
                : 'bg-yellow-50 border-yellow-200 text-yellow-800' ?>">
            <i class="fa-solid
                <?= $brigadistasActuales >= $minimoBrigadistas
                    ? 'fa-circle-check'
                    : 'fa-triangle-exclamation' ?> mt-0.5"></i>
            <div class="flex-1 text-sm">
                <strong>Resolución 256/2014:</strong>
                Brigada actual: <strong><?= $brigadistasActuales ?></strong> de
                <strong><?= $minimoBrigadistas ?></strong> requeridos (20% de
                <?= (int) $analisis['num_trabajadores'] ?> trabajadores).
                <?php if ($brigadistasActuales < $minimoBrigadistas): ?>
                    Faltan <strong><?= $minimoBrigadistas - $brigadistasActuales ?></strong>.
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabla -->
        <?php if (empty($personal)): ?>
            <div class="p-10 text-center border border-dashed border-slate-300 rounded-xl">
                <i class="fa-solid fa-user-plus text-4xl text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium">Aún no hay personal registrado</p>
                <p class="text-slate-400 text-sm mt-1 mb-4">Empieza agregando los trabajadores</p>
                <button type="button"
                        onclick="document.dispatchEvent(new CustomEvent('abrir-modal-personal'))"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                    <i class="fa-solid fa-plus mr-2"></i> Agregar el primero
                </button>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-y border-slate-200">
                        <tr>
                            <th class="text-left px-3 py-3 font-semibold text-slate-700">Nombre</th>
                            <th class="text-left px-3 py-3 font-semibold text-slate-700">Cédula</th>
                            <th class="text-left px-3 py-3 font-semibold text-slate-700">Cargo</th>
                            <th class="text-left px-3 py-3 font-semibold text-slate-700">Celular</th>
                            <th class="text-left px-3 py-3 font-semibold text-slate-700">EPS</th>
                            <th class="text-left px-3 py-3 font-semibold text-slate-700">Brigada</th>
                            <th class="text-left px-3 py-3 font-semibold text-slate-700">Firma</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($personal as $p): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-medium text-slate-800">
                                    <?= e($p['nombre_completo']) ?>
                                </td>
                                <td class="px-3 py-3 text-slate-600"><?= e($p['cedula']) ?></td>
                                <td class="px-3 py-3 text-slate-600"><?= e($p['cargo']) ?></td>
                                <td class="px-3 py-3 text-slate-600"><?= e($p['celular']) ?></td>
                                <td class="px-3 py-3 text-slate-600"><?= e($p['eps']) ?></td>
                                <td class="px-3 py-3">
                                    <?php if ($p['entrenado_brigada']): ?>
                                        <span class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded">
                                            <?= e($p['entrenado_brigada']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3">
                                    <?php if (!empty($p['firma_url'])): ?>
                                        <img src="<?= base_url($p['firma_url']) ?>"
                                             alt="Firma"
                                             class="h-8 bg-white border border-slate-200 rounded">
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs">Sin firma</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick='abrirEditarPersonal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'
                                            class="text-brand-600 hover:bg-brand-50 p-1.5 rounded"
                                            title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST"
                                          action="<?= base_url("personal/{$p['id']}/eliminar") ?>"
                                          class="inline"
                                          onsubmit="return confirm('¿Eliminar a <?= e($p['nombre_completo']) ?>?')">
                                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="analisis_id" value="<?= $analisisId ?>">
                                        <button type="submit"
                                                class="text-red-500 hover:bg-red-50 p-1.5 rounded"
                                                title="Eliminar">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Botones navegación -->
        <div class="flex justify-between pt-6 border-t border-slate-200">
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso4") ?>"
               class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left mr-2"></i> Anterior
            </a>
            <a href="<?= base_url("analisis/{$analisisId}/wizard/paso6") ?>"
               class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                Siguiente <i class="fa-solid fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</div>

<!-- ============ MODAL PERSONAL ============ -->
<div id="modalPersonal"
     class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4"
     onclick="if(event.target===this) cerrarModalPersonal()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto"
         onclick="event.stopPropagation()">

        <div class="sticky top-0 bg-brand-600 text-white px-6 py-4 flex justify-between items-center rounded-t-2xl">
            <h3 class="text-lg font-semibold" id="modalTitulo">
                <i class="fa-solid fa-user-plus mr-2"></i> Agregar personal
            </h3>
            <button type="button" onclick="cerrarModalPersonal()"
                    class="text-white/80 hover:text-white text-xl">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <form method="POST"
              action="<?= base_url('personal') ?>"
              enctype="multipart/form-data"
              class="p-6 space-y-4"
              id="formPersonal">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="analisis_id" value="<?= $analisisId ?>">
            <input type="hidden" name="empresa_id" value="<?= $empresaId ?>">
            <input type="hidden" name="personal_id" id="personalId" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Nombre completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nombre_completo" id="f_nombre" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cédula</label>
                    <input type="text" name="cedula" id="f_cedula"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Correo</label>
                    <input type="email" name="email" id="f_email"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cargo</label>
                    <input type="text" name="cargo" id="f_cargo"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">RH</label>
                    <input type="text" name="rh" id="f_rh" placeholder="O+"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Celular</label>
                    <input type="tel" name="celular" id="f_celular"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Dependencia</label>
                    <input type="text" name="dependencia" id="f_dependencia"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Entrenamiento en brigada
                    </label>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="entrenado_brigada[]" value="Primeros Auxilios"
                                   class="brigada-check">
                            <span class="text-sm">Primeros Auxilios</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="entrenado_brigada[]" value="Evacuación"
                                   class="brigada-check">
                            <span class="text-sm">Evacuación</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="entrenado_brigada[]" value="Incendios"
                                   class="brigada-check">
                            <span class="text-sm">Incendios</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="entrenado_brigada[]" value="Primeros Auxilios, Evacuación"
                                   class="brigada-check">
                            <span class="text-sm">Varios</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">EPS</label>
                    <input type="text" name="eps" id="f_eps"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">AFP</label>
                    <input type="text" name="afp" id="f_afp"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Contacto emergencia (nombre)
                    </label>
                    <input type="text" name="contacto_emergencia_nombre" id="f_ce_nombre"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Contacto emergencia (teléfono)
                    </label>
                    <input type="tel" name="contacto_emergencia_tel" id="f_ce_tel"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Alergias / medicamentos permanentes
                    </label>
                    <textarea name="alergias" id="f_alergias" rows="2"
                              class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Firma (imagen PNG/JPG, máx 2MB)
                    </label>
                    <input type="file" name="firma" accept="image/png,image/jpeg"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <p class="text-xs text-slate-500 mt-1">
                        Sube una foto o escaneo de la firma del trabajador con fondo blanco.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                <button type="button" onclick="cerrarModalPersonal()"
                        class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">
                    <i class="fa-solid fa-save mr-2"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('modalPersonal');
const form  = document.getElementById('formPersonal');
const titulo = document.getElementById('modalTitulo');
const baseUrlPersonal = '<?= base_url('personal') ?>';

document.addEventListener('abrir-modal-personal', () => abrirNuevoPersonal());

function abrirNuevoPersonal() {
    titulo.innerHTML = '<i class="fa-solid fa-user-plus mr-2"></i> Agregar personal';
    form.action = baseUrlPersonal;
    form.reset();
    document.getElementById('personalId').value = '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => document.getElementById('f_nombre').focus(), 100);
}

function abrirEditarPersonal(p) {
    titulo.innerHTML = '<i class="fa-solid fa-user-pen mr-2"></i> Editar personal';
    form.action = baseUrlPersonal + '/' + p.id;
    form.reset();

    document.getElementById('personalId').value = p.id;
    document.getElementById('f_nombre').value = p.nombre_completo || '';
    document.getElementById('f_cedula').value = p.cedula || '';
    document.getElementById('f_email').value = p.email || '';
    document.getElementById('f_cargo').value = p.cargo || '';
    document.getElementById('f_rh').value = p.rh || '';
    document.getElementById('f_celular').value = p.celular || '';
    document.getElementById('f_dependencia').value = p.dependencia || '';
    document.getElementById('f_eps').value = p.eps || '';
    document.getElementById('f_afp').value = p.afp || '';
    document.getElementById('f_ce_nombre').value = p.contacto_emergencia_nombre || '';
    document.getElementById('f_ce_tel').value = p.contacto_emergencia_tel || '';
    document.getElementById('f_alergias').value = p.alergias || '';

    // Marcar checkboxes de brigada
    const seleccion = (p.entrenado_brigada || '').split(',').map(s => s.trim());
    document.querySelectorAll('.brigada-check').forEach(cb => {
        cb.checked = seleccion.includes(cb.value);
    });

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function cerrarModalPersonal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Cerrar con ESC
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        cerrarModalPersonal();
    }
});
</script>
