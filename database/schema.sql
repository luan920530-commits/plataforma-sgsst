PRAGMA foreign_keys = ON;

-- Normia SST · esquema SQLite 3
-- Toda entidad operativa contiene tenant_id. Los catálogos y planes son globales.

CREATE TABLE IF NOT EXISTS planes_saas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    descripcion TEXT NOT NULL DEFAULT '',
    precio_mensual REAL NOT NULL DEFAULT 0 CHECK (precio_mensual >= 0),
    max_usuarios INTEGER NOT NULL DEFAULT 5 CHECK (max_usuarios > 0),
    max_empleados INTEGER NOT NULL DEFAULT 50 CHECK (max_empleados > 0),
    modulo TEXT NOT NULL DEFAULT 'sgsst' CHECK (modulo IN ('sgsst','pesv','emergencias')),
    activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS actividades_economicas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_ciuu TEXT NOT NULL UNIQUE,
    descripcion TEXT NOT NULL,
    sector TEXT NOT NULL DEFAULT 'Servicios',
    nivel_riesgo_predeterminado TEXT NOT NULL CHECK (nivel_riesgo_predeterminado IN ('I','II','III','IV','V')),
    perfil_sociodemografico TEXT NOT NULL DEFAULT '',
    examenes_medicos TEXT NOT NULL DEFAULT '[]',
    matriz_ipevr_base TEXT NOT NULL DEFAULT '[]',
    activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    razonsocial TEXT NOT NULL,
    nit TEXT NOT NULL UNIQUE,
    sector_economico TEXT NOT NULL,
    actividad_economica_id INTEGER,
    numero_trabajadores INTEGER NOT NULL CHECK (numero_trabajadores >= 1),
    nivel_riesgo TEXT NOT NULL CHECK (nivel_riesgo IN ('I','II','III','IV','V')),
    grupo_estandares INTEGER NOT NULL CHECK (grupo_estandares IN (7,21,60)),
    metodo_configuracion TEXT NOT NULL DEFAULT 'Combinado' CHECK (metodo_configuracion IN ('Riesgo','Actividad','Combinado')),
    plan_id INTEGER,
    plan_condicion TEXT NOT NULL DEFAULT 'Pagado' CHECK (plan_condicion IN ('Pagado','Sugerido')),
    representante_legal TEXT NOT NULL DEFAULT '',
    correo_contacto TEXT NOT NULL DEFAULT '',
    telefono TEXT NOT NULL DEFAULT '',
    ciudad TEXT NOT NULL DEFAULT '',
    direccion TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'Activa' CHECK (estado IN ('Activa','Suspendida')),
    onboarding_completado INTEGER NOT NULL DEFAULT 0 CHECK (onboarding_completado IN (0,1)),
    configuracion_generada_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_economica_id) REFERENCES actividades_economicas(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES planes_saas(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL,
    password TEXT NOT NULL,
    rol TEXT NOT NULL CHECK (rol IN ('SuperAdmin','AdminEmpresa','ResponsableSST','Trabajador')),
    licencia_sst TEXT,
    activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0,1)),
    last_login_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CHECK ((rol = 'SuperAdmin' AND tenant_id IS NULL) OR (rol <> 'SuperAdmin' AND tenant_id IS NOT NULL))
);

CREATE TABLE IF NOT EXISTS estandares_minimos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grupo INTEGER NOT NULL CHECK (grupo IN (7,21,60)),
    orden INTEGER NOT NULL,
    codigo TEXT NOT NULL,
    nombre TEXT NOT NULL,
    fase_phva TEXT NOT NULL CHECK (fase_phva IN ('Planear','Hacer','Verificar','Actuar')),
    descripcion TEXT NOT NULL DEFAULT '',
    UNIQUE (grupo, codigo),
    UNIQUE (grupo, orden)
);

CREATE TABLE IF NOT EXISTS documentos_sgsst (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    codigo_estandar TEXT NOT NULL,
    nombre_documento TEXT NOT NULL,
    fase_phva TEXT NOT NULL CHECK (fase_phva IN ('Planear','Hacer','Verificar','Actuar')),
    contenido_html TEXT NOT NULL,
    tipo TEXT NOT NULL DEFAULT 'Estandar' CHECK (tipo IN ('Estandar','Politica','Plan','Programa','Matriz','Procedimiento','Registro')),
    biblioteca_id TEXT,
    estado TEXT NOT NULL DEFAULT 'Borrador' CHECK (estado IN ('Borrador','Aprobado','Obsoleto')),
    version INTEGER NOT NULL DEFAULT 1 CHECK (version >= 1),
    aprobado_por INTEGER,
    aprobado_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobado_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, codigo_estandar)
);

