<?php
session_start();
require_once '../db_connect.php';

// In a real app, you'd have robust authentication and authorization checks.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add' || $action === 'edit') {
            // Sanitize and retrieve POST data
            $id = !empty($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
            $trip_id = !empty($_POST['trip_id']) ? filter_var($_POST['trip_id'], FILTER_VALIDATE_INT) : null;
            $client_name = trim($_POST['client_name'] ?? '');
            $amount = filter_var(str_replace(['₱', ',', '$'], '', $_POST['amount']), FILTER_VALIDATE_FLOAT);
            $status = $_POST['status'] ?? 'Draft';
            $issue_date = !empty($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+30 days'));

            if (!$client_name || $amount === false) {
                throw new Exception("Client name and amount are required.");
            }

            if ($action === 'add') {
                // Generate a unique invoice number
                $invoice_number = 'INV-' . date('Y') . '-' . str_pad($pdo->query("SELECT COUNT(id) FROM invoices WHERE YEAR(issue_date) = YEAR(CURDATE())")->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);

                $sql = "INSERT INTO invoices (trip_id, invoice_number, client_name, amount, status, issue_date, due_date) 
                        VALUES (:trip_id, :invoice_number, :client_name, :amount, :status, :issue_date, :due_date)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':trip_id' => $trip_id,
                    ':invoice_number' => $invoice_number,
                    ':client_name' => $client_name,
                    ':amount' => $amount,
                    ':status' => 'Draft', // Always start as draft
                    ':issue_date' => $issue_date,
                    ':due_date' => $due_date
                ]);
                $_SESSION['success_message'] = "Invoice #{$invoice_number} created successfully!";
            } else { // 'edit'
                if (!$id) throw new Exception("Invalid invoice ID for editing.");

                // If status is being changed to 'Paid', also set the payment_date
                $payment_date_sql = ($status === 'Paid') ? ", payment_date = COALESCE(payment_date, NOW())" : "";

                $sql = "UPDATE invoices SET 
                            client_name = :client_name, 
                            amount = :amount, 
                            status = :status, 
                            issue_date = :issue_date, 
                            due_date = :due_date 
                            {$payment_date_sql}
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':client_name' => $client_name,
                    ':amount' => $amount,
                    ':status' => $status,
                    ':issue_date' => $issue_date,
                    ':due_date' => $due_date,
                    ':id' => $id
                ]);
                $_SESSION['success_message'] = "Invoice updated successfully!";
            }
        } else {
            throw new Exception("Invalid action specified for invoices.");
        }
    } catch (PDOException | Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../accountant.php#view-invoices"); // Added hash
    exit();
}

// Handle GET requests for actions like deleting or changing status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    // Placeholder for future actions like delete or void
    // e.g., ?action=void&id=123

    header("Location: ../accountant.php#view-invoices"); // Added hash
    exit();
}
?>