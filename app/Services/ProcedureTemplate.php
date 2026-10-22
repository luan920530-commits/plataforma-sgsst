<?php
declare(strict_types=1);

namespace App\Services;

final class ProcedureTemplate
{
    public static function activity(array $tenant, array $activity, string $code): string
    {
        $hazards = self::hazards($activity);
        $hazardSections = '';
        foreach ($hazards as $index => $hazard) {
            $number = $index + 1;
            $hazardSections .= '<h3>5.' . $number . ' ' . e($hazard['proceso']) . ': ' . e($hazard['peligro']) . '</h3>'
                . '<p><strong>Posibles efectos:</strong> ' . e($hazard['efectos']) . '. <strong>Controles orientativos por validar:</strong> ' . e($hazard['medidas_control']) . '</p>';
        }
        $title = 'Procedimiento de trabajo seguro · ' . $activity['descripcion'];
        $body = '<h2>1. Objeto y alcance</h2>'
            . '<p>Definir condiciones seguras para las actividades de <strong>' . e($activity['descripcion']) . '</strong> (CIIU ' . e($activity['codigo_ciuu']) . ') realizadas por ' . $tenant['razonsocial_html'] . ', incluidos trabajadores, contratistas y aprendices bajo su coordinación.</p>'
            . '<h2>2. Referentes y documentos relacionados</h2><ul><li>Matriz IPEVR vigente y evaluación de los puestos de trabajo.</li><li>Plan Anual de Trabajo, programa de capacitación y plan de emergencias.</li><li>Decreto 1072 de 2015 y requisitos específicos aplicables a la tarea.</li><li>Instrucciones de fabricantes, fichas de datos de seguridad y procedimientos de contratistas cuando correspondan.</li></ul>'
            . '<h2>3. Responsabilidades</h2><ul><li><strong>Alta dirección:</strong> asignar recursos y resolver barreras para implementar controles.</li><li><strong>Responsable SG-SST:</strong> verificar la actualización del procedimiento, la formación y las evidencias.</li><li><strong>Líder de la actividad:</strong> planificar, realizar el AST, inspeccionar el frente y detener la tarea ante condiciones no controladas.</li><li><strong>Trabajadores y contratistas:</strong> participar en el análisis, aplicar los controles y reportar desviaciones.</li></ul>'
            . '<h2>4. Secuencia segura</h2><ol><li>Definir la tarea, el lugar, el personal competente, los equipos y los recursos antes de iniciar.</li><li>Realizar el Análisis Seguro de Trabajo con las personas ejecutantes y revisar si se requiere un permiso de trabajo.</li><li>Aplicar controles según jerarquía: eliminación, sustitución, ingeniería, controles administrativos y EPP complementario.</li><li>Inspeccionar herramientas, equipos, señalización y condiciones del entorno. Registrar las desviaciones y no operar equipos en condiciones inseguras.</li><li>Ejecutar la actividad dentro del alcance analizado, manteniendo comunicación y orden del área.</li><li>Detener la tarea ante un peligro grave, cambios significativos o pérdida de controles; informar y reevaluar antes de continuar.</li><li>Cerrar la actividad, retirar residuos y energías según corresponda, entregar el área y conservar los registros.</li></ol>'
            . '<h2>5. Controles específicos de la actividad</h2>' . $hazardSections
            . '<h2>6. Emergencias y registros</h2><p>Suspender la tarea, activar el plan de emergencias y solicitar apoyo por los canales definidos. Conservar AST, inspecciones, capacitaciones, permisos, mantenimientos y evidencias de cierre según aplique. Este procedimiento no reemplaza evaluaciones técnicas ni permisos para tareas de alto riesgo.</p>';
        return DocumentTemplate::layout($tenant, $code, $title, 'Hacer', $body);
    }

