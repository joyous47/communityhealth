<?php
require_once '../includes/header.php';
requireRole('admin', '../auth/login.php');

$db = getDB();

// Get SMS statistics
$stats = [
    'total' => 0,
    'incoming' => 0,
    'outgoing' => 0,
    'pending' => 0,
    'delivered' => 0,
    'failed' => 0
];

try {
    $stmt = $db->query("
        SELECT direction, status, COUNT(*) as count 
        FROM sms_log 
        GROUP BY direction, status
    ");
    while ($row = $stmt->fetch()) {
        $stats['total'] += $row['count'];
        if ($row['direction'] === 'incoming') {
            $stats['incoming'] += $row['count'];
        } else {
            $stats['outgoing'] += $row['count'];
        }
        if ($row['status'] === 'pending') {
            $stats['pending'] += $row['count'];
        } elseif ($row['status'] === 'delivered') {
            $stats['delivered'] += $row['count'];
        } elseif ($row['status'] === 'failed' || $row['status'] === 'error') {
            $stats['failed'] += $row['count'];
        }
    }
} catch (PDOException $e) {
    // Table might not exist yet
}

// Get recent SMS messages
$recentMessages = [];
try {
    $stmt = $db->query("
        SELECT * FROM sms_log 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $recentMessages = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// Get subscriptions
$subscriptions = [];
try {
    $stmt = $db->query("
        SELECT * FROM sms_subscriptions 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $subscriptions = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Management - CHMEWS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        .page-header {
            background: linear-gradient(135deg, #0077cc, #005599);
            color: white;
            padding: 30px;
            margin-bottom: 30px;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #0077cc;
        }
        .stat-card h3 {
            margin: 0 0 10px;
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #0077cc;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .card h2 {
            margin: 0 0 20px;
            color: #333;
            font-size: 1.3rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-sent { background: #e3f2fd; color: #1976d2; }
        .status-delivered { background: #e8f5e9; color: #388e3c; }
        .status-failed { background: #ffebee; color: #d32f2f; }
        .status-pending { background: #fff3e0; color: #f57c00; }
        .direction-incoming { color: #0077cc; }
        .direction-outgoing { color: #388e3c; }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #0077cc;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            border-bottom-color: #0077cc;
            color: #0077cc;
            font-weight: 600;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1><i class="fas fa-sms"></i> SMS Management</h1>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Messages</h3>
                <div class="value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Incoming</h3>
                <div class="value"><?php echo $stats['incoming']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Outgoing</h3>
                <div class="value"><?php echo $stats['outgoing']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Delivered</h3>
                <div class="value"><?php echo $stats['delivered']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Failed</h3>
                <div class="value"><?php echo $stats['failed']; ?></div>
            </div>
        </div>
        
        <div class="card">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('logs')">SMS Logs</div>
                <div class="tab" onclick="switchTab('subscriptions')">Subscriptions</div>
                <div class="tab" onclick="switchTab('send')">Send SMS</div>
                <div class="tab" onclick="switchTab('setup')">Setup</div>
            </div>
            
            <div id="logs" class="tab-content active">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Phone Number</th>
                            <th>Message</th>
                            <th>Direction</th>
                            <th>Status</th>
                            <th>Report ID</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentMessages)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #666;">
                                No SMS messages yet. <a href="sms_tables.php">Create SMS tables</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentMessages as $msg): ?>
                        <tr>
                            <td><?php echo $msg['id']; ?></td>
                            <td><?php echo htmlspecialchars($msg['phone_number']); ?></td>
                            <td><?php echo htmlspecialchars(substr($msg['message'], 0, 50)); ?><?php echo strlen($msg['message']) > 50 ? '...' : ''; ?></td>
                            <td class="direction-<?php echo $msg['direction']; ?>">
                                <i class="fas fa-<?php echo $msg['direction'] === 'incoming' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                <?php echo ucfirst($msg['direction']); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $msg['status']; ?>">
                                    <?php echo ucfirst($msg['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $msg['report_id'] ? '#' . $msg['report_id'] : '-'; ?></td>
                            <td><?php echo date('M d, H:i', strtotime($msg['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="subscriptions" class="tab-content">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Phone Number</th>
                            <th>Type</th>
                            <th>Language</th>
                            <th>County</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subscriptions)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #666;">No subscriptions yet</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td><?php echo $sub['id']; ?></td>
                            <td><?php echo htmlspecialchars($sub['phone_number']); ?></td>
                            <td><?php echo ucfirst($sub['subscription_type']); ?></td>
                            <td><?php echo strtoupper($sub['preferred_language']); ?></td>
                            <td><?php echo htmlspecialchars($sub['county'] ?? '-'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $sub['active'] ? 'status-delivered' : 'status-failed'; ?>">
                                    <?php echo $sub['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="send" class="tab-content">
                <form id="sendSmsForm">
                    <div class="form-group">
                        <label>Recipient Phone Number</label>
                        <input type="tel" name="phone" placeholder="+2547XXXXXXXX" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" rows="4" placeholder="Enter your message..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send SMS
                    </button>
                </form>
                <div id="sendResult" style="margin-top: 15px;"></div>
            </div>
            
            <div id="setup" class="tab-content">
                <h3>SMS Configuration</h3>
                <p>To enable SMS reporting, follow these steps:</p>
                <ol style="line-height: 2;">
                    <li>Configure your SMS provider in <code>config/sms_config.php</code></li>
                    <li>Create the SMS tables by running: <a href="sms_tables.php" class="btn btn-primary">Create Tables</a></li>
                    <li>Set up webhook URL in your SMS provider dashboard:
                        <code style="background: #f5f5f5; padding: 5px 10px; border-radius: 3px;">
                            https://yourdomain.com/chmews/api/sms_incoming.php
                        </code>
                    </li>
                    <li>Citizens can now report via SMS using format:
                        <code style="background: #f5f5f5; padding: 5px 10px; border-radius: 3px;">
                            CHMEWS SYMPTOMS LOCATION SEVERITY
                        </code>
                    </li>
                </ol>
                <h4>Available Symptoms Codes:</h4>
                <ul>
                    <li>FEVER, COUGH, COLD, HEADACHE, DIARRHEA, VOMITING, RASH, FATIGUE, BREATHING, COVID</li>
                </ul>
                <h4>Severity Levels:</h4>
                <ul>
                    <li>LOW, MEDIUM, HIGH, CRITICAL</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }
        
        document.getElementById('sendSmsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const resultDiv = document.getElementById('sendResult');
            
            resultDiv.innerHTML = '<p>Sending...</p>';
            
            try {
                const response = await fetch('../api/send_sms.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<p style="color: green;">SMS sent successfully!</p>';
                    this.reset();
                } else {
                    resultDiv.innerHTML = '<p style="color: red;">Error: ' + data.message + '</p>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<p style="color: red;">Network error. Make sure the send_sms.php API exists.</p>';
            }
        });
    </script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>
