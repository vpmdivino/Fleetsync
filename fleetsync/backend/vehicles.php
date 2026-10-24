<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Sanitize and retrieve POST data
    $id = $_POST['id'] ?? null;
    $plate_number = trim($_POST['plate_number'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $capacity = trim($_POST['capacity'] ?? '');
    $mileage = filter_var($_POST['mileage'], FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? 'Available';
    $registration_date = $_POST['registration_date'] ?? '';
    $insurance_expiry = !empty($_POST['insurance_expiry']) ? $_POST['insurance_expiry'] : null;

    try {
        if (empty($plate_number) || empty($vehicle_type) || empty($brand) || empty($model)) {
            throw new Exception("Validation Error: Plate number, vehicle type, brand, and model are required.");
        }

        if ($mileage === false || $mileage < 0) {
            throw new Exception("Validation Error: Vehicle mileage cannot accept negative values. Provided: " . htmlspecialchars($_POST['mileage']));
        }

        if (!empty($registration_date)) {
            $reg = new DateTime($registration_date);
            $tomorrow = (new DateTime())->modify('+1 day');
            if ($reg > $tomorrow) {
                throw new Exception("Validation Error: Vehicle registration date cannot be in the future.");
            }
            if ($reg < new DateTime('1980-01-01')) {
                throw new Exception("Validation Error: Vehicle registration date cannot be prior to 1980.");
            }
        }

        if (!empty($insurance_expiry)) {
            $ins = new DateTime($insurance_expiry);
            if ($ins < new DateTime('2024-01-01')) {
                throw new Exception("Validation Error: Insurance expiry date cannot be an obsolete past year (such as 2012, 2023).");
            }
        }

        if ($action === 'add') {
            $sql = "INSERT INTO vehicles (plate_number, vehicle_type, brand, model, capacity, mileage, status, registration_date, insurance_expiry) 
                    VALUES (:plate_number, :vehicle_type, :brand, :model, :capacity, :mileage, :status, :registration_date, :insurance_expiry)";
            $stmt = $pdo->prepare($sql);
            $log_action = "Added new vehicle with plate number: {$plate_number}.";

        } elseif ($action === 'edit' && $id) {
            $sql = "UPDATE vehicles SET 
                        plate_number = :plate_number, 
                        vehicle_type = :vehicle_type, 
                        brand = :brand, 
                        model = :model, 
                        capacity = :capacity, 
                        mileage = :mileage, 
                        status = :status, 
                        registration_date = :registration_date, 
                        insurance_expiry = :insurance_expiry,
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $log_action = "Edited vehicle with plate number: {$plate_number}.";
        } else {
            throw new Exception("Invalid action.");
        }

        $stmt->bindParam(':plate_number', $plate_number);
        $stmt->bindParam(':vehicle_type', $vehicle_type);
        $stmt->bindParam(':brand', $brand);
        $stmt->bindParam(':model', $model);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->bindParam(':mileage', $mileage, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':registration_date', $registration_date);
        $stmt->bindParam(':insurance_expiry', $insurance_expiry);
        
        $stmt->execute();

        if (isset($log_action)) {
            log_system_event($pdo, $log_action);
        }
        $_SESSION['success_message'] = "Vehicle information saved successfully.";

    } catch (PDOException | Exception $e) {
        // In production, you should log the error instead of echoing it.
        log_error($e);
        // For now, we can redirect with an error message for simplicity.
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }

    header("Location: ../fleet.php#view-vehicles"); // Added hash
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        log_system_event($pdo, "Deleted vehicle with ID: {$id}.");
        $_SESSION['success_message'] = "Vehicle deleted successfully.";
    } catch (PDOException $e) {
        log_error($e);
        $_SESSION['error_message'] = "Failed to delete vehicle: " . $e->getMessage();
    }
    header("Location: ../fleet.php#view-vehicles"); // Added hash
    exit();
}