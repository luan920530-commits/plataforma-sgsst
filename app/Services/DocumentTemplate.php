<?php
declare(strict_types=1);

namespace App\Services;

final class DocumentTemplate
{
    public static function standard(array $tenant, array $standard): string
    {
        $name = text_lower($standard['nombre']);
        if (str_contains($name, 'política de seguridad y salud')) {
            return self::policy($tenant, 'sst', $standard['codigo']);
        }

        $title = e($standard['nombre']);
        $body = <<<HTML
            <h2>1. Objeto</h2>
            <p>Establecer los lineamientos para implementar y mantener <strong>{$title}</strong> en {$tenant['razonsocial_html']}, con responsables definidos, participación de los trabajadores y evidencias verificables.</p>
            <h2>2. Alcance</h2>
            <p>Aplica a todos los centros de trabajo, procesos, jornadas y trabajadores, independientemente de su forma de vinculación o contratación. Cuando existan contratistas, se articularán las responsabilidades sin transferir las obligaciones del empleador.</p>
            <h2>3. Referentes de gestión</h2>
            <ul>
                <li>Decreto 1072 de 2015, especialmente el marco del Sistema de Gestión de Seguridad y Salud en el Trabajo.</li>
                <li>Resolución 0312 de 2019, en los estándares que resultan aplicables a la empresa.</li>
                <li>Requisitos legales y técnicos identificados en la matriz legal y en la matriz IPEVR.</li>
                <li>Principios de mejora continua y participación de ISO 45001, cuando la organización los adopte como referencia voluntaria.</li>
            </ul>
            <h2>4. Responsabilidades</h2>
            <p>La alta dirección asigna recursos y verifica el resultado. El responsable del SG-SST programa, acompaña y consolida las evidencias. Los líderes de proceso ejecutan los controles en sus equipos. Trabajadores y contratistas participan, reportan condiciones de riesgo y cumplen los lineamientos aplicables.</p>
            <h2>5. Desarrollo y control</h2>
            <ol>
                <li>Realizar un diagnóstico de la situación actual y registrar las brechas frente a este estándar.</li>
                <li>Definir acciones, responsables, recursos y fechas en el Plan Anual de Trabajo o en el plan de mejoramiento.</li>
                <li>Socializar el lineamiento con las personas involucradas y ejecutar las acciones priorizadas según la IPEVR.</li>
                <li>Conservar soportes fechados, revisar su eficacia y gestionar las desviaciones mediante acciones preventivas o correctivas.</li>
            </ol>
            <h2>6. Evidencias mínimas</h2>
            <p>Según corresponda: acta o documento de asignación, registros de socialización, listas de asistencia, informes, matrices, conceptos, inspecciones, certificados, soportes de ejecución y seguimiento. Los documentos clínicos privados no se incorporan a este repositorio general.</p>
            <h2>7. Revisión</h2>
            <p>Revisar al menos una vez al año, cuando cambien los procesos o la normatividad aplicable, y después de accidentes o eventos que evidencien la necesidad de ajustar los controles.</p>
        HTML;
        return self::layout($tenant, (string) $standard['codigo'], (string) $standard['nombre'], (string) $standard['fase_phva'], $body);
    }

    public static function policy(array $tenant, string $type, string $code = ''): string
    {
        [$title, $phase, $body] = match ($type) {
            'alcohol' => ['Política de prevención de consumo de alcohol y drogas', 'Hacer', self::alcoholBody($tenant)],
            'convivencia' => ['Política y ruta del Comité de Convivencia Laboral', 'Planear', self::convivenciaBody($tenant)],
            default => ['Política de Seguridad y Salud en el Trabajo', 'Planear', self::sstBody($tenant)],
        };
        return self::layout($tenant, $code ?: strtoupper($type), $title, $phase, $body);
    }

