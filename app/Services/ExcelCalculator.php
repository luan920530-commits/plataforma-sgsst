<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class ExcelCalculator
{
    private array $values = [];
    private array $formulas = [];
    private array $calculated = [];
    private array $unsupported = [];
    private array $visiting = [];

    public static function calculate(array $form, array $input): array
    {
        $calculator = new self();
        return $calculator->run($form, $input);
    }

    public static function initialValues(array $form): array
    {
        $values = [];
        foreach ($form['sheets'] as $sheet) {
            $name = $sheet['name'];
            $values[$name] = [];
            foreach ($sheet['cells'] as $coordinate => $cell) {
                if (array_key_exists('v', $cell)) {
                    $values[$name][$coordinate] = $cell['v'];
                }
            }
        }
        return $values;
    }

    public static function normalizeInput(array $form, array $input): array
    {
        $values = self::initialValues($form);
        $count = 0;
        foreach ($input as $sheetName => $cells) {
            if ((!is_string($sheetName) && !is_int($sheetName)) || !is_array($cells)) {
                throw new InvalidArgumentException('La estructura enviada no es válida.');
            }
            $sheetName = (string) $sheetName;
            $sheet = self::sheet($form, $sheetName);
            if ($sheet === null || ($sheet['state'] ?? 'visible') !== 'visible') {
                throw new InvalidArgumentException('Se recibió una hoja no editable.');
            }
            $rows = array_flip($sheet['rows']);
            $columns = array_flip($sheet['columns']);
            foreach ($cells as $coordinate => $value) {
                if (++$count > 60000 || !is_string($coordinate) || !is_scalar($value) && $value !== null) {
                    throw new InvalidArgumentException('El formulario supera los límites o contiene valores no válidos.');
                }
                if (!preg_match('/^([A-Z]{1,3})([1-9][0-9]*)$/', $coordinate, $matches) || !isset($rows[(int) $matches[2]]) || !isset($columns[self::columnNumber($matches[1])]) || isset($sheet['merged'][$coordinate])) {
                    throw new InvalidArgumentException('Se recibió una celda fuera de la plantilla.');
                }
                if (isset($sheet['cells'][$coordinate]['f'])) {
                    continue;
                }
                $value = trim((string) $value);
                if (strlen($value) > 5000) {
                    throw new InvalidArgumentException('Una celda supera el máximo de 5.000 caracteres.');
                }
                $values[$sheetName][$coordinate] = $value;
            }
        }
        return $values;
    }

    private function run(array $form, array $values): array
    {
        $this->values = $values;
        foreach ($form['sheets'] as $sheet) {
            foreach ($sheet['cells'] as $coordinate => $cell) {
                if (isset($cell['f'])) {
                    $this->formulas[$sheet['name']][$coordinate] = $cell['f'];
                }
            }
        }
        foreach ($this->formulas as $sheetName => $cells) {
            foreach (array_keys($cells) as $coordinate) {
                $this->evaluateCell((string) $sheetName, $coordinate);
            }
        }
        $calculated = [];
        foreach ($this->calculated as $sheetName => $cells) {
            foreach ($cells as $coordinate => $value) {
                $calculated[$sheetName][$coordinate] = $value instanceof ExcelError ? $value->message : $value;
            }
        }
        return ['values' => $this->values, 'calculated' => $calculated, 'unsupported' => $this->unsupported];
    }

    private function evaluateCell(string $sheet, string $coordinate): mixed
    {
        if (isset($this->calculated[$sheet]) && array_key_exists($coordinate, $this->calculated[$sheet])) {
            return $this->calculated[$sheet][$coordinate];
        }
        if (!isset($this->formulas[$sheet][$coordinate])) {
            return $this->values[$sheet][$coordinate] ?? '';
        }
        $key = $sheet . '!' . $coordinate;
        if (isset($this->visiting[$key])) {
            return new ExcelError('#CYCLE!');
        }
        $this->visiting[$key] = true;
        $formula = $this->formulas[$sheet][$coordinate];
        try {
            $parser = new ExcelExpression($formula, $sheet, fn(string $reference, string $current): mixed => $this->reference($reference, $current));
            $result = $parser->parse();
            if (is_array($result)) {
                $result = new ExcelError('#VALUE!');
            }
        } catch (InvalidArgumentException) {
            $result = $this->values[$sheet][$coordinate] ?? '';
            $this->unsupported[$key] = ['sheet' => $sheet, 'coordinate' => $coordinate, 'formula' => $formula];
        } finally {
            unset($this->visiting[$key]);
        }
        $this->calculated[$sheet][$coordinate] = $result;
        return $result;
    }

    private function reference(string $reference, string $currentSheet): mixed
    {
        $parts = explode('!', $reference, 2);
        $sheet = count($parts) === 2 ? trim($parts[0], "'") : $currentSheet;
        $sheet = str_replace("''", "'", $sheet);
        $address = str_replace('$', '', count($parts) === 2 ? $parts[1] : $parts[0]);
        if (!isset($this->values[$sheet]) && !isset($this->formulas[$sheet])) {
            return new ExcelError('#REF!');
        }
        $bounds = explode(':', $address, 2);
        $start = $this->coordinate($bounds[0]);
        $end = count($bounds) === 2 ? $this->coordinate($bounds[1]) : $start;
        if (($end[0] - $start[0] + 1) * ($end[1] - $start[1] + 1) > 20000) {
            throw new InvalidArgumentException('Rango demasiado grande.');
        }
        if (count($bounds) === 1) {
            return $this->evaluateCell($sheet, self::columnName($start[1]) . $start[0]);
        }
        $result = [];
        for ($row = $start[0]; $row <= $end[0]; $row++) {
            for ($column = $start[1]; $column <= $end[1]; $column++) {
                $result[] = $this->evaluateCell($sheet, self::columnName($column) . $row);
            }
        }
        return $result;
    }

    private function coordinate(string $address): array
    {
        if (!preg_match('/^([A-Z]{1,3})([1-9][0-9]*)$/i', $address, $matches)) {
            throw new InvalidArgumentException('Referencia no compatible.');
        }
        return [(int) $matches[2], self::columnNumber(strtoupper($matches[1]))];
    }

    private static function sheet(array $form, string $name): ?array
    {
        foreach ($form['sheets'] as $sheet) {
            if ($sheet['name'] === $name) {
                return $sheet;
            }
        }
        return null;
    }

    private static function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }
        return $number;
    }

    private static function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + $column % 26) . $name;
            $column = intdiv($column, 26);
        }
        return $name;
    }
}

