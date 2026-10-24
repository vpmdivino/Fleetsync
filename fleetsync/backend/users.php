<?php
session_start();
require_once '../db_connect.php';

// 1. Authentication & Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['error_message'] = "Unauthorized action.";
    header('Location: ../admin.php#view-users');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add' || $action === 'edit') {
            // Sanitize and retrieve POST data
            $id = !empty($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $role = trim($_POST['role'] ?? '');
            $password = $_POST['password'] ?? '';

            // --- Validation ---
            if (empty($first_name) || empty($last_name) || !$email || empty($role)) {
                throw new Exception("All fields except password are required.");
            }
            if (!in_array($role, ['Admin', 'Fleet Manager', 'Accountant'])) {
                throw new Exception("Invalid role specified.");
            }

            if ($action === 'add') {
                if (strlen($password) < 8) {
                    throw new Exception("Password must be at least 8 characters long for new users.");
                }
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                if ($stmt->fetch()) {
                    throw new Exception("An account with this email address already exists.");
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (first_name, last_name, email, password, role) VALUES (:first_name, :last_name, :email, :password, :role)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':email' => $email,
                    ':password' => $hashed_password,
                    ':role' => $role
                ]);
                log_system_event($pdo, "Created new user: {$email} with role {$role}.");
                $_SESSION['success_message'] = "User created successfully!";
            } else { // 'edit'
                if (!$id) throw new Exception("Invalid user ID for editing.");

                $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, role = :role";
                $params = [
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':email' => $email,
                    ':role' => $role,
                    ':id' => $id
                ];

                // Only update password if a new one is provided
                if (!empty($password)) {
                    if (strlen($password) < 8) throw new Exception("Password must be at least 8 characters long.");
                    $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ", password = :password";
                }

                $sql .= " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                log_system_event($pdo, "Updated user profile for email: {$email}.");
                $_SESSION['success_message'] = "User updated successfully!";
            }
        } else {
            throw new Exception("Invalid action specified.");
        }
    } catch (PDOException | Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../admin.php#view-users"); // Added hash
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $new_status = $_GET['status'] ?? '';

    if ($action === 'update_status' && $id && in_array($new_status, ['Active', 'Deactivated'])) {
        try {
            // Rule 1: Prevent admin from deactivating themselves
            if ($id === $_SESSION['user_id']) {
                 throw new Exception("Security Rule Violation: You cannot change the status of your own account.");
            }

            // Retrieve target user data for verification and audit trail
            $targetStmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, status FROM users WHERE id = :id");
            $targetStmt->execute([':id' => $id]);
            $targetUser = $targetStmt->fetch();

            if (!$targetUser) {
                throw new Exception("User account not found.");
            }

            // Rule 2: Minimum Active Administrator Quorum (Last Admin Safeguard)
            if ($new_status === 'Deactivated' && $targetUser['role'] === 'Admin') {
                $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND status = 'Active'");
                $activeAdminCount = (int)$adminCountStmt->fetchColumn();
                if ($activeAdminCount <= 1) {
                    throw new Exception("Security Rule Violation: Cannot deactivate the last remaining active Administrator. At least one active Admin must remain in the system.");
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $new_status, ':id' => $id]);
            log_system_event($pdo, "Admin {$_SESSION['user_name']} (ID: {$_SESSION['user_id']}) changed user '{$targetUser['email']}' (ID: {$id}) status to {$new_status}.");
            $_SESSION['success_message'] = "User account ({$targetUser['email']}) status updated to {$new_status} successfully.";
        } catch (PDOException | Exception $e) {
            log_error($e);
            $_SESSION['error_message'] = "Failed to update status: " . $e->getMessage();
        }
    }
    header("Location: ../admin.php#view-users"); // Added hash
    exit();
}
?>