    public static function induction(array $tenant): string
    {
        $body = <<<HTML
            <h2>Bienvenida y propósito</h2>
            <p>Esta inducción presenta los lineamientos generales para realizar un trabajo seguro y saludable. Antes de iniciar labores, cada persona debe recibir además la inducción específica de su cargo, centro de trabajo y peligros asociados.</p>
            <h2>Compromisos de la empresa</h2>
            <ul><li>Identificar peligros, evaluar riesgos e implementar controles con participación de los trabajadores.</li><li>Suministrar información, capacitación y elementos de protección cuando el análisis de riesgo los requiera.</li><li>Atender los reportes y promover un entorno de respeto, sin represalias por informar condiciones de seguridad o salud.</li></ul>
            <h2>Responsabilidades de cada persona</h2>
            <ol><li>Conocer y aplicar los procedimientos, controles y rutas de atención relacionados con su tarea.</li><li>Usar y cuidar correctamente los EPP suministrados; no sustituirlos ni modificarlos sin autorización técnica.</li><li>Reportar de inmediato incidentes, accidentes, síntomas, actos y condiciones que puedan generar daño.</li><li>Participar en capacitaciones, simulacros, evaluaciones médicas y actividades de mejora.</li><li>No realizar tareas para las que no se encuentra entrenado o autorizado y detenerse ante un peligro grave e inminente, informando al responsable.</li></ol>
            <h2>Autoreporte y emergencias</h2>
            <p>Usa el módulo <strong>Autoreportes</strong> del portal para registrar condiciones o incidentes. En una emergencia, activa la alarma, sigue las instrucciones de la brigada, evacúa por las rutas señalizadas y dirígete al punto de encuentro. No expongas tu vida para controlar una situación para la que no estás entrenado.</p>
            <h2>Convivencia y bienestar</h2>
            <p>Las quejas de acoso o comportamientos contrarios a la convivencia se tramitan por los canales definidos, con reserva y debido proceso. La prevención del consumo de alcohol y drogas se aborda con enfoque de promoción de la salud y confidencialidad.</p>
            <h2>Declaración de conocimiento</h2>
            <p>Al firmar digitalmente, la persona declara que tuvo acceso a este contenido, que conoce los canales para resolver preguntas y que se compromete a cumplir los lineamientos. La firma no reemplaza la inducción específica del puesto ni las demás evidencias de formación requeridas.</p>
        HTML;
        return self::layout($tenant, 'IND-SST', 'Inducción general de Seguridad y Salud en el Trabajo', 'Hacer', $body, false);
    }

    private static function sstBody(array $tenant): string
    {
        return <<<HTML
            <p>{$tenant['razonsocial_html']} reconoce que la seguridad y la salud de las personas son una prioridad de la gestión empresarial. La alta dirección asume el liderazgo del SG-SST y destina recursos humanos, financieros, técnicos y tecnológicos para su implementación y mejora continua.</p>
            <h2>Compromisos</h2>
            <ol>
                <li>Identificar peligros, evaluar y valorar riesgos y aplicar controles según su jerarquía, priorizando la eliminación y la intervención en la fuente y el medio cuando sea factible.</li>
                <li>Proteger a todos los trabajadores, contratistas, aprendices y visitantes en los centros y jornadas bajo responsabilidad de la organización.</li>
                <li>Cumplir los requisitos legales y otros compromisos aplicables en riesgos laborales.</li>
                <li>Garantizar la consulta y participación de los trabajadores y sus representantes, facilitando canales sin represalias.</li>
                <li>Prevenir accidentes y enfermedades laborales, promover entornos saludables y revisar periódicamente objetivos, indicadores y resultados.</li>
            </ol>
            <h2>Aplicación y revisión</h2>
            <p>Esta política se comunica a todos los niveles, se encuentra disponible para las partes interesadas pertinentes y se revisa como mínimo anualmente o ante cambios significativos. Su aceptación no transfiere a los trabajadores las obligaciones que corresponden al empleador.</p>
            <p><strong>Representante legal:</strong> {$tenant['representante_html']} &nbsp;·&nbsp; <strong>Estado:</strong> pendiente de firma manuscrita o mecanismo de aprobación definido por la empresa.</p>
        HTML;
    }

