<?php

class JwtHelper
{
    private string $secret;
    private string $alg = 'HS256';

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'your-default-fallback-secret-only-for-dev';

        if ($this->secret === 'your-default-fallback-secret-only-for-dev') {
            error_log("SECURITY WARNING: Using default JWT secret. Set the JWT_SECRET environment variable for production!");
        }
        if (empty($this->secret)) {
            throw new \Exception("JWT Secret Key is not configured. Set the JWT_SECRET environment variable.");
        }
    }

    public function generateToken(array $payload, int $expireInSeconds = 3600): string
    {
        $header = json_encode(['alg' => $this->alg, 'typ' => 'JWT']);

        $payload['iat'] = time();
        $payload['exp'] = time() + $expireInSeconds;

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    public function verifyToken(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            error_log("JWT Verify Error: Invalid token structure (not 3 parts)");
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        $payloadJson = $this->base64UrlDecode($base64UrlPayload);
        $payload = json_decode($payloadJson, true);

        if ($payload === null) {
            error_log("JWT Verify Error: Invalid payload encoding");
            return null;
        }

        $signatureToCheck = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignatureToCheck = $this->base64UrlEncode($signatureToCheck);

        if (!hash_equals($base64UrlSignature, $base64UrlSignatureToCheck)) {
            error_log("JWT Verify Error: Signature verification failed");
            return null;
        }

        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            error_log("JWT Verify Error: Token has expired (exp=" . ($payload['exp'] ?? 'null') . ")");
            return null;
        }

        if (isset($payload['iat']) && $payload['iat'] > time() + 60) {
            error_log("JWT Verify Error: Invalid issue time (iat=" . $payload['iat'] . ")");
            return null;
        }

        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            error_log("JWT Verify Error: Token not valid yet (nbf=" . $payload['nbf'] . ")");
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}