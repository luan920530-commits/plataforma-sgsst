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
    }
}