    private static function alcoholBody(array $tenant): string
    {
        return <<<HTML
            <p>{$tenant['razonsocial_html']} promueve ambientes de trabajo seguros, saludables y respetuosos mediante la prevención del consumo problemático de alcohol y otras drogas, sin estigmatización y con protección de la confidencialidad.</p>
            <h2>Lineamientos</h2>
            <ul><li>Realizar actividades de información, prevención y promoción de hábitos saludables con enfoque poblacional y de riesgo.</li><li>No ingresar ni permanecer bajo efectos que comprometan la seguridad propia o de terceros. Ante una condición de riesgo, el responsable aplicará medidas preventivas proporcionales y respetuosas.</li><li>Definir canales de orientación y remisión a profesionales o instituciones competentes. Las decisiones clínicas corresponden exclusivamente al personal de salud.</li><li>Tratar la información con reserva. Esta plataforma no almacena historias clínicas ni resultados diagnósticos.</li><li>Articular esta política con el reglamento interno, el debido proceso y la normatividad vigente.</li></ul>
            <h2>Responsabilidad compartida</h2>
            <p>La dirección suministra recursos y acompañamiento; los líderes gestionan preventivamente las situaciones; trabajadores y contratistas participan en las actividades y solicitan apoyo oportunamente.</p>
        HTML;
    }

    private static function convivenciaBody(array $tenant): string
    {
        return <<<HTML
            <p>{$tenant['razonsocial_html']} promueve relaciones basadas en el respeto, la dignidad y el trato equitativo. El Comité de Convivencia Laboral es un mecanismo preventivo y de mejora, con actuación confidencial y sin sustituir las competencias de las autoridades.</p>
            <h2>Ruta general de actuación</h2>
            <ol><li><strong>Recepción:</strong> la persona presenta su relato por el canal designado. Se registra la solicitud con reserva y se informa la ruta.</li><li><strong>Orientación inicial:</strong> el comité verifica su competencia y adopta medidas de protección razonables cuando sean necesarias, evitando represalias.</li><li><strong>Debido proceso:</strong> se escuchan las partes por separado, se preserva la confidencialidad y no se presume responsabilidad sin análisis.</li><li><strong>Acuerdos y seguimiento:</strong> cuando procede, se documentan compromisos y se verifica su cumplimiento. Los casos fuera de competencia se remiten por los canales correspondientes.</li></ol>
            <h2>Principios</h2>
            <p>Imparcialidad, buena fe, reserva, proporcionalidad, participación voluntaria en los acuerdos y protección frente a represalias. Los registros sensibles tendrán acceso restringido y no se publicarán en el portal general de trabajadores.</p>
        HTML;
    }

    private static function layout(array $tenant, string $code, string $title, string $phase, string $body, bool $notice = true): string
    {
        $noticeHtml = $notice ? '<p class="document-notice"><strong>Nota de control:</strong> documento base generado a partir de la clasificación registrada. Debe ser ajustado a los procesos y riesgos reales, revisado por una persona competente, aprobado y firmado según corresponda. Su generación no equivale a certificación de cumplimiento.</p>' : '';
        return '<article class="sst-document">'
            . '<header class="document-header"><p class="document-kicker">SISTEMA DE GESTIÓN DE SEGURIDAD Y SALUD EN EL TRABAJO</p>'
            . '<h1>' . e($title) . '</h1><p>' . $tenant['razonsocial_html'] . ' · NIT ' . e($tenant['nit']) . '</p></header>'
            . '<div class="document-meta"><span><strong>Código:</strong> ' . e($code) . '</span><span><strong>Versión:</strong> 1</span><span><strong>Fase:</strong> ' . e($phase) . '</span><span><strong>Fecha:</strong> ' . date('d/m/Y') . '</span></div>'
            . '<section class="document-body">' . $body . '</section>'
            . '<footer class="document-footer">' . $noticeHtml . '<p>Control documental · Normia SST · La copia impresa debe verificarse contra la versión vigente.</p></footer></article>';
    }

    public static function prepareTenant(array $tenant): array
    {
        $tenant['razonsocial_html'] = '<strong>' . e($tenant['razonsocial']) . '</strong>';
        $tenant['representante_html'] = e($tenant['representante_legal'] ?: 'Por designar');
        return $tenant;
    }
}
