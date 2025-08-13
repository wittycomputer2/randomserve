<?php

// --- Configuration ---
define('SCHEDULE_FILE', 'schedule2025.csv');
define('TEMPLATE_FILE', 'pics-lovelyviolet-pagetemplate.html');
define('DUMMY_IMAGE', 'imagenes/sample.jpg');

// --- Functions ---

/**
 * Reads the schedule CSV file and returns an array of files scheduled for a given date.
 * @param string $date The date in 'Y-m-d' format.
 * @return array An array of scheduled file data.
 */
function getScheduledFiles($date) {
    $scheduledFiles = [];
    if (($handle = fopen(SCHEDULE_FILE, "r")) !== FALSE) {
        $header = array_map('trim', fgetcsv($handle)); // Trim header fields
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowData = array_combine($header, array_map('trim', $data)); // Trim data fields
            if ($rowData['date'] === $date) {
                $scheduledFiles[] = $rowData;
            }
        }
        fclose($handle);
    }
    return $scheduledFiles;
}

/**
 * Serves a file with the correct headers.
 * @param string $filePath The path to the file to serve.
 * @param string $contentType The Content-Type header value.
 */
function serveFile($filePath, $contentType) {
    header("Content-Type: $contentType");
    header("Content-Length: " . filesize($filePath));
    readfile($filePath);
    exit;
}

/**
 * Sends a 404 Not Found response.
 */
function serveNotFound() {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1><p>The page you requested was not found.</p>";
    exit;
}

// --- Main Logic ---

// Get the requested URI from the .htaccess rewrite rule
$requestUri = isset($_GET['uri']) ? trim($_GET['uri']) : '';

if (empty($requestUri)) {
    // If no URI is passed, it might be a direct access to index.php, which is not intended.
    // Or you could serve a default page here. For now, let's serve a 404.
    serveNotFound();
}

// Get the current date. For testing, we can override this.
// $today = '2025-08-12';
$yesterday = date('Y-m-d', strtotime('-1 day'));
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$yesterdaysFiles = getScheduledFiles($yesterday);
$todaysFiles = getScheduledFiles($today);
$tomorrowsFiles = getScheduledFiles($tomorrow);

$scheduledFiles = array_merge($yesterdaysFiles, $todaysFiles, $tomorrowsFiles);

$isScheduled = false;
$requestedFileDetails = null;
$category = null;
$filename = basename($requestUri);

if (strpos($requestUri, 'imagenes/') === 0) {
    $category = 'imagenes';
    $htmlFilename = pathinfo($filename, PATHINFO_FILENAME) . '.html';
    foreach ($scheduledFiles as $file) {
        if ($file['html_name'] === $htmlFilename) {
            $isScheduled = true;
            break;
        }
    }
} else {
    // It's a request for an HTML file.
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($fileExtension === 'html') {
        foreach ($scheduledFiles as $file) {
            if ($file['html_name'] === $filename) {
                $isScheduled = true;
                $requestedFileDetails = $file;
                $category = 'category' . $file['category'];
                break;
            }
        }
    }
}


if (!$isScheduled) {
    serveNotFound();
}

// --- Serve Content ---

$fileExtension = pathinfo($filename, PATHINFO_EXTENSION);

if ($fileExtension === 'html') {
    $filePath = $category . '/' . $filename;

    if (!file_exists($filePath)) {
        // If the file doesn't exist in the category folder, serve a 404.
        serveNotFound();
    }

    $pageContent = file_get_contents($filePath);

    // Age verification for category2
    if ($category === 'category2' && (!isset($_COOKIE['age_verified']) || $_COOKIE['age_verified'] !== 'true')) {
        $ageVerificationHtml = <<<HTML
        <style>
            #age-verification-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); z-index: 1000; display: flex; justify-content: center; align-items: center; }
            #age-verification-modal { background-color: #1f2937; padding: 40px; border-radius: 10px; text-align: center; color: white; }
            #age-verification-buttons button { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 0 10px; }
            #age-verification-yes { background-color: #EB2952; color: white; }
            #age-verification-no { background-color: white; color: black; }
        </style>
        <div id="age-verification-overlay">
            <div id="age-verification-modal">
                <h2>Age Verification</h2>
                <p>You must be 18 or older to view this content.</p>
                <div id="age-verification-buttons">
                    <button id="age-verification-yes">Yes, I'm over 18</button>
                    <button id="age-verification-no">I'm under 18</button>
                </div>
            </div>
        </div>
        <script>
            document.getElementById('age-verification-yes').addEventListener('click', function() {
                document.cookie = "age_verified=true; path=/; max-age=31536000";
                document.getElementById('age-verification-overlay').style.display = 'none';
            });
            document.getElementById('age-verification-no').addEventListener('click', function() {
                window.location.href = "/";
            });
        </script>
HTML;
        $pageContent = str_replace('</body>', $ageVerificationHtml . '</body>', $pageContent);
    }

    header("Content-Type: text/html");
    echo $pageContent;
    exit;

} elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
    // For any image, serve the dummy image
    serveFile(DUMMY_IMAGE, 'image/jpeg');
} else {
    // Should not happen with the current .htaccess, but as a fallback
    serveNotFound();
}

?>
