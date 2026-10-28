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
        $body = '<h2>1. Objeto y aplicabilidad</h2><p>Establecer el programa de prevención y protección contra caídas para planificar, autorizar, ejecutar y cerrar trabajos en alturas realizados por ' . $tenant['razonsocial_html'] . ' o contratistas. Aplica cuando la evaluación identifique riesgo de caída de personas u objetos; no autoriza una tarea sin permiso y verificación en sitio.</p>'
            . '<h2>2. Referentes</h2><ul><li>Resolución 4272 de 2021 y sus modificaciones o requisitos vigentes aplicables.</li><li>Decreto 1072 de 2015, matriz IPEVR, plan de emergencias y procedimiento de contratistas.</li><li>Instrucciones y certificaciones de fabricantes de equipos y sistemas de protección contra caídas.</li></ul>'
            . '<h2>3. Gestión del programa y responsabilidades</h2><table><thead><tr><th>Rol</th><th>Responsabilidad mínima</th><th>Persona / soporte por asignar</th></tr></thead><tbody><tr><td>Alta dirección</td><td>Recursos, programa, contratistas y verificación de resultados</td><td><br></td></tr><tr><td>Coordinador de trabajo en alturas</td><td>Planificación, AST, permiso, controles, suspensión y cierre</td><td><br></td></tr><tr><td>Trabajadores autorizados</td><td>Aplicar formación, inspeccionar equipos, informar cambios y detener la tarea insegura</td><td><br></td></tr><tr><td>Responsable de emergencia y rescate</td><td>Plan, recursos, prácticas, activación y coordinación del rescate</td><td><br></td></tr></tbody></table>'
            . '<p>El programa debe incluir identificación de tareas, evaluación de riesgos, competencias, salud ocupacional, equipos, inspecciones, permisos, rescate, indicadores y mejora. La empresa define la frecuencia de revisión y las competencias según la norma y la criticidad real.</p>'
            . '<h2>4. Requisitos antes del trabajo</h2><ol><li>Definir coordinador, trabajadores autorizados, ayudante y responsable de activar la emergencia, según corresponda.</li><li>Verificar formación vigente, competencias y evaluaciones de salud ocupacional exigidas para cada rol; conservar los conceptos y soportes bajo el acceso que corresponda.</li><li>Realizar AST con los ejecutantes y obtener permiso de trabajo en alturas vigente antes de iniciar.</li><li>Evaluar acceso, superficie, estructura, puntos de anclaje, distancia y espacio libre de caída, caída de objetos, clima, energías y tránsito.</li><li>Priorizar protección colectiva. Seleccionar el sistema personal y los anclajes con criterio técnico, compatibilidad e instrucciones del fabricante.</li><li>Inspeccionar arneses, conectores, líneas de vida, andamios, escaleras y equipos; registrar identificación y retirar de servicio elementos no conformes.</li><li>Delimitar el área y establecer un plan de rescate específico con recursos disponibles, comunicación y personal entrenado.</li></ol>'
            . '<h2>5. Ejecución y cambios</h2><ul><li>Mantener protección colectiva como prioridad y conexión al sistema definido cuando se requiera protección personal.</li><li>No improvisar anclajes ni modificar equipos. Controlar herramientas y materiales para evitar caída de objetos.</li><li>Suspender ante condiciones climáticas, estructurales, médicas u operativas que comprometan el control.</li><li>Reevaluar el permiso ante cambios de tarea, personal, equipo, frente o entorno; una autorización anterior no cubre automáticamente el nuevo escenario.</li></ul>'
            . '<h2>6. Rescate, cierre y verificación del programa</h2><p>Activar la emergencia y el rescate sin exponer a otras personas ni improvisar ingresos. Al terminar, verificar personas y materiales, cerrar el permiso, inspeccionar el área, retirar equipos y registrar novedades. Practicar el rescate según el programa, analizar inspecciones e incidentes y revisar la eficacia de los controles. El permiso permanece en el sitio y se conserva según el sistema documental.</p>';
        return DocumentTemplate::layout($tenant, $code, 'Procedimiento para trabajo en alturas', 'Hacer', $body);
    }

    public static function heightsFormat(array $tenant, string $code): string
    {
        $checks = ['AST y permiso vigente en el sitio', 'Coordinador y trabajadores autorizados identificados', 'Formación y aptitud verificadas', 'Accesos, andamios y superficies inspeccionados', 'Puntos de anclaje y sistema de protección definidos', 'Arnés, conectores y líneas de vida inspeccionados', 'Área delimitada y caída de objetos controlada', 'Plan de rescate, recursos y responsable de emergencia disponibles', 'Condiciones climáticas y entorno evaluados'];
        $body = '<h2>1. Datos de la verificación</h2><p>Permiso No. __________ &nbsp; Fecha y turno: __________ &nbsp; Lugar: ____________________ &nbsp; Altura / nivel: __________</p><p>Tarea: __________________________________________ &nbsp; Coordinador: __________________________</p>'
            . '<h2>2. Roles y competencias verificadas</h2><table><thead><tr><th>Nombre / rol</th><th>Formación y vigencia / soporte</th><th>Evaluación de salud según aplique</th><th>Autorización</th></tr></thead><tbody>' . str_repeat('<tr><td><br></td><td><br></td><td><br></td><td><br></td></tr>', 4) . '</tbody></table><p>No registre diagnósticos; anexe sólo los conceptos o soportes que sean necesarios y con acceso adecuado.</p>'
            . '<h2>3. Lista de chequeo</h2>' . self::checklist($checks)
            . '<h2>4. Sistemas, equipos y anclajes</h2><table><thead><tr><th>Equipo / identificación</th><th>Sistema / anclaje / compatibilidad</th><th>Inspección y fecha</th><th>Novedad / retiro de servicio</th></tr></thead><tbody>' . str_repeat('<tr><td><br></td><td><br></td><td><br></td><td><br></td></tr>', 4) . '</tbody></table>'
            . '<h2>5. Rescate y autorización</h2><table><tbody><tr><th>Escenario y método de rescate</th><td><br></td><th>Responsable y medios disponibles</th><td><br></td></tr><tr><th>Comunicación / ayuda externa</th><td><br></td><th>Espacio libre y caída de objetos</th><td><br></td></tr></tbody></table><p>☐ Verificación conforme &nbsp; ☐ No iniciar hasta corregir las novedades</p><p>Coordinador / firma: __________________________ &nbsp; Responsable SG-SST / firma, cuando aplique: __________________________</p>'
            . '<h2>6. Cierre</h2><p>Hora de finalización: __________ &nbsp; ☐ Personas y materiales retirados &nbsp; ☐ Área entregada &nbsp; ☐ Permiso cerrado</p><p>Novedades, incidentes, acciones y responsables: _________________________________________________________________</p>';
        return DocumentTemplate::layout($tenant, $code, 'Formato de verificación previa para trabajo en alturas', 'Hacer', $body);
    }

    public static function confined(array $tenant, string $code): string
    {
        $body = '<h2>1. Objeto y alcance</h2><p>Establecer el programa de gestión para identificación, evaluación, autorización, ingreso, permanencia, salida y cierre de trabajos en espacios confinados de ' . $tenant['razonsocial_html'] . ', incluidos contratistas. Ningún espacio se considera seguro por su nombre o por una medición anterior.</p>'
            . '<h2>2. Referentes</h2><ul><li>Resolución 0491 de 2020, sus modificaciones —incluida la Resolución 2605 de 2020 cuando corresponda— y disposiciones vigentes aplicables.</li><li>Fichas de datos de seguridad, evaluación atmosférica, matriz IPEVR y plan de emergencias.</li><li>Instrucciones de fabricantes para detección, ventilación, aislamiento y rescate.</li></ul>'
            . '<h2>3. Identificación, roles y competencias</h2><p>Levantar un inventario por centro con identificación del espacio, configuración, accesos, contenido, energías, tareas y peligros. Señalizar y controlar accesos. Evaluar alternativas para ejecutar la tarea sin ingreso.</p><table><thead><tr><th>Rol</th><th>Función que debe quedar asignada</th><th>Persona / soporte por validar</th></tr></thead><tbody><tr><td>Responsable o supervisor del ingreso</td><td>Evaluar, coordinar, autorizar, suspender y cerrar el permiso</td><td><br></td></tr><tr><td>Vigilante</td><td>Comunicación, conteo, condiciones externas y activación de emergencia; no abandona su función</td><td><br></td></tr><tr><td>Entrantes autorizados</td><td>Aplicar controles, monitorear condiciones, comunicar novedades y evacuar</td><td><br></td></tr><tr><td>Equipo de emergencia y rescate</td><td>Plan específico, disponibilidad, formación, equipos y prácticas</td><td><br></td></tr></tbody></table><p>Verificar formación, competencias y evaluaciones de salud que resulten aplicables a cada rol, sin registrar diagnósticos en el permiso.</p>'
            . '<h2>4. Condiciones previas al ingreso</h2><ol><li>Realizar AST y permiso escrito con vigencia definida para la tarea y el turno. Mantenerlo en el sitio.</li><li>Aislar, bloquear, etiquetar, limpiar, purgar o ventilar según la evaluación; verificar energías, conexiones, mecanismos de atrapamiento e inundación.</li><li>Definir los parámetros y criterios de aceptación con personal competente antes de medir. Registrar oxígeno, gases, vapores u otros agentes que correspondan, sin copiar límites genéricos.</li><li>Realizar mediciones iniciales por persona competente con equipos adecuados, verificados y calibrados según corresponda; registrar hora, punto, resultado, unidad, equipo y responsable.</li><li>Definir monitoreo continuo o periódico según el riesgo, ventilación, iluminación, comunicación, acceso, EPP y rescate. No ingresar si los controles no son eficaces.</li></ol>'
            . '<h2>5. Durante el ingreso</h2><ul><li>El vigilante permanece en su función, mantiene comunicación y control de ingreso y salida; no abandona el puesto para intentar rescates sin protección.</li><li>Monitorear la atmósfera y las condiciones según el riesgo. Ante alarma, pérdida de comunicación o ventilación, síntomas o cambio de condiciones, evacuar de inmediato.</li><li>Controlar herramientas, fuentes de ignición, trabajos en caliente y otros permisos concurrentes. No modificar el alcance ni reingresar después de una emergencia sin nueva evaluación y autorización.</li></ul>'
            . '<h2>6. Emergencia, rescate y cierre</h2><p>Activar el rescate con recursos y personal preparados; evitar el ingreso improvisado de rescatistas. Confirmar previamente disponibilidad y tiempos de ayuda externa cuando se utilice. Al finalizar, verificar la salida de todas las personas y equipos, restablecer el espacio de forma segura, cerrar el permiso, registrar observaciones y conservar anexos, mediciones y FDS cuando correspondan.</p>'
            . '<h2>7. Verificación del programa</h2><p>Revisar permisos, mediciones, incidentes, inspecciones, formación y ejercicios de rescate. Actualizar el inventario y la evaluación cuando cambien el espacio, su contenido, la tarea o los controles.</p>';
        return DocumentTemplate::layout($tenant, $code, 'Procedimiento para trabajo en espacios confinados', 'Hacer', $body);
    }

    public static function confinedFormat(array $tenant, string $code): string
    {
        $checks = ['Espacio, tarea y peligros identificados', 'AST y permiso vigente en el sitio', 'Responsables, vigilante y ejecutantes designados', 'Aislamiento, bloqueo y etiquetado verificados', 'Limpieza, purga y ventilación según evaluación', 'Equipo de medición verificado y criterios definidos', 'Comunicación, iluminación y acceso disponibles', 'EPP y equipos de rescate inspeccionados', 'Plan de emergencia y responsables confirmados'];
        $body = '<h2>1. Identificación del ingreso</h2><p>Permiso No. __________ &nbsp; Espacio e ID de inventario: ____________________ &nbsp; Fecha: __________ &nbsp; Vigencia: __________</p><p>Tarea: __________________________________________ &nbsp; Responsable del ingreso: __________________________ &nbsp; Vigilante: __________________________</p><p>Contenido previo, accesos, energías y peligros: _________________________________________________________________</p>'
            . '<h2>2. Roles y competencias verificadas</h2><table><thead><tr><th>Nombre / rol</th><th>Formación y competencia / soporte</th><th>Equipo asignado</th><th>Firma</th></tr></thead><tbody>' . str_repeat('<tr><td><br></td><td><br></td><td><br></td><td><br></td></tr>', 4) . '</tbody></table>'
            . '<h2>3. Verificación previa</h2>' . self::checklist($checks)
            . '<h2>4. Registro de mediciones atmosféricas</h2><p>Parámetros y criterios de aceptación definidos por: __________________________ &nbsp; Equipo / serial / verificación o calibración: __________________________</p><table><thead><tr><th>Fecha / hora</th><th>Punto</th><th>Parámetro</th><th>Resultado / unidad</th><th>Criterio de aceptación</th><th>Responsable</th></tr></thead><tbody>' . str_repeat('<tr><td><br></td><td><br></td><td><br></td><td><br></td><td><br></td><td><br></td></tr>', 5) . '</tbody></table><p>Agregue una fila por cada punto y lectura requerida. No escriba resultados antes de medir.</p>'
            . '<h2>5. Control de ingreso y salida</h2><table><thead><tr><th>Persona</th><th>Hora de ingreso</th><th>Hora de salida</th><th>Novedad / comunicación</th></tr></thead><tbody>' . str_repeat('<tr><td><br></td><td><br></td><td><br></td><td><br></td></tr>', 4) . '</tbody></table>'
            . '<p>Continuidad y frecuencia de monitoreo definidas: ______________________________________________________________</p><p>☐ Ingreso autorizado dentro de las condiciones registradas &nbsp; ☐ Ingreso no autorizado</p><p>Responsable / firma: __________________________ &nbsp; Vigilante / firma: __________________________</p>'
            . '<h2>6. Rescate y cierre</h2><p>Método, equipo y responsables de rescate disponibles: ______________________________________________________________</p><p>Hora de cierre: __________ &nbsp; ☐ Todas las personas y equipos salieron &nbsp; ☐ Espacio restablecido &nbsp; ☐ Permiso cerrado</p><p>Novedades, acciones y anexos: __________________________________________________________________________________</p>';
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