CREATE TABLE IF NOT EXISTS empleados (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    user_id INTEGER,
    nombre TEXT NOT NULL,
    documento TEXT NOT NULL,
    email TEXT NOT NULL DEFAULT '',
    cargo TEXT NOT NULL DEFAULT '',
    area TEXT NOT NULL DEFAULT '',
    tipo_vinculacion TEXT NOT NULL DEFAULT 'Contrato laboral' CHECK (tipo_vinculacion IN ('Contrato laboral','Contratista','Aprendiz','Temporal')),
    fecha_ingreso TEXT,
    telefono TEXT NOT NULL DEFAULT '',
    activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, documento)
);

CREATE TABLE IF NOT EXISTS peligros_ipevr (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    actividad_economica_id INTEGER,
    proceso TEXT NOT NULL DEFAULT 'General',
    cargo TEXT NOT NULL DEFAULT 'Todos los cargos',
    peligro TEXT NOT NULL,
    clasificacion TEXT NOT NULL,
    efectos TEXT NOT NULL DEFAULT '',
    controles_existentes TEXT NOT NULL DEFAULT '',
    nivel_deficiencia INTEGER,
    nivel_exposicion INTEGER,
    nivel_probabilidad INTEGER,
    nivel_consecuencia INTEGER,
    nivel_riesgo TEXT NOT NULL DEFAULT 'Medio' CHECK (nivel_riesgo IN ('Bajo','Medio','Alto','Critico')),
    medidas_control TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo','Controlado')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_economica_id) REFERENCES actividades_economicas(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS perfiles_riesgo (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    actividad_economica_id INTEGER,
    tipo TEXT NOT NULL CHECK (tipo IN ('Sociodemografico','Examenes medicos')),
    nombre TEXT NOT NULL,
    contenido TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_economica_id) REFERENCES actividades_economicas(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, tipo, nombre)
);

