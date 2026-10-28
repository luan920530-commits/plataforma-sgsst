-- Normia SST · migración 006
-- Brechas operativas B–N: IPEVR, eventos, salud, formación, comités,
-- estadística trazable, benchmarking y segundo factor. Todo inicia vacío.


CREATE TABLE IF NOT EXISTS peligro_acciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    peligro_id INTEGER NOT NULL,
    descripcion TEXT NOT NULL,
    jerarquia TEXT NOT NULL CHECK (jerarquia IN ('Eliminacion','Sustitucion','Ingenieria','Administrativo','EPP')),
    responsable TEXT NOT NULL,
    recursos TEXT NOT NULL DEFAULT '',
    fecha_compromiso TEXT NOT NULL,
    indicador_eficacia TEXT NOT NULL DEFAULT '',
    porcentaje INTEGER NOT NULL DEFAULT 0 CHECK (porcentaje >= 0 AND porcentaje <= 100),
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','En ejecucion','Implementado','Eficaz','No eficaz','Cancelado')),
    fecha_ejecucion TEXT,
    resultado TEXT NOT NULL DEFAULT '',
    eficacia_fecha TEXT,
    verificado_por INTEGER,
    verificado_at TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (peligro_id) REFERENCES peligros_ipevr(id) ON DELETE CASCADE,
    FOREIGN KEY (verificado_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_peligro_acciones ON peligro_acciones(tenant_id, peligro_id, estado, fecha_compromiso);

CREATE TABLE IF NOT EXISTS peligro_accion_hitos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    accion_id INTEGER NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_programada TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','Completado')),
    fecha_cumplimiento TEXT,
    nota TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (accion_id) REFERENCES peligro_acciones(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_peligro_hitos ON peligro_accion_hitos(tenant_id, accion_id, fecha_programada);


CREATE TABLE IF NOT EXISTS periodos_estadisticos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    periodo TEXT NOT NULL,
    fecha_inicio TEXT NOT NULL,
    fecha_fin TEXT NOT NULL,
    horas_hombre_trabajadas REAL NOT NULL CHECK (horas_hombre_trabajadas > 0),
    trabajadores_promedio REAL NOT NULL CHECK (trabajadores_promedio > 0),
    dias_programados REAL NOT NULL DEFAULT 0 CHECK (dias_programados >= 0),
    dias_ausencia REAL NOT NULL DEFAULT 0 CHECK (dias_ausencia >= 0),
    fuente TEXT NOT NULL,
    observaciones TEXT NOT NULL DEFAULT '',
    verificado_por INTEGER,
    verificado_at TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (verificado_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, periodo),
    CHECK (fecha_fin >= fecha_inicio),
    CHECK (dias_ausencia <= dias_programados OR dias_programados = 0)
);
CREATE INDEX IF NOT EXISTS idx_periodos_estadisticos ON periodos_estadisticos(tenant_id, fecha_inicio, fecha_fin);


CREATE UNIQUE INDEX IF NOT EXISTS idx_certificado_capacitacion ON capacitacion_asistentes(tenant_id, certificado_codigo) WHERE certificado_codigo <> '';

CREATE TABLE IF NOT EXISTS comite_compromisos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    comite_id INTEGER NOT NULL,
    reunion_id INTEGER,
    categoria TEXT NOT NULL DEFAULT 'Compromiso' CHECK (categoria IN ('Compromiso','Recomendacion Vigia','Reporte condicion insegura','Seguimiento COPASST')),
    descripcion TEXT NOT NULL,
    responsable TEXT NOT NULL,
    fecha_compromiso TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','En curso','Completado','Verificado','Cancelado')),
    resultado TEXT NOT NULL DEFAULT '',
    verificado_por INTEGER,
    verificado_at TEXT,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (comite_id) REFERENCES comites(id) ON DELETE CASCADE,
    FOREIGN KEY (reunion_id) REFERENCES comite_reuniones(id) ON DELETE SET NULL,
    FOREIGN KEY (verificado_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_comite_compromisos ON comite_compromisos(tenant_id, comite_id, estado, fecha_compromiso);

-- La tabla histórica tiene un CHECK cerrado de cuatro permisos. La ampliación
-- aditiva evita reconstruirla y mantiene los permisos existentes intactos.
CREATE TABLE IF NOT EXISTS usuario_permisos_extendidos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    permiso TEXT NOT NULL CHECK (permiso IN ('integraciones.gestionar')),
    otorgado_por INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (otorgado_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (user_id, permiso)
);
CREATE INDEX IF NOT EXISTS idx_permisos_extendidos_tenant_user ON usuario_permisos_extendidos(tenant_id, user_id);

-- Conectores HTTPS reales. Nunca se almacena el cuerpo intercambiado: sólo huellas,
-- metadatos técnicos y una referencia escalar opcional de la respuesta.
CREATE TABLE IF NOT EXISTS integraciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('ARL','Nomina','Medicina ocupacional')),
    nombre TEXT NOT NULL,
    endpoint TEXT NOT NULL,
    metodo TEXT NOT NULL DEFAULT 'POST' CHECK (metodo IN ('POST','PUT','PATCH')),
    autenticacion TEXT NOT NULL DEFAULT 'Bearer' CHECK (autenticacion IN ('Bearer','X-API-Key')),
    credencial_cifrada TEXT NOT NULL,
    ruta_referencia TEXT NOT NULL DEFAULT '',
    activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0,1)),
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, tipo, nombre)
);
CREATE INDEX IF NOT EXISTS idx_integraciones ON integraciones(tenant_id, tipo, activo);

CREATE TABLE IF NOT EXISTS integracion_ejecuciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    integracion_id INTEGER NOT NULL,
    correlacion TEXT NOT NULL,
    operacion TEXT NOT NULL,
    solicitud_sha256 TEXT NOT NULL,
    respuesta_sha256 TEXT NOT NULL DEFAULT '',
    http_status INTEGER,
    bytes_respuesta INTEGER NOT NULL DEFAULT 0,
    duracion_ms INTEGER NOT NULL DEFAULT 0,
    estado_tecnico TEXT NOT NULL CHECK (estado_tecnico IN ('En curso','Respuesta HTTPS','Error HTTP','Error de conexion','Respuesta invalida')),
    detalle TEXT NOT NULL DEFAULT '',
    referencia_externa TEXT NOT NULL DEFAULT '',
    ejecutado_por INTEGER,
    ejecutado_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verificado_por INTEGER,
    verificado_at TEXT,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (integracion_id) REFERENCES integraciones(id) ON DELETE CASCADE,
    FOREIGN KEY (ejecutado_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (verificado_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (tenant_id, correlacion)
);
CREATE INDEX IF NOT EXISTS idx_integracion_ejecuciones ON integracion_ejecuciones(tenant_id, integracion_id, ejecutado_at);
