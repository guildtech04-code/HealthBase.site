<?php
// Simple database connection test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

try {
    require '../config/db_connect.php';
    echo "<p>✅ Database connection successful</p>";
    echo "<p>Connection object: " . get_class($conn) . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Test Query</h2>";
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    echo "<p>✅ Query successful. User count: " . $row['count'] . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Query failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Test Login Query</h2>";
try {
    $stmt = $conn->prepare("SELECT id, email, password, role, status FROM users WHERE email = ?");
    $stmt->bind_param("s", "test@example.com");
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        echo "<p>✅ Test user found</p>";
        echo "<p>User ID: " . $user['id'] . "</p>";
        echo "<p>User Role: " . $user['role'] . "</p>";
        echo "<p>User Status: " . $user['status'] . "</p>";
    } else {
        echo "<p>❌ Test user not found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Login query failed: " . $e->getMessage() . "</p>";
}
?>




