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

    const planInputs = $$('[data-plan-module]', onboarding);
    const updatePlanSummary = () => {
      const selected = planInputs.find((input) => input.checked);
      const module = selected?.dataset.planModule || 'sgsst';
      $$('[data-generation-list]', onboarding).forEach((list) => list.classList.toggle('hidden', list.dataset.generationList !== module));
      const label = $('[data-generate-label]', onboarding);
      if (label) label.textContent = 'Crear empresa y generar ' + ({ pesv: 'PESVial', emergencias: 'Emergencias y Vigilancia', sgsst: 'SG-SST' }[module] || 'SG-SST');
    };
    planInputs.forEach((input) => input.addEventListener('change', updatePlanSummary));
    updatePlanSummary();

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
    const numeratorHelp = $('[data-indicator-numerator-help]', indicatorForm);
    const denominatorHelp = $('[data-indicator-denominator-help]', indicatorForm);
    const calculate = () => {
      const definition = definitions[type?.value] || { factor: 1, formula: '' };
      const factor = type?.value === 'Personalizado' ? Number(customFactor?.value || 1) : Number(definition.factor || 1);
      const num = Number(numerator?.value || 0);
      const den = Number(denominator?.value || 0);
      const presentism = type?.value === 'Presentismo';
      if (numeratorHelp) numeratorHelp.textContent = presentism ? 'Días de ausencia en el mismo periodo.' : 'Eventos, días o casos, según corresponda.';
      if (denominatorHelp) denominatorHelp.textContent = presentism ? 'Días de trabajo programados en el mismo periodo.' : 'HHT, trabajadores, eventos o días programados.';
      const validPresentism = !presentism || (num >= 0 && num <= den);
      const effectiveNumerator = presentism ? den - num : num;
      const value = den > 0 && validPresentism ? (effectiveNumerator * factor) / den : 0;
      if (formula) formula.textContent = definition.formula || '';
      if (result) result.textContent = validPresentism ? `${value.toLocaleString('es-CO', { maximumFractionDigits: 2 })} ${definition.unidad || ''}`.trim() : 'Las ausencias no pueden superar los días programados.';
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

  // Editor visual documental: el HTML queda como detalle técnico interno, no como interfaz de edición.
  $$('[data-rich-editor]').forEach((editor) => {
    const surface = $('[data-editor-surface]', editor);
    const input = $('[data-editor-input]', editor);
    const toolbar = $('[data-editor-toolbar]', editor);
    const form = editor.closest('form');
    if (!surface || !input || !toolbar || !form) return;
    const submit = $('[data-editor-submit]', form);
    if (submit) submit.disabled = false;

    surface.innerHTML = input.value;
    input.classList.add('hidden');
    surface.classList.remove('hidden');
    toolbar.classList.remove('hidden');
    const status = $('[data-editor-status]', editor);
    const error = $('[data-editor-error]', editor);
    const sync = () => { input.value = surface.innerHTML.trim(); };
    const changed = () => {
      sync();
      if (status) status.textContent = 'Cambios sin guardar';
      error?.classList.add('hidden');
    };
    let savedRange = null;
    document.addEventListener('selectionchange', () => {
      const selection = window.getSelection();
      if (selection.rangeCount && surface.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
    });
    const command = (name, value = null) => {
      surface.focus();
      if (savedRange) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange);
      }
      document.execCommand(name, false, value);
      changed();
    };

    $$('button', toolbar).forEach((button) => button.addEventListener('mousedown', (event) => event.preventDefault()));
    $$('[data-editor-command]', toolbar).forEach((button) => {
      button.addEventListener('click', () => command(button.dataset.editorCommand));
    });
    $('[data-editor-action="table"]', toolbar)?.addEventListener('click', () => {
      command('insertHTML', '<table><thead><tr><th>Elemento</th><th>Verificación</th><th>Observaciones</th></tr></thead><tbody><tr><td><br></td><td><br></td><td><br></td></tr><tr><td><br></td><td><br></td><td><br></td></tr></tbody></table><p><br></p>');
    });
    $('[data-editor-block]', toolbar)?.addEventListener('change', (event) => command('formatBlock', event.target.value));
    surface.addEventListener('input', changed);
    surface.addEventListener('paste', (event) => {
      event.preventDefault();
      const text = event.clipboardData?.getData('text/plain') || '';
      document.execCommand('insertText', false, text);
    });
    surface.addEventListener('drop', (event) => {
      event.preventDefault();
      const text = event.dataTransfer?.getData('text/plain') || '';
      document.execCommand('insertText', false, text);
    });
    form.addEventListener('submit', (event) => {
      sync();
      if (!surface.textContent.trim()) {
        event.preventDefault();
        if (error) {
          error.textContent = 'El documento debe conservar contenido legible.';
          error.classList.remove('hidden');
        }
        surface.focus();
      }
    });
  });

  // Copia de enlaces y QR local, sin enviar la URL a servicios de terceros.
  $$('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
      const target = document.getElementById(button.dataset.copyTarget);
      if (!target) return;
      const value = target.value || target.textContent || '';
      try {
        await navigator.clipboard.writeText(value);
      } catch (_) {
        target.focus?.();
        target.select?.();
        document.execCommand('copy');
      }
      const previous = button.textContent;
      button.textContent = 'Copiado';
      window.setTimeout(() => { button.textContent = previous; }, 1800);
    });
  });
  $$('[data-qr-card]').forEach((card) => {
    const container = $('[data-qr-code]', card);
    const button = $('[data-download-qr]', card);
    if (!container || !button) return;
    const fail = (message) => {
      container.textContent = message;
      container.setAttribute('aria-busy', 'false');
      button.disabled = true;
    };
    if (typeof window.qrcode !== 'function') {
      fail('No fue posible cargar el generador QR local. Puedes copiar la URL o renovar el acceso.');
      return;
    }
    try {
      const code = window.qrcode(0, 'M');
      code.addData(container.dataset.qrCode || '');
      code.make();
      let source;
      let extension = 'png';
      try {
        // PNG nítido para navegadores actuales; no depende de un servicio externo.
        const canvas = document.createElement('canvas');
        const scale = 8;
        const margin = 4;
        const modules = code.getModuleCount();
        canvas.width = (modules + margin * 2) * scale;
        canvas.height = canvas.width;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas no disponible');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#000000';
        for (let row = 0; row < modules; row += 1) {
          for (let column = 0; column < modules; column += 1) {
            if (code.isDark(row, column)) context.fillRect((column + margin) * scale, (row + margin) * scale, scale, scale);
          }
        }
        source = canvas.toDataURL('image/png');
        if (!source.startsWith('data:image/png;base64,')) throw new Error('PNG no disponible');
      } catch (_) {
        // Compatibilidad con navegadores o visores sin canvas.
        source = code.createDataURL(8, 4);
        extension = 'gif';
      }
      const image = new Image();
      image.src = source;
      image.alt = container.dataset.qrAlt || 'Código QR del enlace de autoreportes';
      image.className = 'h-48 w-48 max-w-full';
      image.style.imageRendering = 'pixelated';
      container.replaceChildren(image);
      container.setAttribute('aria-busy', 'false');
      button.qrSource = source;
      button.dataset.qrExtension = extension;
      button.disabled = false;
    } catch (_) {
      fail('No fue posible generar el QR. Puedes copiar la URL o renovar el acceso.');
    }
    button.addEventListener('click', () => {
      if (!button.qrSource || button.disabled) return;
      const link = document.createElement('a');
      link.href = button.qrSource;
      link.download = (button.dataset.downloadQr || 'autoreporte-qr') + '.' + (button.dataset.qrExtension || 'png');
      document.body.appendChild(link);
      link.click();
      link.remove();
    });
  });
  // Formularios bibliográficos: texto plano, coordenadas conocidas y cálculo posterior en PHP.
  $$('[data-library-form]').forEach((form) => {
    const payload = $('[data-library-payload]', form);
    const cells = $$('[data-cell]', form);
    const submitButtons = $$('[data-library-submit]', form);
    const dirty = $('[data-library-dirty]', form);
    submitButtons.forEach((button) => { button.disabled = false; });
    const changed = () => { if (dirty) dirty.textContent = 'Cambios sin guardar'; };
    cells.forEach((cell) => {
      cell.addEventListener('input', changed);
      cell.addEventListener('change', changed);
      if (cell.getAttribute('contenteditable') !== null) {
        cell.addEventListener('paste', (event) => {
          event.preventDefault();
          document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') || '');
        });
      }
    });
    $('[data-library-edit-all]', form)?.addEventListener('click', (event) => {
      cells.forEach((cell) => {
        if (cell.getAttribute('contenteditable') !== null) cell.setAttribute('contenteditable', 'true');
      });
      event.currentTarget.textContent = 'Celdas con contenido editables';
      changed();
    });
    form.addEventListener('submit', (event) => {
      if (!payload) { event.preventDefault(); return; }
      const data = {};
      cells.forEach((cell) => {
        const sheet = cell.dataset.sheet;
        const coordinate = cell.dataset.coordinate;
        if (!sheet || !coordinate) return;
        if (!data[sheet]) data[sheet] = {};
        const value = cell.tagName === 'SELECT' ? cell.value : (typeof cell.innerText === 'string' ? cell.innerText : cell.textContent);
        data[sheet][coordinate] = (value || '').replace(/\u00a0/g, ' ');
      });
      const serialized = JSON.stringify(data);
      if (serialized.length > 12 * 1024 * 1024) {
        event.preventDefault();
        if (dirty) dirty.textContent = 'El formulario supera el tamaño permitido; usa el archivo original.';
        return;
      }
      payload.value = serialized;
      submitButtons.forEach((button) => { button.disabled = true; });
    });
  });
  $$('[data-anonymous-toggle]').forEach((toggle) => {
    const form = toggle.closest('form');
    const fields = $('[data-identified-fields]', form);
    if (!fields) return;
    const update = () => {
      fields?.classList.toggle('hidden', toggle.checked);
      $$('input', fields).forEach((input) => {
        input.disabled = toggle.checked;
        if (input.dataset.identityRequired !== undefined) input.required = !toggle.checked;
      });
    };
    toggle.addEventListener('change', update);
    update();
  });
})();
