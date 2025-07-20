<?php

// The target URL for the login request
$loginUrl = 'https://gurumantrapsc.com/customer/login';
// The URL to fetch for a valid CSRF token
$homeUrl = 'https://gurumantrapsc.com/home';


// The phone number and password for the login attempt
$phoneNumber = '9809642422';
$password = 'rupesh123';

// Initialize a cURL session to get the CSRF token
$ch = curl_init($homeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

// Now, get the login page to get a fresh CSRF token
curl_setopt($ch, CURLOPT_URL, $loginUrl);
$loginPageHtml = curl_exec($ch);

// Function to extract the CSRF token from the HTML content
function getCsrfToken($html) {
    if (preg_match('/<input type="hidden" name="_token" value="(.*?)?">/', $html, $matches)) {
        return $matches[1];
    }
    return false;
}

$csrfToken = getCsrfToken($loginPageHtml);

if ($csrfToken) {
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

    for ($i = 0; $i <= 10; $i++) {
        // The URL of the video to fetch
        $videoUrl = 'https://gurumantrapsc.com/purchase/video/' . $i;

        // Now, fetch the video page
        curl_setopt($ch, CURLOPT_URL, $videoUrl);
        curl_setopt($ch, CURLOPT_POST, false);
        $videoPage = curl_exec($ch);

        // Extract YouTube URL
        $youtubeUrl = '';
        if (preg_match('/<div class="plyr__video-embed" id="player">\s*<iframe.*?src="(.*?)".*?>/', $videoPage, $matches)) {
            $rawUrl = $matches[1];
            // Ensure we get a clean youtube.com/watch?v=... URL
            if(preg_match('/embed\/(.*?)(?:\?|$)/', $rawUrl, $id_match)){
                $youtubeUrl = 'https://www.youtube.com/watch?v=' . $id_match[1];
            }
        }

        // Extract Title
        $title = '';
        if (preg_match('/<h4 class="font-20 font-bold font-jakarta"> (.*?)<\/h4>/', $videoPage, $matches)) {
            $title = trim($matches[1]);
        }

        if ($youtubeUrl && $title) {
            echo $i . "=" . $youtubeUrl . ":" . $title . "\n";
        }
    }

} else {
    echo "Could not find CSRF token on the live page.";
}

curl_close($ch);

?>