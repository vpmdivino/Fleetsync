<?php
session_start();
require_once '../db_connect.php';

// In a real app, you'd have robust authentication and authorization checks.
// For example, ensuring only users with an 'Accountant' role can access this.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            // Sanitize and retrieve POST data
            $category = trim($_POST['expense_category'] ?? '');
            $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
            $date_incurred = !empty($_POST['date_incurred']) ? $_POST['date_incurred'] : null;
            $vendor = trim($_POST['vendor'] ?? '');

            if (!$category || $amount === false || !$date_incurred) {
                throw new Exception("Category, amount, and date are required fields.");
            }

            $sql = "INSERT INTO expenses (expense_category, amount, date_incurred, vendor) 
                    VALUES (:category, :amount, :date_incurred, :vendor)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':category' => $category,
                ':amount' => $amount,
                ':date_incurred' => $date_incurred,
                ':vendor' => $vendor
            ]);

            $_SESSION['success_message'] = "Expense logged successfully!";

        } else {
            throw new Exception("Invalid action specified for expenses.");
        }
    } catch (PDOException | Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../accountant.php#view-expenses"); // Added hash
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success_message'] = "Expense record deleted successfully.";
    } catch (PDOException $e) {
        log_error($e);
        $_SESSION['error_message'] = "Failed to delete expense record: " . $e->getMessage();
    }
    header("Location: ../accountant.php#view-expenses"); // Added hash
    exit();
}