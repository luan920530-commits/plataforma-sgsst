<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/** Formularios sanitarios configurables; cada campo se conserva completo en datos_json. */
final class SanitaryCatalog
{
    public static function types(): array
    {
        return [
            'limpieza' => self::type('Limpieza y desinfección', 'clipboard', 'Programación y ejecución por áreas, productos, diluciones y verificación.', [
                self::field('area', 'Área, superficie o equipo', 'text', true),
                self::field('frecuencia', 'Frecuencia definida'),
                self::field('procedimiento', 'Procedimiento aplicado', 'textarea'),
                self::field('productos', 'Productos utilizados'),
                self::field('dilucion', 'Dilución y dosificación según etiqueta/fabricante'),
                self::field('epp', 'EPP y precauciones'),
                self::field('resultado', 'Resultado y verificación en sitio', 'textarea'),
            ]),
            'residuos' => self::type('Residuos y manifiestos', 'phva', 'Inventario, separación, almacenamiento, salidas, gestores y soportes de disposición.', [
                self::field('residuo', 'Residuo y clasificación', 'text', true),
                self::field('cantidad', 'Cantidad'),
                self::field('unidad', 'Unidad'),
                self::field('peligrosidad', 'Características o peligrosidad'),
                self::field('recipiente', 'Recipiente, color y ubicación'),
                self::field('gestor', 'Gestor o transportista'),
                self::field('manifiesto', 'Manifiesto, remisión, acta o radicado'),
                self::field('destino', 'Tratamiento o disposición final', 'textarea'),
            ]),
            'plagas' => self::type('Manejo integral de plagas', 'shield', 'Prevención, monitoreo, control, proveedores y concepto sanitario cuando aplique.', [
                self::field('zona', 'Zona inspeccionada o intervenida', 'text', true),
                self::field('hallazgos', 'Hallazgos y señales de presencia', 'textarea'),
                self::field('metodo', 'Medida preventiva o método de control', 'textarea'),
                self::field('producto', 'Producto utilizado, si aplica'),
                self::field('proveedor', 'Proveedor o ejecutor'),
                self::field('concepto_sanitario', 'Concepto sanitario y vigencia, si aplica'),
                self::field('proxima_fecha', 'Próxima inspección', 'date'),
            ]),
            'quimicos' => self::type('Químicos, insumos y SDS', 'alert', 'Inventario real, hojas de seguridad, compatibilidad, almacenamiento, etiquetado y EPP.', [
                self::field('producto', 'Nombre genérico y comercial', 'text', true),
                self::field('fabricante', 'Fabricante o proveedor'),
                self::field('uso', 'Uso y proceso'),
                self::field('cantidad', 'Cantidad existente'),
                self::field('unidad', 'Unidad'),
                self::field('estado_fisico', 'Estado físico'),
                self::field('ubicacion', 'Ubicación y condiciones de almacenamiento'),
                self::field('sds_fecha', 'Fecha o revisión de la SDS', 'date'),
                self::field('compatibilidad', 'Compatibilidad e incompatibilidades', 'textarea'),
                self::field('epp', 'EPP y controles según SDS', 'textarea'),
            ]),
            'mantenimiento' => self::type('Mantenimiento locativo y equipos', 'settings', 'Órdenes e informes de mantenimiento locativo, eléctrico, maquinaria y equipos.', [
                self::field('elemento', 'Instalación, máquina o equipo', 'text', true),
                self::field('sistema', 'Sistema', 'select', false, ['Locativo', 'Eléctrico', 'Maquinaria', 'Equipo de trabajo', 'Otro']),
                self::field('tipo_mantenimiento', 'Tipo', 'select', false, ['Inspección', 'Preventivo', 'Correctivo', 'Calibración', 'Otro']),
                self::field('trabajo', 'Trabajo realizado o requerido', 'textarea'),
                self::field('ejecutor', 'Ejecutor y competencia verificada'),
                self::field('repuestos', 'Repuestos, materiales o referencias'),
                self::field('resultado', 'Resultado y condición de entrega', 'textarea'),
                self::field('proxima_fecha', 'Próxima revisión', 'date'),
            ]),
            'agua' => self::type('Tanque y agua potable', 'database', 'Capacidad, inspección, lavado y desinfección del sistema de almacenamiento.', [
                self::field('tanque', 'Identificación del tanque o sistema', 'text', true),
                self::field('capacidad', 'Capacidad en litros', 'number'),
                self::field('actividad', 'Actividad', 'select', false, ['Inspección', 'Lavado y desinfección', 'Reparación', 'Verificación de capacidad', 'Otra']),
                self::field('metodo', 'Método, producto y dosificación', 'textarea'),
                self::field('ejecutor', 'Responsable o proveedor'),
                self::field('resultado', 'Resultado y condiciones finales', 'textarea'),
                self::field('proxima_fecha', 'Próxima ejecución', 'date'),
            ]),
            'derrames' => self::type('Derrames y contención', 'alert', 'Escenarios, kits, procedimientos, EPP, disposición y preparación de la respuesta.', [
                self::field('material', 'Sustancia o escenario', 'text', true),
                self::field('ubicacion', 'Ubicación del kit o contención'),
                self::field('elementos', 'Elementos y cantidades disponibles', 'textarea'),
                self::field('procedimiento', 'Secuencia de contención y limpieza', 'textarea'),
                self::field('epp', 'EPP requerido'),
                self::field('residuos', 'Gestión del residuo resultante', 'textarea'),
                self::field('prueba', 'Prueba, inspección o reposición realizada', 'textarea'),
            ]),
            'recursos' => self::type('Recursos de emergencia', 'shield', 'Extintores propios, alarmas, botiquines y demás recursos; no usa inventario de clientes.', [
                self::field('recurso', 'Recurso', 'select', true, ['Extintor propio', 'Alarma', 'Botiquín', 'Kit de derrames', 'Otro']),
                self::field('identificacion', 'ID, serial o referencia'),
                self::field('ubicacion', 'Ubicación real'),
                self::field('cantidad', 'Cantidad', 'number'),
                self::field('vigencia', 'Vigencia, carga o vencimiento', 'date'),
                self::field('estado_fisico', 'Estado, acceso y señalización', 'textarea'),
                self::field('verificacion', 'Inspección, prueba o mantenimiento', 'textarea'),
            ]),
            'senalizacion' => self::type('Señalización y demarcación', 'grid', 'Levantamiento, instalación e inspección de señales, salidas, áreas y circulación.', [
                self::field('senal', 'Señal, pictograma o demarcación', 'text', true),
                self::field('ubicacion', 'Ubicación real verificada'),
                self::field('cantidad', 'Cantidad', 'number'),
                self::field('referencia', 'Referencia técnica o normativa aplicable'),
                self::field('instalacion', 'Instalación, dimensiones y visibilidad', 'textarea'),
                self::field('inspeccion', 'Resultado de inspección y recorrido libre', 'textarea'),
            ]),
            'productos' => self::type('Productos terminados', 'briefcase', 'Listado de productos o existencias asociado a la actividad real del establecimiento.', [
                self::field('producto', 'Nombre genérico y comercial', 'text', true),
                self::field('lote', 'Lote o referencia'),
                self::field('cantidad', 'Cantidad', 'number'),
                self::field('unidad', 'Unidad'),
                self::field('estado', 'Estado o condición'),
                self::field('alcance', 'Producido, comercializado o en servicio'),
                self::field('conciliacion', 'Conciliación con inventario y observaciones', 'textarea'),
            ]),
            'validacion' => self::type('Datos por validar', 'search', 'Discrepancias entre acta, datos previos y soportes; no unifica datos automáticamente.', [
                self::field('tema', 'Dato o tema', 'text', true),
                self::field('dato_acta', 'Dato registrado en el acta', 'textarea'),
                self::field('dato_previo', 'Dato previo o declarado', 'textarea'),
                self::field('accion', 'Acción de conciliación', 'textarea'),
                self::field('soporte', 'Soporte requerido'),
            ]),
            'seguimiento' => self::type('Seguimiento con la ETS', 'mail', 'Comunicaciones, radicados, respuestas, compromisos y próximas acciones con la autoridad.', [
                self::field('entidad', 'Entidad y contacto', 'text', true),
                self::field('canal', 'Canal oficial utilizado'),
                self::field('radicado', 'Radicado o identificador de la gestión'),
                self::field('gestion', 'Gestión, respuesta o compromiso', 'textarea'),
                self::field('proxima_accion', 'Próxima acción', 'textarea'),
                self::field('fecha_proxima', 'Fecha de próxima acción', 'date'),
            ]),
        ];
    }

    public static function definition(string $type): array
    {
        $types = self::types();
        if (!isset($types[$type])) {
            throw new InvalidArgumentException('El tipo de registro sanitario no existe.');
        }
        return $types[$type];
    }

    private static function type(string $label, string $icon, string $description, array $fields): array
    {
        return ['label' => $label, 'icon' => $icon, 'description' => $description, 'fields' => $fields];
    }

    private static function field(string $key, string $label, string $type = 'text', bool $required = false, array $options = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required, 'options' => $options];
    }
}
