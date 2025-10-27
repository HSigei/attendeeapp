<?php
// scan.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>QR Check-In Scanner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .nav {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .nav a {
            text-decoration: none;
            color: #007bff;
            padding: 8px 15px;
            border: 1px solid #007bff;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav a:hover {
            background: #007bff;
            color: white;
        }
        
        .scanner-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        #reader {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
            border: 2px solid #333;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .instructions {
            background: #e9f7fe;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        
        #result-box {
            margin-top: 20px;
            padding: 20px;
            border-radius: 5px;
            display: none;
        }
        
        #result-box.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        #result-box.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .attendee-info {
            text-align: left;
            margin-top: 15px;
        }
        
        .info-row {
            display: flex;
            margin: 8px 0;
        }
        
        .info-label {
            font-weight: bold;
            width: 120px;
            color: #555;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .camera-controls {
            margin: 15px 0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .camera-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>QR Code Scanner</h1>
        <p>Scan attendee badges for check-in</p>
    </div>

    <div class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="register.php">Register</a>
        <a href="upload.php">Upload CSV</a>
        <a href="attendees.php">Attendees</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="scanner-container">
        <h2>Scan QR Code</h2>
        
        <div class="instructions">
            <strong>Instructions:</strong> Position the QR code within the camera view. Ensure good lighting for best results.
        </div>

        <div class="camera-controls">
            <button class="btn btn-primary" onclick="startCamera()">Start Camera</button>
            <button class="btn btn-secondary" onclick="stopCamera()">Stop Camera</button>
        </div>

        <div id="reader"></div>
        <div id="camera-error" class="camera-error" style="display: none;">
            Camera not available. Please check permissions and try again.
        </div>

        <div id="result-box"></div>
    </div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        let html5QrCode = null;
        const resultBox = document.getElementById('result-box');
        const cameraError = document.getElementById('camera-error');

        function showResult(status, message, attendee = null) {
            resultBox.style.display = 'block';
            resultBox.className = status;
            
            if (status === 'success') {
                resultBox.innerHTML = `
                    <h3>✓ Check-In Successful</h3>
                    <div class="attendee-info">
                        <div class="info-row">
                            <div class="info-label">Name:</div>
                            <div class="info-value">${attendee.first_name} ${attendee.last_name}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Company:</div>
                            <div class="info-value">${attendee.company || 'N/A'}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Position:</div>
                            <div class="info-value">${attendee.position || 'N/A'}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Type:</div>
                            <div class="info-value">${attendee.type}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Time:</div>
                            <div class="info-value">${new Date().toLocaleString()}</div>
                        </div>
                    </div>
                `;
            } else {
                resultBox.innerHTML = `<h3>✗ ${message}</h3>`;
            }
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                resultBox.style.display = 'none';
            }, 5000);
        }

        function onScanSuccess(qrMessage) {
            fetch('scan_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'qr_code=' + encodeURIComponent(qrMessage)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showResult('success', '', data.attendee);
                } else {
                    showResult('error', data.message);
                }
            })
            .catch(() => showResult('error', 'Network error'));
        }

        function onScanFailure(error) {
            // Silent failure
        }

        async function startCamera() {
            try {
                cameraError.style.display = 'none';
                
                if (html5QrCode && html5QrCode.isScanning) {
                    return; // Already running
                }

                html5QrCode = new Html5Qrcode("reader");
                
                await html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: { width: 250, height: 250 }
                    },
                    onScanSuccess,
                    onScanFailure
                );
                
                console.log('Camera started successfully');
                
            } catch (err) {
                console.error('Camera error:', err);
                cameraError.style.display = 'block';
                cameraError.textContent = 'Camera error: ' + err.message;
            }
        }

        function stopCamera() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().then(() => {
                    console.log('Camera stopped');
                }).catch(err => {
                    console.error('Stop error:', err);
                });
            }
        }

        // Auto-start camera when page loads
        window.addEventListener('load', function() {
            setTimeout(startCamera, 1000);
        });
    </script>
</body>
</html>