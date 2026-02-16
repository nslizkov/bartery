<?php
define('API_BASE_URL', 'http://localhost:8080/api'); // замените на реальный адрес API

function apiRequest($method, $endpoint, $data = null, $token = null, $fileField = null) {
    $url = API_BASE_URL . $endpoint;
    $ch = curl_init($url);

    $headers = [];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    if ($method === 'GET' && $data) {
        $url .= '?' . http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        if ($fileField && isset($_FILES[$fileField])) {
            $file = $_FILES[$fileField];
            $postData = [
                $fileField => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        } else {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Type: application/json';
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'body' => json_decode($response, true),
        'httpCode' => $httpCode
    ];
}
?>