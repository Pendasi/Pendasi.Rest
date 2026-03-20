<?php
declare(strict_types=1);

namespace Pendasi\Rest\Core;

/**
 * Parse/represente la "query string" côté client (Maui) :
 * - orderBy=field desc
 * - limit=10
 * - skip=20
 * - filters: supporte des segments du type field=value ou field>value, etc.
 *
 * Conçu pour être "framework-only" (sans lib externe).
 */
class RequestQuery {
    /** @var array<int, array{field:string, operator:string, value:mixed}> */
    private array $filters = [];
    private ?string $orderByField = null;
    private string $orderByDirection = 'ASC';
    private ?int $limit = null;
    private ?int $skip = null;

    public static function fromRequest(): self {
        $q = new self();
        $q->parseStandardKeys();
        $q->parseFiltersFromRawQueryString();
        return $q;
    }

    /**
     * Reconstruction à partir de RequestQuery::toArray()
     */
    public static function fromArray(array $data): self {
        $q = new self();

        $q->filters = [];
        if (isset($data['filters']) && is_array($data['filters'])) {
            foreach ($data['filters'] as $f) {
                if (!is_array($f)) continue;
                if (!isset($f['field'], $f['operator']) || !array_key_exists('value', $f)) continue;
                $q->filters[] = [
                    'field' => (string)$f['field'],
                    'operator' => (string)$f['operator'],
                    'value' => $f['value'],
                ];
            }
        }

        $orderBy = $data['orderBy'] ?? null;
        if (is_string($orderBy) && trim($orderBy) !== '') {
            $parts = preg_split('/\s+/', trim($orderBy)) ?: [];
            if (count($parts) >= 1) {
                $q->orderByField = (string)$parts[0];
            }
            if (count($parts) >= 2) {
                $dir = strtolower((string)$parts[count($parts) - 1]);
                $q->orderByDirection = $dir === 'desc' ? 'DESC' : 'ASC';
            }
        }

        $limit = $data['limit'] ?? null;
        $skip = $data['skip'] ?? null;

        if ($limit !== null && $limit !== '') {
            $v = filter_var($limit, FILTER_VALIDATE_INT);
            if ($v !== false && $v >= 0) {
                $q->limit = (int)$v;
            }
        }

        if ($skip !== null && $skip !== '') {
            $v = filter_var($skip, FILTER_VALIDATE_INT);
            if ($v !== false && $v >= 0) {
                $q->skip = (int)$v;
            }
        }

        return $q;
    }

    private function parseStandardKeys(): void {
        $limit = $_GET['limit'] ?? null;
        $skip = $_GET['skip'] ?? null;
        $orderBy = $_GET['orderBy'] ?? null;

        if ($limit !== null && $limit !== '') {
            $v = filter_var($limit, FILTER_VALIDATE_INT);
            if ($v !== false && $v >= 0) {
                $this->limit = (int)$v;
            }
        }

        if ($skip !== null && $skip !== '') {
            $v = filter_var($skip, FILTER_VALIDATE_INT);
            if ($v !== false && $v >= 0) {
                $this->skip = (int)$v;
            }
        }

        if (is_string($orderBy) && $orderBy !== '') {
            $orderBy = trim($orderBy);
            $parts = preg_split('/\s+/', $orderBy) ?: [];
            if (count($parts) >= 1) {
                $this->orderByField = (string)$parts[0];
            }
            if (count($parts) >= 2) {
                $dir = strtolower((string)$parts[count($parts) - 1]);
                $this->orderByDirection = $dir === 'desc' ? 'DESC' : 'ASC';
            }
        }
    }

    private function parseFiltersFromRawQueryString(): void {
        $raw = $_SERVER['QUERY_STRING'] ?? '';
        if (!is_string($raw) || $raw === '') {
            return;
        }

        $segments = explode('&', $raw);
        foreach ($segments as $seg) {
            if ($seg === '') continue;

            // ignore standard keys
            if (str_starts_with($seg, 'orderBy=') || str_starts_with($seg, 'limit=') || str_starts_with($seg, 'skip=')) {
                continue;
            }

            $decoded = urldecode($seg);

            // case: field>value, field>=value, field!=value, etc.
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_\.]*)\s*(<=|>=|!=|<>|==|=|<|>)\s*(.+)$/', $decoded, $m)) {
                $this->filters[] = [
                    'field' => $m[1],
                    'operator' => $m[2],
                    'value' => $this->castValue($m[3]),
                ];
                continue;
            }

            // case: field=value (égalité "simple", sans symboles)
            if (strpos($decoded, '=') !== false) {
                [$field, $value] = explode('=', $decoded, 2);
                $field = trim($field);
                if ($field !== '') {
                    $this->filters[] = [
                        'field' => $field,
                        'operator' => '=',
                        'value' => $this->castValue($value),
                    ];
                }
                continue;
            }

            // case: field<op>value where op is text (eq, ne, lt, lte, gt, gte, like)
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_\.]*)\s*(eq|ne|lt|lte|gt|gte|like)\s*(.+)$/i', $decoded, $m)) {
                $op = strtolower($m[2]);
                $map = [
                    'eq' => '=',
                    'ne' => '!=',
                    'lt' => '<',
                    'lte' => '<=',
                    'gt' => '>',
                    'gte' => '>=',
                    'like' => 'LIKE',
                ];

                $this->filters[] = [
                    'field' => $m[1],
                    'operator' => $map[$op] ?? '=',
                    'value' => $this->castValue($m[3]),
                ];
            }
        }
    }

    private function castValue(string $rawValue): mixed {
        $rawValue = trim($rawValue);
        if ($rawValue === '') return '';

        // nombres
        if (is_numeric($rawValue)) {
            return (strpos($rawValue, '.') !== false) ? (float)$rawValue : (int)$rawValue;
        }

        // bool string
        $lower = strtolower($rawValue);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;

        return $rawValue;
    }

    public function applyTo(QueryBuilder $builder): QueryBuilder {
        foreach ($this->filters as $f) {
            $builder->whereOp($f['field'], $f['operator'], $f['value']);
        }

        if ($this->orderByField) {
            $builder->orderBy($this->orderByField, $this->orderByDirection);
        }

        if ($this->limit !== null) {
            $offset = $this->skip ?? 0;
            $builder->limit($this->limit, $offset);
        }

        return $builder;
    }

    public function toArray(): array {
        return [
            'filters' => $this->filters,
            'orderBy' => $this->orderByField ? ($this->orderByField . ' ' . strtolower($this->orderByDirection)) : null,
            'limit' => $this->limit,
            'skip' => $this->skip,
        ];
    }
}

