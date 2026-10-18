PRAGMA foreign_keys = ON;

CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    nombre TEXT NOT NULL,
    rol TEXT CHECK(rol IN ('superadmin','consultor','cliente')) DEFAULT 'consultor',
    activo INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE empresas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    razon_social TEXT NOT NULL,
    nit TEXT UNIQUE NOT NULL,
    ciiu TEXT,
    actividad_economica TEXT,
    direccion TEXT,
    ciudad TEXT,
    departamento TEXT,
    telefono TEXT,
    email TEXT,
    arl TEXT,
    nivel_riesgo TEXT CHECK(nivel_riesgo IN ('I','II','III','IV','V')),
    representante_legal TEXT,
    num_trabajadores INTEGER DEFAULT 0,
    aforo_max INTEGER DEFAULT 0,
    area_m2 REAL,
    num_pisos INTEGER,
    horarios TEXT,
    areas_alto_riesgo TEXT,
    logo_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE analisis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id INTEGER NOT NULL,
    codigo_documento TEXT DEFAULT 'SST-F-1',
    version TEXT DEFAULT '1',
    fecha_elaboracion DATE NOT NULL,
    estado TEXT CHECK(estado IN ('borrador','completo','generado')) DEFAULT 'borrador',
    observaciones TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

CREATE TABLE inv_extintores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analisis_id INTEGER NOT NULL,
    numero TEXT,
    ubicacion TEXT,
    tipo TEXT,
    peso TEXT,
    fecha_recarga DATE,
    capacidad TEXT,
    cumple INTEGER DEFAULT 1,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
);

CREATE TABLE inv_botiquin (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analisis_id INTEGER NOT NULL,
    elemento TEXT NOT NULL,
    cantidad INTEGER DEFAULT 1,
    fecha_vencimiento DATE,
    apto_para_uso INTEGER DEFAULT 1,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
);

CREATE TABLE inv_otros (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analisis_id INTEGER NOT NULL,
    categoria TEXT,
    cantidad INTEGER DEFAULT 0,
    descripcion TEXT,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
);

CREATE TABLE amenazas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analisis_id INTEGER NOT NULL,
    tipo TEXT,
    nombre TEXT NOT NULL,
    origen TEXT CHECK(origen IN ('interna','externa')),
    calificacion_color TEXT,
    aplica INTEGER DEFAULT 1,
    promedio_personas REAL DEFAULT 0,
    promedio_recursos REAL DEFAULT 0,
    promedio_sistemas REAL DEFAULT 0,
    total_personas REAL DEFAULT 0,
    total_recursos REAL DEFAULT 0,
    total_sistemas REAL DEFAULT 0,
    color_personas TEXT,
    color_recursos TEXT,
    color_sistemas TEXT,
    nivel_riesgo TEXT,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
);

CREATE TABLE respuestas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    amenaza_id INTEGER NOT NULL,
    formato INTEGER NOT NULL,
    categoria TEXT NOT NULL,
    pregunta TEXT NOT NULL,
    respuesta TEXT CHECK(respuesta IN ('SI','NO','PARCIAL','N/A')),
    calificacion REAL,
    observacion TEXT,
    orden INTEGER DEFAULT 0,
    FOREIGN KEY (amenaza_id) REFERENCES amenazas(id) ON DELETE CASCADE
);

CREATE TABLE personal (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id INTEGER NOT NULL,
    nombre_completo TEXT NOT NULL,
    cedula TEXT,
    email TEXT,
    cargo TEXT,
    rh TEXT,
    celular TEXT,
    dependencia TEXT,
    entrenado_brigada TEXT,
    eps TEXT,
    afp TEXT,
    contacto_emergencia_nombre TEXT,
    contacto_emergencia_tel TEXT,
    alergias TEXT,
    firma_url TEXT,
    activo INTEGER DEFAULT 1,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

CREATE TABLE documentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analisis_id INTEGER NOT NULL,
    tipo TEXT,
    nombre_archivo TEXT,
    ruta TEXT,
    hash_sha256 TEXT,
    generado_por INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
);

CREATE TABLE recursos_externos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    analisis_id INTEGER NOT NULL,
    entidad TEXT,
    telefono TEXT,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
);

CREATE INDEX idx_analisis_empresa ON analisis(empresa_id);
CREATE INDEX idx_amenazas_analisis ON amenazas(analisis_id);
CREATE INDEX idx_respuestas_amenaza ON respuestas(amenaza_id);
CREATE INDEX idx_personal_empresa ON personal(empresa_id);
