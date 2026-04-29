<?php
/**
 * CORS Proxy for fetching remote content
 * Usage: proxy.php?url=https://example.com/file.md
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the URL from query parameter
$url = isset($_GET['url']) ? $_GET['url'] : '';

// Validate URL
if (empty($url)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No URL provided']);
    exit;
}

// Validate URL format
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid URL format']);
    exit;
}

// Only allow http and https protocols
$scheme = parse_url($url, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Only HTTP and HTTPS protocols are allowed']);
    exit;
}

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; NotasIPCC-Proxy/1.0)');

// Get headers from remote response
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
    $len = strlen($header);
    $header = explode(':', $header, 2);

    // Forward content-type header
    if (count($header) == 2) {
        $name = strtolower(trim($header[0]));
        $value = trim($header[1]);

        if ($name === 'content-type') {
            header('Content-Type: ' . $value);
        }
    }

    return $len;
});

// Execute request
$content = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

// Get HTTP response code
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Set the same response code
http_response_code($httpCode);

// If response is not successful, return error
if ($httpCode >= 400) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Remote server returned HTTP ' . $httpCode]);
    exit;
}

// Return the content
echo $content;
