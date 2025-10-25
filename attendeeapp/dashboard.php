<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

// Access control - check for the correct session variable
if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$isAdmin = ($role === 'admin');

// Stats queries
$total_attendees = $conn->query("SELECT COUNT(*) as total FROM attendees")->fetch_assoc()['total'];
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$checked_in = $conn->query("SELECT COUNT(*) as total FROM attendees WHERE checkin_time IS NOT NULL")->fetch_assoc()['total'];
$today_registrations = $conn->query("SELECT COUNT(*) as total FROM attendees WHERE DATE(registration_time) = CURDATE()")->fetch_assoc()['total'];

// Get recent attendees
$recent_attendees = $conn->query("SELECT a.*, u.username as registered_by_name 
                                 FROM attendees a 
                                 LEFT JOIN users u ON a.registered_by = u.id 
                                 ORDER BY a.registration_time DESC 
                                 LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Get recent check-ins
$recent_checkins = $conn->query("SELECT a.*, u.username as registered_by_name 
                                FROM attendees a 
                                LEFT JOIN users u ON a.registered_by = u.id 
                                WHERE a.checkin_time IS NOT NULL 
                                ORDER BY a.checkin_time DESC 
                                LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Attendee type breakdown
$types = ["delegate", "speaker", "exhibitor", "usher", "staff"];
$type_counts = [];
foreach ($types as $type) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendees WHERE type = ?");
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $type_counts[$type] = $result['total'];
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Event Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .card-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-success { background: linear-gradient(135deg, #42b883 0%, #347474 100%); }
        .card-warning { background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%); }
        .card-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .card-danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .stat-card .card-body {
            color: white;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .table th {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 12px;
            font-weight: 600;
        }
        
        .table td {
            padding: 12px;
            vertical-align: middle;
            border-color: #f1f2f6;
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
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section-title {
            color: var(--primary);
            border-left: 4px solid var(--info);
            padding-left: 15px;
            margin-bottom: 20px;
        }
        
        .recent-activity {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #f1f2f6;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .attendee-photo {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.8rem;
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link" href="register.php">
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
                            <h2 class="h3 mb-1 text-dark">Dashboard Overview</h2>
                            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($username) ?>!</p>
                        </div>
                        <button class="btn btn-outline-primary d-md-none" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-5">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card card-primary" onclick="window.location.href='attendees.php'">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-3"></i>
                                    <h3 class="card-title"><?= $total_attendees ?></h3>
                                    <p class="card-text">Total Attendees</p>
                                    <small>Click to view all</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card card-success" onclick="window.location.href='attendees.php?filter=checkedin'">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x mb-3"></i>
                                    <h3 class="card-title"><?= $checked_in ?></h3>
                                    <p class="card-text">Checked In</p>
                                    <small><?= $total_attendees > 0 ? round(($checked_in / $total_attendees) * 100) : 0 ?>% of total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card card-warning" onclick="window.location.href='attendees.php?filter=pending'">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-3"></i>
                                    <h3 class="card-title"><?= $total_attendees - $checked_in ?></h3>
                                    <p class="card-text">Pending Check-in</p>
                                    <small>Awaiting arrival</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card card-info" onclick="window.location.href='attendees.php?filter=today'">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-day fa-2x mb-3"></i>
                                    <h3 class="card-title"><?= $today_registrations ?></h3>
                                    <p class="card-text">Today's Registrations</p>
                                    <small>New attendees today</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isAdmin): ?>
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card card-danger" onclick="window.location.href='users.php'">
                                <div class="card-body text-center">
                                    <i class="fas fa-users-cog fa-2x mb-3"></i>
                                    <h3 class="card-title"><?= $total_users ?></h3>
                                    <p class="card-text">System Users</p>
                                    <small>Admin & Staff accounts</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Type Breakdown Cards -->
                        <?php foreach ($type_counts as $type => $count): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card" style="background: linear-gradient(135deg, <?= getTypeColor($type) ?>);" 
                                 onclick="window.location.href='attendees.php?type=<?= $type ?>'">
                                <div class="card-body text-center">
                                    <i class="fas fa-<?= getTypeIcon($type) ?> fa-2x mb-3"></i>
                                    <h3 class="card-title"><?= $count ?></h3>
                                    <p class="card-text"><?= ucfirst($type) ?>s</p>
                                    <small><?= $total_attendees > 0 ? round(($count / $total_attendees) * 100) : 0 ?>% of total</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Charts -->
                        <div class="col-lg-8 mb-4">
                            <div class="chart-container">
                                <h4 class="section-title">Attendee Analytics</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <canvas id="attendeeChart" height="250"></canvas>
                                    </div>
                                    <div class="col-md-6">
                                        <canvas id="pieChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="col-lg-4 mb-4">
                            <div class="recent-activity">
                                <h4 class="section-title">Recent Check-ins</h4>
                                <?php if (!empty($recent_checkins)): ?>
                                    <?php foreach ($recent_checkins as $attendee): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <div class="attendee-photo me-3">
                                                <?= strtoupper(substr($attendee['first_name'], 0, 1) . substr($attendee['last_name'], 0, 1)) ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <strong><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) ?></strong>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($attendee['company']) ?>
                                                </div>
                                            </div>
                                            <span class="badge badge-type badge-<?= $attendee['type'] ?>">
                                                <?= ucfirst($attendee['type']) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted ms-5">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('g:i A', strtotime($attendee['checkin_time'])) ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No recent check-ins</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Registrations Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="table-container">
                                <div class="p-3 border-bottom">
                                    <h5 class="mb-0">
                                        <i class="fas fa-list me-2"></i>
                                        Recent Registrations
                                        <a href="attendees.php" class="btn btn-sm btn-primary float-end">View All</a>
                                    </h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Company</th>
                                                <th>Position</th>
                                                <th>Type</th>
                                                <th>Registered By</th>
                                                <th>Registration Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_attendees)): ?>
                                                <?php foreach ($recent_attendees as $attendee): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="attendee-photo me-3">
                                                                <?= strtoupper(substr($attendee['first_name'], 0, 1) . substr($attendee['last_name'], 0, 1)) ?>
                                                            </div>
                                                            <strong><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) ?></strong>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($attendee['company']) ?></td>
                                                    <td><?= htmlspecialchars($attendee['position']) ?></td>
                                                    <td>
                                                        <span class="badge badge-type badge-<?= $attendee['type'] ?>">
                                                            <?= ucfirst($attendee['type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($attendee['registered_by_name'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, g:i A', strtotime($attendee['registration_time'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php if ($attendee['checkin_time']): ?>
                                                            <span class="badge bg-success">Checked In</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                                        <p class="text-muted">No attendees registered yet</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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

        // Bar chart
        new Chart(document.getElementById('attendeeChart'), {
            type: 'bar',
            data: {
                labels: ['Delegates', 'Speakers', 'Exhibitors', 'Ushers', 'Staff'],
                datasets: [{
                    label: 'Attendees',
                    data: [
                        <?= $type_counts['delegate'] ?>,
                        <?= $type_counts['speaker'] ?>,
                        <?= $type_counts['exhibitor'] ?>,
                        <?= $type_counts['usher'] ?>,
                        <?= $type_counts['staff'] ?>
                    ],
                    backgroundColor: [
                        '#3498db', '#9b59b6', '#e67e22', '#e74c3c', '#2ecc71'
                    ],
                    borderColor: [
                        '#2980b9', '#8e44ad', '#d35400', '#c0392b', '#27ae60'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Pie chart
        new Chart(document.getElementById('pieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Delegates', 'Speakers', 'Exhibitors', 'Ushers', 'Staff'],
                datasets: [{
                    data: [
                        <?= $type_counts['delegate'] ?>,
                        <?= $type_counts['speaker'] ?>,
                        <?= $type_counts['exhibitor'] ?>,
                        <?= $type_counts['usher'] ?>,
                        <?= $type_counts['staff'] ?>
                    ],
                    backgroundColor: [
                        '#3498db', '#9b59b6', '#e67e22', '#e74c3c', '#2ecc71'
                    ],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Add click animations to cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>
</html>

<?php
// Helper functions for type styling
function getTypeColor($type) {
    $colors = [
        'delegate' => '#3498db, #2980b9',
        'speaker' => '#9b59b6, #8e44ad',
        'exhibitor' => '#e67e22, #d35400',
        'usher' => '#e74c3c, #c0392b',
        'staff' => '#2ecc71, #27ae60'
    ];
    return $colors[$type] ?? '#95a5a6, #7f8c8d';
}

function getTypeIcon($type) {
    $icons = [
        'delegate' => 'user',
        'speaker' => 'microphone',
        'exhibitor' => 'store',
        'usher' => 'hands-helping',
        'staff' => 'user-tie'
    ];
    return $icons[$type] ?? 'user';
}
?>