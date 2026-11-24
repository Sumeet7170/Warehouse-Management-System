<?php

final class V
{
    public static function clean(array $input, array $rules, array $labels = []): array
    {
        $out = [];
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $raw = $input[$field] ?? null;
            if (is_string($raw)) {
                $raw = trim($raw);
            }
            if (in_array('bool', $ruleList, true)) {
                $out[$field] = filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                continue;
            }
            if ($raw === null || $raw === '') {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field] = $label . ' is required.';
                }
                $out[$field] = null;
                continue;
            }
            $type = 'string';
            $min = null;
            $max = null;
            $in = null;
            foreach ($ruleList as $rule) {
                if (in_array($rule, ['string', 'int', 'numeric', 'date', 'email'], true)) {
                    $type = $rule;
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (float)substr($rule, 4);
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (float)substr($rule, 4);
                } elseif (str_starts_with($rule, 'in:')) {
                    $in = explode(',', substr($rule, 3));
                }
            }
            $value = $raw;
            switch ($type) {
                case 'int':
                    $value = filter_var($raw, FILTER_VALIDATE_INT);
                    if ($value === false) {
                        $errors[$field] = $label . ' must be a whole number.';
                        $out[$field] = null;
                        continue 2;
                    }
                    break;
                case 'numeric':
                    if (!is_numeric($raw) || !is_finite((float)$raw)) {
                        $errors[$field] = $label . ' must be a valid number.';
                        $out[$field] = null;
                        continue 2;
                    }
                    $value = round((float)$raw, 2);
                    break;
                case 'date':
                    $date = is_string($raw) ? DateTime::createFromFormat('!Y-m-d', $raw) : false;
                    if (!$date || $date->format('Y-m-d') !== $raw) {
                        $errors[$field] = $label . ' must be a valid date (YYYY-MM-DD).';
                        $out[$field] = null;
                        continue 2;
                    }
                    break;
                case 'email':
                    if (!is_string($raw) || !filter_var($raw, FILTER_VALIDATE_EMAIL) || strlen($raw) > 150) {
                        $errors[$field] = $label . ' must be a valid email address.';
                        $out[$field] = null;
                        continue 2;
                    }
                    break;
                default:
                    if (!is_scalar($raw)) {
                        $errors[$field] = $label . ' is invalid.';
                        $out[$field] = null;
                        continue 2;
                    }
                    $value = (string)$raw;
            }
            if ($in !== null && !in_array((string)$value, $in, true)) {
                $errors[$field] = $label . ' has an invalid value.';
                $out[$field] = null;
                continue;
            }
            if ($type === 'string' || $type === 'email') {
                $length = mb_strlen((string)$value);
                if ($max !== null && $length > $max) {
                    $errors[$field] = $label . ' must not exceed ' . (int)$max . ' characters.';
                } elseif ($min !== null && $length < $min) {
                    $errors[$field] = $label . ' must be at least ' . (int)$min . ' characters.';
                }
            } elseif ($type === 'int' || $type === 'numeric') {
                if ($min !== null && $value < $min) {
                    $errors[$field] = $label . ' must be at least ' . $min . '.';
                } elseif ($max !== null && $value > $max) {
                    $errors[$field] = $label . ' must not exceed ' . $max . '.';
                }
            }
            $out[$field] = $value;
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $out;
    }

    public static function id(mixed $value, string $label = 'Record'): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw new AppException($label . ' is invalid.', 422);
        }
        return $id;
    }
}
