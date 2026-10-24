<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActividadEconomica;
use App\Models\Autoreporte;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Hallazgo;
use App\Models\Indicador;
use App\Models\PlanEmergencia;
use App\Models\PlanoEmergencia;
use App\Models\PlanTrabajo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Indicator;

final class DemoSeeder
{
    public static function seed(): void
    {
        self::createUserIfMissing(null, 'Administrador de plataforma', 'superadmin@normia.co', 'SuperAdmin');
        if (Database::first('SELECT id FROM tenants WHERE nit = ?', ['901.245.780-3']) !== null) {
            return;
        }

        $activity = fn(string $code): ?array => Database::first('SELECT * FROM actividades_economicas WHERE codigo_ciuu = ?', [$code]);
        $plan = fn(string $name): ?array => Database::first('SELECT * FROM planes_saas WHERE nombre = ?', [$name]);
        $professional = $plan('Profesional');
        $essential = $plan('Esencial');

        $mainId = Tenant::create([
            'razonsocial' => 'Andina Digital S.A.S.', 'nit' => '901.245.780-3', 'sector_economico' => 'Servicios',
            'actividad_economica_id' => $activity('6201')['id'] ?? null, 'numero_trabajadores' => 28,
            'nivel_riesgo' => 'II', 'grupo_estandares' => 21, 'metodo_configuracion' => 'Combinado',
            'plan_id' => $professional['id'] ?? null, 'representante_legal' => 'Valentina Rojas',
            'correo_contacto' => 'admin@andina.co', 'telefono' => '601 745 0180', 'ciudad' => 'Bogotá D.C.',
            'direccion' => 'Carrera 13 # 93-40, oficina 402',
        ]);
        $constructionId = Tenant::create([
            'razonsocial' => 'Constructora Horizonte S.A.S.', 'nit' => '900.812.447-1', 'sector_economico' => 'Construcción',
            'actividad_economica_id' => $activity('4100')['id'] ?? null, 'numero_trabajadores' => 64,
            'nivel_riesgo' => 'V', 'grupo_estandares' => 60, 'metodo_configuracion' => 'Combinado',
            'plan_id' => $professional['id'] ?? null, 'representante_legal' => 'Martín Cárdenas',
            'correo_contacto' => 'sst@horizonte.co', 'telefono' => '604 480 2233', 'ciudad' => 'Medellín',
            'direccion' => 'Calle 44 # 70-120',
        ]);
        $bakeryId = Tenant::create([
            'razonsocial' => 'La Espiga Panadería S.A.S.', 'nit' => '901.552.019-8', 'sector_economico' => 'Industrial',
            'actividad_economica_id' => $activity('1071')['id'] ?? null, 'numero_trabajadores' => 8,
            'nivel_riesgo' => 'II', 'grupo_estandares' => 7, 'metodo_configuracion' => 'Combinado',
            'plan_id' => $essential['id'] ?? null, 'representante_legal' => 'Elena Suárez',
            'correo_contacto' => 'hola@laespiga.co', 'telefono' => '602 552 9012', 'ciudad' => 'Cali',
            'direccion' => 'Avenida 4N # 22-31',
        ]);

        $adminId = self::createUserIfMissing($mainId, 'Valentina Rojas', 'admin@andina.co', 'AdminEmpresa');
        $sstId = self::createUserIfMissing($mainId, 'Camilo Andrés Mora', 'sst@andina.co', 'ResponsableSST', 'SST-2026-02841');
        $workerId = self::createUserIfMissing($mainId, 'Laura Sofía Benítez', 'trabajador@andina.co', 'Trabajador');

        $employees = [
            ['Valentina Rojas', '52.480.112', 'admin@andina.co', 'Gerente general', 'Dirección', $adminId],
            ['Camilo Andrés Mora', '79.845.221', 'sst@andina.co', 'Coordinador SG-SST', 'Gestión humana', $sstId],
            ['Laura Sofía Benítez', '1.032.455.890', 'trabajador@andina.co', 'Analista de producto', 'Producto', $workerId],
            ['Diego Alejandro Ruiz', '80.745.663', 'diego@andina.co', 'Desarrollador senior', 'Tecnología', null],
            ['Sara Isabel Nieto', '1.020.887.410', 'sara@andina.co', 'Diseñadora UX', 'Producto', null],
            ['Julián Esteban Silva', '1.101.552.034', 'julian@andina.co', 'Ingeniero de soporte', 'Operaciones', null],
            ['Natalia Cifuentes', '52.889.731', 'natalia@andina.co', 'Líder de talento humano', 'Gestión humana', null],
            ['Andrés Felipe Leal', '72.450.112', 'andres@andina.co', 'Consultor de implementación', 'Operaciones', null],
        ];
        foreach ($employees as $employee) {
            Empleado::create([
                'tenant_id' => $mainId, 'nombre' => $employee[0], 'documento' => $employee[1], 'email' => $employee[2],
                'cargo' => $employee[3], 'area' => $employee[4], 'user_id' => $employee[5],
                'tipo_vinculacion' => 'Contrato laboral', 'fecha_ingreso' => '2025-02-03', 'telefono' => '300 555 0190',
            ]);
        }

        $summary = DocumentGenerator::generate($mainId);
        DocumentGenerator::generate($constructionId);
        DocumentGenerator::generate($bakeryId);

        $policies = Database::query("SELECT id FROM documentos_sgsst WHERE tenant_id = ? AND tipo = 'Politica'", [$mainId]);
        foreach ($policies as $policy) {
            Documento::approve((int) $policy['id'], $mainId, $sstId);
        }
        $drafts = Database::query("SELECT id FROM documentos_sgsst WHERE tenant_id = ? AND estado = 'Borrador' ORDER BY id LIMIT 7", [$mainId]);
        foreach ($drafts as $document) {
            Documento::approve((int) $document['id'], $mainId, $sstId);
        }

        $activities = PlanTrabajo::forTenant($mainId);
        foreach (array_slice($activities, 0, 4) as $activityItem) {
            PlanTrabajo::updateStatus((int) $activityItem['id'], $mainId, 'Completado');
        }
        foreach (array_slice($activities, 4, 2) as $activityItem) {
            PlanTrabajo::updateStatus((int) $activityItem['id'], $mainId, 'En Proceso');
        }

        Hallazgo::create(['tenant_id' => $mainId, 'origen' => 'Inspeccion', 'descripcion' => 'Dos puestos del área de tecnología no tienen ajuste ergonómico documentado.', 'accion_correctiva' => 'Evaluar puestos, ajustar apoyos lumbares y registrar pausas activas con los equipos.', 'responsable' => 'Camilo Andrés Mora', 'fecha_limite' => date('Y-m-d', strtotime('+12 days'))]);
        Hallazgo::create(['tenant_id' => $mainId, 'origen' => 'Reporte', 'descripcion' => 'Reporte de fatiga visual al finalizar jornadas con reuniones continuas.', 'accion_correctiva' => 'Revisar programación de reuniones y socializar pautas de descanso visual.', 'responsable' => 'Natalia Cifuentes', 'fecha_limite' => date('Y-m-d', strtotime('+20 days'))]);
        $closedFinding = Hallazgo::create(['tenant_id' => $mainId, 'origen' => 'Auditoria', 'descripcion' => 'La matriz legal no tenía evidencia de revisión trimestral.', 'accion_correctiva' => 'Actualizar matriz y asignar responsable de verificación de cambios.', 'responsable' => 'Camilo Andrés Mora', 'fecha_limite' => date('Y-m-d', strtotime('-10 days'))]);
        Hallazgo::setStatus($closedFinding, $mainId, 'Cerrado');

        foreach ([
            ['Frecuencia', 'Frecuencia de accidentalidad', '2026-09', 0, 5040, 240000, 0],
            ['Ausentismo', 'Ausentismo por causa médica', '2026-09', 18, 560, 100, 3],
            ['Personalizado', 'Ejecución del Plan Anual de Trabajo', '2026-10', 4, 12, 100, 85],
        ] as $item) {
            $definition = Indicator::definition($item[0]);
            $factor = $item[0] === 'Personalizado' ? 100 : $definition['factor'];
            Indicador::create([
                'tenant_id' => $mainId, 'tipo' => $item[0], 'nombre' => $item[1], 'periodo' => $item[2],
                'numerador' => $item[3], 'denominador' => $item[4], 'factor' => $factor,
                'resultado' => Indicator::calculate($item[3], $item[4], $factor),
                'unidad' => $item[0] === 'Personalizado' ? '%' : $definition['unidad'], 'meta' => $item[6], 'fecha_corte' => '2026-09-30',
            ]);
        }

        $reportId = Autoreporte::create(['tenant_id' => $mainId, 'user_id' => $workerId, 'tipo' => 'Sintoma', 'descripcion' => 'Al final de la jornada presento fatiga visual después de varias reuniones seguidas con pantalla.', 'ubicacion' => 'Oficina 402 · Producto', 'fecha_evento' => date('Y-m-d', strtotime('-3 days'))]);
        Autoreporte::updateManagement($reportId, $mainId, 'En revision', 'Recibimos tu reporte. Se revisará la distribución de reuniones y las pausas visuales con tu líder.');
        Autoreporte::create(['tenant_id' => $mainId, 'user_id' => $workerId, 'tipo' => 'Sugerencia', 'descripcion' => 'Propongo habilitar una pausa activa corta los martes y jueves para el equipo de producto.', 'ubicacion' => 'Equipo de producto', 'fecha_evento' => date('Y-m-d', strtotime('-8 days'))]);

        self::createEmergencyExample($mainId, $sstId);
        Database::execute('INSERT INTO auditoria_saas (tenant_id, user_id, accion, entidad, entidad_id, detalles_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)', [$mainId, $sstId, 'Configuración inicial', 'Tenant', $mainId, json_encode($summary, JSON_UNESCAPED_UNICODE), '127.0.0.1']);
    }

