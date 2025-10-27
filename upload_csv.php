<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$username = $_SESSION['username'];

$message = "";
$success_count = 0;
$error_count = 0;
$error_details = [];

// Set higher limits for large files
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $file_name = $_FILES['csv_file']['name'];
    $file_size = $_FILES['csv_file']['size'];
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

    // Check file size (max 20MB)
    if ($file_size > 20 * 1024 * 1024) {
        $message = '<div class="alert alert-danger">❌ File too large. Maximum size is 20MB.</div>';
    } elseif ($file_ext !== 'csv') {
        $message = '<div class="alert alert-danger">❌ Please upload a valid CSV file.</div>';
    } elseif (!is_uploaded_file($file)) {
        $message = '<div class="alert alert-danger">❌ Invalid file upload.</div>';
    } else {
        $handle = fopen($file, 'r');
        $row = 0;
        $batch_size = 100; // Process in batches
        $batch_data = [];

        // Start transaction for better performance
        $conn->begin_transaction();

        try {
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $row++;
                if ($row == 1) {
                    // Skip header row
                    continue;
                }

                // Validate that we have at least the required columns
                if (count($data) < 7) {
                    $error_count++;
                    $error_details[] = "Row $row: Insufficient columns (expected 7, got " . count($data) . ")";
                    continue;
                }

                // Safely get data with null checks
                $first_name = isset($data[0]) ? trim($data[0]) : '';
                $last_name  = isset($data[1]) ? trim($data[1]) : '';
                $email      = isset($data[2]) ? trim($data[2]) : '';
                $phone      = isset($data[3]) ? trim($data[3]) : '';
                $company    = isset($data[4]) ? trim($data[4]) : '';
                $position   = isset($data[5]) ? trim($data[5]) : '';
                $type       = isset($data[6]) ? strtolower(trim($data[6])) : 'delegate';

                // Validate required fields
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    $error_count++;
                    $error_details[] = "Row $row: Missing required fields (first_name, last_name, or email)";
                    continue;
                }

                // Validate and sanitize type
                $valid_types = ['delegate', 'speaker', 'exhibitor', 'usher', 'staff'];
                if (!in_array($type, $valid_types)) {
                    $type = 'delegate'; // Default to delegate if invalid
                }

                // Generate unique QR code
                $qr_code = "ATTENDEE|" . uniqid() . "|" . $email;

                // Prepare batch data
                $batch_data[] = [
                    $first_name, $last_name, $email, $phone, $company, $position, $type, $qr_code, $user_id
                ];

                // Insert in batches
                if (count($batch_data) >= $batch_size) {
                    $this->insertBatch($batch_data, $conn);
                    $success_count += count($batch_data);
                    $batch_data = [];
                }
            }

            // Insert remaining records
            if (count($batch_data) > 0) {
                $this->insertBatch($batch_data, $conn);
                $success_count += count($batch_data);
            }

            // Commit transaction
            $conn->commit();

            if ($success_count > 0) {
                $message = '<div class="alert alert-success">✅ Successfully imported ' . $success_count . ' attendees!</div>';
                if ($error_count > 0) {
                    $message .= '<div class="alert alert-warning">⚠️ ' . $error_count . ' rows failed to import.</div>';
                }
            } else {
                $message = '<div class="alert alert-warning">❌ No attendees were imported. Please check your CSV format.</div>';
            }

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = '<div class="alert alert-danger">❌ Error during import: ' . $e->getMessage() . '</div>';
        }

        fclose($handle);
    }
}

