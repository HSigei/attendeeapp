<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php'; // Changed from require_once to include

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$message = '';

// Handle Add User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password, $role);
    
    if ($stmt->execute()) {
        $message = "User created successfully!";
    } else {
        $message = "Error creating user: " . $conn->error;
    }
    $stmt->close();
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Prevent deleting the main admin account
    if ($id > 1) { // Assuming admin user has ID 1
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "User deleted successfully!";
        } else {
            $message = "Error deleting user: " . $conn->error;
        }
        $stmt->close();
    } else {
        $message = "Cannot delete the main admin account!";
    }
}

// Fetch all users
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Management | Admin Panel</title>
<style>
    body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f6fa;
        margin: 0;
        padding: 0;
    }
    .container {
        width: 90%;
        margin: 50px auto;
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 {
        color: #2f3640;
        text-align: center;
    }
    form {
        margin-bottom: 30px;
    }
    input[type="text"], input[type="password"], select {
        padding: 10px;
        width: 250px;
        border: 1px solid #ccc;
        border-radius: 6px;
        margin-right: 10px;
    }
    button {
        background-color: #0097e6;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
    }
    button:hover {
        background-color: #0984e3;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    th, td {
        border: 1px solid #dcdde1;
        padding: 10px;
        text-align: left;
    }
    th {
        background-color: #718093;
        color: white;
    }
    tr:nth-child(even) {
        background-color: #f1f2f6;
    }
    .message {
        background: #dff9fb;
        color: #130f40;
        padding: 10px;
        border-radius: 6px;
        text-align: center;
        margin-bottom: 15px;
    }
    .delete-btn {
        color: red;
        text-decoration: none;
        font-weight: bold;
    }
    .logout-btn {
        float: right;
        background-color: #e84118;
        margin-top: -40px;
    }
</style>
</head>
<body>
<div class="container">
    <h2>Admin - User Management</h2>
    <a href="dashboard.php"><button>⬅ Back to Dashboard</button></a>
    <a href="logout.php"><button class="logout-btn">Logout</button></a>

    <?php if (!empty($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role">
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select>
        <button type="submit" name="add_user">Add User</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($users) > 0): ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo $user['created_at']; ?></td>
                    <td>
                        <?php if ($user['username'] !== 'admin' && $user['id'] > 1): ?>
                            <a href="?delete=<?php echo $user['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">No users found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>