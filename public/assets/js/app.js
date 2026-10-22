(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  // Navegación móvil.
  const sidebar = $('#app-sidebar');
  const sidebarToggle = $('#sidebar-toggle');
  const backdrop = $('#sidebar-backdrop');
  const setSidebar = (open) => {
    if (!sidebar) return;
    sidebar.classList.toggle('sidebar-open', open);
    backdrop?.classList.toggle('hidden', !open);
    sidebarToggle?.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
  };
  sidebarToggle?.addEventListener('click', () => setSidebar(!sidebar?.classList.contains('sidebar-open')));
  $$('[data-sidebar-close]').forEach((button) => button.addEventListener('click', () => setSidebar(false)));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setSidebar(false);
      $$('[data-menu]').forEach((menu) => menu.classList.add('hidden'));
    }
  });

  // Menús flotantes.
  $$('[data-menu-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();
      const menu = document.getElementById(button.dataset.menuToggle);
      const willOpen = menu?.classList.contains('hidden');
      $$('[data-menu]').forEach((item) => item.classList.add('hidden'));
      if (menu && willOpen) menu.classList.remove('hidden');
      button.setAttribute('aria-expanded', String(Boolean(willOpen)));
    });
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-menu]') && !event.target.closest('[data-menu-toggle]')) {
      $$('[data-menu]').forEach((menu) => menu.classList.add('hidden'));
    }
  });

  // Mensajes, impresión y confirmaciones.
  $$('[data-dismiss-flash]').forEach((button) => button.addEventListener('click', () => button.closest('.flash-message')?.remove()));
  $$('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
  $$('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
  });
  $$('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = event.submitter?.dataset.confirmSubmit;
      if (message && !window.confirm(message)) event.preventDefault();
    });
  });

  // Contraseñas y accesos demo.
  $$('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      button.setAttribute('aria-label', input.type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');
    });
  });
  $$('[data-demo-email]').forEach((button) => {
    button.addEventListener('click', () => {
      const email = $('#email');
      const password = $('#password');
      if (email) email.value = button.dataset.demoEmail;
      if (password) password.value = button.dataset.demoPassword;
      password?.focus();
    });
  });

  // Asistente de configuración de empresa.
  const onboarding = $('#onboarding-form');
  if (onboarding) {
    const panels = $$('[data-step]', onboarding);
    const indicators = $$('[data-step-indicator]');
    let currentStep = 1;
    const methodInputs = $$('[data-config-method]', onboarding);
    const activitySelect = $('[data-activity-select]', onboarding);
    const activityFields = $('[data-activity-fields]', onboarding);
    const workers = $('[data-workers]', onboarding);
    const riskSelect = $('[data-risk-select]', onboarding);
    const sectorSelect = $('[data-sector-select]', onboarding);
    const companyInput = $('#razonsocial');

    const selectedMethod = () => methodInputs.find((input) => input.checked)?.value || 'Combinado';
    const showStep = (step) => {
      currentStep = Math.max(1, Math.min(3, step));
      panels.forEach((panel) => panel.classList.toggle('hidden', Number(panel.dataset.step) !== currentStep));
      indicators.forEach((indicator) => {
        const index = Number(indicator.dataset.stepIndicator);
        indicator.classList.toggle('is-active', index === currentStep);
        indicator.classList.toggle('is-complete', index < currentStep);
        const bubble = indicator.querySelector(':scope > span');
        if (bubble) bubble.innerHTML = index < currentStep ? '✓' : String(index);
      });
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const validateCurrentStep = () => {
      const panel = panels.find((item) => Number(item.dataset.step) === currentStep);
      if (!panel) return true;
      const fields = $$('input, select, textarea', panel).filter((field) => !field.disabled && field.type !== 'hidden');
      for (const field of fields) {
        if (!field.checkValidity()) {
          field.reportValidity();
          field.focus();
          return false;
        }
      }
      return true;
    };

    const updateMethod = () => {
      const needsActivity = selectedMethod() !== 'Riesgo';
      if (activityFields) activityFields.classList.toggle('opacity-50', !needsActivity);
      if (activitySelect) {
        activitySelect.required = needsActivity;
        activitySelect.disabled = !needsActivity;
      }
      updateActivityPreview();
    };

    const updateClassification = () => {
      const count = Number(workers?.value || 0);
      const risk = riskSelect?.value || '';
      let group = null;
      if (count >= 1 && risk) group = count > 50 || ['IV', 'V'].includes(risk) ? 60 : (count <= 10 ? 7 : 21);
      const number = $('[data-group-number]', onboarding);
      const reason = $('[data-group-reason]', onboarding);
      const responsible = $('[data-group-responsible]', onboarding);
      const summary = $('[data-summary-group]', onboarding);
      if (number) number.textContent = group || '—';
      if (summary) summary.textContent = group || '—';
      if (reason) {
        reason.textContent = group === 7
          ? 'Hasta 10 trabajadores y clase de riesgo I, II o III.'
          : group === 21
            ? 'Entre 11 y 50 trabajadores y clase de riesgo I, II o III.'
            : group === 60
              ? (['IV', 'V'].includes(risk) ? 'La clase IV o V exige el esquema completo, sin importar la dotación.' : 'Más de 50 trabajadores: aplica el esquema completo.')
              : 'Completa el número de trabajadores y la clase de riesgo para calcular el grupo.';
      }
      if (responsible && group) {
        responsible.textContent = group === 60
          ? 'Verifica profesional o posgrado en SST, licencia vigente y curso de 50 horas para diseño e implementación.'
          : 'Verifica el perfil, la experiencia, licencia y formación exigidos para este grupo.';
      }
    };

    const updateActivityPreview = () => {
      const preview = $('[data-activity-preview]', onboarding);
      if (!preview || !activitySelect) return;
      const option = activitySelect.selectedOptions[0];
      if (!option || !option.value || selectedMethod() === 'Riesgo') {
        preview.innerHTML = '<p class="text-xs font-semibold text-slate-700">Parametrización sectorial</p><p class="mt-2 text-[11px] leading-5 text-slate-400">Selecciona una actividad para conocer los recursos que se asignarán.</p>';
        return;
      }
      preview.innerHTML = '';
      const title = document.createElement('p');
      title.className = 'text-xs font-semibold text-slate-700';
      title.textContent = option.dataset.sector + ' · Riesgo guía ' + option.dataset.risk;
      const profile = document.createElement('p');
      profile.className = 'mt-2 text-[11px] leading-5 text-slate-500';
      profile.textContent = option.dataset.profile;
      const resources = document.createElement('p');
      resources.className = 'mt-3 rounded-xl bg-blue-50 p-3 text-[11px] leading-5 text-blue-700';
      resources.textContent = option.dataset.hazards + ' peligros específicos + 3 transversales · ' + option.dataset.exams;
      preview.append(title, profile, resources);
    };

    methodInputs.forEach((input) => input.addEventListener('change', updateMethod));
    activitySelect?.addEventListener('change', () => {
      const option = activitySelect.selectedOptions[0];
      if (option?.value) {
        if (riskSelect) riskSelect.value = option.dataset.risk;
        if (sectorSelect) sectorSelect.value = option.dataset.sector;
      }
      updateActivityPreview();
      updateClassification();
    });
    workers?.addEventListener('input', updateClassification);
    riskSelect?.addEventListener('change', updateClassification);
    companyInput?.addEventListener('input', () => {
      const summary = $('[data-summary-company]', onboarding);
      if (summary) summary.textContent = companyInput.value.trim() || 'Empresa por identificar';
    });
    $$('[data-next-step]', onboarding).forEach((button) => button.addEventListener('click', () => {
      if (validateCurrentStep()) showStep(currentStep + 1);
    }));
    $$('[data-previous-step]', onboarding).forEach((button) => button.addEventListener('click', () => showStep(currentStep - 1)));
    onboarding.addEventListener('submit', (event) => {
      if (currentStep !== 3 || !validateCurrentStep()) {
        event.preventDefault();
        return;
      }
      const button = $('[data-generate-company]', onboarding);
      if (button) {
        button.disabled = true;
        button.textContent = 'Generando configuración segura…';
      }
    });
    updateMethod();
    updateClassification();
    if (companyInput?.value) companyInput.dispatchEvent(new Event('input'));
  }

  // Filas repetibles para amenazas, contactos, brigada y recursos.
  $$('[data-add-row]').forEach((button) => {
    button.addEventListener('click', () => {
      const template = document.getElementById('repeat-template-' + button.dataset.addRow);
      const list = button.parentElement?.querySelector('[data-repeat-list]');
      if (!template || !list) return;
      list.appendChild(template.content.cloneNode(true));
      list.lastElementChild?.querySelector('input')?.focus();
    });
  });
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-row]');
    if (!button) return;
    const row = button.closest('[data-repeat-row]');
    const list = button.closest('[data-repeat-list]');
    if (row && list) row.remove();
  });

  // Valoración orientativa ND × NE × NC.
  const riskForm = $('[data-risk-form]');
  if (riskForm) {
    const fields = ['nd', 'ne', 'nc'].map((key) => $(`[data-risk-${key}]`, riskForm));
    const output = $('[data-risk-result]', riskForm);
    const fallback = $('[data-risk-fallback]', riskForm);
    const calculate = () => {
      const values = fields.map((field) => field?.value || '');
      if (values.some((value) => value === '')) {
        if (output) output.textContent = values.every((value) => value === '') ? `Valoración manual: ${fallback?.value || 'Medio'}` : 'Completa ND, NE y NC para calcular';
        return;
      }
      const [nd, ne, nc] = values.map(Number);
      const probability = nd * ne;
      const score = probability * nc;
      const level = score >= 600 ? 'Crítico' : score >= 150 ? 'Alto' : score >= 40 ? 'Medio' : 'Bajo';
      if (output) output.textContent = `NP ${probability} · NR ${score} · ${level}`;
    };
    fields.forEach((field) => field?.addEventListener('change', calculate));
    fallback?.addEventListener('change', calculate);
    calculate();
  }

  // Fórmulas y estimación de indicadores.
  const indicatorForm = $('[data-indicator-form]');
  if (indicatorForm) {
    let definitions = {};
    try { definitions = JSON.parse($('#indicator-definitions')?.textContent || '{}'); } catch (_) { definitions = {}; }
    const type = $('[data-indicator-type]', indicatorForm);
    const numerator = $('[data-indicator-numerator]', indicatorForm);
    const denominator = $('[data-indicator-denominator]', indicatorForm);
    const customFactor = $('[data-indicator-factor]', indicatorForm);
    const formula = $('[data-indicator-formula]', indicatorForm);
    const result = $('[data-indicator-result]', indicatorForm);
    const calculate = () => {
      const definition = definitions[type?.value] || { factor: 1, formula: '' };
      const factor = type?.value === 'Personalizado' ? Number(customFactor?.value || 1) : Number(definition.factor || 1);
      const num = Number(numerator?.value || 0);
      const den = Number(denominator?.value || 0);
      const value = den > 0 ? (num * factor) / den : 0;
      if (formula) formula.textContent = definition.formula || '';
      if (result) result.textContent = `${value.toLocaleString('es-CO', { maximumFractionDigits: 2 })} ${definition.unidad || ''}`.trim();
    };
    const changeType = () => {
      const personalized = type?.value === 'Personalizado';
      $$('[data-personal-factor], [data-personal-unit]', indicatorForm).forEach((field) => field.classList.toggle('hidden', !personalized));
      const name = $('input[name="nombre"]', indicatorForm);
      if (name && (!name.value.trim() || Object.keys(definitions).some((key) => name.dataset.autoName === key))) {
        name.value = personalized ? 'Indicador personalizado' : `${type.value} del SG-SST`;
        name.dataset.autoName = type.value === 'Frecuencia' ? 'Frecuencia' : type.value;
      }
      calculate();
    };
    type?.addEventListener('change', changeType);
    [numerator, denominator, customFactor].forEach((field) => field?.addEventListener('input', calculate));
    changeType();
  }
})();
