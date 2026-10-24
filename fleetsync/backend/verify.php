<?php
session_start();
require_once '../db_connect.php';

// Get the token from the query string
$token = $_GET['token'] ?? '';
if (empty($token)) {
    $_SESSION['error_message'] = 'Invalid verification link.';
    header('Location: ../index.php');
    exit();
}

try {
    // Look up the token – it should point to a user_id and not be expired
    $stmt = $pdo->prepare(
        "SELECT user_id, expires_at FROM email_verifications WHERE token = :token"
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception('Verification token not found or already used.');
    }

    // Optional: check expiration
    if (strtotime($row['expires_at']) < time()) {
        // Token expired – clean it up and inform the user
        $stmt = $pdo->prepare(
            "DELETE FROM email_verifications WHERE token = :token"
        );
        $stmt->execute([':token' => $token]);
        throw new Exception('Verification link has expired. Please register again.');
    }

    $userId = $row['user_id'];

    // Mark the user as verified
    $stmt = $pdo->prepare(
        "UPDATE users SET email_verified = 1 WHERE id = :id"
    );
    $stmt->execute([':id' => $userId]);

    // Remove the token – it should be single‑use
    $stmt = $pdo->prepare(
        "DELETE FROM email_verifications WHERE token = :token"
    );
    $stmt->execute([':token' => $token]);

    $_SESSION['success_message'] = 'Your email address has been verified. You can now log in.';
    header('Location: ../index.php');
    exit();

} catch (Exception $e) {
    // Log the error for debugging purposes
    if (function_exists('log_error')) {
        log_error($e);
    }
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ../index.php');
    exit();
}
?>

