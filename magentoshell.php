<?php

// Set the Magento root path
$magentoRoot = dirname(__DIR__);

// Function to execute shell commands and return full output
function runShellCommand($command)
{
    global $magentoRoot;
    chdir($magentoRoot);
    return nl2br(htmlspecialchars(shell_exec($command . ' 2>&1')));
}

// Initialize output
$output = '';

// Execute custom shell command
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_command'])) {
    $command = trim($_POST['custom_command']);
    if (!empty($command)) {
        $output = runShellCommand($command);
    }
}

// Magento predefined commands list
$commands = [
    'full' => 'Magento Complete Command *',
    'cache:flush' => 'Cache Flush',
    'cache:clean' => 'Cache Clean',
    'setup:upgrade' => 'Setup Upgrade',
    'setup:di:compile' => 'DI Compile',
    'setup:static-content:deploy -f' => 'Static Content Deploy',
    'indexer:reindex' => 'Reindex',
    'maintenance:enable' => 'Enable Maintenance Mode',
    'maintenance:disable' => 'Disable Maintenance Mode',
];

// Handle predefined Magento commands
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['predefined_command'])) {
    $commandKey = trim($_POST['predefined_command']);
    
    if ($commandKey === 'full') {
        // Run full shell command without auto-prefixing
        $output = runShellCommand('php bin/magento setup:upgrade && php bin/magento setup:di:compile && php bin/magento setup:static-content:deploy -f && php bin/magento indexer:reindex && php bin/magento cache:flush && chmod -R 777 generated/ pub/ var/');
    } elseif (!empty($commandKey) && isset($commands[$commandKey])) {
        // Prefix normal Magento CLI commands
        $output = runShellCommand('php bin/magento ' . escapeshellcmd($commandKey));
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magento Shell Executor</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            padding: 20px;
        }
        /* Centered Loader */
        #loader {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            border: 10px solid #f3f3f3;
            border-top: 10px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 1000;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Blurred Output */
        .blurred {
            filter: blur(5px);
            opacity: 0.5;
            transition: all 0.3s ease-in-out;
        }
    </style>
</head>
<body class="container">

    <h2 class="text-center mb-4">Magento Shell Command Executor</h2>

    <!-- Custom Command Form -->
    <form method="POST" onsubmit="showLoader()">
        <div class="mb-3">
            <label class="form-label">Enter Full Shell Command:</label>
            <input type="text" class="form-control" name="custom_command" placeholder="e.g., sudo php bin/magento c:f" required>
        </div>
        <button type="submit" class="btn btn-success">Run Command</button>
    </form>

    <hr>

    <!-- Predefined Magento Commands -->
    <form method="POST" id="commandForm">
        <div class="mb-3">
            <label class="form-label">Select Magento Command:</label>
            <select class="form-select" name="predefined_command" id="commandSelect">
                <option value="">-- Select Command --</option>
                <?php foreach ($commands as $cmdKey => $desc): ?>
                    <option value="<?= htmlspecialchars($cmdKey) ?>"><?= htmlspecialchars($desc) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <!-- Centered Loader -->
    <div id="loader"></div>

    <hr>

    <!-- Output Section -->
    <h3 class="mt-4">Command Output:</h3>
    <pre id="commandOutput" class="p-3 bg-light border rounded" style="white-space: pre-wrap;"><?= $output ?></pre>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function showLoader() {
            $("#loader").show();
            $("#commandOutput").addClass("blurred"); // Add blur effect to output
        }

        function hideLoader() {
            $("#loader").hide();
            $("#commandOutput").removeClass("blurred"); // Remove blur after execution
        }

        $(document).ready(function() {
            // Show loader on form submit
            $("form").submit(function() {
                showLoader();
            });

            // Show loader when selecting an option from the dropdown
            $("#commandSelect").change(function() {
                if ($(this).val()) {
                    showLoader();
                    $("#commandForm").submit(); // Auto-submit the form
                }
            });

            // Remove loader & blur after page loads (ensures it disappears if refreshed)
            $(window).on("load", function() {
                hideLoader();
            });
        });
    </script>

</body>
</html>
