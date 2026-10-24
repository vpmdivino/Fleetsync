<?php
session_start();
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Sanitize and retrieve POST data
    $id = $_POST['id'] ?? null;
    $vehicle_id = filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT);
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
    $completion_date = !empty($_POST['completion_date']) ? $_POST['completion_date'] : null;
    $service_type = trim($_POST['service_type'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $cost = filter_var($_POST['cost'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $status = $_POST['status'] ?? 'Scheduled';

    try {
        if (!$vehicle_id || !$service_type) {
            throw new Exception("Validation Error: Vehicle and service type are required.");
        }

        // Validate Cost (must be non-negative)
        if ($cost !== null) {
            if ($cost === false || $cost < 0) {
                throw new Exception("Validation Error: Maintenance cost cannot accept negative values. Provided: " . htmlspecialchars($_POST['cost']));
            }
            if ($cost > 1000000) {
                throw new Exception("Validation Error: Maintenance cost exceeds maximum allowable threshold.");
            }
        }

        // Validate Scheduled Date (timing rules)
        $now = new DateTime();
        $minDate = (clone $now)->modify('-60 days');
        $maxDate = (clone $now)->modify('+1 year');

        if ($scheduled_date) {
            $sch = new DateTime($scheduled_date);
            if ($sch < $minDate) {
                throw new Exception("Validation Error: Scheduled maintenance date is too early! System rejects outdated historical years (e.g., 2012, 2023). Earliest allowed date is " . $minDate->format('Y-m-d') . ".");
            }
            if ($sch > $maxDate) {
                throw new Exception("Validation Error: Scheduled maintenance date is too late! Maximum advance scheduling horizon is 1 year (Latest allowed: " . $maxDate->format('Y-m-d') . ").");
            }
        }

        if ($completion_date) {
            $comp = new DateTime($completion_date);
            if ($comp < $minDate) {
                throw new Exception("Validation Error: Completion date cannot be an outdated historical year.");
            }
            if ($scheduled_date && isset($sch) && $comp < $sch) {
                throw new Exception("Validation Error: Completion date cannot be earlier than the scheduled date.");
            }
        }

        if ($action === 'add') {
            if (!$scheduled_date) {
                throw new Exception("Validation Error: Scheduled date is required.");
            }
            $sql = "INSERT INTO maintenance_logs (vehicle_id, scheduled_date, service_type, details, cost, status) 
                    VALUES (:vehicle_id, :scheduled_date, :service_type, :details, :cost, :status)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
            $stmt->bindParam(':scheduled_date', $scheduled_date);
            $stmt->bindParam(':service_type', $service_type);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':cost', $cost);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            $_SESSION['success_message'] = "Maintenance scheduled successfully!";
        } elseif ($action === 'edit' && $id) {
            $sql = "UPDATE maintenance_logs SET 
                        vehicle_id = :vehicle_id, 
                        service_type = :service_type, 
                        details = :details, 
                        scheduled_date = :scheduled_date, 
                        completion_date = :completion_date, 
                        cost = :cost, 
                        status = :status,
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
            $stmt->bindParam(':service_type', $service_type);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':scheduled_date', $scheduled_date);
            $stmt->bindParam(':completion_date', $completion_date);
            $stmt->bindParam(':cost', $cost);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            $_SESSION['success_message'] = "Maintenance log updated successfully!";
        } else {
            throw new Exception("Invalid action specified for maintenance log.");
        }
    } catch (PDOException | Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../fleet.php#view-maintenance"); // Added hash
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM maintenance_logs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $_SESSION['success_message'] = "Maintenance log deleted successfully.";
    } catch (PDOException $e) {
        log_error($e);
        $_SESSION['error_message'] = "Failed to delete maintenance log: " . $e->getMessage();
    }
    header("Location: ../fleet.php#view-maintenance"); // Added hash
    exit();
}