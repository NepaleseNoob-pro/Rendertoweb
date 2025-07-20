<?php

// The target URL for the login request
$loginUrl = 'https://gurumantrapsc.com/customer/login';
// The URL to fetch for a valid CSRF token
$homeUrl = 'https://gurumantrapsc.com/home';


// The phone number and password for the login attempt
$phoneNumber = '9809642422';
$password = 'rupesh123';

// Set time limit to unlimited
set_time_limit(0);

// Enable implicit flushing
ob_implicit_flush(true);
@ob_end_flush();

// The file to save results to
    $resultsFile = 'education.txt';

    // Read existing results to avoid duplicates
    $existingResults = [];
    if (file_exists($resultsFile)) {
        $lines = file($resultsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^(\d+)=/', $line, $matches)) {
                $existingResults[$matches[1]] = true;
            }
        }
    }

?>
<!DOCTYPE html>
<html>
<head>
    <title>Gurumantra Video Fetcher</title>
</head>
<body>
    <h1>Fetching Video Information...</h1>
    <p>Results will be saved to <strong>results.txt</strong></p>
    <pre>
<?php

// Initialize a cURL session to get the CSRF token
$ch = curl_init($homeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.5',
]);
$html = curl_exec($ch);

// The CSRF token is on the home page
$loginPageHtml = $html;

// Function to extract the CSRF token from the HTML content
function getCsrfToken($html) {
    if (preg_match('~<input type="hidden" name="_token" value="(.*?)">~', $html, $matches)) {
        return $matches[1];
    }
    return false;
}

$csrfToken = getCsrfToken($loginPageHtml);

if ($csrfToken) {
    echo "Successfully obtained CSRF token. Logging in...\n";
    flush();

    // Prepare the data to be sent in the POST request
    $data = [
        '_token' => $csrfToken,
        'phone_number' => $phoneNumber,
        'password' => $password,
    ];

    // Initialize a cURL session for the login request
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    echo "Login successful. Starting to fetch video data...\n\n";
    flush();


    $currentVideoId = 0;
    $lastFoundVideoId = 0;
    $consecutiveNotFoundCount = 0;

    while (true) {
        echo "Fetching video ID: $currentVideoId...\n";
        flush();

        // The URL of the video to fetch
        $videoUrl = 'https://gurumantrapsc.com/purchase/video/' . $currentVideoId;

        // Now, fetch the video page
        curl_setopt($ch, CURLOPT_URL, $videoUrl);
        curl_setopt($ch, CURLOPT_POST, false);
        $videoPage = curl_exec($ch);

        // Extract YouTube URL (corrected regex to capture video ID directly)
        $youtubeUrl = '';
        $title = '';
        if (preg_match('~id="player">.*?src="https://www.youtube.com/embed/([a-zA-Z0-9_-]+)(?:.*?title="(.*?)")?~s', $videoPage, $matches)) {
            $youtubeUrl = 'https://www.youtube.com/watch?v=' . $matches[1];
            if (isset($matches[2])) {
                $title = trim($matches[2]);
            }
        }

        // Extract Title
        if (empty($title)) {
            if (preg_match('~<div class="dashboard-header">.*?<p>\s*(.*?)\s*</p>~s', $videoPage, $matches)) {
                $title = trim($matches[1]);
            } else {
                // Fallback to title tag if p not found (less accurate for exact video title)
                if (preg_match('~<title>(.*?)</title>~', $videoPage, $matches)) {
                    $title = trim(str_replace("| Gurumantra", "", $matches[1]));
                }
            }
        }

        if ($youtubeUrl && $title) {
            if (!isset($existingResults[$currentVideoId])) {
                $resultLine = $currentVideoId . "=" . $youtubeUrl . ":" . $title . "\n";
                echo "FOUND: " . $resultLine;
                
                // Save to local file (commented out, enable if needed)
                file_put_contents($resultsFile, $resultLine, FILE_APPEND);

                // Attempt to upload to remote server
                $remoteUploadUrl = 'http://rupeshkumarmahato.com.np/upload_education.php'; // Placeholder URL
                $uploadCh = curl_init($remoteUploadUrl);
                curl_setopt($uploadCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($uploadCh, CURLOPT_POST, true);
                curl_setopt($uploadCh, CURLOPT_POSTFIELDS, ['data' => $resultLine]);
                $uploadResponse = curl_exec($uploadCh);
                curl_close($uploadCh);

                if ($uploadResponse === false) {
                    echo "ERROR: Failed to upload to remote server: " . curl_error($uploadCh) . "\n";
                } else {
                    echo "INFO: Uploaded to remote server: " . $uploadResponse . "\n";
                }

                $lastFoundVideoId = $currentVideoId;
                $consecutiveNotFoundCount = 0;
            } else {
                echo "SKIPPED (already exists): Video ID: $currentVideoId\n";
            }
        } else {
            $consecutiveNotFoundCount++;
            if ($consecutiveNotFoundCount >= 15) {
                echo "INFO: 15 consecutive videos not found. Restarting from last found ID: $lastFoundVideoId\n";
                $currentVideoId = $lastFoundVideoId;
                $consecutiveNotFoundCount = 0;
            }
        }
        echo "------------------------------------\n";
        flush();
        sleep(1); // Wait for 1 second
        $currentVideoId++;
    }
    echo "
Fetching complete. Check education.txt";
    flush();

} else {
    echo "Could not find CSRF token on the live page. The HTML received was:\n";
    echo htmlspecialchars($loginPageHtml);
    flush();
}

curl_close($ch);

?>
    </pre>
</body>
</html>