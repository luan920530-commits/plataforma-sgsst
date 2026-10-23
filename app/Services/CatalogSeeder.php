<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CatalogSeeder
{
    public static function seed(): void
    {
        Database::transaction(static function (): void {
            $standardStatement = Database::connection()->prepare(
                'INSERT OR IGNORE INTO estandares_minimos (grupo, orden, codigo, nombre, fase_phva, descripcion) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach (CatalogData::standards() as $group => $standards) {
                foreach ($standards as $index => $standard) {
                    $standardStatement->execute([
                        $group, $index + 1, $standard[0], $standard[1], $standard[2],
                        'Documentar la implementación de “' . $standard[1] . '”, definir responsables y conservar evidencias verificables acordes con la realidad de la empresa.',
                    ]);
                }
            }

            $planStatement = Database::connection()->prepare(
                'INSERT OR IGNORE INTO planes_saas (nombre, descripcion, precio_mensual, max_usuarios, max_empleados, modulo) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach (CatalogData::plans() as $plan) {
                $planStatement->execute($plan);
            }

            $activityStatement = Database::connection()->prepare(
                'INSERT OR IGNORE INTO actividades_economicas (codigo_ciuu, descripcion, sector, nivel_riesgo_predeterminado,
                                                                 perfil_sociodemografico, examenes_medicos, matriz_ipevr_base)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach (ActivityCatalog::activities() as $activity) {
                $activityStatement->execute([
                    $activity['codigo_ciuu'], $activity['descripcion'], $activity['sector'],
                    $activity['nivel_riesgo_predeterminado'], $activity['perfil_sociodemografico'],
                    json_encode($activity['examenes_medicos'], JSON_UNESCAPED_UNICODE),
                    json_encode($activity['matriz_ipevr_base'], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }
}
