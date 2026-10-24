<?php
session_start();
require_once '../db_connect.php';

// In a real app, you'd have robust authentication and authorization checks.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'record_payment') {
            $invoice_id = filter_var($_POST['invoice_id'], FILTER_VALIDATE_INT);
            $amount_received = filter_var($_POST['amount_received'], FILTER_VALIDATE_FLOAT);
            $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
            $payment_mode = trim($_POST['payment_mode'] ?? '');
            $reference = trim($_POST['reference'] ?? '');

            if (!$invoice_id || $amount_received === false || !$payment_date) {
                throw new Exception("Invoice ID, amount, and payment date are required.");
            }

            // In a real app, you might want to log the payment transaction in a separate `payments` table
            // and then update the invoice status. For simplicity here, we'll just update the invoice.

            $sql = "UPDATE invoices 
                    SET status = 'Paid', 
                        payment_date = :payment_date 
                    WHERE id = :invoice_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':payment_date' => $payment_date,
                ':invoice_id' => $invoice_id
            ]);

            $_SESSION['success_message'] = "Payment for invoice recorded successfully!";
        } else {
            throw new Exception("Invalid action specified for payments.");
        }
    } catch (PDOException | Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../accountant.php#view-payments"); // Added hash
    exit();
}