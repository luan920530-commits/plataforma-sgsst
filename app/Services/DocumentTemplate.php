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

        return StandardDocumentTemplate::render($tenant, $standard);
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
            <p>{$tenant['razonsocial_html']} promueve relaciones basadas en el respeto, la dignidad y el trato equitativo. El Comité de Convivencia Laboral es una instancia preventiva, orientadora, conciliadora y canalizadora; <strong>no determina si existió acoso laboral</strong> ni sustituye las competencias de las autoridades.</p>
            <h2>Ruta general de actuación</h2>
            <ol><li><strong>Recepción:</strong> la persona presenta su relato por el canal designado. Se registra la solicitud con reserva y se informa la ruta.</li><li><strong>Orientación inicial:</strong> el comité verifica su competencia y adopta medidas de protección razonables cuando sean necesarias, evitando represalias y revictimización.</li><li><strong>Debido proceso:</strong> se escuchan las partes por separado, se preserva la confidencialidad y no se presume responsabilidad sin análisis.</li><li><strong>Acuerdos y seguimiento:</strong> cuando procede, se documentan compromisos y se verifica mensualmente su cumplimiento. Los casos fuera de competencia se remiten por los canales correspondientes.</li></ol>
            <h2>Reglas de protección y actualización</h2>
            <p>La conformación, el reglamento propio, el período de dos años, las reuniones ordinarias mensuales y el procedimiento preventivo se rigen por la <strong>Resolución 3461 de 2025</strong>, que derogó las Resoluciones 652 y 1356 de 2012. El procedimiento preventivo no puede superar <strong>65 días calendario</strong> desde la recepción de la queja formal y sus tiempos no son acumulativos.</p>
            <p>En presunto acoso sexual o violencia por razones de género se activa la ruta especial definida por la empresa conforme a la Ley 2365 de 2024 y demás regulación aplicable; <strong>estas conductas no se concilian ante el Comité de Convivencia</strong>.</p>
            <h2>Principios</h2>
            <p>Celeridad, eficacia, imparcialidad, confidencialidad, no discriminación, debido proceso y protección frente a represalias. Los registros sensibles se custodian con acceso restringido y no se publican en el portal general de trabajadores.</p>
        HTML;
    }

    public static function layout(array $tenant, string $code, string $title, string $phase, string $body, bool $notice = true): string
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
