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
        $header = fgetcsv($handle); // Skip header row
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowData = array_combine($header, $data);
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
$requestUri = isset($_GET['uri']) ? $_GET['uri'] : '';

if (empty($requestUri)) {
    // If no URI is passed, it might be a direct access to index.php, which is not intended.
    // Or you could serve a default page here. For now, let's serve a 404.
    serveNotFound();
}

// Get the current date. For testing, we can override this.
// $today = '2025-08-12';
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$todaysFiles = getScheduledFiles($today);
$tomorrowsFiles = getScheduledFiles($tomorrow);

$scheduledFiles = array_merge($todaysFiles, $tomorrowsFiles);

$isScheduled = false;
$requestedFileDetails = null;

// The request URI will be something like 'category1/some-file.html'
$parts = explode('/', $requestUri);
$category = $parts[0];
$filename = $parts[1];

foreach ($scheduledFiles as $file) {
    if ($file['html_name'] === $filename && 'category' . $file['category'] === $category) {
        $isScheduled = true;
        $requestedFileDetails = $file;
        break;
    }
}

// Also check for images. The URI for an image will be like 'imagenes/some-file.jpg'
if ($category === 'imagenes') {
    $htmlFilename = pathinfo($filename, PATHINFO_FILENAME) . '.html';
    foreach ($scheduledFiles as $file) {
        if ($file['html_name'] === $htmlFilename) {
            $isScheduled = true;
            break;
        }
    }
}


if (!$isScheduled) {
    serveNotFound();
}

// --- Serve Content ---

$fileExtension = pathinfo($filename, PATHINFO_EXTENSION);

if ($fileExtension === 'html') {
    $templateContent = file_get_contents(TEMPLATE_FILE);

    $imageName = pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
    // The path needs to be relative from the root, as the request is rewritten.
    $imagePathInHtml = '/imagenes/' . $imageName;

    $pageContent = str_replace('src=""', 'src="' . $imagePathInHtml . '"', $templateContent);

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
