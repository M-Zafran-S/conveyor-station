<?php
// Enable CORS for Unity WebGL requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// Set timezone to Malaysia Time (UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Replace with your real Firebase Database URL ending in /ProductionData.json
$firebaseUrl = "https://unity-sql-app-default-rtdb.asia-southeast1.firebasedatabase.app/ProductionData.json";
$logsUrl = "https://unity-sql-app-default-rtdb.asia-southeast1.firebasedatabase.app/ProductionLogs.json";

function firebaseRequest($url, $method = 'GET',$data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function addProductionLog($logsUrl,$producedCount, $isInterrupted = false) {$timestamp = date("Y-m-d H:i:s");
    $producedDisplay =$isInterrupted ? $producedCount . "*" : (string)$producedCount;
    
    $logEntry = [
        'timestamp' => $timestamp,
        'produced'  => $producedDisplay
    ];
    
    firebaseRequest($logsUrl, 'POST',$logEntry);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action =$_POST['action'] ?? '';

    if ($action === 'set_demand') {$newDemand = isset($_POST['demand']) ? (int)$_POST['demand'] : 0;
        
        $currentData = firebaseRequest($firebaseUrl, 'GET');$oldDemand   = isset($currentData['Demand']) ? (int)$currentData['Demand'] : null;
        $oldProduced = isset($currentData['Produced']) ? (int)$currentData['Produced'] : 0;

        if ($oldProduced > 0 && ($oldDemand === null || $oldProduced <$oldDemand)) {
            addProductionLog($logsUrl,$oldProduced, true);
        }

        $payload = [
            'Demand'       => $newDemand,
            'Produced'     => 0,
            'last_updated' => time(),
            'logged'       => false,
            'mpu'          => '00:00'
        ];
        firebaseRequest($firebaseUrl, 'PATCH',$payload);

        echo json_encode(['status' => 'success', 'demand' => $newDemand]);
        exit;
    }

    if ($action === 'reset_system') {$currentData = firebaseRequest($firebaseUrl, 'GET');$oldDemand   = isset($currentData['Demand']) ? (int)$currentData['Demand'] : null;
        $oldProduced = isset($currentData['Produced']) ? (int)$currentData['Produced'] : 0;

        if ($oldProduced > 0 && ($oldDemand === null || $oldProduced <$oldDemand)) {
            addProductionLog($logsUrl,$oldProduced, true);
        }

        $payload = [
            'Demand'       => null,
            'Produced'     => 0,
            'last_updated' => time(),
            'logged'       => false,
            'mpu'          => '00:00'
        ];
        firebaseRequest($firebaseUrl, 'PUT',$payload);

        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'get_status') {
        $data = firebaseRequest($firebaseUrl, 'GET');

        $demand      = isset($data['Demand']) ? (int)$data['Demand'] : null;
        $produced    = isset($data['Produced']) ? (int)$data['Produced'] : 0;
        $lastUpdated = isset($data['last_updated']) ? (int)$data['last_updated'] : time();$isLogged    = isset($data['logged']) ? (bool)$data['logged'] : false;
        $mpu         = isset($data['mpu']) ? (string)$data['mpu'] : '00:00';$currentTime = time();

        if ($produced > 0 && ($currentTime -$lastUpdated >= 300)) {
            $isInterrupted = ($demand === null || $produced <$demand);
            addProductionLog($logsUrl, $produced,$isInterrupted);

            $resetPayload = [
                'Demand'       => null,
                'Produced'     => 0,
                'last_updated' => $currentTime,
                'logged'       => false,
                'mpu'          => '00:00'
            ];
            firebaseRequest($firebaseUrl, 'PUT',$resetPayload);

            $demand   = null;
            $produced = 0;
            $isLogged = false;
            $mpu      = '00:00';
        }

        $isCompleted = ($demand !== null &&$demand > 0 && $produced >=$demand);
        if ($isCompleted && !$isLogged) {
            firebaseRequest($firebaseUrl, 'PATCH', ['logged' => true]);
            addProductionLog($logsUrl,$produced, false);
        }

        // Retrieve historical logs and calculate Cumulative Produced (Logs Total + Active Produced)
        $logsData = firebaseRequest($logsUrl, 'GET');
        $logsList = [];$cumulative = 0;

        if (is_array($logsData)) {
            foreach ($logsData as $key =>$entry) {
                if (is_array($entry) && isset($entry['timestamp'])) {
                    $logsList[] =$entry;

                    // Strip interruption asterisk (*) for raw sum
                    $rawCount = (int)str_replace('*', '',$entry['produced'] ?? '0');
                    $cumulative +=$rawCount;
                }
            }
        }

        // Add currently active batch count
        $cumulative +=$produced;

        echo json_encode([
            'status'     => 'success',
            'demand'     => $demand,
            'produced'   => $produced,
            'cumulative' => $cumulative,
            'mpu'        => $mpu,
            'completed'  => $isCompleted,
            'logs'       => array_reverse($logsList)
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Conveyor Production Monitoring System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #121212; color: #fff; }
        .container { display: flex; gap: 20px; flex-wrap: wrap; }
        .card { background: #1e1e1e; padding: 25px; border-radius: 8px; width: 380px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="number"] { width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #333; background: #2a2a2a; color: #fff; box-sizing: border-box; }
        .btn { width: 100%; padding: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-bottom: 10px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .stats { margin-top: 15px; padding: 15px; background: #2a2a2a; border-radius: 6px; font-size: 16px; }
        .completed-banner { display: none; margin-top: 15px; padding: 15px; background: #28a745; color: white; border-radius: 6px; font-weight: bold; text-align: center; font-size: 16px; }
        .alert-toast { display: none; margin-top: 15px; padding: 10px; background: #17a2b8; color: white; border-radius: 4px; text-align: center; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #2a2a2a; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>Production Control</h2>

        <div class="form-group">
            <label for="demandInput">Set Target Demand:</label>
            <input type="number" id="demandInput" min="1" placeholder="Enter quantity (e.g., 5)">
        </div>
        <button class="btn btn-primary" onclick="updateDemand()">Update Target Demand</button>
        <button class="btn btn-danger" onclick="resetSystem()">Reset Target (Clear)</button>

        <div id="orderNotification" class="alert-toast">📦 Target updated successfully!</div>

        <div class="stats">
            <p><strong>Target Demand:</strong> <span id="displayDemand">Not Set</span></p>
            <p><strong>Currently Produced:</strong> <span id="displayProduced">0</span></p>
            <p><strong>Cumulative Produced:</strong> <span id="displayCumulative">0</span></p>
            <p><strong>Minutes per Unit (MPU):</strong> <span id="displayMpu">00:00</span></p>
        </div>

        <div id="completionBanner" class="completed-banner">🎉 Process Has Been Completed!</div>
    </div>

    <div class="card" style="flex-grow: 1; max-width: 500px;">
        <h2>Production Logs</h2>
        <table>
            <thead>
                <tr>
                    <th>Timestamp (MYT)</th>
                    <th>Produced</th>
                </tr>
            </thead>
            <tbody id="logsTableBody">
                <tr><td colspan="2">No logs recorded yet.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateDemand() {
    const input = document.getElementById('demandInput');
    const demandVal = input.value;
    if (!demandVal || demandVal <= 0) return alert("Please enter a valid target.");

    const formData = new FormData();
    formData.append('action', 'set_demand');
    formData.append('demand', demandVal);

    fetch('production_manager.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const toast = document.getElementById('orderNotification');
                toast.style.display = 'block';
                setTimeout(() => { toast.style.display = 'none'; }, 3000);
                input.value = '';
                checkStatus();
            }
        });
}

function resetSystem() {
    const formData = new FormData();
    formData.append('action', 'reset_system');
    fetch('production_manager.php', { method: 'POST', body: formData }).then(() => checkStatus());
}

function checkStatus() {
    const formData = new FormData();
    formData.append('action', 'get_status');

    fetch('production_manager.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('displayDemand').innerText = (data.demand !== null) ? data.demand : "Not Set";
                document.getElementById('displayProduced').innerText = data.produced;
                document.getElementById('displayCumulative').innerText = (data.cumulative !== undefined) ? data.cumulative : 0;
                
                const mpuValue = (data.mpu !== undefined && data.mpu !== null && data.mpu !== "0") ? data.mpu : "00:00";
                document.getElementById('displayMpu').innerText = mpuValue;

                document.getElementById('completionBanner').style.display = data.completed ? 'block' : 'none';

                const tableBody = document.getElementById('logsTableBody');
                if (data.logs && data.logs.length > 0) {
                    tableBody.innerHTML = data.logs.map(log => 
                        `<tr><td>${log.timestamp}</td><td>${log.produced}</td></tr>`
                    ).join('');
                } else {
                    tableBody.innerHTML = '<tr><td colspan="2">No logs recorded yet.</td></tr>';
                }
            }
        })
        .catch(err => console.error("Error fetching status:", err));
}

setInterval(checkStatus, 1000);
checkStatus();
</script>

</body>
</html>