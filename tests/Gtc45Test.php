<?php
// Pruebas de la metodología GTC 45. Ejecutar: php tests/Gtc45Test.php
require __DIR__ . '/../app/Gtc45.php';
use App\Gtc45;

$fallos = 0;
$check = function (string $caso, array $real, array $esperado) use (&$fallos) {
    foreach ($esperado as $k => $v) {
        if (($real[$k] ?? null) !== $v) { echo "FALLO {$caso}: {$k} esperado " . var_export($v, true) . ", obtenido " . var_export($real[$k] ?? null, true) . PHP_EOL; $fallos++; return; }
    }
    echo "OK {$caso}" . PHP_EOL;
};

// Trabajo en alturas sin protección: ND Muy alto, exposición continua, consecuencia mortal
$check('riesgo I no aceptable', Gtc45::evaluar(10, 4, 100), ['np' => 40, 'np_nivel' => 'Muy alto', 'nr' => 4000, 'nr_nivel' => 'I', 'aceptabilidad' => 'No aceptable']);
// Riesgo moderado típico de oficina
$check('riesgo III mejorable', Gtc45::evaluar(2, 3, 10), ['np' => 6, 'np_nivel' => 'Medio', 'nr' => 60, 'nr_nivel' => 'III']);
// Sin deficiencia detectada → probabilidad baja, riesgo aceptable
$check('riesgo IV aceptable', Gtc45::evaluar(0, 4, 10), ['np' => 0, 'np_nivel' => 'Bajo', 'nr' => 0, 'nr_nivel' => 'IV', 'aceptabilidad' => 'Aceptable']);
// Límite NR II
$check('riesgo II', Gtc45::evaluar(6, 3, 25), ['np' => 18, 'nr' => 450, 'nr_nivel' => 'II']);

exit($fallos ? 1 : 0);