    public static function activityFormat(array $tenant, array $activity, string $code): string
    {
        $hazards = self::hazards($activity);
        $rows = '';
        foreach ($hazards as $hazard) {
            $rows .= '<tr><td>' . e($hazard['proceso'] . ' · ' . $hazard['peligro']) . '</td><td>' . e($hazard['medidas_control']) . '</td><td>☐ Sí ☐ No ☐ N.A.</td><td><br></td></tr>';
        }
        $title = 'Formato de verificación de actividad · CIIU ' . $activity['codigo_ciuu'];
        $body = '<h2>1. Identificación</h2><table><tbody><tr><th>Fecha y turno</th><td><br></td><th>Centro / área</th><td><br></td></tr><tr><th>Actividad y tarea</th><td><br></td><th>Líder</th><td><br></td></tr><tr><th>Personas ejecutantes</th><td colspan="3"><br></td></tr></tbody></table>'
            . '<h2>2. Verificación específica</h2><table><thead><tr><th>Peligro / proceso</th><th>Control a verificar</th><th>Resultado</th><th>Observaciones y acción</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<h2>3. Condiciones transversales</h2><table><tbody><tr><td>AST realizado y permisos identificados</td><td>☐ Sí ☐ No ☐ N.A.</td><td>Observación:<br></td></tr><tr><td>Personal competente, inducción y EPP verificados</td><td>☐ Sí ☐ No ☐ N.A.</td><td>Observación:<br></td></tr><tr><td>Equipos, herramientas y guardas inspeccionados</td><td>☐ Sí ☐ No ☐ N.A.</td><td>Observación:<br></td></tr><tr><td>Rutas, alarmas y recursos de emergencia disponibles</td><td>☐ Sí ☐ No ☐ N.A.</td><td>Observación:<br></td></tr></tbody></table>'
            . '<h2>4. Cierre</h2><p>Desviaciones, responsables y fechas de corrección: ______________________________________________________________________________</p><p>☐ Actividad autorizada dentro de las condiciones verificadas &nbsp; ☐ Actividad suspendida hasta implementar controles</p><p>Firma del líder: __________________________ &nbsp; Firma responsable SG-SST, cuando aplique: __________________________</p>';
        return DocumentTemplate::layout($tenant, $code, $title, 'Hacer', $body);
    }

    public static function ast(array $tenant, string $code): string
    {
        $rows = str_repeat('<tr><td><br><br></td><td><br></td><td><br></td><td><br></td><td><br></td></tr>', 6);
        $body = '<h2>1. Información de la tarea</h2><table><tbody><tr><th>Tarea / actividad</th><td><br></td><th>Fecha</th><td><br></td></tr><tr><th>Lugar</th><td><br></td><th>Líder</th><td><br></td></tr><tr><th>Ejecutantes</th><td colspan="3"><br></td></tr><tr><th>Procedimiento relacionado</th><td><br></td><th>¿Requiere permiso?</th><td>☐ No ☐ Sí: __________</td></tr></tbody></table>'
            . '<h2>2. Análisis participativo</h2><table><thead><tr><th>Paso de la tarea</th><th>Peligros</th><th>Consecuencias</th><th>Controles según jerarquía</th><th>Responsable</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<h2>3. Verificación previa y cierre</h2><p>☐ Competencia y formación verificadas &nbsp; ☐ Equipos inspeccionados &nbsp; ☐ EPP complementario &nbsp; ☐ Emergencias y comunicación revisadas &nbsp; ☐ Cambios reevaluados</p><p>Condiciones que suspenden la tarea: ______________________________________________________________________________</p><p>Participantes y firmas: ________________________________________________________________________________________</p><p>Cierre, novedades y entrega del área: _____________________________________________________________________________</p>';
        return DocumentTemplate::layout($tenant, $code, 'Formato de Análisis Seguro de Trabajo (AST)', 'Hacer', $body);
    }

