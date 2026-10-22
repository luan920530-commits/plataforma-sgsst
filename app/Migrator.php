<?php
namespace App;

/** Migraciones idempotentes: se ejecutan al arrancar y nunca destruyen datos. */
class Migrator
{
    public static function run(): void
    {
        $pdo = Database::get();
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $done = array_column(Database::query('SELECT version FROM schema_migrations'), 'version');

        if (!in_array('2026_01_sgsst_core', $done, true)) {
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS sgsst_evaluaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL, codigo TEXT NOT NULL,
                estado TEXT CHECK(estado IN ('cumple','parcial','no_cumple','no_aplica')) DEFAULT 'no_cumple',
                evidencia TEXT, observacion TEXT, responsable TEXT, fecha_compromiso DATE,
                actualizado_por INTEGER, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(empresa_id, codigo), FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS sgsst_actividades (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL, titulo TEXT NOT NULL,
                ciclo TEXT DEFAULT 'PLANEAR', responsable TEXT, fecha_inicio DATE, fecha_vencimiento DATE,
                estado TEXT CHECK(estado IN ('pendiente','en_proceso','completada','vencida','cancelada')) DEFAULT 'pendiente',
                evidencia TEXT, origen TEXT DEFAULT 'manual', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS sgsst_registros (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL,
                tipo TEXT NOT NULL, titulo TEXT NOT NULL, descripcion TEXT, fecha DATE NOT NULL,
                fecha_vencimiento DATE, responsable TEXT,
                estado TEXT CHECK(estado IN ('abierto','en_gestion','cerrado','vencido')) DEFAULT 'abierto',
                severidad TEXT CHECK(severidad IN ('baja','media','alta','critica')) DEFAULT 'media',
                evidencia TEXT, creado_por INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS sgsst_documentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL, plantilla TEXT NOT NULL,
                nombre_archivo TEXT NOT NULL, ruta TEXT NOT NULL, hash_sha256 TEXT, generado_por INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS auditoria_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER, empresa_id INTEGER,
                accion TEXT NOT NULL, entidad TEXT, entidad_id INTEGER, detalle TEXT, ip TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE INDEX IF NOT EXISTS idx_sgsst_eval_empresa ON sgsst_evaluaciones(empresa_id);
            CREATE INDEX IF NOT EXISTS idx_sgsst_act_empresa ON sgsst_actividades(empresa_id, estado);
            CREATE INDEX IF NOT EXISTS idx_sgsst_reg_empresa ON sgsst_registros(empresa_id, tipo);
            CREATE INDEX IF NOT EXISTS idx_auditoria_empresa ON auditoria_log(empresa_id, created_at);
            ");
            // Columna de cambio forzado de contraseña (instalaciones existentes)
            $cols = array_column(Database::query('PRAGMA table_info(usuarios)'), 'name');
            if (!in_array('must_change_password', $cols, true)) {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN must_change_password INTEGER DEFAULT 0');
            }
            Database::execute('INSERT INTO schema_migrations (version) VALUES (?)', ['2026_01_sgsst_core']);
        }
        if (!in_array('2026_02_fase2', $done, true)) {
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS sgsst_peligros (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL,
                proceso TEXT, zona TEXT, actividad TEXT, tarea TEXT, rutinaria INTEGER DEFAULT 1,
                descripcion TEXT NOT NULL, clasificacion TEXT, efectos TEXT,
                control_fuente TEXT, control_medio TEXT, control_individuo TEXT,
                nd INTEGER DEFAULT 2, ne INTEGER DEFAULT 2, nc INTEGER DEFAULT 25,
                np INTEGER DEFAULT 0, np_nivel TEXT, nr INTEGER DEFAULT 0, nr_nivel TEXT, aceptabilidad TEXT,
                medidas_intervencion TEXT, responsable TEXT, fecha_compromiso DATE,
                estado TEXT CHECK(estado IN ('abierto','en_control','cerrado')) DEFAULT 'abierto',
                creado_por INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS sgsst_periodos (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL,
                periodo TEXT NOT NULL, trabajadores_promedio INTEGER DEFAULT 0,
                horas_trabajadas REAL DEFAULT 0, dias_perdidos INTEGER DEFAULT 0, dias_cargados INTEGER DEFAULT 0,
                accidentes_mortales INTEGER DEFAULT 0, dias_ausencia_medica INTEGER DEFAULT 0, dias_programados INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(empresa_id, periodo),
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS empresa_accesos (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL, usuario_id INTEGER NOT NULL,
                permiso TEXT CHECK(permiso IN ('lectura')) DEFAULT 'lectura',
                otorgado_por INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(empresa_id, usuario_id),
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE);
            CREATE INDEX IF NOT EXISTS idx_peligros_empresa ON sgsst_peligros(empresa_id, nr_nivel);
            CREATE INDEX IF NOT EXISTS idx_accesos_usuario ON empresa_accesos(usuario_id);
            ");
            Database::execute('INSERT INTO schema_migrations (version) VALUES (?)', ['2026_02_fase2']);
        }

        if (!in_array('2026_03_portal_cliente', $done, true)) {
            // Ampliar permisos de acceso: lectura | representante (reconstruye la tabla por el CHECK de SQLite)
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS empresa_accesos_nueva (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL, usuario_id INTEGER NOT NULL,
                permiso TEXT CHECK(permiso IN ('lectura','representante')) DEFAULT 'lectura',
                otorgado_por INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(empresa_id, usuario_id),
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE);
            INSERT OR IGNORE INTO empresa_accesos_nueva (id, empresa_id, usuario_id, permiso, otorgado_por, created_at)
                SELECT id, empresa_id, usuario_id, permiso, otorgado_por, created_at FROM empresa_accesos;
            DROP TABLE empresa_accesos;
            ALTER TABLE empresa_accesos_nueva RENAME TO empresa_accesos;
            CREATE INDEX IF NOT EXISTS idx_accesos_usuario ON empresa_accesos(usuario_id);
            ");
            $cols = array_column(Database::query('PRAGMA table_info(empresas)'), 'name');
            if (!in_array('reporte_token', $cols, true)) {
                $pdo->exec('ALTER TABLE empresas ADD COLUMN reporte_token TEXT');
            }
            Database::execute('INSERT INTO schema_migrations (version) VALUES (?)', ['2026_03_portal_cliente']);
        }

        if (!in_array('2026_04_formularios_web', $done, true)) {
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS sgsst_formularios (
                id INTEGER PRIMARY KEY AUTOINCREMENT, empresa_id INTEGER NOT NULL, formato TEXT NOT NULL,
                datos TEXT NOT NULL, fecha DATE NOT NULL, fecha_vencimiento DATE,
                estado TEXT CHECK(estado IN ('abierto','en_gestion','cerrado')) DEFAULT 'abierto',
                diligenciado_por INTEGER, creado_por_nombre TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE);
            CREATE INDEX IF NOT EXISTS idx_formularios_empresa ON sgsst_formularios(empresa_id, formato);
            ");
            Database::execute('INSERT INTO schema_migrations (version) VALUES (?)', ['2026_04_formularios_web']);
        }

        if (!in_array('2026_05_paquete_documental', $done, true)) {
            $cols = array_column(Database::query('PRAGMA table_info(sgsst_documentos)'), 'name');
            if (!in_array('carpeta', $cols, true)) $pdo->exec('ALTER TABLE sgsst_documentos ADD COLUMN carpeta TEXT');
            if (!in_array('estado', $cols, true)) $pdo->exec("ALTER TABLE sgsst_documentos ADD COLUMN estado TEXT DEFAULT 'pendiente_revision'");
            if (!in_array('version', $cols, true)) $pdo->exec('ALTER TABLE sgsst_documentos ADD COLUMN version INTEGER DEFAULT 1');
            Database::execute('INSERT INTO schema_migrations (version) VALUES (?)', ['2026_05_paquete_documental']);
        }
    }
}
