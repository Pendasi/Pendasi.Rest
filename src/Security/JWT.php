<?php
namespace Pendasi\Rest\Security;
use Pendasi\Rest\Core\Config;
class JWT {
    
    /**
     * Générer un token JWT
     */
    public static function generate(array $payload, ?int $expiresIn = null): string {
        $secret = Config::getJwtSecret();
        $expiresIn = $expiresIn ?? Config::getJwtExpiry();

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        $signature = self::sign("$headerEncoded.$payloadEncoded", $secret);

        return "$headerEncoded.$payloadEncoded.$signature";
    }

    /**
     * Vérifier et décoder un token JWT
     */
    public static function verify(string $token): ?array {
        $secret = Config::getJwtSecret();
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Vérifier la signature
        $expectedSignature = self::sign("$headerEncoded.$payloadEncoded", $secret);
        if (!hash_equals($signatureEncoded, $expectedSignature)) {
            return null;
        }

        // Décoder le payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Vérifier l'expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Extraire le token du header Authorization
     */
    public static function getTokenFromHeader(): ?string {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? null;

        if (!$auth) {
            return null;
        }

        // Format: "Bearer <token>"
        if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function sign(string $message, string $secret): string {
        return self::base64UrlEncode(hash_hmac('sha256', $message, $secret, true));
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        $data = strtr($data, '-_', '+/');
        $data .= str_repeat('=', 4 - (strlen($data) % 4));
        return base64_decode($data);
    }
}
