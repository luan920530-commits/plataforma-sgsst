<?php
declare(strict_types=1);

namespace App\Services;

final class ActivityCatalog
{
    public static function activities(): array
    {
        return [
            self::a('0113', 'Cultivo de hortalizas, raíces y tubérculos', 'Agropecuario', 'III', 'Población con trabajo de campo, exposición solar y esfuerzo físico; caracterizar jornadas, edad, sexo y procedencia.', ['Evaluación médica ocupacional de ingreso', 'Evaluación dermatológica y musculoesquelética según exposición', 'Tamizaje visual según tarea'], [
                self::h('Campo', 'Radiación solar y condiciones climáticas', 'Fisico', 'Lesiones cutáneas y estrés térmico', 'Alto', 'Hidratación, pausas, sombra, ropa y protección solar.'),
                self::h('Campo', 'Manipulación de plaguicidas y fertilizantes', 'Quimico', 'Intoxicación y afectación respiratoria o dérmica', 'Critico', 'Sustitución, fichas de seguridad, ventilación, EPP y capacitación específica.'),
            ]),
            self::a('0141', 'Cría de ganado bovino y búfalos', 'Agropecuario', 'III', 'Trabajadores de campo con manipulación de animales, desplazamientos rurales y labores físicas variables.', ['Evaluación médica ocupacional de ingreso', 'Valoración musculoesquelética', 'Evaluaciones específicas según exposición biológica'], [
                self::h('Manejo animal', 'Contacto con animales y agentes biológicos', 'Biologico', 'Zoonosis, heridas y trauma', 'Alto', 'Vacunación, higiene, manejo seguro, barreras y EPP.'),
                self::h('Mantenimiento', 'Uso de herramientas y maquinaria agrícola', 'Seguridad', 'Cortes, atrapamientos y lesiones traumáticas', 'Alto', 'Guardas, mantenimiento, bloqueo y operación por personal entrenado.'),
            ]),
            self::a('1071', 'Elaboración de productos de panadería', 'Industrial', 'II', 'Personal de producción y atención, turnos extendidos, trabajo de pie y manipulación de alimentos.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular', 'Evaluación respiratoria según exposición a polvo'], [
                self::h('Producción', 'Polvo de harina y aerosoles', 'Quimico', 'Irritación y sensibilización respiratoria', 'Medio', 'Extracción localizada, limpieza en húmedo y protección respiratoria según evaluación.'),
                self::h('Producción', 'Hornos, superficies calientes y equipos cortantes', 'Seguridad', 'Quemaduras, cortes y atrapamientos', 'Alto', 'Guardas, aislamiento, señalización, procedimientos y EPP térmico.'),
            ]),
            self::a('1410', 'Confección de prendas de vestir', 'Industrial', 'III', 'Predominio de tareas sedentarias, movimientos repetitivos y demandas de producción por turnos.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular y visual', 'Evaluación auditiva según medición de ruido'], [
                self::h('Confección', 'Movimientos repetitivos y postura sedente prolongada', 'Biomecanico', 'Desórdenes musculoesqueléticos y fatiga visual', 'Alto', 'Puestos ajustables, pausas activas, rotación y análisis ergonómico.'),
                self::h('Confección', 'Agujas, cortantes y ruido de máquinas', 'Seguridad', 'Heridas, cortes y pérdida auditiva', 'Alto', 'Guardas, mantenimiento, protectores de aguja y vigilancia del ruido.'),
            ]),
            self::a('2220', 'Fabricación de productos de plástico', 'Industrial', 'IV', 'Operarios de planta expuestos a maquinaria, sustancias químicas, ruido y turnos de producción.', ['Evaluación médica ocupacional de ingreso', 'Evaluación respiratoria y dermatológica', 'Audiometría según exposición'], [
                self::h('Transformación', 'Vapores, humos y contacto con resinas o aditivos', 'Quimico', 'Irritación, sensibilización y efectos sistémicos', 'Critico', 'Sustitución, extracción, fichas SDS, medición ambiental y EPP específico.'),
                self::h('Transformación', 'Puntos de atrapamiento y superficies calientes', 'Seguridad', 'Amputaciones, aplastamientos y quemaduras', 'Critico', 'Resguardos, enclavamientos, bloqueo de energías y mantenimiento.'),
            ]),
            self::a('2511', 'Fabricación de productos metálicos para uso estructural', 'Industrial', 'IV', 'Personal operativo con soldadura, corte, izajes, ruido y manipulación manual de cargas.', ['Evaluación médica ocupacional de ingreso', 'Evaluación visual y respiratoria', 'Audiometría y valoración osteomuscular según exposición'], [
                self::h('Fabricación', 'Humos metálicos, radiación y partículas de soldadura', 'Quimico', 'Afectación respiratoria, ocular y cutánea', 'Critico', 'Ventilación localizada, pantallas, careta adecuada y vigilancia de la salud.'),
                self::h('Fabricación', 'Izaje y manipulación de piezas metálicas', 'Seguridad', 'Golpes, atrapamientos y lesiones por carga', 'Alto', 'Plan de izaje, accesorios certificados, delimitación y ayudas mecánicas.'),
            ]),
            self::a('4100', 'Construcción de edificios', 'Construcción', 'V', 'Cuadrillas variables, contratistas, trabajo en alturas y cambios frecuentes de frentes de obra.', ['Evaluación médica ocupacional de ingreso con enfoque en alturas', 'Evaluación cardiovascular según tarea', 'Valoración osteomuscular y visual'], [
                self::h('Obra', 'Trabajo en alturas y diferencias de nivel', 'Seguridad', 'Caídas con consecuencias graves o fatales', 'Critico', 'Sistema de protección contra caídas, líneas de vida, andamios certificados, permisos y supervisión.'),
                self::h('Obra', 'Maquinaria, izajes y tránsito interno de equipos', 'Seguridad', 'Atropellos, golpes y atrapamientos', 'Critico', 'Plan de tránsito, señalero, inspección de equipos, exclusión y comunicación.'),
                self::h('Obra', 'Polvo de sílice, ruido y vibración', 'Fisico', 'Enfermedad respiratoria y pérdida auditiva', 'Critico', 'Control en húmedo, extracción, mediciones, mantenimiento y EPP específico.'),
            ]),
            self::a('4210', 'Construcción de carreteras y vías de ferrocarril', 'Construcción', 'V', 'Trabajo en vía pública, turnos variables, maquinaria pesada y exposición a condiciones ambientales.', ['Evaluación médica ocupacional de ingreso', 'Evaluación visual y cardiovascular', 'Audiometría y valoración osteomuscular según tarea'], [
                self::h('Frente de obra', 'Interacción con tránsito vehicular', 'Publico', 'Atropellos y colisiones', 'Critico', 'Plan de manejo de tránsito, cierres, señalización, barreras y personal certificado.'),
                self::h('Frente de obra', 'Maquinaria pesada y puntos ciegos', 'Seguridad', 'Atrapamientos, golpes y vuelcos', 'Critico', 'Inspecciones, alarmas, separación peatonal, señalero y reglas de aproximación.'),
            ]),
            self::a('4321', 'Instalaciones eléctricas', 'Construcción', 'IV', 'Técnicos con trabajo en campo, exposición eléctrica, alturas y desplazamientos entre centros.', ['Evaluación médica ocupacional de ingreso', 'Evaluación cardiovascular y visual según tarea', 'Concepto para trabajo en alturas cuando aplique'], [
                self::h('Instalación', 'Contacto con energía eléctrica y arco eléctrico', 'Seguridad', 'Choque eléctrico, quemaduras y muerte', 'Critico', 'Desenergización, bloqueo y verificación, personal competente, herramientas y EPP dieléctrico.'),
                self::h('Instalación', 'Trabajo en escaleras, techos o estructuras', 'Seguridad', 'Caídas de altura', 'Critico', 'Protección contra caídas, inspección de accesos y permiso de trabajo.'),
            ]),
            self::a('4711', 'Comercio al por menor en establecimientos no especializados', 'Comercio', 'II', 'Personal de atención al cliente, cajas, surtido y almacenamiento con trabajo de pie y turnos.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular', 'Evaluación psicosocial según condiciones del cargo'], [
                self::h('Operación', 'Manipulación de carga y trabajo prolongado de pie', 'Biomecanico', 'Fatiga y desórdenes musculoesqueléticos', 'Medio', 'Ayudas de transporte, pausas, rotación, puestos ajustables y capacitación.'),
                self::h('Operación', 'Atención al público, efectivo y horarios nocturnos', 'Publico', 'Agresiones, atracos y estrés', 'Alto', 'Protocolo ante hurtos, medidas de seguridad, acompañamiento y gestión de turnos.'),
            ]),
            self::a('4759', 'Comercio al por menor de otros artículos domésticos en establecimientos especializados', 'Comercio', 'II', 'Personal de atención, almacenamiento y manipulación de artículos especializados; validar actividades técnicas adicionales, turnos y exposiciones reales.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular', 'Evaluaciones específicas según exposiciones y tareas declaradas'], [
                self::h('Operación', 'Manipulación de artículos, cargas y trabajo prolongado de pie', 'Biomecanico', 'Fatiga y desórdenes musculoesqueléticos', 'Medio', 'Ayudas de transporte, técnicas de manipulación, pausas, rotación y puestos ajustados.'),
                self::h('Almacenamiento', 'Orden, estanterías, circulación y caída de objetos', 'Seguridad', 'Golpes, atrapamientos y lesiones por caída de objetos', 'Alto', 'Estanterías estables, límites de carga, demarcación, orden e inspecciones.'),
                self::h('Operación', 'Atención al público, efectivo y horarios variables', 'Publico', 'Agresiones, atracos y estrés', 'Alto', 'Protocolo ante hurtos, medidas de seguridad, acompañamiento y gestión de turnos.'),
            ]),
            self::a('4923', 'Transporte de carga por carretera', 'Transporte', 'V', 'Conductores con jornadas variables, desplazamientos prolongados y manipulación ocasional de carga.', ['Evaluación médica ocupacional de ingreso para conducción', 'Evaluación visual y auditiva', 'Valoración cardiovascular y osteomuscular según tarea'], [
                self::h('Conducción', 'Accidentes de tránsito y fatiga del conductor', 'Publico', 'Lesiones graves, discapacidad y muerte', 'Critico', 'Plan estratégico de seguridad vial, gestión de fatiga, mantenimiento y monitoreo de conducción.'),
                self::h('Carga y descarga', 'Manipulación manual y estabilidad de la carga', 'Seguridad', 'Atrapamientos, golpes y lesiones lumbares', 'Alto', 'Plan de cargue, ayudas mecánicas, amarre, delimitación y EPP.'),
            ]),
            self::a('5210', 'Almacenamiento y depósito', 'Transporte', 'IV', 'Operarios de bodega, montacargas, picking y despacho con trabajo por turnos y manipulación de cargas.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular y visual', 'Evaluación para operación de equipos según cargo'], [
                self::h('Bodega', 'Interacción de peatones con montacargas', 'Seguridad', 'Atropellos, atrapamientos y lesiones graves', 'Critico', 'Segregación, cruces seguros, velocidad, alarmas, operadores autorizados e inspección.'),
                self::h('Bodega', 'Almacenamiento en altura y manipulación de carga', 'Seguridad', 'Caída de objetos y lesiones musculoesqueléticas', 'Alto', 'Estibas estables, inspección de racks, límites de carga y ayudas mecánicas.'),
            ]),
            self::a('5511', 'Alojamiento en hoteles', 'Servicios', 'II', 'Equipos de recepción, camaristas, cocina y mantenimiento, con turnos rotativos y atención al público.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular', 'Evaluaciones según exposición química o turnos'], [
                self::h('Camaristería', 'Posturas forzadas y movimientos repetitivos', 'Biomecanico', 'Dolor lumbar y trastornos de miembros superiores', 'Alto', 'Carros ergonómicos, técnicas de trabajo, rotación y pausas.'),
                self::h('Limpieza', 'Contacto con productos de limpieza y desinfección', 'Quimico', 'Dermatitis e irritación respiratoria', 'Alto', 'Dilución controlada, ventilación, fichas SDS, guantes y capacitación.'),
            ]),
            self::a('5610', 'Expendio de comidas preparadas en restaurantes', 'Servicios', 'II', 'Personal de cocina, meseros y caja, con calor, cortantes, desplazamientos y atención al público.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular', 'Evaluación dérmica o respiratoria según exposición'], [
                self::h('Cocina', 'Aceites, vapor, superficies calientes y utensilios', 'Seguridad', 'Quemaduras y cortes', 'Alto', 'Procedimientos, guardas, manipulación segura, orden y EPP adecuado.'),
                self::h('Cocina', 'Pisos húmedos, grasas y obstáculos', 'Seguridad', 'Caídas al mismo nivel', 'Alto', 'Limpieza programada, drenajes, señalización y calzado antideslizante.'),
            ]),
            self::a('6201', 'Desarrollo de sistemas informáticos y software', 'Servicios', 'I', 'Trabajo intelectual sedente, pantallas, alta concentración, cooperación remota y entregas por proyectos.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular y visual', 'Evaluación psicosocial según diagnóstico organizacional'], [
                self::h('Operación', 'Trabajo con pantallas y postura sedente prolongada', 'Biomecanico', 'Fatiga visual y desórdenes musculoesqueléticos', 'Medio', 'Diseño del puesto, pausas visuales, movilidad y apoyo ergonómico.'),
                self::h('Proyectos', 'Carga mental, plazos exigentes y trabajo remoto', 'Psicosocial', 'Estrés, fatiga y afectación del descanso', 'Alto', 'Planificación realista, desconexión, canales de apoyo y evaluación psicosocial.'),
            ]),
            self::a('7020', 'Actividades de consultoría de gestión empresarial', 'Servicios', 'I', 'Personal profesional con análisis, reuniones, desplazamientos ocasionales y trabajo con pantallas.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular y visual', 'Evaluación psicosocial según perfil del cargo'], [
                self::h('Consultoría', 'Pantallas, documentación y posturas mantenidas', 'Biomecanico', 'Fatiga visual y molestias osteomusculares', 'Medio', 'Ergonomía, pausas activas y alternancia de tareas.'),
                self::h('Visitas', 'Desplazamientos a instalaciones de clientes', 'Publico', 'Accidentes de tránsito y exposición a condiciones ajenas', 'Medio', 'Coordinación previa, inducción del sitio, seguridad vial y comunicación.'),
            ]),
            self::a('8530', 'Educación de nivel superior', 'Servicios', 'I', 'Docentes y personal administrativo con interacción social, pantallas, desplazamientos y jornadas variables.', ['Evaluación médica ocupacional de ingreso', 'Valoración osteomuscular y de voz según tarea', 'Evaluación psicosocial según diagnóstico'], [
                self::h('Docencia', 'Uso prolongado de la voz y pantallas', 'Biomecanico', 'Fatiga vocal, visual y musculoesquelética', 'Medio', 'Pausas, hidratación, amplificación cuando aplique y ergonomía.'),
                self::h('Docencia', 'Demandas emocionales y de atención a grupos', 'Psicosocial', 'Estrés y agotamiento', 'Medio', 'Canales de apoyo, distribución de carga y promoción de convivencia.'),
            ]),
            self::a('8621', 'Actividades de la práctica médica general, con internación', 'Salud', 'III', 'Talento en salud con contacto con pacientes, turnos, manipulación de material clínico y carga emocional.', ['Evaluación médica ocupacional de ingreso', 'Esquema de inmunización y vigilancia biológica según perfil', 'Evaluación osteomuscular y psicosocial según tarea'], [
                self::h('Atención clínica', 'Exposición a fluidos, aerosoles y material cortopunzante', 'Biologico', 'Infecciones y enfermedades de transmisión sanguínea', 'Critico', 'Precauciones estándar, controles de ingeniería, vacunación, EPP y protocolo postexposición.'),
                self::h('Atención clínica', 'Turnos, urgencias y carga emocional', 'Psicosocial', 'Fatiga, estrés y agotamiento', 'Alto', 'Gestión de turnos, pausas, apoyo psicosocial y revisión de carga.'),
            ]),
            self::a('8610', 'Actividades de hospitales y clínicas, con internación', 'Salud', 'IV', 'Población asistencial y administrativa con exposición biológica, química, turnos y movilización de pacientes.', ['Evaluación médica ocupacional de ingreso', 'Vigilancia biológica e inmunización según cargo', 'Evaluación osteomuscular y psicosocial'], [
                self::h('Asistencia', 'Agentes infecciosos y cortopunzantes', 'Biologico', 'Infecciones ocupacionales', 'Critico', 'Precauciones estándar, aislamiento, vacunación, dispositivos seguros y protocolo postexposición.'),
                self::h('Asistencia', 'Movilización manual de pacientes', 'Biomecanico', 'Lesiones lumbares y musculoesqueléticas', 'Alto', 'Ayudas técnicas, trabajo en equipo, entrenamiento y evaluación ergonómica.'),
            ]),
            self::a('8121', 'Limpieza general interior de edificios', 'Servicios', 'III', 'Personal operativo con exposición a químicos, posturas, desplazamientos y trabajo en instalaciones de terceros.', ['Evaluación médica ocupacional de ingreso', 'Evaluación dermatológica y respiratoria según productos', 'Valoración osteomuscular'], [
                self::h('Limpieza', 'Mezcla y aplicación de sustancias químicas', 'Quimico', 'Quemaduras, dermatitis e irritación respiratoria', 'Alto', 'Inventario y fichas SDS, prohibición de mezclas, ventilación, dosificación y EPP.'),
                self::h('Limpieza', 'Superficies húmedas y cables u obstáculos', 'Seguridad', 'Caídas al mismo nivel', 'Alto', 'Señalización, secuencia segura, orden y calzado antideslizante.'),
            ]),
        ];
    }

    public static function commonHazards(): array
    {
        return [
            self::h('Transversal', 'Condiciones ergonómicas de las tareas habituales', 'Biomecanico', 'Fatiga y desórdenes musculoesqueléticos', 'Medio', 'Evaluación del puesto, pausas, rotación y participación de los trabajadores.'),
            self::h('Transversal', 'Demandas de trabajo, comunicación y convivencia', 'Psicosocial', 'Estrés y afectación del bienestar', 'Medio', 'Evaluación psicosocial, canales de reporte, liderazgo y promoción de convivencia.'),
            self::h('Transversal', 'Incendio, evacuación y eventos de emergencia', 'Tecnologico', 'Lesiones y pérdida de vidas o infraestructura', 'Alto', 'Plan de emergencias, inspecciones, simulacros, brigada y mantenimiento de equipos.'),
        ];
    }

    private static function a(string $code, string $description, string $sector, string $risk, string $profile, array $exams, array $hazards): array
    {
        return [
            'codigo_ciuu' => $code,
            'descripcion' => $description,
            'sector' => $sector,
            'nivel_riesgo_predeterminado' => $risk,
            'perfil_sociodemografico' => $profile,
            'examenes_medicos' => $exams,
            'matriz_ipevr_base' => $hazards,
        ];
    }

    private static function h(string $process, string $hazard, string $classification, string $effects, string $level, string $controls): array
    {
        return [
            'proceso' => $process,
            'cargo' => 'Cargos expuestos según tarea',
            'peligro' => $hazard,
            'clasificacion' => $classification,
            'efectos' => $effects,
            'controles_existentes' => 'Por verificar en la evaluación inicial de la empresa.',
            'nivel_riesgo' => $level,
            'medidas_control' => $controls,
        ];
    }
}
