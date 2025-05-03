<?php
function recursiveTraverseFolder($dir, $ignoredFiles = [], $ignoredDirectories = []) {
    $folders = [];
    $regularFiles = [];

    // Ignore specific directories
    $ignoredDirectories = array_map('trim', $ignoredDirectories);
    $dirBasename = basename($dir);
    if (in_array($dirBasename, $ignoredDirectories)) {
        return [$folders, $regularFiles];
    }

    try {
        $items = scandir($dir);
    } catch (Exception $e) {
        return [$folders, $regularFiles];
    }

    foreach ($items as $item) {
        // Skip current and parent directory references
        if ($item === '.' || $item === '..') {
            continue;
        }

        $itemPath = $dir . DIRECTORY_SEPARATOR . $item;

        // Skip ignored files
        if (in_array($item, $ignoredFiles)) {
            continue;
        }

        if (is_dir($itemPath)) {
            // Recursively traverse subdirectories
            $folders[] = substr($itemPath, strlen(realpath('.') . DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR;
            
            list($subFolders, $subFiles) = recursiveTraverseFolder(
                $itemPath, 
                $ignoredFiles, 
                $ignoredDirectories
            );

            // Merge subdirectory results
            $folders = array_merge($folders, $subFolders);
            $regularFiles = array_merge($regularFiles, $subFiles);
        } else {
            // Add file with its relative path
            $regularFiles[] = substr($itemPath, strlen(realpath('.') . DIRECTORY_SEPARATOR));
        }
    }

    // Sort results
    sort($folders);
    sort($regularFiles);

    return [$folders, $regularFiles];
}


// Handle form submission
function processSearchRequest() {
    // Validate and sanitize input
    $content = $_POST['content'] ?? '';
    $inDirectory = $_POST['in_directory'] ?? './';
    $inExtensions = $_POST['in_extensions'] ?? '';
    $exFiles = $_POST['ex_files'] ?? '';
    $exExtensions = $_POST['ex_extensions'] ?? '';
    $exDirectory = $_POST['ex_directory'] ?? '';

    // Prepare arrays for filtering
    $ignoredFiles = explode(',', $exFiles);
    $ignoredFiles = array_map('trim', $ignoredFiles);
    
    // Prepare ignored directories
    $ignoredDirectories = explode(',', $exDirectory);
    $ignoredDirectories = array_map('trim', $ignoredDirectories);

    // Recursively traverse the folder
    list($folders, $regularFiles) = recursiveTraverseFolder(
        realpath($inDirectory), 
        $ignoredFiles, 
        $ignoredDirectories
    );

    // Filter files based on allowed extensions
    if (!empty($inExtensions)) {
        $allowedExtensions = explode(',', $inExtensions);
        $allowedExtensions = array_map('trim', $allowedExtensions);
        $regularFiles = array_filter($regularFiles, function($file) use ($allowedExtensions) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            return in_array($ext, $allowedExtensions);
        });
    }

    // Filter out files with excluded extensions
    if (!empty($exExtensions)) {
        $excludedExtensions = explode(',', $exExtensions);
        $excludedExtensions = array_map('trim', $excludedExtensions);
        $regularFiles = array_filter($regularFiles, function($file) use ($excludedExtensions) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            return !in_array($ext, $excludedExtensions);
        });
    }

    // Filter files containing the specified content
    $contentMatchedFiles = [];
    foreach ($regularFiles as $file) {
        $filePath = realpath('.') . DIRECTORY_SEPARATOR . $file;
        
        if (is_file($filePath)) {
            $fileContent = file_get_contents($filePath);
            
            // Use stripos for case-insensitive search
            if (stripos($fileContent, $content) !== false) {
                $contentMatchedFiles[] = $file;
            }
        }
    }

    // Prepare response
    $response = [
        'files' => $contentMatchedFiles
    ];

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    processSearchRequest();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Tracer</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .main-container {
        display: flex;
        background: white;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        width: 90%;
        max-width: 1200px;
        height: 80vh;
    }

    .form-container {
        width: 40%;
        padding: 20px;
        border-right: 1px solid #e0e0e0;
        overflow-y: auto;
    }

    .result-container {
        width: 60%;
        padding: 20px;
        overflow-y: auto;
    }

    h3 {
        text-align: center;
        color: #333;
        margin-bottom: 20px;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    label {
        font-size: 12px;
        margin-bottom: 3px;
        color: #555;
    }

    textarea,
    input {
        width: calc(100% - 16px);
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 14px;
        display: block;
    }

    button {
        background-color: rgb(5, 182, 73);
        color: white;
        border: none;
        padding: 10px;
        margin-top: 15px;
        width: 100%;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
        text-align: center;
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: rgb(5, 249, 33);
    }

    .result {
        background: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        margin-top: 10px;
        max-height: 70vh;
        overflow-y: auto;
    }

    .result h4 {
        color: #333;
        border-bottom: 1px solid #ddd;
        padding-bottom: 5px;
        margin-top: 15px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 7px;
    }

    .loading {
        text-align: center;
        color: #666;
        font-style: italic;
    }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="form-container">
            <form id="FrmParameters">
                <h3>Search Files</h3>
                <div class="form-group">
                    <label for="content">Search criteria</label>
                    <textarea name="content" id="content" class="form-control" rows="3"
                        placeholder="Enter search text"></textarea>
                </div>
                <div class="form-group">
                    <label for="in_directory">Include directory</label>
                    <input name="in_directory" id="in_directory" class="form-control" value="./"
                        placeholder="Directory to search">
                </div>
                <div class="form-group">
                    <label for="in_extensions">Include extension</label>
                    <input name="in_extensions" id="in_extensions" class="form-control" placeholder="e.g: php, txt, js">
                </div>
                <div class="form-group">
                    <label for="ex_files">Exclude files</label>
                    <input name="ex_files" id="ex_files" class="form-control"
                        placeholder="e.g: filename.php, .gitignore">
                </div>
                <div class="form-group">
                    <label for="ex_extensions">Exclude extensions</label>
                    <input name="ex_extensions" id="ex_extensions" class="form-control" placeholder="e.g: log, tmp">
                </div>
                <div class="form-group">
                    <label for="ex_directory">Exclude directory</label>
                    <input name="ex_directory" id="ex_directory" class="form-control"
                        placeholder="e.g: vendor, libraries">
                </div>
                <div class="form-group">
                    <button type="button" id="btn-search">Search files</button>
                </div>
            </form>
        </div>
        <div class="result-container">
            <div id="result" class="result">
                <p class="loading">Search parameters and click "Search Files" to view results.</p>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('btn-search').onclick = function() {

        // Collect form data
        const form = document.getElementById('FrmParameters');
        const formData = new FormData(form);

        if (formData.get('content').trim() == "") {
            alert("Please add search criteria");
            return;
        }

        // Show loading state
        const resultDiv = document.getElementById('result');
        resultDiv.innerHTML = '<p class="loading">Searching... Please wait.</p>';

        fetch("traverse.php", {
                method: "POST",
                body: formData
            })
            .then((response) => response.json())
            .then((data) => {
                resultDiv.innerHTML = ''; // Clear previous results

                // Display files
                if (data.files.length > 0) {
                    resultDiv.innerHTML += '<div style="background-color:#fce9aa"><h4>Files:</h4></div>';
                    resultDiv.innerHTML += data.files.map(file =>
                        `<div>${file}</div>`
                    ).join('');
                }

                if (data.files.length === 0) {
                    resultDiv.innerHTML = '<p class="loading">Oops, No data found!</p>';
                }
            })
            .catch((error) => {
                console.error("Error loading data:", error);
                resultDiv.innerHTML = '<p class="loading">An error occurred while searching.</p>';
            });
    }
    </script>
</body>

</html>