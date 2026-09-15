<?php

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function __construct(private array $data, private array $rules)
    {
        $this->run();
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramString] = explode(':', $rule, 2);
                    $params = explode(',', $paramString);
                }

                $this->applyRule($field, $value, $rule, $params);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, array $params): void
    {
        switch ($rule) {
            case 'required':
                if ($value === null || trim((string) $value) === '') {
                    $this->addError($field, 'wajib diisi.');
                }
                break;
            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'harus berupa email yang valid.');
                }
                break;
            case 'min':
                if ($value !== null && strlen((string) $value) < (int) $params[0]) {
                    $this->addError($field, "minimal {$params[0]} karakter.");
                }
                break;
            case 'max':
                if ($value !== null && strlen((string) $value) > (int) $params[0]) {
                    $this->addError($field, "maksimal {$params[0]} karakter.");
                }
                break;
            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->addError($field, 'harus berupa angka.');
                }
                break;
            case 'in':
                if ($value !== null && $value !== '' && !in_array($value, $params, true)) {
                    $this->addError($field, 'nilai tidak valid.');
                }
                break;
            case 'confirmed':
                $confirmField = "{$field}_confirmation";
                if (($this->data[$confirmField] ?? null) !== $value) {
                    $this->addError($field, 'konfirmasi tidak cocok.');
                }
                break;
            case 'unique':
                [$table, $column] = [$params[0], $params[1] ?? $field];
                $excludeId = $params[2] ?? null;
                if ($value !== null && $value !== '') {
                    $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE {$column} = ?";
                    $bindings = [$value];
                    if ($excludeId !== null) {
                        $sql .= " AND id != ?";
                        $bindings[] = $excludeId;
                    }
                    $exists = (int) (Database::fetch($sql, $bindings)['total'] ?? 0) > 0;
                    if ($exists) {
                        $this->addError($field, 'sudah digunakan.');
                    }
                }
                break;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
