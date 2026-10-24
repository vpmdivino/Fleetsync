<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

// We should add authentication and authorization checks here in a real app

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'log_event') {
            $trip_id = filter_var($_POST['trip_id'], FILTER_VALIDATE_INT);
            $event_type = trim($_POST['event_type'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $event_datetime = !empty($_POST['event_datetime']) ? $_POST['event_datetime'] : date('Y-m-d H:i:s');

            if (!$trip_id || !$event_type) {
                throw new Exception("Trip ID and event type are required.");
            }

            // This assumes a `trip_events` table exists.
            // You would need to create it.
            /*
            CREATE TABLE `trip_events` (
              `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `trip_id` bigint(20) UNSIGNED NOT NULL,
              `event_type` varchar(50) NOT NULL,
              `notes` text DEFAULT NULL,
              `event_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `fk_event_trip` (`trip_id`),
              CONSTRAINT `fk_event_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            */

            $sql = "INSERT INTO trip_events (trip_id, event_type, notes, event_datetime) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$trip_id, $event_type, $notes, $event_datetime]);

            $_SESSION['success_message'] = "Trip event logged successfully!";

        } elseif ($action === 'confirm_delivery') {
            $trip_id = filter_var($_POST['trip_id'], FILTER_VALIDATE_INT);

            if (!$trip_id) {
                throw new Exception("Trip ID is required.");
            }

            // Here you could also handle file uploads for the Bill of Lading

            $sql = "UPDATE trips SET status = 'Completed', arrival_datetime = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$trip_id]);

            $_SESSION['success_message'] = "Delivery confirmed and trip status updated to 'Completed'.";

        } else {
            throw new Exception("Invalid tracking action.");
        }
    } catch (PDOException | Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../fleet.php#view-tracking"); // Added hash
    exit();
}