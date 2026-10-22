<?php
declare(strict_types=1);

namespace App\Services;

final class CatalogData
{
    public static function standards(): array
    {
        return [
            7 => [
                ['M7-01', 'Asignación de persona que diseña y administra el SG-SST', 'Planear'],
                ['M7-02', 'Afiliación al Sistema de Seguridad Social Integral', 'Planear'],
                ['M7-03', 'Capacitación en Seguridad y Salud en el Trabajo', 'Hacer'],
                ['M7-04', 'Plan Anual de Trabajo del SG-SST', 'Planear'],
                ['M7-05', 'Evaluaciones médicas ocupacionales', 'Hacer'],
                ['M7-06', 'Identificación de peligros, evaluación y valoración de riesgos', 'Hacer'],
                ['M7-07', 'Medidas de prevención y control frente a peligros y riesgos identificados', 'Hacer'],
            ],
            21 => [
                ['M21-01', 'Asignación de una persona que diseñe el SG-SST', 'Planear'],
                ['M21-02', 'Asignación de recursos para el SG-SST', 'Planear'],
                ['M21-03', 'Afiliación al Sistema de Seguridad Social Integral', 'Planear'],
                ['M21-04', 'Conformación y funcionamiento del COPASST', 'Planear'],
                ['M21-05', 'Conformación y funcionamiento del Comité de Convivencia Laboral', 'Planear'],
                ['M21-06', 'Programa de capacitación en promoción y prevención', 'Planear'],
                ['M21-07', 'Política de Seguridad y Salud en el Trabajo', 'Planear'],
                ['M21-08', 'Plan Anual de Trabajo', 'Planear'],
                ['M21-09', 'Archivo y retención documental del SG-SST', 'Planear'],
                ['M21-10', 'Descripción sociodemográfica y diagnóstico de condiciones de salud', 'Hacer'],
                ['M21-11', 'Actividades de medicina del trabajo, prevención y promoción de la salud', 'Hacer'],
                ['M21-12', 'Evaluaciones médicas ocupacionales', 'Hacer'],
                ['M21-13', 'Restricciones y recomendaciones médico-laborales', 'Hacer'],
                ['M21-14', 'Reporte de accidentes de trabajo y enfermedades laborales', 'Hacer'],
                ['M21-15', 'Investigación de incidentes, accidentes y enfermedades laborales', 'Hacer'],
                ['M21-16', 'Identificación de peligros, evaluación y valoración de riesgos', 'Hacer'],
                ['M21-17', 'Mantenimiento periódico de instalaciones, equipos, máquinas y herramientas', 'Hacer'],
                ['M21-18', 'Entrega de EPP y capacitación en su uso adecuado', 'Hacer'],
                ['M21-19', 'Plan de prevención, preparación y respuesta ante emergencias', 'Hacer'],
                ['M21-20', 'Brigada de prevención, preparación y respuesta ante emergencias', 'Hacer'],
                ['M21-21', 'Revisión por la alta dirección', 'Verificar'],
            ],
            60 => self::fullStandards(),
        ];
    }

