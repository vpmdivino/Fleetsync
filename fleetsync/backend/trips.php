<?php
session_start();
require_once '../db_connect.php';

// We should add authentication and authorization checks here in a real app

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;

    // Common fields
    $trip_number = trim($_POST['trip_number'] ?? '');
    $driver_id = !empty($_POST['driver_id']) ? filter_var($_POST['driver_id'], FILTER_VALIDATE_INT) : null;
    $vehicle_id = !empty($_POST['vehicle_id']) ? filter_var($_POST['vehicle_id'], FILTER_VALIDATE_INT) : null;
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $departure_datetime = !empty($_POST['departure_datetime']) ? trim($_POST['departure_datetime']) : null;
    $arrival_datetime = !empty($_POST['arrival_datetime']) ? trim($_POST['arrival_datetime']) : null;
    $cargo = trim($_POST['cargo'] ?? '');
    $distance = (isset($_POST['distance']) && $_POST['distance'] !== '') ? filter_var($_POST['distance'], FILTER_VALIDATE_FLOAT) : null;
    $fuel_used = (isset($_POST['fuel_used']) && $_POST['fuel_used'] !== '') ? filter_var($_POST['fuel_used'], FILTER_VALIDATE_FLOAT) : null;
    $status = trim($_POST['status'] ?? 'Pending');
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        if (empty($trip_number)) {
            throw new Exception("Validation Error: Trip Number is required.");
        }
        if (empty($origin) || empty($destination)) {
            throw new Exception("Validation Error: Origin and Destination are required.");
        }

        // Validate Distance (must be non-negative)
        if ($distance !== null) {
            if ($distance === false || $distance < 0) {
                throw new Exception("Validation Error: Distance cannot accept negative values. Provided: " . htmlspecialchars($_POST['distance']) . " mi.");
            }
            if ($distance > 25000) {
                throw new Exception("Validation Error: Distance exceeds maximum allowable limit of 25,000 miles.");
            }
        }

        // Validate Fuel Used (must be non-negative)
        if ($fuel_used !== null) {
            if ($fuel_used === false || $fuel_used < 0) {
                throw new Exception("Validation Error: Fuel used cannot accept negative values. Provided: " . htmlspecialchars($_POST['fuel_used']) . " gal.");
            }
            if ($fuel_used > 5000) {
                throw new Exception("Validation Error: Fuel used exceeds maximum allowable limit of 5,000 gallons.");
            }
        }

        // Scheduling Timing Rules: How early and how late a schedule should be
        $now = new DateTime();
        $minScheduleDate = (clone $now)->modify('-30 days'); // Earliest retroactive cut-off (rejects 2012, 2023, etc.)
        $maxScheduleDate = (clone $now)->modify('+1 year');   // Latest forward scheduling horizon (1 year out)

        $depDate = null;
        if ($departure_datetime) {
            $depDate = DateTime::createFromFormat('Y-m-d\TH:i', $departure_datetime) ?: new DateTime($departure_datetime);
            if (!$depDate) {
                throw new Exception("Validation Error: Invalid departure date/time format.");
            }
            if ($depDate < $minScheduleDate) {
                throw new Exception("Validation Error: Departure schedule date is too early! System rejects outdated historical years (e.g., 2012, 2023). Earliest allowed schedule is " . $minScheduleDate->format('Y-m-d') . " (max 30 days retroactive).");
            }
            if ($depDate > $maxScheduleDate) {
                throw new Exception("Validation Error: Departure schedule date is too late! Maximum forward scheduling horizon is 1 year (Latest allowed: " . $maxScheduleDate->format('Y-m-d') . ").");
            }
        }

        if ($arrival_datetime) {
            $arrDate = DateTime::createFromFormat('Y-m-d\TH:i', $arrival_datetime) ?: new DateTime($arrival_datetime);
            if (!$arrDate) {
                throw new Exception("Validation Error: Invalid arrival date/time format.");
            }
            if ($arrDate < $minScheduleDate) {
                throw new Exception("Validation Error: Arrival schedule date is too early! System rejects outdated historical years (e.g., 2012, 2023). Earliest allowed schedule is " . $minScheduleDate->format('Y-m-d') . ".");
            }
            if ($arrDate > $maxScheduleDate) {
                throw new Exception("Validation Error: Arrival schedule date is too late! Maximum forward scheduling horizon is 1 year (Latest allowed: " . $maxScheduleDate->format('Y-m-d') . ").");
            }
            if ($depDate) {
                if ($arrDate < $depDate) {
                    throw new Exception("Validation Error: Invalid schedule sequence. Arrival datetime cannot be earlier than Departure datetime.");
                }
                $intervalDays = $depDate->diff($arrDate)->days;
                if ($intervalDays > 14) {
                    throw new Exception("Validation Error: Trip duration exceeds maximum allowable limit of 14 days.");
                }
            }
        }

        if ($action === 'add') {
            $sql = "INSERT INTO trips (trip_number, driver_id, vehicle_id, origin, destination, departure_datetime, arrival_datetime, cargo, distance, fuel_used, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$trip_number, $driver_id, $vehicle_id, $origin, $destination, $departure_datetime, $arrival_datetime, $cargo, $distance, $fuel_used, $status, $remarks]);
            $_SESSION['success_message'] = "Trip added successfully!";
        } elseif ($action === 'edit' && $id) {
            $sql = "UPDATE trips SET trip_number = ?, driver_id = ?, vehicle_id = ?, origin = ?, destination = ?, departure_datetime = ?, arrival_datetime = ?, cargo = ?, distance = ?, fuel_used = ?, status = ?, remarks = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$trip_number, $driver_id, $vehicle_id, $origin, $destination, $departure_datetime, $arrival_datetime, $cargo, $distance, $fuel_used, $status, $remarks, $id]);
            $_SESSION['success_message'] = "Trip updated successfully!";
        } else {
            throw new Exception("Invalid action.");
        }
    } catch (PDOException $e) {
        log_error($e);
        // Check for duplicate entry
        if ($e->getCode() == 23000) {
            $_SESSION['error_message'] = "Database error: A trip with this Trip Number already exists.";
        } else {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } catch (Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header('Location: ../fleet.php#view-trips'); // Added hash
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? null;

    if ($action === 'delete' && $id) {
        try {
            $sql = "DELETE FROM trips WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "Trip deleted successfully!";
        } catch (PDOException $e) {
            log_error($e);
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } elseif ($action === 'update_status' && $id && isset($_GET['status'])) {
        try {
            $status = $_GET['status'];
            $sql = "UPDATE trips SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $id]);
            $_SESSION['success_message'] = "Trip status updated successfully!";
        } catch (PDOException $e) {
            log_error($e);
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }

    header('Location: ../fleet.php#view-trips'); // Added hash
    exit();
}