<?php
// Ejecutar: php tests/IndicadoresTest.php (sin base de datos: solo cálculo puro)
require __DIR__ . '/../app/Indicadores.php';
use App\Indicadores;

$r = Indicadores::calcular(
    ['horas_trabajadas' => 48000, 'dias_perdidos' => 6, 'dias_cargados' => 0, 'accidentes_mortales' => 0, 'dias_programados' => 5200, 'dias_ausencia_medica' => 52],
    accidentes: 2, enfermedades: 0
);
$esperado = ['frecuencia' => 10.0, 'severidad' => 30.0, 'ausentismo_pct' => 1.0];
$fallos = 0;
foreach ($esperado as $k => $v) {
    if (abs($r[$k] - $v) > 0.001) { echo "FALLO {$k}: esperado {$v}, obtenido {$r[$k]}" . PHP_EOL; $fallos++; }
    else echo "OK {$k} = {$r[$k]}" . PHP_EOL;
}
// Sin horas trabajadas no debe dividir por cero
$r2 = Indicadores::calcular(['horas_trabajadas' => 0], accidentes: 3, enfermedades: 1);
echo ($r2['frecuencia'] === 0.0 ? 'OK sin HHT no divide por cero' : 'FALLO división por cero') . PHP_EOL;
exit($fallos ? 1 : 0);