final class ExcelError
{
    public function __construct(public readonly string $message) {}
}

final class ExcelExpression
{
    private array $tokens = [];
    private int $position = 0;

    public function __construct(string $formula, private readonly string $sheet, private readonly mixed $resolver)
    {
        $this->tokens = $this->tokenize(str_replace(['_xlfn.', '_xlws.'], '', $formula));
    }

    public function parse(): mixed
    {
        $result = $this->comparison();
        if ($this->position !== count($this->tokens)) {
            throw new InvalidArgumentException('Tokens adicionales.');
        }
        return $result;
    }

    private function comparison(): mixed
    {
        $left = $this->concat();
        while (in_array($this->peek(), ['=', '<>', '<', '>', '<=', '>='], true)) {
            $operator = $this->tokens[$this->position++]['value'];
            $right = $this->concat();
            $left = $this->compare($left, $right, $operator);
        }
        return $left;
    }

    private function concat(): mixed
    {
        $left = $this->additive();
        while ($this->peek() === '&') {
            $this->position++;
            $right = $this->additive();
            if ($left instanceof ExcelError) return $left;
            if ($right instanceof ExcelError) return $right;
            $left = $this->text($left) . $this->text($right);
        }
        return $left;
    }

    private function additive(): mixed
    {
        $left = $this->multiplicative();
        while (in_array($this->peek(), ['+', '-'], true)) {
            $operator = $this->tokens[$this->position++]['value'];
            $right = $this->multiplicative();
            $left = $this->arithmetic($left, $right, $operator);
        }
        return $left;
    }

    private function multiplicative(): mixed
    {
        $left = $this->power();
        while (in_array($this->peek(), ['*', '/'], true)) {
            $operator = $this->tokens[$this->position++]['value'];
            $right = $this->power();
            $left = $this->arithmetic($left, $right, $operator);
        }
        return $left;
    }

    private function power(): mixed
    {
        $left = $this->unary();
        if ($this->peek() === '^') {
            $this->position++;
            return $this->arithmetic($left, $this->power(), '^');
        }
        return $left;
    }

    private function unary(): mixed
    {
        if (in_array($this->peek(), ['+', '-'], true)) {
            $operator = $this->tokens[$this->position++]['value'];
            $value = $this->unary();
            if ($operator === '-') {
                $number = $this->number($value);
                return $number instanceof ExcelError ? $number : -$number;
            }
            return $value;
        }
        return $this->postfix();
    }

