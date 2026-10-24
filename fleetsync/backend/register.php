<?php
session_start();
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Only allow POST requests
    header('Location: ../index.php');
    exit();
}

// Sanitize and retrieve POST data
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$role = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

// --- Basic Validation ---
$errors = [];
if (empty($first_name)) $errors[] = "First name is required.";
if (empty($last_name)) $errors[] = "Last name is required.";
if (!$email) $errors[] = "A valid email address is required.";
if (empty($role) || !in_array($role, ['Admin', 'Fleet Manager', 'Accountant'])) $errors[] = "A valid role must be selected.";
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";

if (!empty($errors)) {
    $_SESSION['error_message'] = implode('<br>', $errors);
    header('Location: ../index.php');
    exit();
}

try {
    // --- Check if email already exists ---
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "An account with this email address already exists.";
        header('Location: ../index.php');
        exit();
    }

    // --- Hash the password for security ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- Insert the new user with email verification ---
    $sql = "INSERT INTO users (first_name, last_name, email, password, role, email_verified) VALUES (:first_name, :last_name, :email, :password, :role, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':password' => $hashed_password,
        ':role' => $role
    ]);
    // Generate email verification token
    $verification_token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (:user_id, :token, :expires)");
    $stmt->execute([':user_id' => $pdo->lastInsertId(), ':token' => $verification_token, ':expires' => $expires]);
    // Send verification email using PHPMailer
    require_once __DIR__ . '/../autoload.php';
    $cfg = require __DIR__ . '/smtp_config.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = ($cfg['encryption'] ?? 'tls') === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($cfg['port'] ?? 587);

        // Recipients
        $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : $cfg['username'];
        $fromName  = !empty($cfg['from_name']) ? $cfg['from_name'] : 'FleetSync';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $first_name . ' ' . $last_name);

        // Build verification link
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $verifyLink = $protocol . $host . "/fleetsync/backend/verify.php?token=" . urlencode($verification_token);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify your FleetSync email address';
        $mail->Body    = "<h3>Hello " . htmlspecialchars($first_name) . ",</h3>"
                       . "<p>Thank you for registering with FleetSync. Please click the link below to verify your email address:</p>"
                       . "<p><a href='{$verifyLink}'>{$verifyLink}</a></p>"
                       . "<p>This link will expire in 24 hours.</p>";
        $mail->AltBody = "Hello {$first_name},\n\nPlease verify your email address by opening the following link:\n{$verifyLink}\n\nThis link will expire in 24 hours.";

        $mail->send();
        $_SESSION['success_message'] = "Registration successful! A verification link has been sent to your email.";
    } catch (PHPMailer\PHPMailer\Exception $e) {
        // Log error but continue registration flow
        if (function_exists('log_error')) {
            log_error($e);
        }
        $_SESSION['error_message'] = 'Account created, but verification email could not be sent. Error: ' . $mail->ErrorInfo;
    }

} catch (PDOException $e) {
    log_error($e); // Log the detailed error
    $_SESSION['error_message'] = "A database error occurred during registration. Please try again later.";
}

header('Location: ../index.php');
exit();