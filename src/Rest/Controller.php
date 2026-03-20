<?php
namespace Pendasi\Rest\Rest;

class Controller {
    protected function json(array $data){
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }

    /**
     * Réponse "donnée brute" (compatible avec le client Maui qui désérialise directement la data).
     * Exemple: pour une liste => retourne un JSON array, pas { success, data }.
     */
    protected function jsonData($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }

    /**
     * Applique orderBy/where/limit/skip sur un QueryBuilder
     *
     * @param \Pendasi\Rest\Core\QueryBuilder $builder
     * @param \Pendasi\Rest\Core\RequestQuery|array $query
     * @return \Pendasi\Rest\Core\QueryBuilder
     */
    protected function applyQuery(\Pendasi\Rest\Core\QueryBuilder $builder, $query): \Pendasi\Rest\Core\QueryBuilder {
        if ($query instanceof \Pendasi\Rest\Core\RequestQuery) {
            return $query->applyTo($builder);
        }

        if (is_array($query)) {
            $rq = \Pendasi\Rest\Core\RequestQuery::fromArray($query);
            return $rq->applyTo($builder);
        }

        return $builder;
    }
}