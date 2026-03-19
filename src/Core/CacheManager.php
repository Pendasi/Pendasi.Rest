<?php
namespace Pendasi\Rest\Core;

/**
 * CacheManager - Système de cache simple et natif
 * Utilise uniquement le système de fichiers (pas de dépendances externes)
 */
class CacheManager {
    private string $driver = 'file';
    private string $cacheDir;

    public function __construct(string $cacheDir = '') {
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir();
    }

    /**
     * Mettre une valeur en cache
     */
    public function put(string $key, $value, int $ttl = 3600): bool {
        return $this->putFile($key, $value, $ttl);
    }

    /**
     * Récupérer une valeur du cache
     */
    public function get(string $key, $default = null) {
        return $this->getFile($key, $default);
    }

    /**
     * Vérifier si une clé existe et n'a pas expiré
     */
    public function has(string $key): bool {
        return $this->hasFile($key);
    }

    /**
     * Supprimer une clé du cache
     */
    public function delete(string $key): bool {
        return $this->deleteFile($key);
    }

    /**
     * Vider tout le cache
     */
    public function flush(): bool {
        return $this->flushFile();
    }

    /**
     * Remember - récupérer ou créer
     */
    public function remember(string $key, callable $callback, int $ttl = 3600) {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    /**
     * Obtenir et supprimer
     */
    public function pull(string $key, $default = null) {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * Obtenir toutes les clés en cache
     */
    public function keys(): array {
        $files = glob($this->cacheDir . '/cache_*');
        $keys = [];

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && $data['expires'] >= time()) {
                $keys[] = $data['key'];
            }
        }

        return $keys;
    }

    /**
     * Incrémenter une valeur numérique
     */
    public function increment(string $key, int $value = 1, int $ttl = 3600): int {
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->put($key, $new, $ttl);
        return $new;
    }

    /**
     * Décrémenter une valeur numérique
     */
    public function decrement(string $key, int $value = 1, int $ttl = 3600): int {
        $current = (int)$this->get($key, 0);
        $new = $current - $value;
        $this->put($key, $new, $ttl);
        return $new;
    }

    // ======================== FICHIER CACHE NATIVES ========================

    private function putFile(string $key, $value, int $ttl): bool {
        $filename = $this->getFilepath($key);
        
        $data = [
            'key' => $key,
            'value' => serialize($value),
            'expires' => time() + $ttl,
            'stored' => date('Y-m-d H:i:s')
        ];

        return (bool)file_put_contents($filename, json_encode($data));
    }

    private function getFile(string $key, $default = null) {
        $filename = $this->getFilepath($key);

        if (!file_exists($filename)) {
            return $default;
        }

        $data = json_decode(file_get_contents($filename), true);

        if (!$data || $data['expires'] < time()) {
            @unlink($filename);
            return $default;
        }

        return unserialize($data['value']);
    }

    private function hasFile(string $key): bool {
        $filename = $this->getFilepath($key);

        if (!file_exists($filename)) {
            return false;
        }

        $data = json_decode(file_get_contents($filename), true);
        
        if (!$data || $data['expires'] < time()) {
            @unlink($filename);
            return false;
        }

        return true;
    }

    private function deleteFile(string $key): bool {
        $filename = $this->getFilepath($key);

        if (file_exists($filename)) {
            return @unlink($filename);
        }

        return true;
    }

    private function flushFile(): bool {
        $files = glob($this->cacheDir . '/cache_*');

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }

    private function getFilepath(string $key): string {
        return $this->cacheDir . '/cache_' . sha1($key);
    }
}

