<?php
include '../conn.php';
require 'send_ban_notification.php'; // Ensure this file contains the sendBanNotification function

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $id = $_POST['id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        if ($action === 'approve') {
            // Fetch the reported user's email
            $emailQuery = "SELECT u.email FROM users u INNER JOIN reports r ON u.id = r.reported_user WHERE r.id = ?";
            $emailStmt = $conn->prepare($emailQuery);
            if ($emailStmt === false) {
                throw new Exception('Prepare failed for fetching user email: ' . $conn->error);
            }
            $emailStmt->bind_param("i", $id);

            if (!$emailStmt->execute()) {
                throw new Exception('Error fetching user email: ' . $emailStmt->error);
            }
            $emailResult = $emailStmt->get_result();
            $userEmail = null;

            if ($emailResult->num_rows > 0) {
                $row = $emailResult->fetch_assoc();
                $userEmail = $row['email']; // Get the reported user's email
            } else {
                throw new Exception('No user found for the provided report ID.');
            }

            // Change the user status to 2 to indicate that the user has been banned
            $changeStatusQuery = "UPDATE users SET user_status = 2 WHERE id = (SELECT reported_user FROM reports WHERE id = ?)";
            $stmt2 = $conn->prepare($changeStatusQuery);
            if ($stmt2 === false) {
                throw new Exception('Prepare failed for change status: ' . $conn->error);
            }
            $stmt2->bind_param("i", $id);
    
            if (!$stmt2->execute()) {
                throw new Exception('Error changing user status: ' . $stmt2->error);
            }

            // Now delete the report
            $deleteReportQuery = "DELETE FROM reports WHERE id = ?";
            $stmt = $conn->prepare($deleteReportQuery);
            if ($stmt === false) {
                throw new Exception('Prepare failed for delete report: ' . $conn->error);
            }
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Error deleting report: ' . $stmt->error);
            }

            // Send ban notification email
            $emailResponse = sendBanNotification($userEmail);
            if (!$emailResponse['success']) {
                throw new Exception('Failed to send notification email: ' . $emailResponse['message']);
            }

            echo json_encode(['success' => true, 'message' => 'User has been banned and notified.']);

        } elseif ($action === 'reject') {
            // Delete the report from the reports table
            $deleteReportQuery = "DELETE FROM reports WHERE id = ?";
            $stmt = $conn->prepare($deleteReportQuery);
            if ($stmt === false) {
                throw new Exception('Prepare failed for delete report: ' . $conn->error);
            }
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Error deleting report: ' . $stmt->error);
            }

            echo json_encode(['success' => true, 'message' => 'Report has been rejected.']);
        }

        // Commit the transaction
        $conn->commit();

    } catch (Exception $e) {
        // Rollback the transaction if an error occurred
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } finally {
        // Close all prepared statements
        if (isset($stmt)) $stmt->close();
        if (isset($stmt2)) $stmt2->close();
        if (isset($emailStmt)) $emailStmt->close();
        if (isset($reportStmt)) $reportStmt->close();

        // Close the database connection
        mysqli_close($conn);
    }
}
?>