    public static function heights(array $tenant, string $code): string
    {
        $body = '<h2>1. Objeto y aplicabilidad</h2><p>Establecer controles para planificar, autorizar, ejecutar y cerrar trabajos en alturas realizados por ' . $tenant['razonsocial_html'] . ' o contratistas. Aplica cuando la evaluación identifique riesgo de caída de personas u objetos.</p>'
            . '<h2>2. Referentes</h2><ul><li>Resolución 4272 de 2021 y sus modificaciones o requisitos vigentes aplicables.</li><li>Decreto 1072 de 2015, matriz IPEVR, plan de emergencias y procedimiento de contratistas.</li><li>Instrucciones y certificaciones de fabricantes de equipos y sistemas de protección contra caídas.</li></ul>'
            . '<h2>3. Requisitos antes del trabajo</h2><ol><li>Definir coordinador, trabajadores autorizados, ayudante y responsable de activar la emergencia, según corresponda.</li><li>Verificar aptitud, formación vigente y competencias exigidas para cada rol; conservar soportes.</li><li>Realizar AST y obtener permiso de trabajo en alturas vigente antes de iniciar.</li><li>Evaluar acceso, superficie, estructura, puntos de anclaje, caída de objetos, clima, energías y tránsito.</li><li>Inspeccionar arneses, conectores, líneas de vida, andamios, escaleras y equipos; retirar de servicio elementos no conformes.</li><li>Delimitar el área y establecer un plan de rescate con recursos disponibles y personal entrenado.</li></ol>'
            . '<h2>4. Ejecución</h2><ul><li>Mantener protección colectiva como prioridad y conexión al sistema definido cuando se requiera protección personal.</li><li>No improvisar anclajes ni modificar equipos. Controlar herramientas y materiales para evitar caída de objetos.</li><li>Suspender ante condiciones climáticas, estructurales, médicas u operativas que comprometan el control.</li><li>Reevaluar el permiso ante cambios significativos de tarea, personal, equipo o entorno.</li></ul>'
            . '<h2>5. Rescate y cierre</h2><p>Activar la emergencia y el rescate sin exponer a otras personas. Al terminar, cerrar el permiso, inspeccionar el área, retirar equipos y registrar novedades. El permiso permanece en el sitio y se conserva según el sistema documental.</p>';
        return DocumentTemplate::layout($tenant, $code, 'Procedimiento para trabajo en alturas', 'Hacer', $body);
    }

    public static function heightsFormat(array $tenant, string $code): string
    {
        $checks = ['AST y permiso vigente en el sitio', 'Coordinador y trabajadores autorizados identificados', 'Formación y aptitud verificadas', 'Accesos, andamios y superficies inspeccionados', 'Puntos de anclaje y sistema de protección definidos', 'Arnés, conectores y líneas de vida inspeccionados', 'Área delimitada y caída de objetos controlada', 'Plan de rescate, recursos y responsable de emergencia disponibles', 'Condiciones climáticas y entorno evaluados'];
        $body = '<h2>1. Datos de la verificación</h2><p>Permiso No. __________ &nbsp; Fecha: __________ &nbsp; Lugar: ____________________ &nbsp; Altura / nivel: __________</p><p>Tarea: __________________________________________ &nbsp; Coordinador: __________________________</p>'
            . '<h2>2. Lista de chequeo</h2>' . self::checklist($checks)
            . '<h2>3. Equipos y novedades</h2><table><thead><tr><th>Equipo / identificación</th><th>Inspección</th><th>Novedad / retiro de servicio</th></tr></thead><tbody>' . str_repeat('<tr><td><br></td><td><br></td><td><br></td></tr>', 4) . '</tbody></table>'
            . '<p>Plan de rescate y medios disponibles: ____________________________________________________________________</p><p>☐ Verificación conforme &nbsp; ☐ No iniciar hasta corregir las novedades</p><p>Coordinador / firma: __________________________ &nbsp; Responsable SG-SST / firma: __________________________</p>';
        return DocumentTemplate::layout($tenant, $code, 'Formato de verificación previa para trabajo en alturas', 'Hacer', $body);
    }

