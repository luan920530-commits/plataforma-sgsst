<?php
declare(strict_types=1);

namespace App\Services;

/** Renderiza programas y formatos editables; nunca precarga ejecuciones, firmas o resultados. */
final class StarterPackageTemplate
{
    public static function render(array $tenant, array $activity, array $document, array $context): string
    {
        $body = match ($document['template'] ?? '') {
            'start_guide' => self::startGuide($tenant, $activity, $context),
            'system_manual' => self::systemManual($tenant, $activity, $context),
            'coverage' => self::coverage($tenant, $context),
            'convivencia' => self::convivencia($tenant),
            default => self::standardBody($tenant, $activity, $document),
        };
        $body .= self::references($document['references'] ?? [], $context['references']);
        if (!empty($document['warning'])) {
            $body .= '<h2>Control de uso y reserva</h2><p><strong>' . e($document['warning']) . '</strong></p>';
        }
        return DocumentTemplate::layout($tenant, $document['code'], $document['name'], $document['phase'], $body);
    }

    private static function standardBody(array $tenant, array $activity, array $document): string
    {
        $body = self::context($tenant, $activity, $document);
        if (isset($document['form'])) {
            return $body . self::form($document['form']);
        }
        $body .= '<h2>1. Objetivo</h2><p>' . e($document['objective']) . '</p>'
            . '<h2>2. Alcance y responsabilidades</h2>'
            . '<p>Aplica a los procesos, cargos, turnos, centros y contratistas donde se confirme el peligro o la obligación. La alta dirección asigna recursos; el responsable del SG-SST coordina; los líderes implementan; trabajadores y contratistas participan, reportan desviaciones y verifican la eficacia de los controles.</p>'
            . '<h2>3. Procedimiento</h2><ol>' . self::list($document['steps'], 'li') . '</ol>'
            . '<h2>4. Controles críticos</h2><ul>' . self::list($document['controls'], 'li') . '</ul>'
            . '<h2>5. Registros y evidencias por producir</h2><ul>' . self::list($document['records'], 'li') . '</ul>'
            . '<h2>6. Seguimiento y cierre</h2><p>Defina responsable, frecuencia e indicador para cada acción. Verifique en la operación que el control funciona, gestione las desviaciones y archive soportes fechados. Las actividades descritas permanecen pendientes hasta que exista evidencia real de su ejecución.</p>';
        return $body;
    }

    private static function context(array $tenant, array $activity, array $document): string
    {
        $code = (string) ($activity['codigo_ciuu'] ?? $tenant['codigo_ciuu'] ?? 'Por validar');
        $name = (string) ($activity['descripcion'] ?? $tenant['actividad'] ?? 'Por validar');
        return '<h2>Contexto y aplicabilidad</h2>'
            . '<table><tbody><tr><th>Empresa</th><td>' . $tenant['razonsocial_html'] . '</td><th>Régimen</th><td>' . (int) $tenant['grupo_estandares'] . ' estándares</td></tr>'
            . '<tr><th>Actividad</th><td>CIIU ' . e($code) . ' · ' . e($name) . '</td><th>Kit</th><td>' . e((string) $document['origen']) . '</td></tr>'
            . '<tr><th>Centro / procesos</th><td>Por completar según la operación</td><th>Responsable de adaptación</th><td>Por designar</td></tr></tbody></table>'
            . '<p>Este borrador se seleccionó por la clasificación registrada. Antes de aprobarlo se deben validar peligros, tareas, población, instalaciones y requisitos adicionales. Si el anexo no aplica, documente la justificación en la matriz de cobertura.</p>';
    }

