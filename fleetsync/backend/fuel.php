<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Sanitize and retrieve POST data
    $vehicle_id = filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT);
    $gallons_pumped = filter_var($_POST['gallons_pumped'], FILTER_VALIDATE_FLOAT);
    $total_cost = filter_var($_POST['total_cost'], FILTER_VALIDATE_FLOAT);

    try {
        if ($action === 'add') {
            if (!$vehicle_id || $gallons_pumped === false || $total_cost === false) {
                throw new Exception("Validation Error: Vehicle, gallons pumped, and total cost are required.");
            }
            if ($gallons_pumped <= 0) {
                throw new Exception("Validation Error: Gallons pumped must be greater than zero. Provided: " . htmlspecialchars($_POST['gallons_pumped']));
            }
            if ($total_cost < 0) {
                throw new Exception("Validation Error: Total cost cannot be negative. Provided: " . htmlspecialchars($_POST['total_cost']));
            }

            $sql = "INSERT INTO fuel_logs (vehicle_id, gallons_pumped, total_cost) 
                    VALUES (:vehicle_id, :gallons_pumped, :total_cost)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':vehicle_id' => $vehicle_id,
                ':gallons_pumped' => $gallons_pumped,
                ':total_cost' => $total_cost
            ]);
            $_SESSION['success_message'] = "Fuel log added successfully!";
        } else {
            throw new Exception("Invalid action.");
            log_error($e);
        }
    } catch (PDOException | Exception $e) {
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../fleet.php#view-fuel"); // Added hash
    exit();
}