// Batch insert function for better performance
function insertBatch($batch_data, $conn) {
    $values = [];
    $placeholders = [];
    $types = '';
    $params = [];

    foreach ($batch_data as $row) {
        $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $types .= 'ssssssssi';
        $params = array_merge($params, $row);
    }

    $sql = "INSERT INTO attendees (first_name, last_name, email, phone, company, position, type, qr_code, registered_by) 
            VALUES " . implode(', ', $placeholders);
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Attendees CSV | Event Platform</title>
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
        
        .navbar-brand {
            font-weight: 700;
        }
        
        .upload-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 30px;
        }
        
        .upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            padding: 50px 30px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: var(--info);
            background: #e3f2fd;
            transform: translateY(-2px);
        }
        
        .upload-area.dragover {
            border-color: var(--success);
            background: #d4edda;
        }
        
        .file-input {
            display: none;
        }
        
        .btn-upload {
            background: linear-gradient(135deg, var(--info), #138496);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 25px;
            font-weight: 500;
        }
        
        .btn-upload:hover {
            background: linear-gradient(135deg, #138496, var(--info));
            transform: translateY(-1px);
        }
        
        .file-info {
            background: #e7f3ff;
            border-left: 4px solid var(--info);
            border-radius: 8px;
        }
        
        .format-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }
        
        .progress {
            height: 8px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-calendar-alt me-2"></i>
                Event Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="upload_csv.php">
                            <i class="fas fa-upload me-1"></i> Upload CSV
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendees.php">
                            <i class="fas fa-users me-1"></i> Attendees
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="scan.php">
                            <i class="fas fa-qrcode me-1"></i> QR Scanner
                        </a>
                    </li>
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-cog me-1"></i> Users
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="navbar-nav">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="upload-container">
                    <!-- Header -->
                    <div class="text-center mb-5">
                        <h2 class="text-dark mb-3">
                            <i class="fas fa-cloud-upload-alt text-info me-2"></i>
                            Bulk Import Attendees
                        </h2>
                        <p class="text-muted">Upload a CSV file to import multiple attendees at once</p>
                    </div>

                    <!-- CSV Format Guide -->
                    <div class="format-card p-4 mb-4">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>CSV Format Requirements</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-2">Required Columns:</h6>
                                <code class="text-white bg-transparent">first_name,last_name,email,phone,company,position,type</code>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-2">Example:</h6>
                                <code class="text-white bg-transparent">John,Doe,john@email.com,1234567890,Acme Inc,Manager,delegate</code>
                            </div>
                        </div>
                        <div class="mt-3">
                            <small><i class="fas fa-lightbulb me-1"></i>Valid types: delegate, speaker, exhibitor, usher, staff</small>
                        </div>
                    </div>

                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-file-csv fa-4x text-info mb-3"></i>
                            <h4 class="text-dark">Drag & Drop your CSV file here</h4>
                            <p class="text-muted mb-4">or click to browse files</p>
                            <input type="file" name="csv_file" accept=".csv" class="file-input" id="fileInput" required>
                            <button type="button" class="btn btn-upload">
                                <i class="fas fa-folder-open me-2"></i> Choose File
                            </button>
                            <div class="mt-3">
                                <small class="text-muted">Max file size: 20MB | Supports up to 10,000+ records</small>
                            </div>
                        </div>

                        <!-- File Info -->
                        <div id="fileInfo" class="mt-3" style="display: none;">
                            <div class="alert alert-info file-info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file-csv me-2"></i>
                                        <strong id="fileName"></strong>
                                        <small class="text-muted ms-2" id="fileSize"></small>
                                    </div>
                                    <button type="button" class="btn-close" onclick="clearFile()"></button>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="fas fa-upload me-2"></i> Upload & Import
                            </button>
                        </div>
                    </form>

                    <!-- Results -->
                    <?php if ($message): ?>
                    <div class="mt-4">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($error_details)): ?>
                    <div class="mt-3">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Error Details:</h6>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php foreach (array_slice($error_details, 0, 10) as $error): ?>
                                    <small class="d-block"><?php echo htmlspecialchars($error); ?></small>
                                <?php endforeach; ?>
                                <?php if (count($error_details) > 10): ?>
                                    <small class="text-muted">... and <?php echo count($error_details) - 10; ?> more errors</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            const uploadArea = document.getElementById('uploadArea');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');

            // File selection handler
            fileInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    displayFileInfo(file);
                }
            });

            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });

            function highlight() {
                uploadArea.classList.add('dragover');
            }

            function unhighlight() {
                uploadArea.classList.remove('dragover');
            }

            uploadArea.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                if (files.length > 0) {
                    displayFileInfo(files[0]);
                }
            }

            function displayFileInfo(file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = 'block';
                uploadArea.style.display = 'none';
            }

            function clearFile() {
                fileInput.value = '';
                fileInfo.style.display = 'none';
                uploadArea.style.display = 'block';
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // Form submission with loading state
            document.getElementById('uploadForm').addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Importing...';
            });
        });
    </script>
</body>
</html>