    private static function form(array $form): string
    {
        $html = '<h2>Instrucciones de diligenciamiento</h2>';
        if ($form['instructions'] !== []) {
            $html .= '<ul>' . self::list($form['instructions'], 'li') . '</ul>';
        }
        $html .= '<p>Complete los campos con información verificable. Agregue las filas que necesite, conserve los criterios y anexe los soportes; las celdas en blanco no significan “no aplica” ni “cumple”.</p>'
            . '<h2>Identificación</h2><table><tbody>';
        $fields = array_values($form['identification']);
        for ($index = 0; $index < count($fields); $index += 2) {
            $html .= '<tr><th>' . e($fields[$index]) . '</th><td><br></td>';
            if (isset($fields[$index + 1])) {
                $html .= '<th>' . e($fields[$index + 1]) . '</th><td><br></td>';
            } else {
                $html .= '<td colspan="2"><br></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        foreach ($form['tables'] as $table) {
            $html .= '<h2>' . e($table['title']) . '</h2><table><thead><tr>';
            foreach ($table['headers'] as $header) {
                $html .= '<th>' . e($header) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($table['rows'] as $row) {
                $html .= self::formRow($row, count($table['headers']));
            }
            for ($row = 0; $row < (int) $table['blank_rows']; $row++) {
                $html .= self::formRow([], count($table['headers']));
            }
            $html .= '</tbody></table>';
        }
        if ($form['signatures'] !== []) {
            $html .= '<h2>Firmas obtenidas después de la revisión o ejecución</h2><table><thead><tr><th>Rol</th><th>Nombre y documento</th><th>Fecha</th><th>Firma / soporte</th></tr></thead><tbody>';
            foreach ($form['signatures'] as $signature) {
                $html .= '<tr><td>' . e($signature) . '</td><td><br></td><td><br></td><td><br></td></tr>';
            }
            $html .= '</tbody></table>';
        }
        return $html;
    }

    private static function formRow(array $row, int $columns): string
    {
        $html = '<tr>';
        for ($column = 0; $column < $columns; $column++) {
            $value = trim((string) ($row[$column] ?? ''));
            $html .= '<td>' . ($value === '' ? '<br>' : e($value)) . '</td>';
        }
        return $html . '</tr>';
    }

    private static function startGuide(array $tenant, array $activity, array $context): string
    {
        $body = '<h2>1. Qué contiene y qué no demuestra</h2>'
            . '<p>Este paquete reúne los borradores seleccionados para ' . $tenant['razonsocial_html'] . ' según su régimen de <strong>' . (int) $tenant['grupo_estandares'] . ' estándares</strong>, actividad y peligros. Incluye estándares mínimos, políticas, manual, programas, procedimientos y formatos relacionados. No incluye ejecuciones, aprobaciones, capacitaciones, mediciones, inspecciones, firmas o cierres que no hayan ocurrido.</p>'
            . '<div class="document-notice"><strong>Regla de uso:</strong> disponer de un documento no equivale a implementarlo. La cobertura final depende de la validación en sitio, los módulos contratados y los requisitos particulares de cada centro.</div>'
            . '<h2>2. Secuencia recomendada de puesta en marcha</h2>'
            . '<ol><li><strong>Confirmar el perfil:</strong> validar NIT, CIIU, clase de riesgo, trabajadores, centros, turnos, contratistas, modalidades y peligros. Corregir la clasificación antes de aprobar anexos.</li>'
            . '<li><strong>Depurar la cobertura:</strong> usar FT-COB-01 para justificar documentos no aplicables e identificar requisitos adicionales. No cargar anexos sólo por pertenecer a un sector.</li>'
            . '<li><strong>Adaptar y revisar:</strong> completar responsables, procesos, criterios técnicos, recursos, referencias y formatos con personal competente y participación de los trabajadores.</li>'
            . '<li><strong>Aprobar y comunicar:</strong> aplicar el control de versiones, las firmas o mecanismos de aprobación y la socialización que correspondan.</li>'
            . '<li><strong>Ejecutar:</strong> convertir los programas en actividades, permisos, inspecciones, capacitaciones, mantenimiento, evaluaciones y controles reales.</li>'
            . '<li><strong>Evidenciar y mejorar:</strong> adjuntar soportes en sus módulos, medir eficacia, investigar desviaciones y actualizar IPEVR, plan anual y documentos. Realizar la autoevaluación anual y formular el Plan de Mejoramiento con responsables, recursos y seguimiento; verificar los plazos de reporte vigentes sin dar por cumplido un plazo vencido.</li></ol>'
            . '<h2>3. Kits seleccionados</h2><table><thead><tr><th>Kit</th><th>Documentos</th><th>Aplicabilidad y módulo relacionado</th></tr></thead><tbody>';
        foreach ($context['groups'] as $group) {
            $body .= '<tr><td>' . e($group['nombre']) . '</td><td>' . count($group['documentos']) . '</td><td>' . e($group['aplicabilidad']) . ' Ruta: ' . e($group['related_route']) . '</td></tr>';
        }
        $body .= '</tbody></table><h2>4. Prioridad de adaptación</h2>'
            . '<p>Comience por peligros críticos y controles que protegen la vida: permisos, alturas, espacios confinados, electricidad, maquinaria, químicos, bioseguridad, emergencias y tránsito cuando apliquen. Después complete la gestión PHVA y los registros transversales, sin posponer una condición grave por terminar papeleo.</p>'
            . '<h2>5. Conservación y privacidad</h2><p>Conserve los documentos según su tipo y fundamento. Las historias clínicas, resultados diagnósticos y expedientes individuales de convivencia no se publican en la biblioteca general; utilice custodios y accesos restringidos.</p>';
        return $body;
    }

    private static function systemManual(array $tenant, array $activity, array $context): string
    {
        $activityName = (string) ($activity['descripcion'] ?? 'Por validar');
        return '<h2>1. Presentación y alcance</h2>'
            . '<p>' . $tenant['razonsocial_html'] . ' implementará su SG-SST mediante el ciclo Planear–Hacer–Verificar–Actuar. Este manual integra los procesos y documentos aplicables a <strong>CIIU ' . e((string) ($activity['codigo_ciuu'] ?? 'GENERAL')) . ' · ' . e($activityName) . '</strong>, bajo el régimen de ' . (int) $tenant['grupo_estandares'] . ' estándares.</p>'
            . '<p>El alcance definitivo debe relacionar centros, procesos, cargos, jornadas, contratistas y trabajadores. El sistema conserva por separado los módulos contratados de PESVial, emergencias y vigilancia; su existencia no amplía automáticamente el alcance SG-SST.</p>'
            . '<h2>2. Liderazgo, participación y recursos</h2><p>La alta dirección define política, objetivos, responsabilidades y recursos, y revisa resultados. El responsable del SG-SST coordina su diseño y administración con la dedicación y competencias aplicables. Trabajadores, COPASST o vigía y Comité de Convivencia participan dentro de sus funciones; los comités no reemplazan la responsabilidad del empleador.</p>'
            . '<h2>3. Planear</h2><ul><li>Diagnóstico inicial y plan de mejoramiento.</li><li>Matriz legal y evaluación de requisitos adicionales.</li><li>Política, objetivos, indicadores, Plan Anual de Trabajo y presupuesto.</li><li>IPEVR participativa, criterios de aceptabilidad y controles jerarquizados.</li><li>Perfiles de cargo y exposición, necesidades de salud y capacitación.</li></ul>'
            . '<h2>4. Hacer</h2><ul><li>Afiliaciones, inducción, formación y comunicación.</li><li>Controles operativos, EPP, mantenimiento, compras, contratistas y gestión del cambio.</li><li>Programas de salud, ergonomía, psicosocial, convivencia, saneamiento y anexos por peligro.</li><li>Preparación y respuesta ante emergencias, reporte e investigación de eventos.</li><li>Permisos y procedimientos específicos para tareas críticas; ningún formato general autoriza por sí solo una tarea.</li></ul>'
            . '<h2>5. Verificar</h2><p>Seguimiento del plan, indicadores, inspecciones, mediciones, resultados agregados de salud, auditoría, evaluación de cumplimiento y revisión por la dirección. Las fórmulas se aplican con fuentes trazables y denominadores válidos; ausencia de datos no se interpreta como desempeño satisfactorio.</p>'
            . '<h2>6. Actuar</h2><p>Las no conformidades, incidentes, hallazgos y requerimientos se analizan por causas, generan acciones con responsables y recursos, y se cierran únicamente después de verificar implementación y eficacia. Los cambios se retroalimentan en IPEVR, objetivos, capacitación y control documental.</p>'
            . '<h2>7. Control de documentos, registros y datos</h2><p>Cada documento tiene código, versión, responsable y aprobación cuando corresponda. Los registros se crean al ejecutar la actividad, con acceso, legibilidad, respaldo, retención y disposición definidos. Los datos clínicos y expedientes sensibles se custodian fuera del acceso general.</p>'
            . '<h2>8. Arquitectura del paquete</h2><p>La guía GUI-INI-01 orienta la puesta en marcha y FT-COB-01 relaciona los documentos seleccionados, su estado de adaptación y las evidencias pendientes. Los procedimientos sectoriales se conservan como anexos; no se fusionan con PESVial ni con módulos contratados de emergencia.</p>';
    }

    private static function coverage(array $tenant, array $context): string
    {
        $documents = [];
        foreach ($context['documents'] as $document) {
            $code = (string) ($document['code'] ?? $document['codigo'] ?? $document['codigo_estandar'] ?? '');
            if ($code === '') {
                continue;
            }
            $documents[$code] = [
                'code' => $code,
                'name' => (string) ($document['name'] ?? $document['nombre'] ?? $document['nombre_documento'] ?? ''),
                'type' => (string) ($document['type'] ?? $document['tipo'] ?? 'Documento'),
                'origin' => (string) ($document['origen'] ?? 'Paquete base / estándares'),
                'status' => 'Documento del paquete · estado en biblioteca',
            ];
        }
        foreach ($context['existing'] as $document) {
            $code = (string) $document['codigo_estandar'];
            if (!isset($documents[$code])) {
                $documents[$code] = [
                    'code' => $code,
                    'name' => (string) $document['nombre_documento'],
                    'type' => (string) $document['tipo'],
                    'origin' => 'Documento existente de la empresa',
                    'status' => 'Registro existente · ' . $document['estado'],
                ];
            }
        }
        uasort($documents, static fn (array $a, array $b): int => strcmp($a['code'], $b['code']));
        $body = '<h2>Reglas de lectura</h2><p>La matriz relaciona los documentos seleccionados y los ya registrados. <strong>“Disponible” no significa adaptado, aprobado, implementado ni evidenciado.</strong> Complete la revisión con la operación real y agregue requisitos o anexos que falten.</p>'
            . '<p>Versión de la biblioteca: ' . e($context['version']) . ' · revisión de fuentes: ' . e($context['reviewed_at']) . '. Régimen actual: ' . (int) $tenant['grupo_estandares'] . ' estándares.</p>'
            . '<table><thead><tr><th>Código</th><th>Documento</th><th>Tipo / origen</th><th>Disponibilidad</th><th>Adaptación y aprobación</th><th>Implementación / evidencia real</th></tr></thead><tbody>';
        foreach ($documents as $document) {
            $body .= '<tr><td>' . e($document['code']) . '</td><td>' . e($document['name']) . '</td><td>' . e($document['type'] . ' · ' . $document['origin']) . '</td><td>' . e($document['status']) . '</td><td>☐ Adaptado ☐ Aprobado<br>Responsable / fecha: </td><td>☐ Ejecutado ☐ Verificado<br>Evidencia / brecha: </td></tr>';
        }
        $body .= '</tbody></table><h2>Brechas y anexos adicionales</h2><table><thead><tr><th>Requisito / peligro / centro</th><th>Documento o control requerido</th><th>Justificación de aplicabilidad</th><th>Responsable / fecha</th><th>Estado y soporte</th></tr></thead><tbody>'
            . self::formRow([], 5) . self::formRow([], 5) . self::formRow([], 5) . self::formRow([], 5) . '</tbody></table>'
            . '<h2>Revisión de cobertura</h2><p>Revisó: ____________________ · Fecha: __________ · Participación de trabajadores / comité: ____________________ · Próxima revisión: __________.</p>';
        return $body;
    }

    private static function convivencia(array $tenant): string
    {
        return '<h2>1. Objeto, naturaleza y alcance</h2><p>Prevenir el acoso laboral y promover la convivencia mediante un Comité independiente en su funcionamiento, con rol preventivo, orientador, conciliador y canalizador. <strong>El Comité no determina si existe acoso laboral</strong> ni sustituye a la alta dirección, autoridades o rutas de salud.</p>'
            . '<h2>2. Conformación por validar</h2><table><thead><tr><th>Población de la empresa</th><th>Conformación mínima descrita por la Resolución 3461 de 2025</th><th>Designación en la empresa</th></tr></thead><tbody>'
            . '<tr><td>Menos de 5 trabajadores</td><td>1 representante de trabajadores y 1 del empleador</td><td><br></td></tr>'
            . '<tr><td>Más de 5 y menos de 20</td><td>1 representante de trabajadores y 1 del empleador, con respectivos suplentes</td><td><br></td></tr>'
            . '<tr><td>Más de 20 trabajadores</td><td>2 representantes de trabajadores y 2 del empleador, con respectivos suplentes</td><td><br></td></tr></tbody></table>'
            . '<p>El texto oficial consultado no clasifica expresamente los casos de exactamente 5 o 20 trabajadores; no extrapolar los rangos. La empresa debe resolver esos límites con revisión jurídica o criterio oficial aplicable y documentar la decisión. Puede designar más representantes siempre que ambas partes tengan igual número.</p>'
            . '<p>Con dos o más centros se evalúa un comité central y uno adicional por centro según la organización. El empleador designa sus representantes; los trabajadores eligen los suyos por votación secreta y escrutinio público. No integran el Comité personas que hayan formulado una queja o hayan sido víctimas de acoso laboral en el año anterior a su conformación.</p>'
            . '<h2>3. Período, reglamento y roles</h2><p>El período es de dos años desde la comunicación de la elección o designación. El Comité elabora su propio reglamento, acuerdos de confidencialidad y protocolos de información sensible. Elige por mutuo acuerdo presidencia y secretaría; sus funciones incluyen convocatoria, recepción, citaciones, recomendaciones, actas, archivo, seguimiento e informes.</p>'
            . '<h2>4. Funcionamiento preventivo</h2><ul><li>Sesiones ordinarias mensuales y extraordinarias cuando se requiera, con orden del día y acta.</li><li>Seguimiento mensual a compromisos y recomendaciones.</li><li>Informes trimestrales y un informe anual con estadísticas agregadas, casos y recomendaciones para la alta dirección.</li><li>Actividades preventivas de comunicación, formación y promoción de convivencia, sin exponer casos.</li></ul>'
            . '<h2>5. Procedimiento preventivo de quejas</h2><table><thead><tr><th>Etapa</th><th>Actuación y término orientador</th><th>Registro reservado</th></tr></thead><tbody>'
            . '<tr><td>Recepción y trámite</td><td>Recibir y tramitar la queja: 5 días calendario.</td><td><br></td></tr>'
            . '<tr><td>Examen confidencial</td><td>Examinar el caso: 5 días; puede ampliarse 10 días con justificación escrita, máximo 15.</td><td><br></td></tr>'
            . '<tr><td>Escuchar a las partes</td><td>Audiencias individuales: 5 días calendario.</td><td><br></td></tr>'
            . '<tr><td>Espacio de diálogo</td><td>Después de escuchar: 5 días; puede ampliarse 10 con justificación, máximo 15. Concertar compromisos cuando proceda.</td><td><br></td></tr>'
            . '<tr><td>Recomendaciones</td><td>Presentar recomendaciones a la alta dirección con copia al trabajador: entre 5 y máximo 10 días calendario.</td><td><br></td></tr>'
            . '<tr><td>Sin acuerdo o incumplimiento</td><td>Aplicar la remisión o información prevista en el artículo 6: máximo 15 días desde verificado el incumplimiento.</td><td><br></td></tr></tbody></table>'
            . '<p><strong>El procedimiento preventivo completo no puede superar 65 días calendario desde la queja formal y los tiempos de las etapas no son acumulativos.</strong> El seguimiento mensual de compromisos no se cuenta dentro de ese procedimiento. Se garantizan celeridad, eficacia, imparcialidad, confidencialidad, no discriminación, debido proceso, enfoque diferencial y protección contra represalias.</p>'
            . '<h2>6. Acoso sexual, violencias y discriminación</h2><p>Las medidas frente a acoso, violencia y discriminación se articulan con el SG-SST y talento humano conforme al Decreto 1040 de 2026, con canales reservados y protección contra represalias. El acoso sexual y la violencia por razones de género no son conciliables y el Comité no es la instancia competente. La alta dirección o talento humano debe mantener un procedimiento separado de recepción, atención, prevención y protección conforme a la Ley 2365 de 2024 y regulación aplicable. La persona recibe orientación y canalización sin revictimización.</p>'
            . '<h2>7. Custodia y reserva</h2><p>Secretaría custodia quejas, pruebas, actas y soportes con acceso restringido durante y después de los casos. Al terminar el período, la documentación se entrega al nuevo Comité conservando reserva. En este repositorio sólo se usan actas ordinarias preventivas y estadísticas agregadas; no se cargan expedientes individuales.</p>'
            . '<h2>8. Recursos, articulación y mejora</h2><p>La alta dirección suministra tiempo, medios, formación y condiciones de independencia. Se articulan las actuaciones con SG-SST y talento humano sin divulgar información innecesaria. Las empresas de servicios temporales y usuarias coordinan el trámite previsto por la norma sin trasladar sus obligaciones. La eficacia del procedimiento y del reglamento se revisa periódicamente.</p>';
    }

    private static function references(array $keys, array $references): string
    {
        if ($keys === []) {
            return '';
        }
        $html = '<h2>Referencias para verificar antes de aprobar</h2><ul>';
        foreach ($keys as $key) {
            if (isset($references[$key])) {
                $html .= '<li>' . e($references[$key]['title']) . '. Fuente oficial: ' . e($references[$key]['url']) . '</li>';
            }
        }
        return $html . '</ul><p>Verifique vigencia, modificaciones, ámbito y requisitos sectoriales adicionales. Las fuentes orientan la adaptación; no constituyen una certificación jurídica automática.</p>';
    }

    private static function list(array $items, string $tag): string
    {
        $html = '';
        foreach ($items as $item) {
            $html .= '<' . $tag . '>' . e((string) $item) . '</' . $tag . '>';
        }
        return $html;
    }
}
