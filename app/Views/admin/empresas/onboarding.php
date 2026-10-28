<?php
$selectedPlanId = (string) old('plan_id', $plans[0]['id'] ?? '');
$initialModule = $plans[0]['modulo'] ?? 'sgsst';
foreach ($plans as $planOption) {
    if ((string) $planOption['id'] === $selectedPlanId) {
        $initialModule = $planOption['modulo'];
        break;
    }
}
?>
<div class="mx-auto max-w-6xl">
    <div class="page-heading">
        <div><span class="eyebrow">Asistente de configuración</span><h1>Una empresa, el módulo a su medida.</h1><p>Clasifica la organización y genera únicamente la base de trabajo del plan seleccionado, lista para revisión técnica.</p></div>
        <a href="<?= e(url('/admin/empresas')) ?>" class="btn-secondary"><?= icon('arrow-left', 'h-4 w-4') ?> Volver</a>
    </div>

    <ol class="onboarding-steps mt-7" data-onboarding-steps>
        <li class="is-active" data-step-indicator="1"><span>1</span><div><strong>Identificación</strong><small>Datos de la organización</small></div></li>
        <li data-step-indicator="2"><span>2</span><div><strong>Clasificación</strong><small>Riesgo y actividad económica</small></div></li>
        <li data-step-indicator="3"><span>3</span><div><strong>Acceso y generación</strong><small>Administrador y confirmación</small></div></li>
    </ol>

    <?php if ($errors !== []): ?>
        <div class="mb-5 flex items-start gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-100" data-onboarding-errors><?= icon('alert', 'mt-0.5 h-5 w-5 shrink-0') ?><div><p class="font-semibold">Revisa la información del formulario</p><p class="mt-1 text-xs leading-5 text-rose-600"><?= e($errors['general'] ?? 'Hay campos que requieren tu atención. Navega por los pasos para corregirlos.') ?></p></div></div>
    <?php endif; ?>

    <form id="onboarding-form" method="post" action="<?= e(url('/admin/empresas')) ?>" novalidate>
        <?= csrf_field() ?>
        <section class="onboarding-panel" data-step="1">
            <div class="ios-card p-5 sm:p-7">
                <div class="section-heading"><span class="section-icon"><?= icon('building', 'h-5 w-5') ?></span><div><h2>Información de la empresa</h2><p>Estos datos identificarán el tenant y aparecerán en el control documental.</p></div></div>
                <div class="mt-7 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label" for="razonsocial">Razón social <span>*</span></label><input id="razonsocial" name="razonsocial" class="input" value="<?= e(old('razonsocial')) ?>" placeholder="Ej. Andina Soluciones S.A.S." required maxlength="180"><?= field_error('razonsocial') ?></div>
                    <div><label class="label" for="nit">NIT <span>*</span></label><input id="nit" name="nit" class="input" value="<?= e(old('nit')) ?>" placeholder="901.000.000-0" required maxlength="30"><?= field_error('nit') ?></div>
                    <div><label class="label" for="representante_legal">Representante legal <span>*</span></label><input id="representante_legal" name="representante_legal" class="input" value="<?= e(old('representante_legal')) ?>" placeholder="Nombre completo" required><?= field_error('representante_legal') ?></div>
                    <div><label class="label" for="correo_contacto">Correo de contacto <span>*</span></label><input id="correo_contacto" name="correo_contacto" type="email" class="input" value="<?= e(old('correo_contacto')) ?>" placeholder="contacto@empresa.co" required><?= field_error('correo_contacto') ?></div>
                    <div><label class="label" for="telefono">Teléfono</label><input id="telefono" name="telefono" class="input" value="<?= e(old('telefono')) ?>" placeholder="601 000 0000"></div>
                    <div><label class="label" for="ciudad">Ciudad <span>*</span></label><input id="ciudad" name="ciudad" class="input" value="<?= e(old('ciudad', 'Bogotá D.C.')) ?>" required><?= field_error('ciudad') ?></div>
                    <div><label class="label" for="direccion">Dirección principal</label><input id="direccion" name="direccion" class="input" value="<?= e(old('direccion')) ?>" placeholder="Dirección del centro de trabajo"></div>
                </div>
            </div>
            <div class="form-footer"><span></span><button type="button" class="btn-primary" data-next-step>Continuar con clasificación <?= icon('arrow-right', 'h-4 w-4') ?></button></div>
        </section>

        <section class="onboarding-panel hidden" data-step="2">
            <?php $method = old('metodo_configuracion', 'Combinado'); ?>
            <div class="ios-card p-5 sm:p-7">
                <div class="section-heading"><span class="section-icon"><?= icon('phva', 'h-5 w-5') ?></span><div><h2>Método de configuración inicial</h2><p>Selecciona uno de los métodos o combina ambos para una parametrización más completa.</p></div></div>
                <div class="mt-6 grid gap-3 md:grid-cols-3">
                    <?php foreach ([
                        ['Riesgo', 'Riesgo y trabajadores', 'Calcula el grupo de estándares según la Resolución 0312.', 'shield-plain'],
                        ['Actividad', 'Actividad económica', 'Asigna IPEVR, perfil y exámenes desde el catálogo.', 'briefcase'],
                        ['Combinado', 'Configuración combinada', 'Integra la clasificación normativa y la base sectorial.', 'phva'],
                    ] as $option): ?>
                        <label class="choice-card">
                            <input type="radio" name="metodo_configuracion" value="<?= e($option[0]) ?>"<?= checked($method === $option[0]) ?> data-config-method>
                            <span class="choice-content"><span class="choice-icon"><?= icon($option[3], 'h-5 w-5') ?></span><strong><?= e($option[1]) ?></strong><small><?= e($option[2]) ?></small><span class="choice-check"><?= icon('check', 'h-3.5 w-3.5') ?></span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error('metodo_configuracion') ?>

                <div class="mt-7 grid gap-6 xl:grid-cols-[1fr_0.85fr]">
                    <div class="space-y-5">
                        <div data-activity-fields>
                            <label class="label" for="actividad_economica_id">Actividad económica CIIU <span>*</span></label>
                            <select id="actividad_economica_id" name="actividad_economica_id" class="input" data-activity-select>
                                <option value="">Selecciona una actividad…</option>
                                <?php foreach ($activities as $activity): ?>
                                    <?php $exams = json_decode($activity['examenes_medicos'], true) ?: []; $hazards = json_decode($activity['matriz_ipevr_base'], true) ?: []; ?>
                                    <option value="<?= (int) $activity['id'] ?>"<?= selected(old('actividad_economica_id'), $activity['id']) ?> data-risk="<?= e($activity['nivel_riesgo_predeterminado']) ?>" data-sector="<?= e($activity['sector']) ?>" data-profile="<?= e($activity['perfil_sociodemografico']) ?>" data-exams="<?= e(implode(' · ', $exams)) ?>" data-hazards="<?= count($hazards) ?>"><?= e($activity['codigo_ciuu']) ?> · <?= e($activity['descripcion']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error('actividad_economica_id') ?>
                            <p class="mt-2 text-[11px] leading-5 text-slate-400">El riesgo del catálogo es una orientación inicial. Verifica la clasificación definitiva asignada por la ARL.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div><label class="label" for="numero_trabajadores">Número de trabajadores <span>*</span></label><input id="numero_trabajadores" name="numero_trabajadores" type="number" min="1" max="1000000" class="input" value="<?= e(old('numero_trabajadores')) ?>" placeholder="Ej. 28" required data-workers><?= field_error('numero_trabajadores') ?></div>
                            <div><label class="label" for="nivel_riesgo">Clase de riesgo ARL <span>*</span></label><select id="nivel_riesgo" name="nivel_riesgo" class="input" required data-risk-select><option value="">Selecciona…</option><?php foreach (['I', 'II', 'III', 'IV', 'V'] as $risk): ?><option value="<?= $risk ?>"<?= selected(old('nivel_riesgo'), $risk) ?>>Riesgo <?= $risk ?></option><?php endforeach; ?></select><?= field_error('nivel_riesgo') ?></div>
                        </div>
                        <div><label class="label" for="sector_economico">Sector económico <span>*</span></label><select id="sector_economico" name="sector_economico" class="input" data-sector-select><?php foreach ($sectors as $sector): ?><option value="<?= e($sector) ?>"<?= selected(old('sector_economico', 'Servicios'), $sector) ?>><?= e($sector) ?></option><?php endforeach; ?></select><?= field_error('sector_economico') ?></div>
                    </div>
                    <div>
                        <div class="classification-result" data-classification-result>
                            <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-blue-500">Resultado estimado</span>
                            <div class="mt-3 flex items-end gap-2"><strong class="text-5xl font-semibold tracking-[-0.06em] text-slate-950" data-group-number>—</strong><span class="pb-1.5 text-sm font-medium text-slate-500">estándares mínimos</span></div>
                            <p class="mt-3 text-xs leading-5 text-slate-500" data-group-reason>Completa el número de trabajadores y la clase de riesgo para calcular el grupo.</p>
                            <div class="mt-4 rounded-xl bg-blue-50/80 p-3 text-[11px] leading-5 text-blue-800" data-group-responsible><?= icon('info', 'mr-1 inline h-3.5 w-3.5') ?> El cálculo definitivo se realizará nuevamente en el servidor.</div>
                        </div>
                        <div class="mt-4 rounded-2xl border border-dashed border-slate-200 p-4" data-activity-preview>
                            <p class="text-xs font-semibold text-slate-700">Parametrización sectorial</p>
                            <p class="mt-2 text-[11px] leading-5 text-slate-400">Selecciona una actividad para conocer los recursos que se asignarán.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <?php foreach ([['7', 'Hasta 10', 'Riesgo I, II o III', 'bg-emerald-50 text-emerald-700'], ['21', 'De 11 a 50', 'Riesgo I, II o III', 'bg-blue-50 text-blue-700'], ['60', 'Más de 50 o IV/V', 'Esquema completo', 'bg-violet-50 text-violet-700']] as $rule): ?>
                    <div class="flex items-center gap-3 rounded-2xl p-4 <?= $rule[3] ?>"><strong class="text-2xl"><?= $rule[0] ?></strong><div><p class="text-xs font-semibold"><?= e($rule[1]) ?></p><p class="mt-0.5 text-[10px] opacity-70"><?= e($rule[2]) ?></p></div></div>
                <?php endforeach; ?>
            </div>
            <div class="form-footer"><button type="button" class="btn-secondary" data-previous-step><?= icon('arrow-left', 'h-4 w-4') ?> Anterior</button><button type="button" class="btn-primary" data-next-step>Configurar acceso <?= icon('arrow-right', 'h-4 w-4') ?></button></div>
        </section>

        <section class="onboarding-panel hidden" data-step="3">
            <div class="grid gap-5 lg:grid-cols-[1fr_0.72fr]">
                <div class="space-y-5">
                    <div class="ios-card p-5 sm:p-7">
                        <div class="section-heading"><span class="section-icon"><?= icon('user', 'h-5 w-5') ?></span><div><h2>Administrador de la empresa</h2><p>Esta persona podrá gestionar el SG-SST, el equipo y los accesos.</p></div></div>
                        <div class="mt-6 space-y-5">
                            <div><label class="label" for="admin_nombre">Nombre completo <span>*</span></label><input id="admin_nombre" name="admin_nombre" class="input" value="<?= e(old('admin_nombre')) ?>" required><?= field_error('admin_nombre') ?></div>
                            <div><label class="label" for="admin_email">Correo de acceso <span>*</span></label><input id="admin_email" name="admin_email" type="email" class="input" value="<?= e(old('admin_email')) ?>" autocomplete="off" required><?= field_error('admin_email') ?></div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><label class="label" for="new-password">Contraseña inicial <span>*</span></label><input id="new-password" name="password" type="password" class="input" autocomplete="new-password" required><p class="mt-1.5 text-[10px] text-slate-400">10+ caracteres, mayúscula, minúscula y número.</p><?= field_error('password') ?></div>
                                <div><label class="label" for="password_confirmation">Confirmar contraseña <span>*</span></label><input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required><?= field_error('password_confirmation') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="ios-card p-5 sm:p-7">
                        <h2 class="text-base font-semibold">Plan del SaaS</h2><p class="mt-1 text-xs text-slate-400">Define los límites operativos iniciales.</p>
                        <div class="mt-5 space-y-3">
                            <?php foreach ($plans as $index => $plan): ?>
                                <label class="plan-choice"><input type="radio" name="plan_id" value="<?= (int) $plan['id'] ?>"<?= checked($selectedPlanId === (string) $plan['id']) ?> data-plan-module="<?= e($plan['modulo']) ?>"><span class="flex-1"><strong><?= e($plan['nombre']) ?></strong><small><?= e(App\Services\ModuleAccess::label($plan['modulo'])) ?> · <?= (int) $plan['max_usuarios'] ?> usuarios · <?= (int) $plan['max_empleados'] ?> empleados</small></span><span class="text-xs font-semibold text-slate-600"><?= money($plan['precio_mensual']) ?><small>/mes</small></span></label>
                            <?php endforeach; ?>
                        </div>
                        <?= field_error('plan_id') ?>
                        <div class="mt-4"><label class="label" for="plan_condicion">Condición comercial *</label><select id="plan_condicion" name="plan_condicion" class="input"><option value="Pagado"<?= selected(old('plan_condicion', 'Pagado'), 'Pagado') ?>>Pagado</option><option value="Sugerido"<?= selected(old('plan_condicion', 'Pagado'), 'Sugerido') ?>>Sugerido</option></select><p class="mt-1.5 text-[10px] leading-4 text-slate-400">Es una marca administrativa, no un comprobante de pago. En ambos casos se aplica el mismo módulo.</p><?= field_error('plan_condicion') ?></div>
                    </div>
                </div>
                <div>
                    <div class="ios-card sticky top-24 overflow-hidden">
                        <div class="bg-slate-950 p-6 text-white"><span class="text-[10px] font-bold uppercase tracking-widest text-blue-300">Lista para generar</span><h2 class="mt-3 text-xl font-semibold">Configuración inicial</h2><p class="mt-2 text-xs leading-5 text-slate-400">Se ejecutará una transacción única. Si algo falla, no quedarán registros incompletos.</p></div>
                        <div class="p-6">
                            <div class="flex items-center gap-4 rounded-2xl bg-blue-50 p-4"><strong class="text-4xl tracking-tight text-blue-600" data-summary-group>—</strong><div><p class="text-xs font-semibold text-blue-900">estándares mínimos</p><p class="mt-1 text-[10px] text-blue-600" data-summary-company>Empresa por identificar</p></div></div>
                            <?php $generationFeatures = [
                                'sgsst' => [['documents', 'Documentos y políticas obligatorias'], ['calendar', 'Plan Anual de Trabajo'], ['cap', 'Cronograma de capacitaciones'], ['grid', 'Matriz IPEVR base y perfiles'], ['signature', 'Inducción para firma electrónica']],
                                'pesv' => [['shield-plain', 'Índice PESV de 24 pasos en borrador'], ['grid', 'Biblioteca de 97 recursos únicos'], ['edit', 'Formularios y editor visual'], ['documents', 'Versiones y control documental PESV'], ['check-circle', 'Revisión y aprobación humana']],
                                'emergencias' => [['shield', 'Plan de emergencias en borrador'], ['activity', 'Programa de vigilancia en borrador'], ['calendar', 'Gestión de simulacros'], ['documents', 'Documentos y evidencias del módulo'], ['check-circle', 'Revisión y aprobación humana']],
                            ]; ?>
                            <?php foreach ($generationFeatures as $module => $features): ?><ul class="mt-5 space-y-3 text-xs text-slate-600<?= $initialModule === $module ? '' : ' hidden' ?>" data-generation-list="<?= e($module) ?>"><?php foreach ($features as $feature): ?><li class="flex items-center gap-2.5"><?= icon($feature[0], 'h-4 w-4 text-blue-500') ?><?= e($feature[1]) ?><?= icon('check-circle', 'ml-auto h-4 w-4 text-emerald-500') ?></li><?php endforeach; ?></ul><?php endforeach; ?>
                            <p class="mt-6 border-t border-slate-100 pt-4 text-[10px] leading-5 text-slate-400">Los documentos se crearán como <strong>Borrador</strong>. Deben revisarse, adaptarse y aprobarse; su generación no certifica cumplimiento legal.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-footer"><button type="button" class="btn-secondary" data-previous-step><?= icon('arrow-left', 'h-4 w-4') ?> Anterior</button><button type="submit" class="btn-primary" data-generate-company><?= icon('phva', 'h-4 w-4') ?> <span data-generate-label>Crear empresa y generar <?= e(App\Services\ModuleAccess::shortLabel($initialModule)) ?></span></button></div>
        </section>
    </form>
</div>