    private static function fullStandards(): array
    {
        return [
            ['1.1.1', 'Asignación de una persona que diseñe e implemente el SG-SST', 'Planear'],
            ['1.1.2', 'Asignación de responsabilidades en Seguridad y Salud en el Trabajo', 'Planear'],
            ['1.1.3', 'Asignación de recursos para el SG-SST', 'Planear'],
            ['1.1.4', 'Afiliación al Sistema de Seguridad Social Integral', 'Planear'],
            ['1.1.5', 'Identificación de trabajadores en actividades de alto riesgo y cotización de pensión especial', 'Planear'],
            ['1.1.6', 'Conformación y funcionamiento del COPASST', 'Planear'],
            ['1.1.7', 'Capacitación de los integrantes del COPASST', 'Planear'],
            ['1.1.8', 'Conformación y funcionamiento del Comité de Convivencia Laboral', 'Planear'],
            ['1.2.1', 'Programa de capacitación anual en promoción y prevención', 'Planear'],
            ['1.2.2', 'Inducción y reinducción en Seguridad y Salud en el Trabajo', 'Planear'],
            ['1.2.3', 'Curso virtual de capacitación de cincuenta horas en SST', 'Planear'],
            ['2.1.1', 'Política de Seguridad y Salud en el Trabajo', 'Planear'],
            ['2.2.1', 'Objetivos de Seguridad y Salud en el Trabajo', 'Planear'],
            ['2.3.1', 'Evaluación inicial del Sistema de Gestión de SST', 'Planear'],
            ['2.4.1', 'Plan Anual de Trabajo', 'Planear'],
            ['2.5.1', 'Archivo y retención documental del SG-SST', 'Planear'],
            ['2.6.1', 'Rendición de cuentas', 'Planear'],
            ['2.7.1', 'Matriz legal del Sistema General de Riesgos Laborales', 'Planear'],
            ['2.8.1', 'Mecanismos de comunicación interna y externa en SST', 'Planear'],
            ['2.9.1', 'Identificación y evaluación para la adquisición de bienes y servicios', 'Planear'],
            ['2.10.1', 'Evaluación y selección de proveedores y contratistas', 'Planear'],
            ['2.11.1', 'Gestión del cambio', 'Planear'],
            ['3.1.1', 'Descripción sociodemográfica y diagnóstico de las condiciones de salud', 'Hacer'],
            ['3.1.2', 'Actividades de medicina del trabajo y de prevención y promoción de la salud', 'Hacer'],
            ['3.1.3', 'Perfiles de cargos', 'Hacer'],
            ['3.1.4', 'Evaluaciones médicas ocupacionales', 'Hacer'],
            ['3.1.5', 'Custodia de las historias clínicas', 'Hacer'],
            ['3.1.6', 'Restricciones y recomendaciones médico-laborales', 'Hacer'],
            ['3.1.7', 'Estilos de vida y entorno saludable', 'Hacer'],
            ['3.1.8', 'Servicios de higiene', 'Hacer'],
            ['3.1.9', 'Manejo de residuos', 'Hacer'],
            ['3.2.1', 'Reporte de accidentes de trabajo y enfermedades laborales', 'Hacer'],
            ['3.2.2', 'Investigación de incidentes, accidentes de trabajo y enfermedades laborales', 'Hacer'],
            ['3.2.3', 'Registro y análisis estadístico de accidentes y enfermedades laborales', 'Hacer'],
            ['3.2.4', 'Frecuencia de accidentalidad', 'Hacer'],
            ['3.2.5', 'Severidad de accidentalidad', 'Hacer'],
            ['3.2.6', 'Proporción de accidentes de trabajo mortales', 'Hacer'],
            ['3.2.7', 'Prevalencia de la enfermedad laboral', 'Hacer'],
            ['3.2.8', 'Incidencia de la enfermedad laboral', 'Hacer'],
            ['3.2.9', 'Ausentismo por causa médica', 'Hacer'],
            ['4.1.1', 'Metodología para identificación de peligros, evaluación y valoración de riesgos', 'Hacer'],
            ['4.1.2', 'Identificación de peligros y valoración de riesgos con participación de todos los niveles', 'Hacer'],
            ['4.1.3', 'Identificación de sustancias carcinógenas o con toxicidad aguda', 'Hacer'],
            ['4.1.4', 'Mediciones ambientales de los riesgos prioritarios', 'Hacer'],
            ['4.1.5', 'Medidas de prevención y control frente a peligros y riesgos identificados', 'Hacer'],
            ['4.2.1', 'Aplicación de medidas de prevención y control por parte de los trabajadores', 'Hacer'],
            ['4.2.2', 'Procedimientos e instructivos internos de Seguridad y Salud en el Trabajo', 'Hacer'],
            ['4.2.3', 'Inspecciones a instalaciones, maquinaria o equipos', 'Hacer'],
            ['4.2.4', 'Mantenimiento periódico de instalaciones, equipos, máquinas y herramientas', 'Hacer'],
            ['4.2.5', 'Entrega de elementos de protección personal y capacitación en uso adecuado', 'Hacer'],
            ['5.1.1', 'Plan de prevención, preparación y respuesta ante emergencias', 'Hacer'],
            ['5.1.2', 'Brigada de prevención, preparación y respuesta ante emergencias', 'Hacer'],
            ['6.1.1', 'Definición de indicadores del Sistema de Gestión de SST', 'Verificar'],
            ['6.1.2', 'Auditoría anual del SG-SST', 'Verificar'],
            ['6.1.3', 'Revisión por la alta dirección y alcance de la auditoría', 'Verificar'],
            ['6.1.4', 'Planificación de la auditoría y comunicación de resultados al COPASST', 'Verificar'],
            ['7.1.1', 'Acciones preventivas y correctivas', 'Actuar'],
            ['7.1.2', 'Acciones de mejora conforme a la revisión de la alta dirección', 'Actuar'],
            ['7.1.3', 'Acciones de mejora con base en investigaciones de accidentes y enfermedades', 'Actuar'],
            ['7.1.4', 'Plan de mejoramiento por requerimientos de autoridades y ARL', 'Actuar'],
        ];
    }

    public static function plans(): array
    {
        return [
            ['Esencial', 'Para equipos que inician su SG-SST: documentos, PAT y portal de trabajadores.', 129900, 5, 50],
            ['Profesional', 'Gestión PHVA completa, indicadores, IPEVR y seguimiento ampliado.', 299900, 15, 200],
            ['Empresarial', 'Mayor capacidad, múltiples usuarios y acompañamiento para la operación.', 699900, 50, 1000],
        ];
    }
}