    private function postfix(): mixed
    {
        $value = $this->primary();
        while ($this->peek() === '%') {
            $this->position++;
            $number = $this->number($value);
            $value = $number instanceof ExcelError ? $number : $number / 100;
        }
        return $value;
    }

    private function primary(): mixed
    {
        $token = $this->tokens[$this->position++] ?? null;
        if ($token === null) throw new InvalidArgumentException('Expresión incompleta.');
        if (in_array($token['type'], ['number', 'string'], true)) return $token['value'];
        if ($token['type'] === 'error') return new ExcelError($token['value']);
        if ($token['type'] === 'reference') return ($this->resolver)($token['value'], $this->sheet);
        if ($token['value'] === '(') {
            $value = $this->comparison();
            $this->expect(')');
            return $value;
        }
        if ($token['type'] === 'name') {
            $name = strtoupper($token['value']);
            if ($this->peek() !== '(') {
                if ($name === 'TRUE') return true;
                if ($name === 'FALSE') return false;
                throw new InvalidArgumentException('Nombre definido no compatible.');
            }
            $this->position++;
            $arguments = [];
            if ($this->peek() !== ')') {
                $arguments[] = $this->comparison();
                while ($this->peek() === ',') {
                    $this->position++;
                    $arguments[] = $this->comparison();
                }
            }
            $this->expect(')');
            return $this->function($name, $arguments);
        }
        throw new InvalidArgumentException('Expresión no compatible.');
    }

    private function function(string $name, array $arguments): mixed
    {
        if ($name === 'IF') {
            if (count($arguments) < 2) throw new InvalidArgumentException('IF incompleto.');
            $condition = $this->truth($arguments[0]);
            if ($condition instanceof ExcelError) return $condition;
            return $condition ? $arguments[1] : ($arguments[2] ?? false);
        }
        if ($name === 'IFERROR') {
            if (count($arguments) !== 2) throw new InvalidArgumentException('IFERROR incompleto.');
            return $arguments[0] instanceof ExcelError ? $arguments[1] : $arguments[0];
        }
        if ($name === 'SUMIF') {
            return $this->sumif($arguments);
        }
        if (!in_array($name, ['SUM', 'COUNT', 'MAX', 'AVERAGE'], true)) {
            throw new InvalidArgumentException('Función no compatible: ' . $name);
        }
        $values = $this->flatten($arguments);
        foreach ($values as $value) if ($value instanceof ExcelError) return $value;
        $numbers = array_values(array_map(static fn($value) => (float) $value, array_filter($values, static fn($value): bool => is_int($value) || is_float($value) || is_string($value) && is_numeric($value))));
        if ($name === 'COUNT') return count($numbers);
        if ($name === 'SUM') return array_sum($numbers);
        if ($name === 'MAX') return $numbers === [] ? 0 : max($numbers);
        return $numbers === [] ? new ExcelError('#DIV/0!') : array_sum($numbers) / count($numbers);
    }

    private function sumif(array $arguments): mixed
    {
        if (count($arguments) < 2) throw new InvalidArgumentException('SUMIF incompleto.');
        $range = is_array($arguments[0]) ? $this->flatten([$arguments[0]]) : [$arguments[0]];
        $sum = isset($arguments[2]) ? (is_array($arguments[2]) ? $this->flatten([$arguments[2]]) : [$arguments[2]]) : $range;
        $total = 0;
        foreach ($range as $index => $value) {
            if (!$this->matches($value, $arguments[1])) continue;
            $candidate = $sum[$index] ?? 0;
            if ($candidate instanceof ExcelError) return $candidate;
            if (is_int($candidate) || is_float($candidate) || is_string($candidate) && is_numeric($candidate)) $total += (float) $candidate;
        }
        return $total;
    }

