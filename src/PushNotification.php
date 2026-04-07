<?php

class PushNotification
{
    private static ?PushNotification $instance = null;
    private array $config;
    private string $serviceAccountPath;
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;
    private string $logFile;
    private string $fcmLogFile;

    private function __construct()
    {
        $this->config = require __DIR__ . '/config.php';
        $this->serviceAccountPath = __DIR__ . '/bartery-1-firebase-adminsdk-fbsvc-20493bcfca.json';
        $this->logFile = __DIR__ . '/../logs/push_errors.log';
        $this->fcmLogFile = __DIR__ . '/../logs/fcm_notifications.log';
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function getInstance(): PushNotification
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log error to file
     */
    private function logError(string $method, string $message, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'message' => $message,
            'context' => $context,
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log FCM notification details to dedicated log file
     */
    private function logFcm(string $event, array $data = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'data' => $data,
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($this->fcmLogFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get OAuth2 access token for FCM HTTP v1 API
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        if (!file_exists($this->serviceAccountPath)) {
            $errorMsg = 'Firebase service account key file not found: ' . $this->serviceAccountPath;
            $this->logError('getAccessToken', $errorMsg, ['path' => $this->serviceAccountPath]);
            throw new Exception($errorMsg);
        }

        $serviceAccountJson = file_get_contents($this->serviceAccountPath);
        $serviceAccount = json_decode($serviceAccountJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'Invalid JSON in service account file: ' . json_last_error_msg();
            $this->logError('getAccessToken', $errorMsg, [
                'file' => $this->serviceAccountPath,
                'json_error' => json_last_error_msg(),
                'raw_content_preview' => substr($serviceAccountJson, 0, 200),
            ]);
            throw new Exception($errorMsg);
        }

        // Create JWT
        $now = time();
        $expiry = $now + 3600; // 1 hour

        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]);

        $payload = json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $serviceAccount['token_uri'],
            'iat' => $now,
            'exp' => $expiry,
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signatureInput = $base64UrlHeader . '.' . $base64UrlPayload;

        // Sign with private key (suppress deprecation warnings)
        $privateKey = @openssl_pkey_get_private($serviceAccount['private_key']);
        if ($privateKey === false) {
            $errorMsg = 'Invalid private key in service account: ' . openssl_error_string();
            $this->logError('getAccessToken', $errorMsg, ['openssl_errors' => openssl_error_string()]);
            throw new Exception($errorMsg);
        }
        
        $signature = '';
        @openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        // openssl_free_key() is deprecated in PHP 8+, keys are freed automatically
        if (PHP_VERSION_ID < 80000) {
            @openssl_free_key($privateKey);
        }

        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $signatureInput . '.' . $base64UrlSignature;

        // Exchange JWT for access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serviceAccount['token_uri']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorMsg = "Failed to get FCM access token (HTTP {$httpCode})";
            $this->logError('getAccessToken', $errorMsg, [
                'http_code' => $httpCode,
                'response' => $response,
                'curl_error' => $curlError,
                'token_uri' => $serviceAccount['token_uri'],
            ]);
            throw new Exception("{$errorMsg}. Response: {$response}");
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'Invalid JSON in token response: ' . json_last_error_msg();
            $this->logError('getAccessToken', $errorMsg, [
                'http_code' => $httpCode,
                'response' => $response,
                'json_error' => json_last_error_msg(),
            ]);
            throw new Exception($errorMsg);
        }

        if (!isset($result['access_token'])) {
            $errorMsg = 'No access_token in token response';
            $this->logError('getAccessToken', $errorMsg, [
                'http_code' => $httpCode,
                'response_keys' => array_keys($result),
                'response' => $response,
            ]);
            throw new Exception($errorMsg);
        }

        $this->accessToken = $result['access_token'];
        $this->tokenExpiresAt = $now + ($result['expires_in'] - 300); // 5 min buffer

        return $this->accessToken;
    }

