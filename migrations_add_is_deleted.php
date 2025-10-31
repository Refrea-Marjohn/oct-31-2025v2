<?php
require_once __DIR__ . '/config.php';

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): void {
    $db = $conn->query('SELECT DATABASE()')->fetch_row()[0];
    $check = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $check->bind_param('sss', $db, $table, $column);
    $check->execute();
    $exists = (int)$check->get_result()->fetch_row()[0] > 0;
    if (!$exists) {
        $sql = "ALTER TABLE `$table` ADD COLUMN $definition";
        if (!$conn->query($sql)) {
            die("Failed to alter $table: " . $conn->error);
        }
        echo "Added $column to $table\n";
    } else {
        echo "$column already exists on $table\n";
    }
}

ensureColumnExists($conn, 'attorney_documents', 'is_deleted', 'is_deleted TINYINT(1) NOT NULL DEFAULT 0');
ensureColumnExists($conn, 'employee_documents', 'is_deleted', 'is_deleted TINYINT(1) NOT NULL DEFAULT 0');

echo "Done.\n";

