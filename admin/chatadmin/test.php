<?php
// File: debug_session.php - Temporary diagnostic script
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

header('Content-Type: application/json');

// Check session data
echo json_encode([
    'session_data' => $_SESSION,
    'noble_user_type' => gettype($_SESSION['noble_user'] ?? null),
    'noble_user_value' => $_SESSION['noble_user'] ?? null,
    'is_logged_in' => isset($_SESSION['noble_user']),
    'database_connection' => $conn ? 'Connected' : 'Failed',
    'admin_accounts' => []
]);

// Get all admin accounts to verify IDs
if ($conn) {
    $adminQuery = "SELECT id, name, email FROM nobleaccount LIMIT 10";
    $result = $conn->query($adminQuery);
    
    $admins = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
    }
    
    echo json_encode([
        'session_data' => $_SESSION,
        'noble_user_type' => gettype($_SESSION['noble_user'] ?? null),
        'noble_user_value' => $_SESSION['noble_user'] ?? null,
        'is_logged_in' => isset($_SESSION['noble_user']),
        'database_connection' => 'Connected',
        'admin_accounts' => $admins,
        'chat_messages_structure' => []
    ]);
    
    // Check table structure
    $structureQuery = "DESCRIBE chat_messages";
    $structureResult = $conn->query($structureQuery);
    $structure = [];
    if ($structureResult) {
        while ($row = $structureResult->fetch_assoc()) {
            $structure[] = $row;
        }
    }
    
    // Check foreign key constraints
    $fkQuery = "SELECT 
                    CONSTRAINT_NAME, 
                    COLUMN_NAME, 
                    REFERENCED_TABLE_NAME, 
                    REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = 'chat_messages' 
                AND TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL";
    $fkResult = $conn->query($fkQuery);
    $foreignKeys = [];
    if ($fkResult) {
        while ($row = $fkResult->fetch_assoc()) {
            $foreignKeys[] = $row;
        }
    }
    
    echo json_encode([
        'session_data' => $_SESSION,
        'noble_user_type' => gettype($_SESSION['noble_user'] ?? null),
        'noble_user_value' => $_SESSION['noble_user'] ?? null,
        'is_logged_in' => isset($_SESSION['noble_user']),
        'database_connection' => 'Connected',
        'admin_accounts' => $admins,
        'chat_messages_structure' => $structure,
        'foreign_keys' => $foreignKeys,
        'sample_messages' => []
    ]);
    
    // Get sample messages
    $messagesQuery = "SELECT * FROM chat_messages LIMIT 5";
    $messagesResult = $conn->query($messagesQuery);
    $messages = [];
    if ($messagesResult) {
        while ($row = $messagesResult->fetch_assoc()) {
            $messages[] = $row;
        }
    }
    
    echo json_encode([
        'session_data' => $_SESSION,
        'noble_user_type' => gettype($_SESSION['noble_user'] ?? null),
        'noble_user_value' => $_SESSION['noble_user'] ?? null,
        'is_logged_in' => isset($_SESSION['noble_user']),
        'database_connection' => 'Connected',
        'admin_accounts' => $admins,
        'chat_messages_structure' => $structure,
        'foreign_keys' => $foreignKeys,
        'sample_messages' => $messages
    ]);
}
?>