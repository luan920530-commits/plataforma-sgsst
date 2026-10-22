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

CREATE TABLE IF NOT EXISTS autoreportes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('Condicion insegura','Acto inseguro','Incidente','Sintoma','Sugerencia')),
    descripcion TEXT NOT NULL,
    ubicacion TEXT NOT NULL DEFAULT '',
    fecha_evento TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Recibido' CHECK (estado IN ('Recibido','En revision','Gestionado')),
    respuesta TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
CREATE INDEX IF NOT EXISTS idx_inducciones_tenant ON inducciones(tenant_id, activo);
CREATE INDEX IF NOT EXISTS idx_firmas_tenant ON firmas_induccion(tenant_id);
CREATE INDEX IF NOT EXISTS idx_firmas_user ON firmas_induccion(user_id);
CREATE INDEX IF NOT EXISTS idx_auditoria_tenant_fecha ON auditoria_saas(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_auditoria_user ON auditoria_saas(user_id);
CREATE INDEX IF NOT EXISTS idx_estandares_grupo ON estandares_minimos(grupo, orden);
