<?php
session_start();
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Only allow POST requests
    header('Location: ../index.php');
    exit();
}

// Sanitize and retrieve POST data
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    $_SESSION['error_message'] = "Email and password are required.";
    header('Location: ../index.php');
    exit();
}

try {
    // --- Find the user by email ---
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password, role, status, email_verified FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // --- Verify user and password ---
    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION['error_message'] = "Invalid email or password.";
        header('Location: ../index.php');
        exit();
    }

    // --- Verify email is confirmed ---
    if (empty($user['email_verified']) || $user['email_verified'] != 1) {
        $_SESSION['error_message'] = "Please verify your email address before logging in.";
        header('Location: ../index.php');
        exit();
    }

    // --- Check if account is active ---
    if ($user['status'] !== 'Active') {
        $_SESSION['error_message'] = "Your account has been deactivated. Please contact an administrator.";
        header('Location: ../index.php');
        exit();
    }


    // --- Store user data in session ---
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role'];

    // --- Redirect based on role ---
    switch ($user['role']) {
        case 'Admin':
            header('Location: ../admin.php');
            break;
        case 'Fleet Manager':
            header('Location: ../fleet.php');
            break;
        case 'Accountant':
            header('Location: ../accountant.php');
            break;
        default:
            // Default redirect if role is not recognized
            header('Location: ../index.php');
            break;
    }
    exit();

} catch (PDOException $e) {
    log_error($e); // Log the detailed error
    $_SESSION['error_message'] = "A database error occurred. Please try again later.";
    header('Location: ../index.php');
    exit();
}