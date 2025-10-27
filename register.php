<?php
// register.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$isAdmin = ($role === 'admin');

// Handle registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $company = trim($_POST['company']);
    $position = trim($_POST['position']);
    $type = $_POST['type'];

    if ($first_name && $last_name && $type) {
        // Generate QR data (unique link)
        $qr_data = "ATTENDEE|" . uniqid() . "|" . $first_name . "|" . $last_name . "|" . $company . "|" . $position;

        $stmt = $conn->prepare("INSERT INTO attendees (first_name, last_name, company, position, type, registered_by, qr_code)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssis", $first_name, $last_name, $company, $position, $type, $user_id, $qr_data);
        if ($stmt->execute()) {
            $success = true;
            $attendee_id = $stmt->insert_id;
        } else {
            $error = "Database Error: " . $stmt->error;
        }
    } else {
        $error = "Please fill all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Attendee | Event Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            min-height: 100vh;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 62, 80, 0.3);
        }
        
        .alert {
            border: none;
            border-radius: 8px;
            padding: 15px 20px;
        }
        
        .badge-type {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .badge-delegate { background: #3498db; color: white; }
        .badge-speaker { background: #9b59b6; color: white; }
        .badge-exhibitor { background: #e67e22; color: white; }
        .badge-usher { background: #e74c3c; color: white; }
        .badge-staff { background: #2ecc71; color: white; }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 1000;
                width: 280px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-calendar-alt"></i> Event Manager
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="register.php">
                            <i class="fas fa-user-plus"></i> Register Attendee
                        </a>
                        <a class="nav-link" href="upload.php">
                            <i class="fas fa-upload"></i> Upload CSV
                        </a>
                        <a class="nav-link" href="attendees.php">
                            <i class="fas fa-users"></i> View Attendees
                        </a>
                        <a class="nav-link" href="scan.php">
                            <i class="fas fa-qrcode"></i> QR Scanner
                        </a>
                        <?php if ($isAdmin): ?>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-cog"></i> Manage Users
                        </a>
                        <?php endif; ?>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="h3 mb-1 text-dark">
                                <i class="fas fa-user-plus me-2"></i>Register New Attendee
                            </h2>
                            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($username) ?>!</p>
                        </div>
                        <button class="btn btn-outline-primary d-md-none" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>

                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="mb-0">Attendee Information</h4>
                                </div>
                                <div class="card-body p-4">
                                    <?php if (isset($success) && $success): ?>
                                        <div class="alert alert-success d-flex align-items-center">
                                            <i class="fas fa-check-circle fa-2x me-3"></i>
                                            <div>
                                                <h5 class="mb-1">Attendee registered successfully!</h5>
                                                <p class="mb-0">The attendee has been added to the system and a QR code has been generated.</p>
                                                <div class="mt-2">
                                                    <a href="print_badge.php?id=<?= $attendee_id ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-print me-1"></i>Print Badge
                                                    </a>
                                                    <a href="register.php" class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-plus me-1"></i>Add Another
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif (isset($error)): ?>
                                        <div class="alert alert-danger d-flex align-items-center">
                                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                                            <div>
                                                <h5 class="mb-1">Registration Failed</h5>
                                                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="first_name" class="form-label required-field">First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>" 
                                                       required>
                                                <div class="invalid-feedback">
                                                    Please provide a first name.
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="last_name" class="form-label required-field">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>" 
                                                       required>
                                                <div class="invalid-feedback">
                                                    Please provide a last name.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="company" class="form-label">Company</label>
                                                <input type="text" class="form-control" id="company" name="company" 
                                                       value="<?= isset($_POST['company']) ? htmlspecialchars($_POST['company']) : '' ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="position" class="form-label">Position</label>
                                                <input type="text" class="form-control" id="position" name="position" 
                                                       value="<?= isset($_POST['position']) ? htmlspecialchars($_POST['position']) : '' ?>">
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="type" class="form-label required-field">Attendee Type</label>
                                            <select class="form-select" id="type" name="type" required>
                                                <option value="">-- Select Type --</option>
                                                <option value="delegate" <?= (isset($_POST['type']) && $_POST['type'] == 'delegate') ? 'selected' : '' ?>>Delegate</option>
                                                <option value="speaker" <?= (isset($_POST['type']) && $_POST['type'] == 'speaker') ? 'selected' : '' ?>>Speaker</option>
                                                <option value="exhibitor" <?= (isset($_POST['type']) && $_POST['type'] == 'exhibitor') ? 'selected' : '' ?>>Exhibitor</option>
                                                <option value="usher" <?= (isset($_POST['type']) && $_POST['type'] == 'usher') ? 'selected' : '' ?>>Usher</option>
                                                <option value="staff" <?= (isset($_POST['type']) && $_POST['type'] == 'staff') ? 'selected' : '' ?>>Staff</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Please select an attendee type.
                                            </div>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-user-plus me-2"></i>Register Attendee
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-users fa-2x mb-2"></i>
                                            <h5 class="mb-1">Total Attendees</h5>
                                            <?php
                                            $total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendees");
                                            $total_stmt->execute();
                                            $total_result = $total_stmt->get_result();
                                            $total_attendees = $total_result->fetch_assoc()['total'];
                                            ?>
                                            <h3 class="mb-0"><?= $total_attendees ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <h5 class="mb-1">Checked In</h5>
                                            <?php
                                            $checked_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendees WHERE checkin_time IS NOT NULL");
                                            $checked_stmt->execute();
                                            $checked_result = $checked_stmt->get_result();
                                            $checked_in = $checked_result->fetch_assoc()['total'];
                                            ?>
                                            <h3 class="mb-0"><?= $checked_in ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                            <h5 class="mb-1">Today</h5>
                                            <?php
                                            $today_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendees WHERE DATE(registration_time) = CURDATE()");
                                            $today_stmt->execute();
                                            $today_result = $today_stmt->get_result();
                                            $today_reg = $today_result->fetch_assoc()['total'];
                                            ?>
                                            <h3 class="mb-0"><?= $today_reg ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Auto-focus on first field
        document.getElementById('first_name').focus();
    </script>
</body>
</html>
<?php
// Close database connections
if (isset($total_stmt)) $total_stmt->close();
if (isset($checked_stmt)) $checked_stmt->close();
if (isset($today_stmt)) $today_stmt->close();
if (isset($stmt)) $stmt->close();
$conn->close();
?>