    private function matches(mixed $value, mixed $criteria): bool
    {
        if (is_string($criteria) && preg_match('/^(<>|>=|<=|=|>|<)(.*)$/', $criteria, $matches)) {
            $operand = trim($matches[2]);
            if (is_numeric($operand)) $operand = (float) $operand;
            return $this->compare($value, $operand, $matches[1]) === true;
        }
        if (is_string($criteria) && strpbrk($criteria, '*?') !== false) {
            $pattern = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($criteria, '/')) . '$/iu';
            return preg_match($pattern, (string) $value) === 1;
        }
        return $this->compare($value, $criteria, '=') === true;
    }

    private function compare(mixed $left, mixed $right, string $operator): mixed
    {
        if ($left instanceof ExcelError) return $left;
        if ($right instanceof ExcelError) return $right;
        if (is_array($left) || is_array($right)) return new ExcelError('#VALUE!');
        if ((is_numeric($left) || is_int($left) || is_float($left)) && (is_numeric($right) || is_int($right) || is_float($right))) {
            $left = (float) $left; $right = (float) $right;
        } else {
            $left = text_lower((string) $left); $right = text_lower((string) $right);
        }
        return match ($operator) {
            '=' => $left == $right, '<>' => $left != $right, '>' => $left > $right,
            '<' => $left < $right, '>=' => $left >= $right, '<=' => $left <= $right,
            default => throw new InvalidArgumentException('Comparador no compatible.'),
        };
    }

    private function arithmetic(mixed $left, mixed $right, string $operator): mixed
    {
        $a = $this->number($left); if ($a instanceof ExcelError) return $a;
        $b = $this->number($right); if ($b instanceof ExcelError) return $b;
        return match ($operator) {
            '+' => $a + $b, '-' => $a - $b, '*' => $a * $b,
            '/' => $b == 0.0 ? new ExcelError('#DIV/0!') : $a / $b,
            '^' => $a ** $b, default => throw new InvalidArgumentException('Operador no compatible.'),
        };
    }

    private function number(mixed $value): mixed
    {
        if ($value instanceof ExcelError) return $value;
        if (is_array($value)) return new ExcelError('#VALUE!');
        if ($value === null || $value === '') return 0;
        if (is_bool($value)) return $value ? 1 : 0;
        if (is_int($value) || is_float($value) || is_string($value) && is_numeric($value)) return (float) $value;
        return new ExcelError('#VALUE!');
    }

    private function truth(mixed $value): mixed
    {
        if ($value instanceof ExcelError) return $value;
        if (is_array($value)) return new ExcelError('#VALUE!');
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (float) $value !== 0.0;
        return text_lower((string) $value) === 'true';
    }

    private function text(mixed $value): string
    {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
        if (is_float($value) || is_int($value)) return sprintf('%.12G', $value);
        return (string) $value;
    }

    private function flatten(array $values): array
    {
        $result = [];
        array_walk_recursive($values, static function ($value) use (&$result): void { $result[] = $value; });
        return $result;
    }

    private function peek(): mixed
    {
        return $this->tokens[$this->position]['value'] ?? null;
    }

    private function expect(string $value): void
    {
        if ($this->peek() !== $value) throw new InvalidArgumentException('Se esperaba ' . $value);
        $this->position++;
    }

    private function tokenize(string $formula): array
    {
        $tokens = []; $offset = 0; $length = strlen($formula);
        while ($offset < $length) {
            $rest = substr($formula, $offset);
            if (preg_match('/^\s+/', $rest, $match)) { $offset += strlen($match[0]); continue; }
            if (preg_match('/^"(?:[^"]|"")*"/', $rest, $match)) {
                $tokens[] = ['type' => 'string', 'value' => str_replace('""', '"', substr($match[0], 1, -1))];
            } elseif (preg_match('/^#(?:DIV\/0!|N\/A|VALUE!|REF!|NAME\?|NUM!|NULL!)/i', $rest, $match)) {
                $tokens[] = ['type' => 'error', 'value' => strtoupper($match[0])];
            } elseif (preg_match('/^(?:(?:\'(?:[^\']|\'\')+\'|[A-Za-z_][A-Za-z0-9_. ]*)!)?\$?[A-Z]{1,3}\$?[1-9][0-9]*(?::\$?[A-Z]{1,3}\$?[1-9][0-9]*)?/i', $rest, $match)) {
                $tokens[] = ['type' => 'reference', 'value' => $match[0]];
            } elseif (preg_match('/^(?:\d+\.\d*|\.\d+|\d+)(?:E[+-]?\d+)?/i', $rest, $match)) {
                $tokens[] = ['type' => 'number', 'value' => (float) $match[0]];
            } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_.]*/', $rest, $match)) {
                $tokens[] = ['type' => 'name', 'value' => $match[0]];
            } elseif (preg_match('/^(?:<>|<=|>=|[-+*\/^&=<>%,();])/', $rest, $match)) {
                $tokens[] = ['type' => 'operator', 'value' => $match[0] === ';' ? ',' : $match[0]];
            } else {
                throw new InvalidArgumentException('Token no compatible.');
            }
            $offset += strlen($match[0]);
        }
        return $tokens;
    }
}
