<?php
namespace Pendasi\Rest\Helpers;

class FileUploader {

    /**
     * Upload standard
     *
     * @param array $file $_FILES['file']
     * @param string $path dossier de destination
     * @param array $allowedTypes types MIME autorisés (ex: ['image/jpeg','image/png'])
     * @param int $maxSize taille maximale en octets
     * @return false|string chemin final ou false
     */
    public static function upload(array $file, string $path, array $allowedTypes = [], int $maxSize = 0) {
        // Vérification type
        if ($allowedTypes && !in_array($file['type'], $allowedTypes)) return false;

        // Vérification taille
        if ($maxSize > 0 && $file['size'] > $maxSize) return false;

        // Nom sécurisé
        $filename = self::sanitizeFilename($file['name']);
        $target = rtrim($path,'/').'/'.$filename;

        return move_uploaded_file($file['tmp_name'], $target) ? $target : false;
    }

    /**
     * Upload chunké (fichiers volumineux)
     *
     * @param array $file $_FILES['file']
     * @param string $path dossier de destination
     * @param int $chunkIndex index du chunk (0-based)
     * @param int $totalChunks nombre total de chunks
     * @param string $filename nom final du fichier
     * @param array $allowedTypes types MIME autorisés
     * @param int $maxSize taille maximale totale
     * @return false|string chemin temporaire ou final
     */
    public static function uploadChunked(array $file, string $path, int $chunkIndex, int $totalChunks, string $filename, array $allowedTypes = [], int $maxSize = 0) {
        // Vérification type
        if ($allowedTypes && !in_array($file['type'], $allowedTypes)) return false;

        // Vérification taille totale (approximation)
        if ($maxSize > 0 && ($file['size'] * $totalChunks) > $maxSize) return false;

        // Nom sécurisé
        $filename = self::sanitizeFilename($filename);
        $tempFile = rtrim($path,'/').'/'.$filename.'.part';

        // Ajouter le chunk
        $out = fopen($tempFile, $chunkIndex === 0 ? 'wb' : 'ab');
        if (!$out) return false;

        $in = fopen($file['tmp_name'], 'rb');
        if (!$in) return false;

        while ($buff = fread($in, 4096)) fwrite($out, $buff);

        fclose($in);
        fclose($out);

        // Dernier chunk -> fichier final
        if ($chunkIndex === $totalChunks - 1) {
            $finalPath = rtrim($path,'/').'/'.$filename;
            rename($tempFile, $finalPath);
            return $finalPath;
        }

        return $tempFile;
    }

    /**
     * Sécurise le nom de fichier
     *
     * @param string $filename
     * @return string
     */
    private static function sanitizeFilename(string $filename): string {
        // Supprimer caractères dangereux
        $filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $filename);
        return $filename;
    }
}