    /**
     * Send push notification to a specific user
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $this->logFcm('send_attempt', [
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        try {
            $db = Database::get();
            $stmt = $db->prepare('SELECT id, push_token, platform FROM user_push_tokens WHERE user_id = ?');
            $stmt->execute([$userId]);
            $tokens = $stmt->fetchAll();

            $this->logFcm('tokens_found', [
                'user_id' => $userId,
                'token_count' => count($tokens),
                'tokens' => array_map(fn($t) => [
                    'id' => $t['id'],
                    'platform' => $t['platform'],
                    'token_preview' => substr($t['push_token'], 0, 30) . '...',
                ], $tokens),
            ]);

            if (empty($tokens)) {
                $this->logError('sendToUser', 'No push tokens found for user', ['user_id' => $userId]);
                $this->logFcm('no_tokens_found', ['user_id' => $userId]);
                return ['success' => false, 'message' => 'No push tokens found for user'];
            }

            $projectId = 'bartery-1';
            $results = [];

            foreach ($tokens as $tokenData) {
                try {
                    $this->logFcm('sending_to_token', [
                        'token_id' => $tokenData['id'],
                        'platform' => $tokenData['platform'],
                        'token_preview' => substr($tokenData['push_token'], 0, 30) . '...',
                    ]);

                    $result = $this->sendPushV1(
                        $projectId,
                        $tokenData['push_token'],
                        $title,
                        $body,
                        $data
                    );
                    $results[] = $result;

                    $this->logFcm('token_send_result', [
                        'token_id' => $tokenData['id'],
                        'success' => $result['success'],
                        'http_code' => $result['http_code'] ?? null,
                        'invalid_token' => $result['invalid_token'] ?? false,
                    ]);

                    // Remove invalid tokens
                    if (!$result['success'] && $result['invalid_token']) {
                        $deleteStmt = $db->prepare('DELETE FROM user_push_tokens WHERE id = ?');
                        $deleteStmt->execute([$tokenData['id']]);
                        $this->logError('sendToUser', 'Removed invalid token', [
                            'token_id' => $tokenData['id'],
                            'user_id' => $userId,
                            'result' => $result,
                        ]);
                        $this->logFcm('invalid_token_removed', [
                            'token_id' => $tokenData['id'],
                            'user_id' => $userId,
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logError('sendToUser', 'Exception while sending push', [
                        'token_id' => $tokenData['id'],
                        'user_id' => $userId,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->logFcm('token_send_exception', [
                        'token_id' => $tokenData['id'],
                        'user_id' => $userId,
                        'exception' => $e->getMessage(),
                    ]);
                    $results[] = ['success' => false, 'invalid_token' => false, 'error' => $e->getMessage()];
                }
            }

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            
            if ($successCount === 0) {
                $this->logError('sendToUser', 'All push notifications failed', [
                    'user_id' => $userId,
                    'total_tokens' => count($tokens),
                    'results' => $results,
                ]);
            }
            
            $this->logFcm('send_summary', [
                'user_id' => $userId,
                'success_count' => $successCount,
                'total_tokens' => count($tokens),
                'overall_success' => $successCount > 0,
            ]);

            return [
                'success' => $successCount > 0,
                'sent_to' => $successCount,
                'total' => count($tokens),
                'results' => $results
            ];
        } catch (Exception $e) {
            $this->logError('sendToUser', 'Fatal error in sendToUser', [
                'user_id' => $userId,
                'title' => $title,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->logFcm('send_fatal_error', [
                'user_id' => $userId,
                'title' => $title,
                'exception' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send push notification via FCM HTTP v1 API
     */
    private function sendPushV1(string $projectId, string $token, string $title, string $body, array $data = []): array
    {
        $this->logFcm('fcm_request_start', [
            'project_id' => $projectId,
            'token_preview' => substr($token, 0, 30) . '...',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        try {
            $accessToken = $this->getAccessToken();

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                ],
            ];

            $headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ];

            $this->logFcm('fcm_request_details', [
                'url' => $url,
                'message' => $message,
                'headers_count' => count($headers),
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->logError('sendPushV1', 'cURL error', [
                    'url' => $url,
                    'curl_error' => $error,
                    'http_code' => $httpCode,
                    'token_preview' => substr($token, 0, 20) . '...',
                ]);
                $this->logFcm('fcm_curl_error', [
                    'url' => $url,
                    'curl_error' => $error,
                    'http_code' => $httpCode,
                    'token_preview' => substr($token, 0, 20) . '...',
                ]);
                return ['success' => false, 'invalid_token' => false, 'error' => $error];
            }

            // Try to parse JSON response
            $jsonResponse = json_decode($response, true);
            $jsonError = json_last_error();

            // Check for invalid token errors
            $invalidToken = in_array($httpCode, [400, 401, 404]) ||
                (is_string($response) && stripos($response, 'UNREGISTERED') !== false) ||
                (is_string($response) && stripos($response, 'INVALID_ARGUMENT') !== false);

            if ($httpCode !== 200) {
                $this->logError('sendPushV1', "FCM returned HTTP {$httpCode}", [
                    'url' => $url,
                    'http_code' => $httpCode,
                    'response' => $response,
                    'response_parsed' => $jsonResponse,
                    'json_error' => $jsonError !== JSON_ERROR_NONE ? json_last_error_msg() : null,
                    'token_preview' => substr($token, 0, 20) . '...',
                    'message_preview' => json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'invalid_token' => $invalidToken,
                ]);
            }

            $this->logFcm('fcm_response', [
                'http_code' => $httpCode,
                'response' => $response,
                'response_parsed' => $jsonResponse,
                'success' => $httpCode === 200,
                'invalid_token' => $invalidToken,
            ]);

            return [
                'success' => $httpCode === 200,
                'invalid_token' => $invalidToken,
                'response' => $response,
                'http_code' => $httpCode
            ];
        } catch (Exception $e) {
            $this->logError('sendPushV1', 'Exception in sendPushV1', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token_preview' => substr($token, 0, 20) . '...',
            ]);
            $this->logFcm('fcm_exception', [
                'exception' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...',
            ]);
            throw $e;
        }
    }

    /**
     * Send notification for new message
     */
    public function sendMessageNotification(int $receiverId, string $senderName, string $messagePreview): array
    {
        return $this->sendToUser(
            $receiverId,
            'Новое сообщение',
            "{$senderName}: " . mb_substr($messagePreview, 0, 100),
            [
                'type' => 'message',
                'sender_name' => $senderName,
            ]
        );
    }

    /**
     * Send notification for incoming call
     */
    public function sendCallNotification(int $calleeId, string $callerName, int $callId): array
    {
        return $this->sendToUser(
            $calleeId,
            'Входящий звонок',
            "{$callerName} звонит вам",
            [
                'type' => 'call',
                'caller_name' => $callerName,
                'call_id' => (string)$callId,
            ]
        );
    }

    /**
     * Send notification for new review
     */
    public function sendReviewNotification(int $reviewedId, string $reviewerName, int $rating): array
    {
        $stars = str_repeat('⭐', $rating);
        return $this->sendToUser(
            $reviewedId,
            'Новый отзыв',
            "{$reviewerName} оставил(а) отзыв: {$stars}",
            [
                'type' => 'review',
                'reviewer_name' => $reviewerName,
                'rating' => (string)$rating,
            ]
        );
    }

    /**
     * Send notification for incoming call (callee receives call)
     */
    public function sendIncomingCallNotification(int $calleeId, int $callerId, string $callerName, int $callId, string $roomName): array
    {
        return $this->sendToUser(
            $calleeId,
            'Входящий звонок',
            "{$callerName} звонит вам",
            [
                'type' => 'incoming_call',
                'caller_id' => (string)$callerId,
                'caller_name' => $callerName,
                'call_id' => (string)$callId,
                'room_name' => $roomName,
            ]
        );
    }

    /**
     * Send notification that callee accepted the call (caller receives)
     */
    public function sendCallAcceptedNotification(int $callerId, string $calleeName, int $callId, string $roomName): array
    {
        return $this->sendToUser(
            $callerId,
            'Звонок принят',
            "{$calleeName} принял(а) звонок",
            [
                'type' => 'call_accepted',
                'callee_name' => $calleeName,
                'call_id' => (string)$callId,
                'room_name' => $roomName,
            ]
        );
    }

    /**
     * Send notification that call was declined by callee
     */
    public function sendCallDeclinedNotification(int $callerId, string $calleeName, int $callId): array
    {
        return $this->sendToUser(
            $callerId,
            'Звонок отклонён',
            "{$calleeName} отклонил(а) ваш звонок",
            [
                'type' => 'call_declined',
                'callee_name' => $calleeName,
                'call_id' => (string)$callId,
            ]
        );
    }

    /**
     * Send notification that call was cancelled by caller
     */
    public function sendCallCancelledByCallerNotification(int $calleeId, string $callerName, int $callId): array
    {
        return $this->sendToUser(
            $calleeId,
            'Звонок отменён',
            "{$callerName} отменил(а) звонок",
            [
                'type' => 'call_cancelled',
                'caller_name' => $callerName,
                'call_id' => (string)$callId,
            ]
        );
    }

    /**
     * Send notification that call has ended
     */
    public function sendCallEndedNotification(int $userId, string $endedByName, int $callId, int $duration): array
    {
        $durationMinutes = floor($duration / 60);
        $durationSeconds = $duration % 60;
        $durationText = $durationMinutes > 0
            ? "{$durationMinutes} мин {$durationSeconds} сек"
            : "{$durationSeconds} сек";
            
        return $this->sendToUser(
            $userId,
            'Звонок завершён',
            "{$endedByName} завершил(а) звонок ({$durationText})",
            [
                'type' => 'call_ended',
                'ended_by_name' => $endedByName,
                'call_id' => (string)$callId,
                'duration' => (string)$duration,
            ]
        );
    }
}