    public static function confined(array $tenant, string $code): string
    {
        $body = '<h2>1. Objeto y alcance</h2><p>Controlar la identificación, evaluación, autorización, ingreso, permanencia, salida y cierre de trabajos en espacios confinados de ' . $tenant['razonsocial_html'] . ', incluidos contratistas.</p>'
            . '<h2>2. Referentes</h2><ul><li>Resolución 491 de 2020 y disposiciones vigentes aplicables.</li><li>Fichas de datos de seguridad, evaluación atmosférica, matriz IPEVR y plan de emergencias.</li><li>Instrucciones de fabricantes para detección, ventilación, aislamiento y rescate.</li></ul>'
            . '<h2>3. Condiciones previas</h2><ol><li>Identificar el espacio, sus peligros y la tarea; evaluar alternativas para evitar el ingreso.</li><li>Designar responsables, vigilante, ejecutantes y equipo de emergencia con formación y funciones verificadas.</li><li>Realizar AST y permiso escrito vigente. Mantenerlo en el sitio durante la ejecución.</li><li>Aislar, bloquear, etiquetar, limpiar, purgar o ventilar según la evaluación; verificar energías y conexiones.</li><li>Realizar mediciones atmosféricas por persona competente con equipos adecuados y registrar resultados, hora, punto y criterios de aceptación.</li><li>Definir comunicación, ventilación, iluminación, acceso, EPP, monitoreo y rescate. No ingresar si los controles no son eficaces.</li></ol>'
            . '<h2>4. Durante el ingreso</h2><ul><li>El vigilante permanece en su función, mantiene comunicación y control de ingreso; no abandona el puesto para intentar rescates sin protección.</li><li>Monitorear la atmósfera y las condiciones según el riesgo. Ante alarma, pérdida de comunicación, síntomas o cambio de condiciones, evacuar de inmediato.</li><li>No modificar el alcance ni reingresar después de una emergencia sin nueva evaluación y autorización.</li></ul>'
            . '<h2>5. Emergencia y cierre</h2><p>Activar el rescate con recursos y personal preparados; evitar el ingreso improvisado de rescatistas. Al finalizar, verificar la salida de todas las personas y equipos, cerrar el espacio y el permiso, registrar observaciones y conservar anexos y FDS cuando correspondan.</p>';
        return DocumentTemplate::layout($tenant, $code, 'Procedimiento para trabajo en espacios confinados', 'Hacer', $body);
    }

    public static function confinedFormat(array $tenant, string $code): string
    {
        $checks = ['Espacio, tarea y peligros identificados', 'AST y permiso vigente en el sitio', 'Responsables, vigilante y ejecutantes designados', 'Aislamiento, bloqueo y etiquetado verificados', 'Limpieza, purga y ventilación según evaluación', 'Equipo de medición verificado y criterios definidos', 'Comunicación, iluminación y acceso disponibles', 'EPP y equipos de rescate inspeccionados', 'Plan de emergencia y responsables confirmados'];
        $body = '<h2>1. Identificación del ingreso</h2><p>Permiso No. __________ &nbsp; Espacio: ____________________ &nbsp; Fecha: __________ &nbsp; Vigencia: __________</p><p>Tarea: __________________________________________ &nbsp; Vigilante: __________________________</p>'
            . '<h2>2. Verificación previa</h2>' . self::checklist($checks)
            . '<h2>3. Registro de mediciones atmosféricas</h2><table><thead><tr><th>Fecha / hora</th><th>Punto</th><th>Parámetro</th><th>Resultado / unidad</th><th>Criterio de aceptación</th><th>Equipo / responsable</th></tr></thead><tbody>' . str_repeat('<tr><td><br></td><td><br></td><td><br></td><td><br></td><td><br></td><td><br></td></tr>', 5) . '</tbody></table>'
            . '<p>Continuidad y frecuencia de monitoreo definidas: ______________________________________________________________</p><p>☐ Ingreso autorizado dentro de las condiciones registradas &nbsp; ☐ Ingreso no autorizado</p><p>Responsable / firma: __________________________ &nbsp; Vigilante / firma: __________________________</p><p>Cierre, salida de personas y equipos, novedades: ________________________________________________________________</p>';
        return DocumentTemplate::layout($tenant, $code, 'Formato de verificación y medición para espacios confinados', 'Hacer', $body);
    }

    private static function checklist(array $items): string
    {
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td>' . e($item) . '</td><td>☐ Sí ☐ No ☐ N.A.</td><td><br></td></tr>';
        }
        return '<table><thead><tr><th>Verificación</th><th>Resultado</th><th>Observaciones / acciones</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private static function hazards(array $activity): array
    {
        $hazards = json_decode($activity['matriz_ipevr_base'] ?? '[]', true);
        if (!is_array($hazards) || $hazards === []) {
            return [[
                'proceso' => 'Actividad principal', 'peligro' => 'Peligros por caracterizar en la matriz IPEVR',
                'efectos' => 'Por determinar con participación de los trabajadores',
                'medidas_control' => 'Realizar evaluación específica y definir controles antes de ejecutar la tarea.',
            ]];
        }
        return $hazards;
    }
}
