-- Actas sanitarias, requerimientos verificables y registros operativos por tenant.
CREATE TABLE IF NOT EXISTS ivc_actas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    numero_acta TEXT NOT NULL,
    autoridad TEXT NOT NULL,
    fecha_visita TEXT NOT NULL,
    fecha_notificacion TEXT,
    fecha_limite TEXT,
    concepto TEXT NOT NULL,
    porcentaje REAL CHECK (porcentaje IS NULL OR (porcentaje >= 0 AND porcentaje <= 100)),
    direccion_visita TEXT NOT NULL DEFAULT '',
    fuente_nombre TEXT NOT NULL DEFAULT '',
    fuente_sha256 TEXT NOT NULL DEFAULT '',
    observaciones TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE (tenant_id, numero_acta)
);

CREATE TABLE IF NOT EXISTS ivc_requerimientos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    acta_id INTEGER NOT NULL,
    codigo TEXT NOT NULL,
    origen TEXT NOT NULL DEFAULT 'Requerimiento IVC' CHECK (origen IN ('Requerimiento IVC','Brecha documental','Seguimiento interno')),
    tema TEXT NOT NULL DEFAULT 'general',
    hallazgo TEXT NOT NULL,
    accion TEXT NOT NULL,
    responsable TEXT NOT NULL DEFAULT 'Por asignar',
    fecha_compromiso TEXT,
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','En curso','Por verificar','Cerrado','No aplica')),
    evidencia_requerida TEXT NOT NULL DEFAULT '',
    verificacion TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (acta_id) REFERENCES ivc_actas(id) ON DELETE CASCADE,
    UNIQUE (acta_id, codigo)
);

CREATE TABLE IF NOT EXISTS sanitario_registros (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    requerimiento_id INTEGER,
    tipo TEXT NOT NULL,
    titulo TEXT NOT NULL,
    fecha_registro TEXT NOT NULL,
    responsable TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente','Programado','Ejecutado','Verificado','No aplica')),
    datos_json TEXT NOT NULL DEFAULT '{}',
    observaciones TEXT NOT NULL DEFAULT '',
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (requerimiento_id) REFERENCES ivc_requerimientos(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_ivc_actas_tenant ON ivc_actas(tenant_id, fecha_visita);
CREATE INDEX IF NOT EXISTS idx_ivc_requerimientos_acta ON ivc_requerimientos(acta_id, codigo);
CREATE INDEX IF NOT EXISTS idx_ivc_requerimientos_tenant ON ivc_requerimientos(tenant_id, estado);
CREATE INDEX IF NOT EXISTS idx_sanitario_tenant_tipo ON sanitario_registros(tenant_id, tipo, fecha_registro);
CREATE INDEX IF NOT EXISTS idx_sanitario_requerimiento ON sanitario_registros(requerimiento_id);