    private static function createEmergencyExample(int $tenantId, int $userId): void
    {
        if (PlanEmergencia::forTenant($tenantId) !== []) {
            return;
        }
        $design = FloorPlan::normalize(FloorPlan::encode([
            'format' => 1, 'width' => 1000, 'height' => 650, 'grid' => 20,
            'elements' => [
                ['id' => 'demo_wall_north', 'tool' => 'wall', 'x' => 60, 'y' => 100, 'x2' => 760, 'y2' => 100, 'label' => 'Muro norte', 'color' => '#334155', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_wall_left', 'tool' => 'wall', 'x' => 60, 'y' => 100, 'x2' => 60, 'y2' => 500, 'label' => 'Muro occidental', 'color' => '#334155', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_door', 'tool' => 'door', 'x' => 720, 'y' => 120, 'label' => 'Puerta principal', 'color' => '#334155', 'rotation' => 0, 'stroke' => 8],
                ['id' => 'demo_route', 'tool' => 'route', 'x' => 160, 'y' => 320, 'x2' => 720, 'y2' => 320, 'label' => 'Ruta principal', 'color' => '#237f52', 'rotation' => 0, 'stroke' => 8],
                ['id' => 'demo_exit', 'tool' => 'exit', 'x' => 800, 'y' => 320, 'label' => 'Salida', 'color' => '#15803d', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_assembly', 'tool' => 'assembly', 'x' => 920, 'y' => 320, 'label' => 'Punto de encuentro', 'color' => '#15803d', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_hose', 'tool' => 'iso_f002', 'x' => 180, 'y' => 180, 'label' => 'Manguera', 'color' => '#334155', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_helmet', 'tool' => 'iso_m014', 'x' => 300, 'y' => 180, 'label' => 'Casco obligatorio', 'color' => '#334155', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_obstruct', 'tool' => 'iso_p023', 'x' => 420, 'y' => 180, 'label' => 'No obstruir', 'color' => '#334155', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_electric', 'tool' => 'iso_w012', 'x' => 540, 'y' => 180, 'label' => 'Tablero eléctrico', 'color' => '#334155', 'rotation' => 0, 'stroke' => 10],
                ['id' => 'demo_text', 'tool' => 'text', 'x' => 180, 'y' => 460, 'label' => 'Plano ficticio · verificar dimensiones en sitio', 'color' => '#334155', 'rotation' => 0, 'stroke' => 18],
            ],
        ]));
        Database::transaction(static function () use ($tenantId, $userId, $design): void {
            $planId = PlanEmergencia::create([
                'tenant_id' => $tenantId, 'titulo' => 'Plan de emergencia · sede de demostración',
                'centro_trabajo' => 'Sede principal · datos ficticios', 'direccion' => 'Bogotá D.C. · dirección de ejemplo',
                'alcance' => 'Ejemplo para trabajadores, contratistas y visitantes de todos los turnos.',
                'amenazas_json' => json_encode([['nombre' => 'Incendio', 'probabilidad' => 'Media', 'impacto' => 'Alto', 'controles' => 'Extintores, alarma y recorridos de evacuación']], JSON_UNESCAPED_UNICODE),
                'contactos_json' => json_encode([['nombre' => 'Bomberos', 'tipo' => 'Externo', 'telefono' => '119']], JSON_UNESCAPED_UNICODE),
                'brigada_json' => json_encode([['nombre' => 'Brigadista de demostración', 'rol' => 'Evacuación', 'contacto' => '123']], JSON_UNESCAPED_UNICODE),
                'recursos_json' => json_encode([
                    ['nombre' => 'Extintor multipropósito', 'cantidad' => '2', 'ubicacion' => 'Pasillo principal', 'estado' => 'Disponible', 'inspeccion' => 'Por verificar'],
                    ['nombre' => 'Botiquín', 'cantidad' => '1', 'ubicacion' => 'Recepción', 'estado' => 'Disponible', 'inspeccion' => 'Por verificar'],
                ], JSON_UNESCAPED_UNICODE),
                'rutas_evacuacion' => 'Recorrido principal hacia la salida y la zona segura exterior.',
                'puntos_encuentro' => 'Punto de encuentro exterior indicado en el plano.',
                'procedimientos' => 'Alarmar, evacuar por el recorrido señalado, verificar a las personas y reportar novedades.',
                'plan_rescate' => 'Primeros auxilios y coordinación con apoyo externo.', 'ayuda_mutua' => 'Bomberos y ambulancia.',
                'responsable_plan' => 'Responsable SST de demostración', 'telefono_alarma' => '123',
                'proximo_simulacro' => date('Y-m-d', strtotime('+6 months')), 'fecha_revision' => date('Y-m-d', strtotime('+1 year')),
            ]);
            $documentId = OperationalDocument::createEmergency($planId, $tenantId);
            PlanEmergencia::linkDocument($planId, $tenantId, $documentId);
            PlanoEmergencia::create([
                'tenant_id' => $tenantId, 'plan_id' => $planId, 'nombre' => 'Plano general · datos de demostración',
                'escala' => 'Referencial · verificar en sitio', 'descripcion' => 'Plano ficticio para explorar el editor; no representa un levantamiento real.',
                'datos_json' => FloorPlan::encode($design), 'created_by' => $userId,
            ]);
        });
    }

    private static function createUserIfMissing(?int $tenantId, string $name, string $email, string $role, ?string $license = null): int
    {
        $existing = Database::first('SELECT id FROM users WHERE lower(email) = ?', [text_lower($email)]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return User::create([
            'tenant_id' => $tenantId, 'nombre' => $name, 'email' => $email,
            'password' => password_hash('Normia2026!', PASSWORD_DEFAULT), 'rol' => $role, 'licencia_sst' => $license,
        ]);
    }
}
