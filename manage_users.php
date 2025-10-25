
// manage_users.php
session_start();
include 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
        $stmt->bind_param("ss", $username, $hash);
        if ($stmt->execute()) {
            $msg = "✅ User '$username' created successfully!";
        } else {
            $msg = "⚠️ Error: " . $stmt->error;
        }
    } else {
        $msg = "⚠️ Please fill in all fields.";
    }
}

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id !== $_SESSION['user_id']) { // prevent self-deletion
        $conn->query("DELETE FROM users WHERE id=$id");
        header("Location: manage_users.php");
        exit;
    }
}

// Fetch all users
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users | Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        form {
            margin-bottom: 30px;
        }
        input[type=text], input[type=password] {
            width: 45%;
            padding: 8px;
            margin: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover { background: #0056b3; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th { background: #f1f1f1; }
        a.delete {
            color: red;
            text-decoration: none;
        }
        .msg {
            background: #e8f0fe;
            border-left: 4px solid #007bff;
            padding: 8px;
            margin-bottom: 15px;
        }
        .topnav {
            text-align: center;
            margin-bottom: 20px;
        }
        .topnav a {
            margin: 0 10px;
            text-decoration: none;
            color: #007bff;
        }
        .topnav a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Users</h1>
        <div class="topnav">
            <a href="dashboard.php">Dashboard</a> |
            <a href="manage_users.php">Manage Users</a> |
            <a href="logout.php">Logout</a>
        </div>

        <?php if (isset($msg)) echo "<div class='msg'>$msg</div>"; ?>

        <form method="POST">
            <h3>Create New User</h3>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="create_user">Create User</button>
        </form>

        <h3>Existing Users</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= $row['role'] ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <?php if ($row['role'] !== 'admin'): ?>
                            <a href="manage_users.php?delete=<?= $row['id'] ?>" class="delete"
                               onclick="return confirm('Delete user <?= $row['username'] ?>?');">Delete</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
