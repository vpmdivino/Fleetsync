<?php
session_start();
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Sanitize and retrieve POST data
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $license_no = trim($_POST['license_no'] ?? '');
    $license_expiry = !empty($_POST['license_expiry']) ? $_POST['license_expiry'] : null;
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $employment_status = $_POST['employment_status'] ?? 'Full-Time';
    $date_hired = !empty($_POST['date_hired']) ? $_POST['date_hired'] : null;

    try {
        if (empty($name) || empty($license_no) || empty($contact) || empty($address)) {
            throw new Exception("Validation Error: Name, license number, contact, and address are required.");
        }

        if (!empty($license_expiry)) {
            $lic = new DateTime($license_expiry);
            if ($lic < new DateTime('2024-01-01')) {
                throw new Exception("Validation Error: Driver license expiry cannot be an obsolete past year (such as 2012, 2023).");
            }
        }

        if (!empty($date_hired)) {
            $hired = new DateTime($date_hired);
            $tomorrow = (new DateTime())->modify('+1 day');
            if ($hired > $tomorrow) {
                throw new Exception("Validation Error: Date hired cannot be in the future.");
            }
            if ($hired < new DateTime('1980-01-01')) {
                throw new Exception("Validation Error: Date hired cannot be prior to 1980.");
            }
        }

        if ($action === 'add') {
            $sql = "INSERT INTO drivers (name, license_no, license_expiry, contact, address, employment_status, date_hired) 
                    VALUES (:name, :license_no, :license_expiry, :contact, :address, :employment_status, :date_hired)";
            $stmt = $pdo->prepare($sql);
        } elseif ($action === 'edit' && $id) {
            $sql = "UPDATE drivers SET 
                        name = :name, 
                        license_no = :license_no, 
                        license_expiry = :license_expiry, 
                        contact = :contact, 
                        address = :address, 
                        employment_status = :employment_status, 
                        date_hired = :date_hired,
                        updated_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        } else {
            throw new Exception("Invalid action specified.");
        }

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':license_no', $license_no);
        $stmt->bindParam(':license_expiry', $license_expiry);
        $stmt->bindParam(':contact', $contact);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':employment_status', $employment_status);
        $stmt->bindParam(':date_hired', $date_hired);
        
        $stmt->execute();
        $_SESSION['success_message'] = "Driver information saved successfully.";

    } catch (PDOException | Exception $e) {
        log_error($e);
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }

    header("Location: ../fleet.php#view-drivers"); // Added hash
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM drivers WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $_SESSION['success_message'] = "Driver deleted successfully.";
    } catch (PDOException $e) {
        log_error($e);
        $_SESSION['error_message'] = "Failed to delete driver: " . $e->getMessage();
    }
    header("Location: ../fleet.php#view-drivers"); // Added hash
    exit();
}