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

function firebaseRequest($url, $method = 'GET', $data = null) {
    if (empty($url)) return null;

<<<<<<< HEAD
    $options = [
        'http' => [
            'method'        => $method,
            'ignore_errors' => true,
            'timeout'       => 10,
            'header'        => "Content-Type: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false
        ]
    ];

=======
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
>>>>>>> a3bc2f908b713859889bd667ae932f382202fe98
    if ($data !== null) {
        $content = json_encode($data);
        $options['http']['content'] = $content;
        $options['http']['header'] .= "Content-Length: " . strlen($content) . "\r\n";
    }

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

function closeBatch($logsUrl, $batchId, $produced, $status) {
    if (empty($logsUrl) || empty($batchId)) return;
    $batchItemUrl = str_replace('.json', '/' . $batchId . '.json', $logsUrl);

    $patchData = [
        'produced' => (int)$produced,
        'end_time' => date("Y-m-d H:i:s"),
        'status'   => $status
    ];
    firebaseRequest($batchItemUrl, 'PATCH', $patchData);
}

function createBatch($logsUrl, $demand) {
    if (empty($logsUrl)) return null;

    $batchData = [
        'start_time' => date("Y-m-d H:i:s"),
        'end_time'   => '-',
        'demand'     => (int)$demand,
        'produced'   => 0,
        'status'     => 'IN_PROGRESS'
    ];

    $response = firebaseRequest($logsUrl, 'POST', $batchData);
    return $response['name'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'set_demand') {
        $newDemand = isset($_POST['demand']) ? (int)$_POST['demand'] : 0;
        
        $currentData = firebaseRequest($firebaseUrl, 'GET');
        $oldDemand   = isset($currentData['Demand']) ? (int)$currentData['Demand'] : 0;
        $oldProduced = isset($currentData['Produced']) ? (int)$currentData['Produced'] : 0;
        $oldBatchId  = $currentData['active_batch_id'] ?? '';

        $newBatchId = $oldBatchId;

<<<<<<< HEAD
        // If zero units were produced, reuse and update the active batch in place
=======
        // RULE UPDATE: If zero units were produced, reuse and update the active batch's start_time & demand
>>>>>>> a3bc2f908b713859889bd667ae932f382202fe98
        if (!empty($oldBatchId) && $oldProduced === 0) {
            $batchItemUrl = str_replace('.json', '/' . $oldBatchId . '.json', $logsUrl);
            $updateData = [
                'start_time' => date("Y-m-d H:i:s"),
                'demand'     => $newDemand,
                'produced'   => 0,
                'status'     => 'IN_PROGRESS'
            ];
            firebaseRequest($batchItemUrl, 'PATCH', $updateData);
        } else {
<<<<<<< HEAD
            if (!empty($oldBatchId) && $oldProduced > 0 && $oldProduced < $oldDemand) {
                closeBatch($logsUrl, $oldBatchId, $oldProduced, 'INTERRUPTED');
            }
=======
            // If items were already produced from the previous batch, close it first
            if (!empty($oldBatchId) && $oldProduced > 0 && $oldProduced < $oldDemand) {
                closeBatch($logsUrl, $oldBatchId, $oldProduced, 'INTERRUPTED');
            }
            // Create a brand new batch entry with immediate timestamp and demand
>>>>>>> a3bc2f908b713859889bd667ae932f382202fe98
            $newBatchId = createBatch($logsUrl, $newDemand);
        }

        $payload = [
            'Demand'          => $newDemand,
            'Produced'        => 0,
            'last_updated'    => time(),
            'logged'          => false,
            'mpu'             => '00:00',
            'active_batch_id' => $newBatchId
        ];
        firebaseRequest($firebaseUrl, 'PATCH', $payload);

        echo json_encode(['status' => 'success', 'demand' => $newDemand, 'batch_id' => $newBatchId]);
        exit;
    }

    if ($action === 'reset_system') {
        $currentData = firebaseRequest($firebaseUrl, 'GET');
        $oldDemand   = isset($currentData['Demand']) ? (int)$currentData['Demand'] : 0;
        $oldProduced = isset($currentData['Produced']) ? (int)$currentData['Produced'] : 0;
        $oldBatchId  = $currentData['active_batch_id'] ?? '';

        if (!empty($oldBatchId)) {
            $finalStatus = ($oldDemand > 0 && $oldProduced >= $oldDemand) ? 'COMPLETED' : 'INTERRUPTED';
            closeBatch($logsUrl, $oldBatchId, $oldProduced, $finalStatus);
        }

        $payload = [
            'Demand'          => 0,
            'Produced'        => 0,
            'last_updated'    => time(),
            'logged'          => false,
            'mpu'             => '00:00',
            'active_batch_id' => ''
        ];
        firebaseRequest($firebaseUrl, 'PUT', $payload);

        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'clear_logs') {
        firebaseRequest($logsUrl, 'DELETE');
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'get_status') {
        $data = firebaseRequest($firebaseUrl, 'GET');

        $demand      = isset($data['Demand']) ? (int)$data['Demand'] : 0;
        $produced    = isset($data['Produced']) ? (int)$data['Produced'] : 0;
        $lastUpdated = isset($data['last_updated']) ? (int)$data['last_updated'] : time();
        $isLogged    = isset($data['logged']) ? (bool)$data['logged'] : false;
        $mpu         = isset($data['mpu']) ? (string)$data['mpu'] : '00:00';
        $batchId     = $data['active_batch_id'] ?? '';
        $currentTime = time();

        $isCompleted = ($demand > 0 && $produced >= $demand);

<<<<<<< HEAD
=======
        // Keep active batch produced count synchronized without altering start_time
>>>>>>> a3bc2f908b713859889bd667ae932f382202fe98
        if (!empty($batchId)) {
            $batchItemUrl = str_replace('.json', '/' . $batchId . '.json', $logsUrl);

            if ($isCompleted && !$isLogged) {
                $isLogged = true;
                firebaseRequest($firebaseUrl, 'PATCH', ['logged' => true]);
                closeBatch($logsUrl, $batchId, $produced, 'COMPLETED');
            } else if (!$isCompleted && $produced > 0) {
                firebaseRequest($batchItemUrl, 'PATCH', ['produced' => (int)$produced]);
            }
        }

        // Inactivity timeout (300 seconds)
        if ($produced > 0 && !$isLogged && ($currentTime - $lastUpdated >= 300)) {
            if (!empty($batchId)) {
                closeBatch($logsUrl, $batchId, $produced, 'INTERRUPTED');
            }

            $resetPayload = [
                'Demand'          => 0,
                'Produced'        => 0,
                'last_updated'    => $currentTime,
                'logged'          => false,
                'mpu'             => '00:00',
                'active_batch_id' => ''
            ];
            firebaseRequest($firebaseUrl, 'PUT', $resetPayload);

            $demand   = 0;
            $produced = 0;
            $isLogged = false;
            $mpu      = '00:00';
        }

        $logsData = firebaseRequest($logsUrl, 'GET');
        $logsList = [];
        $cumulative = 0;

        if (is_array($logsData)) {
            foreach ($logsData as $id => $entry) {
                if (is_array($entry) && isset($entry['start_time'])) {
                    $entry['id'] = $id;
                    $logsList[] = $entry;
                    $cumulative += (int)($entry['produced'] ?? 0);
                }
            }
        }

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
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .stats { margin-top: 15px; padding: 15px; background: #2a2a2a; border-radius: 6px; font-size: 16px; }
        .completed-banner { display: none; margin-top: 15px; padding: 15px; background: #28a745; color: white; border-radius: 6px; font-weight: bold; text-align: center; font-size: 16px; }
        .alert-toast { display: none; margin-top: 15px; padding: 10px; background: #17a2b8; color: white; border-radius: 4px; text-align: center; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #2a2a2a; }
        .header-action { display: flex; justify-content: space-between; align-items: center; }
        .action-buttons { display: flex; gap: 8px; }
        .btn-sm { width: auto; padding: 6px 12px; margin-bottom: 0; font-size: 13px; }
        .status-badge { padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .status-in_progress { background: #ffc107; color: #000; }
        .status-completed { background: #28a745; color: #fff; }
        .status-interrupted { background: #dc3545; color: #fff; }
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
            <p><strong>Target Demand:</strong> <span id="displayDemand">0</span></p>
            <p><strong>Currently Produced:</strong> <span id="displayProduced">0</span></p>
            <p><strong>Cumulative Produced:</strong> <span id="displayCumulative">0</span></p>
            <p><strong>Minutes per Unit (MPU):</strong> <span id="displayMpu">00:00</span></p>
        </div>

        <div id="completionBanner" class="completed-banner">🎉 Process Has Been Completed!</div>
    </div>

    <div class="card" style="flex-grow: 1; max-width: 680px;">
        <div class="header-action">
            <h2>Live Production Batches</h2>
            <div class="action-buttons">
                <button class="btn btn-success btn-sm" onclick="exportCSV()">Export CSV</button>
                <button class="btn btn-danger btn-sm" onclick="clearLogs()">Clear Logs</button>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Start Time (MYT)</th>
                    <th>End Time (MYT)</th>
                    <th>Demand</th>
                    <th>Produced</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="logsTableBody">
                <tr><td colspan="5">No logs recorded yet.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let currentLogs = [];

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

function clearLogs() {
    if (!confirm("Are you sure you want to delete all production logs?")) return;

    const formData = new FormData();
    formData.append('action', 'clear_logs');
    fetch('production_manager.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                checkStatus();
            }
        });
}

function exportCSV() {
    if (!currentLogs || currentLogs.length === 0) {
        return alert("No logs available to export.");
    }

    let csvContent = "data:text/csv;charset=utf-8,Start Time,End Time,Demand,Produced,Status\n";

    currentLogs.forEach(log => {
        csvContent += `"${log.start_time}","${log.end_time}","${log.demand}","${log.produced}","${log.status}"\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    const today = new Date().toISOString().split('T')[0];
    
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `production_batches_${today}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function checkStatus() {
    const formData = new FormData();
    formData.append('action', 'get_status');

    fetch('production_manager.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data && data.status === 'success') {
                document.getElementById('displayDemand').innerText = (data.demand !== undefined && data.demand !== null) ? data.demand : 0;
                document.getElementById('displayProduced').innerText = data.produced;
                document.getElementById('displayCumulative').innerText = (data.cumulative !== undefined) ? data.cumulative : 0;
                
                const mpuValue = (data.mpu !== undefined && data.mpu !== null && data.mpu !== "0") ? data.mpu : "00:00";
                document.getElementById('displayMpu').innerText = mpuValue;

                document.getElementById('completionBanner').style.display = data.completed ? 'block' : 'none';

                currentLogs = data.logs || [];

                const tableBody = document.getElementById('logsTableBody');
                if (currentLogs.length > 0) {
                    tableBody.innerHTML = currentLogs.map(log => {
                        const statusClass = 'status-' + (log.status || 'in_progress').toLowerCase();
                        return `<tr>
                            <td>${log.start_time}</td>
                            <td>${log.end_time}</td>
                            <td>${log.demand}</td>
                            <td>${log.produced}</td>
                            <td><span class="status-badge ${statusClass}">${log.status}</span></td>
                        </tr>`;
                    }).join('');
                } else {
                    tableBody.innerHTML = '<tr><td colspan="5">No logs recorded yet.</td></tr>';
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