CREATE TABLE IF NOT EXISTS plan_trabajo (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    actividad TEXT NOT NULL,
    responsable TEXT NOT NULL,
    fecha_programada TEXT NOT NULL,
    fecha_ejecucion TEXT,
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','En Proceso','Completado')),
    presupuesto REAL NOT NULL DEFAULT 0 CHECK (presupuesto >= 0),
    fase_phva TEXT NOT NULL DEFAULT 'Planear' CHECK (fase_phva IN ('Planear','Hacer','Verificar','Actuar')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS hallazgos_acpa (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    origen TEXT NOT NULL CHECK (origen IN ('Auditoria','Inspeccion','Accidente','Reporte')),
    descripcion TEXT NOT NULL,
    accion_correctiva TEXT NOT NULL,
    responsable TEXT NOT NULL,
    fecha_limite TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Abierto' CHECK (estado IN ('Abierto','Cerrado')),
    fecha_cierre TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS capacitaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    tema TEXT NOT NULL,
    descripcion TEXT NOT NULL DEFAULT '',
    responsable TEXT NOT NULL,
    fecha_programada TEXT NOT NULL,
    duracion_horas REAL NOT NULL DEFAULT 1 CHECK (duracion_horas > 0),
    modalidad TEXT NOT NULL DEFAULT 'Presencial' CHECK (modalidad IN ('Presencial','Virtual','Mixta')),
    estado TEXT NOT NULL DEFAULT 'Programada' CHECK (estado IN ('Programada','Realizada','Cancelada')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS indicadores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('Frecuencia','Severidad','Mortalidad','Prevalencia','Incidencia','Ausentismo','Personalizado')),
    nombre TEXT NOT NULL,
    periodo TEXT NOT NULL,
    numerador REAL NOT NULL DEFAULT 0,
    denominador REAL NOT NULL DEFAULT 0,
    factor REAL NOT NULL DEFAULT 1,
    resultado REAL NOT NULL DEFAULT 0,
    unidad TEXT NOT NULL DEFAULT '',
    meta REAL,
    fecha_corte TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Enlaces compartibles: el token completo solo se entrega al crearlo; se conserva su hash.
CREATE TABLE IF NOT EXISTS accesos_reporte (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    nombre TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    token_hint TEXT NOT NULL DEFAULT '',
    expira_at TEXT NOT NULL,
    max_usos INTEGER NOT NULL DEFAULT 0 CHECK (max_usos >= 0),
    usos INTEGER NOT NULL DEFAULT 0 CHECK (usos >= 0),
    activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0,1)),
    created_by INTEGER,
    last_used_at TEXT,
    revoked_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS autoreportes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    -- En enlaces externos, user_id identifica la cuenta propietaria del acceso, no a quien reporta.
    user_id INTEGER NOT NULL,
    acceso_reporte_id INTEGER,
    origen TEXT NOT NULL DEFAULT 'Portal' CHECK (origen IN ('Portal','Enlace externo')),
    nombre_externo TEXT NOT NULL DEFAULT '',
    contacto_externo TEXT NOT NULL DEFAULT '',
    anonimo INTEGER NOT NULL DEFAULT 0 CHECK (anonimo IN (0,1)),
    tipo TEXT NOT NULL CHECK (tipo IN ('Condicion insegura','Acto inseguro','Incidente','Sintoma','Sugerencia')),
    descripcion TEXT NOT NULL,
    ubicacion TEXT NOT NULL DEFAULT '',
    fecha_evento TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Recibido' CHECK (estado IN ('Recibido','En revision','Gestionado')),
    respuesta TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (acceso_reporte_id) REFERENCES accesos_reporte(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inducciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    titulo TEXT NOT NULL,
    contenido_html TEXT NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS firmas_induccion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    induccion_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    nombre_firmante TEXT NOT NULL,
    documento_firmante TEXT NOT NULL,
    hash_evidencia TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    firmado_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (induccion_id) REFERENCES inducciones(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (induccion_id, user_id)
);

CREATE TABLE IF NOT EXISTS auditoria_saas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER,
    user_id INTEGER,
    accion TEXT NOT NULL,
    entidad TEXT NOT NULL,
    entidad_id INTEGER,
    detalles_json TEXT NOT NULL DEFAULT '{}',
    ip_address TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Historial inmutable de las versiones reemplazadas de documentos y planes.
CREATE TABLE IF NOT EXISTS documento_versiones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    documento_id INTEGER NOT NULL,
    version INTEGER NOT NULL,
    contenido_html TEXT NOT NULL,
    estado TEXT NOT NULL,
    aprobado_por INTEGER,
    aprobado_at TEXT,
    archivado_por INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES documentos_sgsst(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobado_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (archivado_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (documento_id, version)
);

-- Permisos de trabajo: cada revisión crea un registro nuevo y conserva la anterior.
CREATE TABLE IF NOT EXISTS permisos_trabajo (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    codigo TEXT NOT NULL,
    version INTEGER NOT NULL DEFAULT 1 CHECK (version >= 1),
    revisado_de INTEGER,
    tipo TEXT NOT NULL CHECK (tipo IN ('General','Alturas','Espacio confinado','Electrico','Trabajo en caliente','Excavacion','Otro')),
    tarea TEXT NOT NULL,
    ubicacion TEXT NOT NULL,
    descripcion TEXT NOT NULL DEFAULT '',
    fecha_inicio TEXT NOT NULL,
    fecha_fin TEXT NOT NULL,
    solicitante_id INTEGER,
    solicitante_nombre TEXT NOT NULL,
    solicitante_documento TEXT NOT NULL DEFAULT '',
    ejecutantes TEXT NOT NULL,
    responsable_trabajo TEXT NOT NULL,
    peligros TEXT NOT NULL,
    controles TEXT NOT NULL,
    epp TEXT NOT NULL,
    equipos_herramientas TEXT NOT NULL DEFAULT '',
    autorreporte_condiciones TEXT NOT NULL DEFAULT '',
    altura_trabajo REAL,
    coordinador_alturas TEXT NOT NULL DEFAULT '',
    responsable_emergencias TEXT NOT NULL DEFAULT '',
    procedimiento_rescate TEXT NOT NULL DEFAULT '',
    vigia_espacio TEXT NOT NULL DEFAULT '',
    mediciones_atmosfericas TEXT NOT NULL DEFAULT '',
    formacion_personal TEXT NOT NULL DEFAULT '',
    observaciones TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'Borrador' CHECK (estado IN ('Borrador','Pendiente','Aprobado','Activo','Cerrado','Cancelado','Obsoleto')),
    aprobado_por INTEGER,
    aprobador_nombre TEXT NOT NULL DEFAULT '',
    aprobador_documento TEXT NOT NULL DEFAULT '',
    aprobado_at TEXT,
    cerrado_por INTEGER,
    cerrado_at TEXT,
    observaciones_cierre TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (revisado_de) REFERENCES permisos_trabajo(id) ON DELETE SET NULL,
    FOREIGN KEY (solicitante_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobado_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cerrado_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, codigo)
);

-- Los planes de emergencia usan el documento asociado como fuente única de estado y versión.
CREATE TABLE IF NOT EXISTS planes_emergencia (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    documento_id INTEGER,
    codigo TEXT NOT NULL,
    titulo TEXT NOT NULL,
    centro_trabajo TEXT NOT NULL,
    direccion TEXT NOT NULL DEFAULT '',
    alcance TEXT NOT NULL,
    amenazas_json TEXT NOT NULL DEFAULT '[]',
    contactos_json TEXT NOT NULL DEFAULT '[]',
    brigada_json TEXT NOT NULL DEFAULT '[]',
    recursos_json TEXT NOT NULL DEFAULT '[]',
    rutas_evacuacion TEXT NOT NULL DEFAULT '',
    puntos_encuentro TEXT NOT NULL DEFAULT '',
    procedimientos TEXT NOT NULL,
    plan_rescate TEXT NOT NULL DEFAULT '',
    ayuda_mutua TEXT NOT NULL DEFAULT '',
    responsable_plan TEXT NOT NULL,
    telefono_alarma TEXT NOT NULL DEFAULT '',
    proximo_simulacro TEXT,
    fecha_revision TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES documentos_sgsst(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, codigo)
);

CREATE TABLE IF NOT EXISTS simulacros_emergencia (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    plan_id INTEGER NOT NULL,
    fecha TEXT NOT NULL,
    tipo TEXT NOT NULL,
    participantes INTEGER NOT NULL DEFAULT 0 CHECK (participantes >= 0),
    responsable TEXT NOT NULL,
    resultado TEXT NOT NULL DEFAULT '',
    acciones_mejora TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'Programado' CHECK (estado IN ('Programado','Realizado','Cancelado')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES planes_emergencia(id) ON DELETE CASCADE
);

-- Programas de promoción, prevención y vigilancia, con actividades medibles.
CREATE TABLE IF NOT EXISTS programas_prevencion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    documento_id INTEGER,
    codigo TEXT NOT NULL,
    categoria TEXT NOT NULL CHECK (categoria IN ('Promocion de la salud','Prevencion de accidentes','Prevencion de enfermedad laboral','Vigilancia epidemiologica')),
    nombre TEXT NOT NULL,
    anio INTEGER NOT NULL CHECK (anio >= 2000 AND anio <= 2200),
    diagnostico TEXT NOT NULL,
    objetivo_general TEXT NOT NULL,
    objetivos_especificos TEXT NOT NULL DEFAULT '',
    poblacion TEXT NOT NULL,
    peligros_relacionados TEXT NOT NULL DEFAULT '',
    metas_indicadores TEXT NOT NULL,
    responsable TEXT NOT NULL,
    recursos TEXT NOT NULL DEFAULT '',
    presupuesto REAL NOT NULL DEFAULT 0 CHECK (presupuesto >= 0),
    fecha_inicio TEXT NOT NULL,
    fecha_fin TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES documentos_sgsst(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, codigo)
);

CREATE TABLE IF NOT EXISTS programa_actividades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    programa_id INTEGER NOT NULL,
    actividad TEXT NOT NULL,
    responsable TEXT NOT NULL,
    fecha_programada TEXT NOT NULL,
    indicador TEXT NOT NULL DEFAULT '',
    meta TEXT NOT NULL DEFAULT '',
    recursos TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','En Proceso','Completado','Cancelado')),
    evidencia TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (programa_id) REFERENCES programas_prevencion(id) ON DELETE CASCADE
);

-- Módulos de gestión por requisito: responsables, recursos, compromisos y evidencia de cierre.
CREATE TABLE IF NOT EXISTS requisito_acciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    requisito_clave TEXT NOT NULL,
    titulo TEXT NOT NULL,
    descripcion TEXT NOT NULL DEFAULT '',
    responsable_id INTEGER NOT NULL,
    recursos TEXT NOT NULL DEFAULT '',
    fecha_compromiso TEXT,
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','En Proceso','Completado')),
    evidencia TEXT NOT NULL DEFAULT '',
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Seguimiento humano y evidencias del tablero documental; no representa certificación automática.
CREATE TABLE IF NOT EXISTS requisito_seguimiento (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    requisito_clave TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','En gestion','Verificado','No aplica')),
    responsable TEXT NOT NULL DEFAULT '',
    fecha_limite TEXT,
    observaciones TEXT NOT NULL DEFAULT '',
    verificado_por INTEGER,
    verificado_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (verificado_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, requisito_clave)
);

CREATE TABLE IF NOT EXISTS soportes_cumplimiento (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    requisito_clave TEXT NOT NULL,
    modulo TEXT NOT NULL DEFAULT 'sgsst' CHECK (modulo IN ('sgsst','pesv','emergencias')),
    categoria TEXT NOT NULL DEFAULT 'Documento SG-SST',
    titulo TEXT NOT NULL DEFAULT '',
    fecha_evidencia TEXT,
    entidad_tipo TEXT NOT NULL DEFAULT 'requisito',
    entidad_id TEXT NOT NULL DEFAULT '',
    entidad_referencia TEXT NOT NULL DEFAULT '',
    nombre_original TEXT NOT NULL,
    nombre_almacenado TEXT NOT NULL,
    ruta_relativa TEXT NOT NULL,
    mime TEXT NOT NULL,
    extension TEXT NOT NULL,
    tamano INTEGER NOT NULL CHECK (tamano > 0),
    hash_sha256 TEXT NOT NULL,
    descripcion TEXT NOT NULL DEFAULT '',
    estado_revision TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado_revision IN ('Pendiente','Revisado')),
    observaciones_revision TEXT NOT NULL DEFAULT '',
    revisado_por INTEGER,
    revisado_at TEXT,
    subido_por INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (subido_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (revisado_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, nombre_almacenado)
);

-- Planos SVG de emergencia y versiones inmutables de cada diseño guardado.
CREATE TABLE IF NOT EXISTS planos_emergencia (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    plan_id INTEGER NOT NULL,
    nombre TEXT NOT NULL,
    escala TEXT NOT NULL DEFAULT 'Referencial · verificar en sitio',
    descripcion TEXT NOT NULL DEFAULT '',
    datos_json TEXT NOT NULL,
    version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0),
    created_by INTEGER,
    updated_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES planes_emergencia(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS planos_emergencia_versiones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    plano_id INTEGER NOT NULL,
    version INTEGER NOT NULL CHECK (version > 0),
    nombre TEXT NOT NULL,
    escala TEXT NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL DEFAULT '',
    datos_json TEXT NOT NULL,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos_emergencia(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (plano_id, version)
);

-- Respuestas tenant de los formularios importados de la biblioteca SG-SST.
CREATE TABLE IF NOT EXISTS biblioteca_respuestas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    plantilla_id TEXT NOT NULL,
    titulo TEXT NOT NULL,
    datos_json TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Borrador' CHECK (estado IN ('Borrador','Completado')),
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_unique ON users(lower(email));
CREATE INDEX IF NOT EXISTS idx_users_tenant ON users(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tenants_actividad ON tenants(actividad_economica_id);
CREATE INDEX IF NOT EXISTS idx_tenants_estado ON tenants(estado);
CREATE INDEX IF NOT EXISTS idx_documentos_tenant_estado ON documentos_sgsst(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_documentos_tenant_fase ON documentos_sgsst(tenant_id, fase_phva);
CREATE INDEX IF NOT EXISTS idx_documentos_aprobado_por ON documentos_sgsst(aprobado_por);
CREATE INDEX IF NOT EXISTS idx_empleados_tenant ON empleados(tenant_id);
CREATE INDEX IF NOT EXISTS idx_empleados_user ON empleados(user_id);
CREATE INDEX IF NOT EXISTS idx_peligros_tenant ON peligros_ipevr(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_peligros_actividad ON peligros_ipevr(actividad_economica_id);
CREATE INDEX IF NOT EXISTS idx_perfiles_tenant ON perfiles_riesgo(tenant_id);
CREATE INDEX IF NOT EXISTS idx_plan_tenant_fecha ON plan_trabajo(tenant_id, fecha_programada);
CREATE INDEX IF NOT EXISTS idx_hallazgos_tenant_estado ON hallazgos_acpa(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_capacitaciones_tenant_fecha ON capacitaciones(tenant_id, fecha_programada);
CREATE INDEX IF NOT EXISTS idx_indicadores_tenant_periodo ON indicadores(tenant_id, periodo);
CREATE INDEX IF NOT EXISTS idx_autoreportes_tenant ON autoreportes(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_autoreportes_user ON autoreportes(user_id);
CREATE INDEX IF NOT EXISTS idx_accesos_reporte_tenant ON accesos_reporte(tenant_id, activo, expira_at);
CREATE INDEX IF NOT EXISTS idx_inducciones_tenant ON inducciones(tenant_id, activo);
CREATE INDEX IF NOT EXISTS idx_firmas_tenant ON firmas_induccion(tenant_id);
CREATE INDEX IF NOT EXISTS idx_firmas_user ON firmas_induccion(user_id);
CREATE INDEX IF NOT EXISTS idx_auditoria_tenant_fecha ON auditoria_saas(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_auditoria_user ON auditoria_saas(user_id);
CREATE INDEX IF NOT EXISTS idx_estandares_grupo ON estandares_minimos(grupo, orden);
CREATE INDEX IF NOT EXISTS idx_documento_versiones_documento ON documento_versiones(documento_id, version);
CREATE INDEX IF NOT EXISTS idx_documento_versiones_tenant ON documento_versiones(tenant_id);
CREATE INDEX IF NOT EXISTS idx_permisos_tenant_estado ON permisos_trabajo(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_permisos_fechas ON permisos_trabajo(tenant_id, fecha_inicio, fecha_fin);
CREATE INDEX IF NOT EXISTS idx_emergencias_tenant ON planes_emergencia(tenant_id);
CREATE INDEX IF NOT EXISTS idx_simulacros_plan ON simulacros_emergencia(plan_id, fecha);
CREATE INDEX IF NOT EXISTS idx_simulacros_tenant ON simulacros_emergencia(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_programas_tenant ON programas_prevencion(tenant_id, anio);
CREATE INDEX IF NOT EXISTS idx_programa_actividades ON programa_actividades(programa_id, fecha_programada);
CREATE INDEX IF NOT EXISTS idx_programa_actividades_tenant ON programa_actividades(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_requisito_acciones_tenant ON requisito_acciones(tenant_id, requisito_clave, estado);
CREATE INDEX IF NOT EXISTS idx_requisito_acciones_responsable ON requisito_acciones(responsable_id, estado);
CREATE INDEX IF NOT EXISTS idx_requisito_seguimiento_tenant ON requisito_seguimiento(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_soportes_requisito ON soportes_cumplimiento(tenant_id, requisito_clave);
CREATE INDEX IF NOT EXISTS idx_soportes_modulo ON soportes_cumplimiento(tenant_id, modulo, estado_revision);
CREATE INDEX IF NOT EXISTS idx_soportes_entidad ON soportes_cumplimiento(tenant_id, entidad_tipo, entidad_id);
CREATE INDEX IF NOT EXISTS idx_soportes_fecha ON soportes_cumplimiento(tenant_id, fecha_evidencia);
CREATE INDEX IF NOT EXISTS idx_planos_emergencia_plan ON planos_emergencia(tenant_id, plan_id, updated_at);
CREATE INDEX IF NOT EXISTS idx_planos_versiones_plano ON planos_emergencia_versiones(tenant_id, plano_id, version);
CREATE INDEX IF NOT EXISTS idx_biblioteca_respuestas_tenant ON biblioteca_respuestas(tenant_id, plantilla_id, updated_at);
