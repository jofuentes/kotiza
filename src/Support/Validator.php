<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Validación de entradas en servidor con mensajes en español.
 * Reglas: required, email, min:n, max:n, same:campo, in:a,b, numeric, between:a,b, integer, rfc, color, url, date (AAAA-MM-DD)
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data, private array $rules, private array $labels = [])
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data, $rules, $labels);
        $v->run();
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $empty = $value === null || $value === '';
            $label = $this->labels[$field] ?? $field;

            if ($empty) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = "El campo «{$label}» es obligatorio.";
                }
                continue;
            }
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $msg = $this->check($name, $arg, $value, $label);
                if ($msg !== null) {
                    $this->errors[$field] = $msg;
                    break;
                }
            }
        }
    }

    private function check(string $rule, ?string $arg, mixed $value, string $label): ?string
    {
        $str = is_scalar($value) ? (string) $value : '';
        return match ($rule) {
            'required' => null,
            'email' => filter_var($str, FILTER_VALIDATE_EMAIL) ? null : "«{$label}» no es un correo válido.",
            'min' => mb_strlen($str) >= (int) $arg ? null : "«{$label}» debe tener al menos {$arg} caracteres.",
            'max' => mb_strlen($str) <= (int) $arg ? null : "«{$label}» no puede exceder {$arg} caracteres.",
            'same' => $str === (string) ($this->data[$arg] ?? '') ? null : "«{$label}» no coincide.",
            'in' => in_array($str, explode(',', (string) $arg), true) ? null : "«{$label}» tiene un valor no permitido.",
            'numeric' => is_numeric($str) ? null : "«{$label}» debe ser numérico.",
            'integer' => filter_var($str, FILTER_VALIDATE_INT) !== false ? null : "«{$label}» debe ser un número entero.",
            'between' => (function () use ($arg, $str, $label) {
                [$a, $b] = explode(',', (string) $arg);
                return is_numeric($str) && (float) $str >= (float) $a && (float) $str <= (float) $b
                    ? null : "«{$label}» debe estar entre {$a} y {$b}.";
            })(),
            'rfc' => preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', mb_strtoupper($str)) ? null : "«{$label}» no es un RFC válido.",
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $str) ? null : "«{$label}» debe ser un color tipo #1E40AF.",
            'url' => filter_var($str, FILTER_VALIDATE_URL) ? null : "«{$label}» no es una URL válida.",
            'date' => (function () use ($str, $label) {
                $d = \DateTime::createFromFormat('!Y-m-d', $str);
                return $d && $d->format('Y-m-d') === $str ? null : "«{$label}» no es una fecha válida.";
            })(),
            default => throw new \LogicException("Regla de validación desconocida: {$rule}"),
        };
    }
}
