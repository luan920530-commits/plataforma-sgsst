<?php
declare(strict_types=1);

namespace App\Support;

final class Risk
{
    public static function calculate(?int $deficiency, ?int $exposure, ?int $consequence, string $fallback = 'Medio'): array
    {
        if ($deficiency === null || $exposure === null || $consequence === null) {
            return ['probability' => null, 'score' => null, 'level' => $fallback];
        }
        $probability = $deficiency * $exposure;
        $score = $probability * $consequence;
        $level = match (true) {
            $score >= 600 => 'Critico',
            $score >= 150 => 'Alto',
            $score >= 40 => 'Medio',
            default => 'Bajo',
        };
        return ['probability' => $probability, 'score' => $score, 'level' => $level];
    }
}
