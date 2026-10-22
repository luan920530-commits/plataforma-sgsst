<?php
declare(strict_types=1);

namespace App\Support;

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        if (trim((string) ($this->data[$field] ?? '')) === '') {
            $this->errors[$field] ??= "El campo {$label} es obligatorio.";
        }
        return $this;
    }

    public function email(string $field, string $label = 'correo electrónico'): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] ??= "Ingresa un {$label} válido.";
        }
        return $this;
    }

    public function integer(string $field, string $label, int $min = 0, ?int $max = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '') {
            $valid = filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= $min && ($max === null || (int) $value <= $max);
            if (!$valid) {
                $this->errors[$field] ??= "El campo {$label} debe ser un número entero" . ($max === null ? " mayor o igual a {$min}." : " entre {$min} y {$max}.");
            }
        }
        return $this;
    }

    public function numeric(string $field, string $label, float $min = 0): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && (!is_numeric($value) || (float) $value < $min)) {
            $this->errors[$field] ??= "El campo {$label} debe ser numérico y mayor o igual a {$min}.";
        }
        return $this;
    }

    public function in(string $field, string $label, array $allowed): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] ??= "El valor seleccionado para {$label} no es válido.";
        }
        return $this;
    }

    public function max(string $field, string $label, int $length): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (text_length($value) > $length) {
            $this->errors[$field] ??= "El campo {$label} no puede superar {$length} caracteres.";
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            if ($date === false || $date->format('Y-m-d') !== $value) {
                $this->errors[$field] ??= "El campo {$label} debe tener una fecha válida.";
            }
        }
        return $this;
    }

    public function password(string $field, string $label = 'contraseña'): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && (strlen($value) < 10 || !preg_match('/[a-z]/', $value) || !preg_match('/[A-Z]/', $value) || !preg_match('/[0-9]/', $value))) {
            $this->errors[$field] ??= "La {$label} debe tener mínimo 10 caracteres, mayúsculas, minúsculas y números.";
        }
        return $this;
    }

    public function confirmed(string $field, string $confirmation): self
    {
        if (($this->data[$field] ?? '') !== ($this->data[$confirmation] ?? '')) {
            $this->errors[$confirmation] ??= 'La confirmación de contraseña no coincide.';
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
