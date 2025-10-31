<?php
session_start();

// Clear the fresh_login flag
if (isset($_SESSION['fresh_login'])) {
    unset($_SESSION['fresh_login']);
}

// Return success
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>

