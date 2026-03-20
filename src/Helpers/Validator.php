<?php
namespace Pendasi\Rest\Helpers;

class Validator {

    public static function make(array $data, array $rules): array {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $rulesArr = explode('|', $ruleSet);

            foreach ($rulesArr as $rule) {
                $ruleError = self::validateRule($field, $value, $rule);
                
                if ($ruleError) {
                    $errors[$field][] = $ruleError;
                }
            }
        }

        return $errors;
    }

    private static function validateRule(string $field, $value, string $rule): ?string {
        // Extraire le nom de la règle et ses paramètres
        if (strpos($rule, ':') !== false) {
            [$ruleName, $params] = explode(':', $rule, 2);
            $params = array_filter(explode(',', $params));
        } else {
            $ruleName = $rule;
            $params = [];
        }

        switch ($ruleName) {
            case 'required':
                if (empty($value)) {
                    return "required";
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "invalid email";
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return "invalid url";
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    return "must be numeric";
                }
                break;

            case 'integer':
                if (!empty($value) && !ctype_digit((string)$value)) {
                    return "must be integer";
                }
                break;

            case 'min':
                if (!empty($value) && strlen((string)$value) < (int)$params[0]) {
                    return "minimum " . $params[0] . " characters";
                }
                break;

            case 'max':
                if (!empty($value) && strlen((string)$value) > (int)$params[0]) {
                    return "maximum " . $params[0] . " characters";
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match($params[0], (string)$value)) {
                    return "invalid format";
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value !== ($data[$confirmField] ?? null)) {
                    return "confirmation does not match";
                }
                break;

            case 'in':
                $allowedValues = $params;
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    return "invalid value";
                }
                break;

            case 'date':
                if (!empty($value) && strtotime($value) === false) {
                    return "invalid date";
                }
                break;

            case 'nullable':
                // Cette règle signifie que le champ peut être vide
                // Elle n'ajoute pas d'erreur
                break;

            default:
                // Ignore les règles inconnues
                break;
        }

        return null;
    }

    /**
     * Valider un seul champ
     */
    public static function validate(string $field, $value, string $rules): array {
        return self::make([$field => $value], [$field => $rules]);
    }

    /**
     * Vérifier si une validation a des erreurs
     */
    public static function hasErrors(array $errors): bool {
        return !empty($errors);
    }
}
