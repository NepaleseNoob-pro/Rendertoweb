<?php
// THIS IS A BASIC, INSECURE EXAMPLE. YOU MUST ADD PROPER SECURITY MEASURES
// BEFORE DEPLOYING THIS TO A PUBLIC SERVER (e.g., API keys, authentication, IP whitelisting).

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data'])) {
    $data = $_POST['data'] . "\n";
    $filePath = 'education.txt';

    // Read existing content to check for duplicates
    $existingContent = file_exists($filePath) ? file_get_contents($filePath) : '';

    // Check if the line already exists to prevent duplicates
    if (strpos($existingContent, $data) === false) {
        file_put_contents($filePath, $data, FILE_APPEND | LOCK_EX);
        echo "Success: Data appended.";
    } else {
        echo "Info: Data already exists (duplicate).";
    }
} else {
    http_response_code(400); // Bad Request
    echo "Error: Invalid request.";
}
?>