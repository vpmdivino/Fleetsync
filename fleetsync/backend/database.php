<?php
session_start();
require_once '../db_connect.php';

// 1. Authentication & Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['error_message'] = "You must be logged in as an administrator to perform this action.";
    header('Location: ../admin.php#view-database');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php');
    exit();
}

$action = $_POST['action'] ?? '';

/**
 * Executes a shell command and throws an exception if it fails.
 *
 * @param string $command The command to execute.
 * @param string &$output The output from the command.
 * @param int &$return_var The return status of the command.
 * @throws Exception If the command fails.
 */
function execute_command($command, &$output, &$return_var) {
    // Redirect stderr to stdout to capture errors
    exec($command . ' 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        throw new Exception("Command failed with status {$return_var}: " . implode("\n", $output));
    }
}

try {
    if ($action === 'backup') {
        // Ensure mysqldump is available. You might need to provide the full path.
        // e.g., $mysqldump_path = 'C:\xampp\mysql\bin\mysqldump.exe';
        $mysqldump_path = 'C:\xampp\mysql\bin\mysqldump.exe';

        $backup_file = 'fleet-backup-' . date('Y-m-d-H-i-s') . '.sql';

        // Build the command
        $command = sprintf(
            '%s --user=%s %s %s > %s',
            escapeshellarg($mysqldump_path),
            escapeshellarg($user),
            empty($pass) ? '' : sprintf('--password=%s', escapeshellarg($pass)),
            escapeshellarg($db),
            escapeshellarg($backup_file)
        );

        execute_command($command, $output, $return_var);

        // Force download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file));
        readfile($backup_file);

        // Clean up the file from the server
        unlink($backup_file);
        log_system_event($pdo, "Created a database backup.");
        exit();

    } elseif ($action === 'restore') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['backup_file']['tmp_name'];
            
            // Ensure mysql is available. You might need to provide the full path.
            // e.g., $mysql_path = 'C:\xampp\mysql\bin\mysql.exe';
            $mysql_path = 'C:\xampp\mysql\bin\mysql.exe';

            $command = sprintf(
                '%s --user=%s %s %s < %s',
                escapeshellarg($mysql_path),
                escapeshellarg($user),
                empty($pass) ? '' : sprintf('--password=%s', escapeshellarg($pass)),
                escapeshellarg($db),
                escapeshellarg($file_tmp_path)
            );

            execute_command($command, $output, $return_var);

            log_system_event($pdo, "Restored database from a backup file.");
            $_SESSION['success_message'] = "Database restored successfully from the backup file.";
        } else {
            throw new Exception("File upload failed or no file was selected. Error code: " . ($_FILES['backup_file']['error'] ?? 'N/A'));
        }
    } else {
        throw new Exception("Invalid action specified.");
    }
} catch (Exception $e) {
    log_error($e);
    $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
}

header('Location: ../admin.php#view-database');
exit();