<?php
session_start();
if (!isset($_SESSION['attorney_name']) || $_SESSION['user_type'] !== 'attorney') {
    header('Location: login_form.php');
    exit();
}

// Set cache control headers to prevent back button access after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config.php';
$attorney_id = $_SESSION['user_id'];

// Check if this is a first-time login AND account was created by admin
$stmt = $conn->prepare("SELECT profile_image, first_login, created_by FROM user_form WHERE id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$is_first_login = false;
$show_password_modal = false;
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $is_first_login = ($row['first_login'] == 1);
    // Only show password change modal if account was created by admin (not self-registered)
    $show_password_modal = $is_first_login && !is_null($row['created_by']);
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }
// Total cases handled
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$total_cases = $stmt->get_result()->fetch_row()[0];
// Total documents uploaded
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_documents WHERE uploaded_by=? AND uploaded_by IS NOT NULL AND uploaded_by > 0 AND is_deleted = 0");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$total_documents = $stmt->get_result()->fetch_row()[0];
// Total clients
$stmt = $conn->prepare("SELECT uf.id FROM user_form uf WHERE uf.user_type='client' AND uf.id IN (SELECT client_id FROM attorney_cases WHERE attorney_id=?)");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$clients_res = $stmt->get_result();
$total_clients = $clients_res ? $clients_res->num_rows : 0;
// Upcoming hearings (next 7 days)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE attorney_id=? AND date BETWEEN ? AND ? AND type IN ('Hearing','Appointment')");
$stmt->bind_param("iss", $attorney_id, $today, $next_week);
$stmt->execute();
$upcoming_events = $stmt->get_result()->fetch_row()[0];
// Case status distribution for this attorney
$status_counts = [];
$stmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM attorney_cases WHERE attorney_id=? GROUP BY status");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $status_counts[$row['status'] ?? 'Unknown'] = (int)$row['cnt'];
}

// If no cases exist, show default statuses with sample data
if (empty($status_counts)) {
    $status_counts = [
        'Active' => 3,
        'Pending' => 2,
        'Closed' => 1
    ];
}
// Upcoming hearings table (next 5)
$hearings = [];
$stmt = $conn->prepare("SELECT cs.*, ac.title as case_title, 
                        CASE 
                            WHEN uf.name IS NOT NULL AND uf.name != '' THEN uf.name
                            WHEN cs.walkin_client_name IS NOT NULL AND cs.walkin_client_name != '' THEN cs.walkin_client_name
                            ELSE 'Unknown Client'
                        END as client_name 
                        FROM case_schedules cs 
                        LEFT JOIN attorney_cases ac ON cs.case_id = ac.id 
                        LEFT JOIN user_form uf ON cs.client_id = uf.id 
                        WHERE cs.attorney_id=? AND cs.date >= ? 
                        ORDER BY cs.date, cs.start_time LIMIT 5");
$stmt->bind_param("is", $attorney_id, $today);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $hearings[] = $row;
// Recent activity (last 5): cases, documents, messages, hearings
$recent = [];
// Cases
$stmt = $conn->prepare("SELECT id, title, created_at FROM attorney_cases WHERE attorney_id=? ORDER BY created_at DESC LIMIT 2");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Case','title'=>$row['title'],'date'=>$row['created_at']];
// Documents
$stmt = $conn->prepare("SELECT file_name, upload_date FROM attorney_documents WHERE uploaded_by=? AND is_deleted = 0 ORDER BY upload_date DESC LIMIT 2");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Document','title'=>$row['file_name'],'date'=>$row['upload_date']];
// Messages
$stmt = $conn->prepare("SELECT message, sent_at FROM attorney_messages WHERE attorney_id=? ORDER BY sent_at DESC LIMIT 1");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Message','title'=>mb_strimwidth($row['message'],0,30,'...'),'date'=>$row['sent_at']];
// Hearings
$stmt = $conn->prepare("SELECT title, date, start_time FROM case_schedules WHERE attorney_id=? ORDER BY date DESC, start_time DESC LIMIT 1");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent[] = ['type'=>'Hearing','title'=>$row['title'],'date'=>$row['date'].' '.$row['start_time']];
// Sort by date desc
usort($recent, function($a,$b){ return strtotime($b['date'])-strtotime($a['date']); });
$recent = array_slice($recent,0,5);
// Cases per month (bar chart)
$cases_per_month = array_fill(1,12,0);
$year = date('Y');
$stmt = $conn->prepare("SELECT MONTH(created_at) as m, COUNT(*) as cnt FROM attorney_cases WHERE attorney_id=? AND YEAR(created_at)=? GROUP BY m");
$stmt->bind_param("ii", $attorney_id, $year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $cases_per_month[(int)$row['m']] = (int)$row['cnt'];

// Additional attorney-specific data
// Today's appointments
$stmt = $conn->prepare("SELECT COUNT(*) FROM case_schedules WHERE attorney_id=? AND date = ?");
$stmt->bind_param("is", $attorney_id, $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_row()[0];

// This month's cases
$this_month = date('Y-m');
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("is", $attorney_id, $this_month);
$stmt->execute();
$this_month_cases = $stmt->get_result()->fetch_row()[0];

// Active cases (not closed)
$stmt = $conn->prepare("SELECT COUNT(*) FROM attorney_cases WHERE attorney_id=? AND status NOT IN ('Closed', 'Completed')");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$active_cases = $stmt->get_result()->fetch_row()[0];

// Top clients by case count
$top_clients = [];
$stmt = $conn->prepare("SELECT uf.name, COUNT(ac.id) as case_count FROM user_form uf 
                        LEFT JOIN attorney_cases ac ON uf.id = ac.client_id 
                        WHERE uf.user_type = 'client' AND ac.attorney_id = ?
                        GROUP BY uf.id, uf.name 
                        ORDER BY case_count DESC LIMIT 5");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $top_clients[] = $row;
}

// Case type distribution
$case_type_counts = [];
$stmt = $conn->prepare("SELECT case_type, COUNT(*) as cnt FROM attorney_cases WHERE attorney_id=? GROUP BY case_type");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $case_type_counts[$row['case_type'] ?? 'Unknown'] = (int)$row['cnt'];
}

// If no case types exist, show default types with sample data
if (empty($case_type_counts)) {
    $case_type_counts = [
        'Criminal' => 2,
        'Civil' => 3,
        'Family' => 2,
        'Corporate' => 1
    ];
}

// eFiling Statistics
$total_efilings = 0;
$successful_efilings = 0;
$failed_efilings = 0;
$this_month_efilings = 0;

// Total eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=?");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$total_efilings = $stmt->get_result()->fetch_row()[0];

// Successful eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=? AND status='Sent'");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$successful_efilings = $stmt->get_result()->fetch_row()[0];

// Failed eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=? AND status='Failed'");
$stmt->bind_param("i", $attorney_id);
$stmt->execute();
$failed_efilings = $stmt->get_result()->fetch_row()[0];

// This month's eFiling submissions
$stmt = $conn->prepare("SELECT COUNT(*) FROM efiling_history WHERE attorney_id=? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("is", $attorney_id, $this_month);
$stmt->execute();
$this_month_efilings = $stmt->get_result()->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attorney Dashboard - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/data-privacy-modal.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/unread-messages.js?v=<?= time() ?>"></script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="attorney_dashboard.php" class="active"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>Scheduling</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>Client Management</span></a></li>
            <li><a href="attorney_messages.php" class="has-badge"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>
    <div class="main-content">
        <?php 
        $page_title = 'Attorney Dashboard';
        $page_subtitle = 'Overview of your cases, clients, and schedule';
        include 'components/profile_header.php'; 
        ?>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>Welcome back, <?= htmlspecialchars($_SESSION['attorney_name']) ?>!</h2>
                <p>Here's your overview for today</p>
            </div>
            <div class="welcome-time">
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="current-time"></span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid" id="cases">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_cases) ?></div>
                    <div class="stat-label">Total Cases</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= number_format($active_cases) ?> active</span>
                    </div>
                </div>
            </div>

            <div class="stat-card primary" id="documents">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_documents) ?></div>
                    <div class="stat-label">My Documents</div>
                    <div class="stat-details">
                        <span class="stat-detail">Uploaded</span>
                    </div>
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_clients) ?></div>
                    <div class="stat-label">Client Management</div>
                    <div class="stat-details">
                        <span class="stat-detail">Active</span>
                    </div>
                </div>
            </div>

            <div class="stat-card warning" id="schedule">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($upcoming_events) ?></div>
                    <div class="stat-label">Upcoming Events</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= number_format($today_appointments) ?> today</span>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($this_month_cases) ?></div>
                    <div class="stat-label">This Month</div>
                    <div class="stat-details">
                        <span class="stat-detail">New cases</span>
                    </div>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($active_cases) ?></div>
                    <div class="stat-label">Active Cases</div>
                    <div class="stat-details">
                        <span class="stat-detail">In progress</span>
                    </div>
                </div>
            </div>

            <div class="stat-card efiling">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_efilings) ?></div>
                    <div class="stat-label">eFiling Submissions</div>
                    <div class="stat-details">
                        <span class="stat-detail"><?= number_format($successful_efilings) ?> sent</span>
                        <?php if ($failed_efilings > 0): ?>
                            <span class="stat-detail failed"><?= number_format($failed_efilings) ?> failed</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Section -->
        <div class="analytics-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-bar"></i> Analytics & Reports</h2>
                <p>Insights into your caseload and activity</p>
            </div>
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Case Status Distribution</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('caseStatusChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="caseStatusChart"></canvas>
                        <?php if ($total_cases == 0): ?>
                        <div class="chart-sample-notice" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 10px; border-radius: 5px; font-size: 12px; color: #666; text-align: center; pointer-events: none;">
                            <i class="fas fa-info-circle"></i> Sample data shown
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> Cases Per Month (<?= $year ?>)</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('casesPerMonthChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="casesPerMonthChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-doughnut"></i> Case Type Distribution</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('caseTypeChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="caseTypeChart"></canvas>
                        <?php if ($total_cases == 0): ?>
                        <div class="chart-sample-notice" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 10px; border-radius: 5px; font-size: 12px; color: #666; text-align: center; pointer-events: none;">
                            <i class="fas fa-info-circle"></i> Sample data shown
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-area"></i> Case Performance</h3>
                        <div class="chart-actions">
                            <button class="chart-btn" onclick="exportChart('casePerformanceChart')"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="casePerformanceChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- My Schedule -->
        <div class="schedules-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> My Schedule</h3>
            </div>
            <?php if (count($hearings) > 0): ?>
                <table class="upcoming-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Case</th>
                            <th>Client</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hearings as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars($h['date']) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($h['start_time']))) ?></td>
                            <td><?= htmlspecialchars($h['type']) ?></td>
                            <td><?= htmlspecialchars($h['location']) ?></td>
                            <td><?= htmlspecialchars($h['case_title'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($h['client_name']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($h['status'] ?? 'scheduled') ?>"><?= htmlspecialchars($h['status'] ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-calendar-times"></i><p>No upcoming hearings or appointments</p></div>
            <?php endif; ?>
        </div>

        <!-- Activity & Performance Section -->
        <div class="activity-schedule-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> Activity & Performance</h2>
                <p>Recent activity and client insights</p>
            </div>
            <div class="activity-schedule-grid">
                <div class="activities-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                    </div>
                    <?php if (count($recent) > 0): ?>
                        <?php foreach ($recent as $r): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?= strtolower($r['type']) === 'case' ? 'gavel' : (strtolower($r['type']) === 'document' ? 'file-alt' : (strtolower($r['type']) === 'message' ? 'comment' : 'calendar')) ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?= htmlspecialchars($r['title']) ?></div>
                                    <div class="activity-meta">
                                        <span class="activity-type"><?= ucfirst($r['type']) ?></span>
                                    </div>
                                    <div class="activity-time"><?= date('M j, Y g:i A', strtotime($r['date'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-inbox"></i><p>No recent activity</p></div>
                    <?php endif; ?>
                </div>

                <div class="clients-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Top Clients</h3>
                    </div>
                    <?php if (count($top_clients) > 0): ?>
                        <?php foreach ($top_clients as $index => $client): ?>
                            <div class="client-item">
                                <div class="client-rank"><?= $index + 1 ?></div>
                                <div class="client-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="client-content">
                                    <div class="client-name"><?= htmlspecialchars($client['name']) ?></div>
                                    <div class="client-cases"><?= number_format($client['case_count']) ?> cases</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-users"></i><p>No clients yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats Section -->
        <div class="quick-stats-section">
            <div class="section-header">
                <h2><i class="fas fa-tachometer-alt"></i> Quick Stats</h2>
                <p>Key performance indicators</p>
            </div>
            <div class="quick-stats-grid">
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($total_cases) ?></div>
                        <div class="quick-stat-label">Total Cases</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($today_appointments) ?></div>
                        <div class="quick-stat-label">Today's Appointments</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($this_month_cases) ?></div>
                        <div class="quick-stat-label">This Month</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($active_cases) ?></div>
                        <div class="quick-stat-label">Active Cases</div>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="quick-stat-content">
                        <div class="quick-stat-value"><?= number_format($this_month_efilings) ?></div>
                        <div class="quick-stat-label">This Month eFilings</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .welcome-section{background:linear-gradient(135deg,#5D0E26 0%,#8B1538 100%);border-radius:16px;padding:32px;margin-bottom:32px;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 8px 32px rgba(93,14,38,.3)}
        .welcome-content{flex:1}
        .welcome-content h2{font-size:2rem;font-weight:700;margin:0 0 8px 0;font-family:'Playfair Display',serif}
        .welcome-content p{opacity:.9;margin:0}
        .welcome-time{display:flex;align-items:center;justify-content:flex-end}
        .current-time{background:rgba(255,255,255,.1);padding:12px 20px;border-radius:8px;backdrop-filter:blur(10px);display:flex;align-items:center;gap:8px;font-size:1rem;opacity:.9}

        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-bottom:32px}
        .stat-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,.08);border:1px solid #f0f0f0;display:flex;gap:20px;align-items:center;position:relative}
        .stat-card .stat-icon{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#5D0E26,#8B1538);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem}
        .stat-card.success .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.warning .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.purple .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.info .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.danger .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-card.efiling .stat-icon{background:linear-gradient(135deg,#5D0E26,#8B1538)}
        .stat-value{font-size:2.2rem;font-weight:700;color:#333;line-height:1}
        .stat-label{color:#666;margin-top:4px}
        .stat-details{margin-top:8px;color:#27ae60;font-size:.85rem}
        .stat-detail.failed{color:#dc3545;margin-left:8px}

        .analytics-section,.activity-schedule-section,.quick-stats-section{margin-bottom:32px}
        .section-header{text-align:center;margin-bottom:20px}
        .section-header h2{display:flex;gap:10px;align-items:center;justify-content:center;margin:0 0 6px 0;color:#333}
        .charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .activity-schedule-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .quick-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
        .chart-card,.schedules-card,.activities-card,.clients-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,.08);border:1px solid #f0f0f0}
        .chart-header,.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f0f0f0}
        .chart-btn{width:32px;height:32px;border:0;border-radius:8px;background:linear-gradient(135deg,#8B1538,#A91B47);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s ease}
        .chart-btn:hover{background:linear-gradient(135deg,#A91B47,#C41E3A);transform:translateY(-1px);box-shadow:0 4px 12px rgba(139,21,56,.3)}
        .chart-container{height:300px;position:relative;background:linear-gradient(135deg,#fafafa 0%,#f5f5f5 100%);border-radius:12px;padding:16px;box-shadow:inset 0 2px 8px rgba(0,0,0,.05)}
        .upcoming-table{width:100%;border-collapse:collapse}
        .upcoming-table th,.upcoming-table td{padding:12px;border-bottom:1px solid #f5f5f5;text-align:left;font-size:.95rem}
        .status-badge{padding:4px 10px;border-radius:12px;font-size:.8rem}
        .status-scheduled{background:#f8f9fa;color:#555}
        .activities-list{max-height:none;overflow-y:visible}
        .activity-item{display:flex;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid #f8f9fa}
        .activity-icon{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#5D0E26,#8B1538);display:flex;align-items:center;justify-content:center;color:#fff}
        .activity-title{font-weight:600;color:#333}
        .activity-time{color:#999;font-size:.85rem}
        .empty-state{text-align:center;padding:40px 20px;color:#666}
        .empty-state i{font-size:3rem;margin-bottom:12px;opacity:.3}

        .client-item{display:flex;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid #f8f9fa}
        .client-rank{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#5D0E26,#8B1538);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600}
        .client-avatar{width:40px;height:40px;border-radius:50%;background:#f8f9fa;display:flex;align-items:center;justify-content:center;color:#666}
        .client-name{font-weight:600;color:#333}
        .client-cases{color:#666;font-size:.85rem}

        .quick-stat-item{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid #f0f0f0;display:flex;gap:16px;align-items:center}
        .quick-stat-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#5D0E26,#8B1538);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem}
        .quick-stat-value{font-size:1.8rem;font-weight:700;color:#333;line-height:1}
        .quick-stat-label{color:#666;font-size:.9rem;margin-top:4px}

        @media(max-width:1200px){.charts-grid,.activity-schedule-grid{grid-template-columns:1fr}}
        @media(max-width:768px){.welcome-section{flex-direction:column;text-align:center;gap:24px}.welcome-time{justify-content:center}}
    </style>

    <script>
        // Update current time
        function updateTime(){
            const now=new Date();
            document.getElementById('current-time').textContent=now.toLocaleString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'});
        }
        updateTime();setInterval(updateTime,60000);
        // Case Status Chart
        const ctx = document.getElementById('caseStatusChart').getContext('2d');
        const caseStatusData = {
            labels: <?= json_encode(array_keys($status_counts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($status_counts)) ?>,
                backgroundColor: [
                    '#8B1538', '#28a745', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#e83e8c', '#20c997'
                ],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 4,
                hoverBorderColor: '#fff'
            }]
        };
        
        const caseStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: caseStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    arc: {
                        borderWidth: 3
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });

        // Cases Per Month Chart
        const ctx2 = document.getElementById('casesPerMonthChart').getContext('2d');
        const monthlyData = {
            labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
            datasets: [{
                label: 'Cases Created',
                data: <?= json_encode(array_values($cases_per_month)) ?>,
                backgroundColor: [
                    'rgba(139, 21, 56, 0.8)', 'rgba(169, 27, 71, 0.8)', 'rgba(196, 30, 58, 0.8)', 
                    'rgba(220, 20, 60, 0.8)', 'rgba(178, 34, 34, 0.8)', 'rgba(139, 0, 0, 0.8)',
                    'rgba(128, 0, 32, 0.8)', 'rgba(114, 47, 55, 0.8)', 'rgba(139, 21, 56, 0.8)', 
                    'rgba(169, 27, 71, 0.8)', 'rgba(196, 30, 58, 0.8)', 'rgba(220, 20, 60, 0.8)'
                ],
                borderColor: '#8B1538',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: [
                    'rgba(139, 21, 56, 1)', 'rgba(169, 27, 71, 1)', 'rgba(196, 30, 58, 1)', 
                    'rgba(220, 20, 60, 1)', 'rgba(178, 34, 34, 1)', 'rgba(139, 0, 0, 1)',
                    'rgba(128, 0, 32, 1)', 'rgba(114, 47, 55, 1)', 'rgba(139, 21, 56, 1)', 
                    'rgba(169, 27, 71, 1)', 'rgba(196, 30, 58, 1)', 'rgba(220, 20, 60, 1)'
                ]
            }]
        };
        
        const casesPerMonthChart = new Chart(ctx2, {
            type: 'bar',
            data: monthlyData,
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
                        stepSize: 1,
                        max: function(context) {
                            const max = Math.max(...context.chart.data.datasets[0].data);
                            return max > 0 ? Math.ceil(max * 1.2) : 5; // Add 20% padding, minimum 5
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    bar: {
                        borderRadius: 6
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Chart export
        function exportChart(id){const c=document.getElementById(id);const a=document.createElement('a');a.href=c.toDataURL('image/png');a.download=id+"_chart.png";a.click();}

        // Case Type Chart
        const ctx3 = document.getElementById('caseTypeChart').getContext('2d');
        const caseTypeData = {
            labels: <?= json_encode(array_keys($case_type_counts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($case_type_counts)) ?>,
                backgroundColor: [
                    '#8B1538', '#28a745', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#e83e8c', '#20c997'
                ],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 4,
                hoverBorderColor: '#fff'
            }]
        };
        
        const caseTypeChart = new Chart(ctx3, {
            type: 'doughnut',
            data: caseTypeData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    arc: {
                        borderWidth: 3
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });

        // Case Performance Chart
        const ctx4 = document.getElementById('casePerformanceChart').getContext('2d');
        const casePerformanceData = {
            labels: ["Active", "Closed", "Pending"],
            datasets: [{
                label: 'Cases',
                data: [
                    <?= $active_cases ?>,
                    <?= max(0, $total_cases - $active_cases) ?>,
                    <?= isset($status_counts['Pending']) ? $status_counts['Pending'] : 0 ?>
                ],
                backgroundColor: [
                    'rgba(139, 21, 56, 0.8)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderColor: [
                    '#8B1538',
                    '#28a745',
                    '#ffc107'
                ],
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: [
                    'rgba(139, 21, 56, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)'
                ]
            }]
        };
        
        const casePerformanceChart = new Chart(ctx4, {
            type: 'bar',
            data: casePerformanceData,
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
                        stepSize: 1,
                        max: function(context) {
                            const max = Math.max(...context.chart.data.datasets[0].data);
                            return max > 0 ? Math.ceil(max * 1.2) : 5; // Add 20% padding, minimum 5
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    bar: {
                        borderRadius: 6
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Fix chart responsiveness on window resize
        window.addEventListener('resize', function() {
            caseStatusChart.resize();
            casesPerMonthChart.resize();
            caseTypeChart.resize();
            casePerformanceChart.resize();
        });

        // Force chart resize after page load
        setTimeout(function() {
            caseStatusChart.resize();
            casesPerMonthChart.resize();
            caseTypeChart.resize();
            casePerformanceChart.resize();
        }, 100);
    </script>

    <!-- Attorney Data Privacy Waiver Modal -->
    <div id="dataPrivacyModal">
        <div id="dataPrivacyModalContent">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-shield-alt"></i> Attorney Data Privacy Notice
                </h2>
            </div>
            <div class="modal-body">
                <div class="modal-content-inner">
                    <div class="office-header">
                        <div class="office-logo">
                            <img src="images/logo.jpg" alt="Opiña Law Office Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                        </div>
                        <h3 class="office-title">OPIÑA LAW OFFICE</h3>
                        <p class="office-subtitle">Attorney Data Privacy Notice & Consent</p>
                    </div>

                    <div class="important-notice">
                        <h4>
                            <i class="fas fa-exclamation-triangle"></i> IMPORTANT NOTICE
                        </h4>
                        <p>
                            By accessing this system as an attorney, you acknowledge that you have read, understood, and agree to the terms outlined in this Data Privacy Notice.
                        </p>
                    </div>

                    <div class="section">
                        <h4>I. Collection of Personal Information</h4>
                        <p>
                            As an attorney, we collect personal information necessary for legal practice and system access:
                        </p>
                        <ul>
                            <li>Professional credentials and bar admission details</li>
                            <li>Client case information and legal documents</li>
                            <li>System access logs and activity records</li>
                            <li>Communication records and client correspondence</li>
                            <li>Schedule and court appearance information</li>
                        </ul>
                    </div>

                    <div class="section">
                        <h4>II. Purpose of Data Processing</h4>
                        <p>
                            Your personal information is processed for:
                        </p>
                        <ul>
                            <li>Managing legal practice and case administration</li>
                            <li>Providing system access and security monitoring</li>
                            <li>Communicating case updates and court schedules</li>
                            <li>Complying with legal and regulatory requirements</li>
                            <li>Maintaining attorney-client privilege and confidentiality</li>
                        </ul>
                    </div>

                    <div class="section">
                        <h4>III. Data Security & Confidentiality</h4>
                        <p>
                            We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. All data is handled with strict confidentiality in accordance with attorney-client privilege and professional ethics standards.
                        </p>
                    </div>

                    <div class="section">
                        <h4>IV. Attorney Responsibilities</h4>
                        <p>
                            As an attorney, you are responsible for:
                        </p>
                        <ul>
                            <li>Maintaining strict client confidentiality and attorney-client privilege</li>
                            <li>Following data security protocols and professional ethics</li>
                            <li>Reporting any security breaches or data incidents</li>
                            <li>Using system access only for authorized legal purposes</li>
                            <li>Complying with bar association rules and regulations</li>
                        </ul>
                    </div>

                    <div class="section">
                        <h4>V. Your Rights</h4>
                        <p>
                            Under the Data Privacy Act of 2012, you have the right to:
                        </p>
                        <ul>
                            <li>Access and request copies of your personal information</li>
                            <li>Correct or update inaccurate information</li>
                            <li>Withdraw consent (subject to professional obligations)</li>
                            <li>File complaints with the National Privacy Commission</li>
                        </ul>
                    </div>

                    <div class="consent-section">
                        <h4>
                            <i class="fas fa-exclamation-triangle"></i> CONSENT DECLARATION
                        </h4>
                        <p>
                            I acknowledge that I have read and understood this Attorney Data Privacy Notice and consent to the collection, processing, and use of my personal information as described herein.
                        </p>
                        <div class="checkbox-container">
                            <input type="checkbox" id="consentCheckbox">
                            <label for="consentCheckbox">
                                I agree to the terms and conditions
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="acceptButton" class="accept-button" onclick="acceptDataPrivacy()" disabled>
                        <i class="fas fa-check"></i> Accept
                    </button>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutConfirmModal" style="display: none; position: fixed; z-index: 10005; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 16px; width: 90%; max-width: 420px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); overflow: hidden; animation: modalFadeIn 0.3s ease;">
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); padding: 20px 24px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-sign-out-alt" style="color: white; font-size: 18px;"></i>
                    </div>
                    <div>
                        <h2 style="color: white; margin: 0; font-size: 1.3em; font-weight: 700;">Confirm Logout</h2>
                        <p style="color: rgba(255,255,255,0.9); margin: 2px 0 0 0; font-size: 0.85em;">Security Notice</p>
                    </div>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div style="padding: 24px;">
                <p style="color: #2c3e50; margin: 0 0 16px 0; font-size: 15px; line-height: 1.6;">
                    Are you sure you want to logout?
                </p>
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 13px; color: #856404; font-weight: 500;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i>
                        You will need to change your password on your next login.
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="closeLogoutModal();" style="padding: 10px 24px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; cursor: pointer; font-weight: 600; color: #374151; transition: all 0.3s ease; font-size: 14px;" onmouseover="this.style.borderColor='#d1d5db'; this.style.background='#f9fafb'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">Cancel</button>
                    <button onclick="confirmLogout();" style="padding: 10px 24px; background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 14px; box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);" onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(93, 14, 38, 0.4)'" onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(93, 14, 38, 0.3)'">Logout</button>
                </div>
            </div>
        </div>
    </div>

    <!-- First-Time Login Password Change Modal -->
    <div id="firstLoginModal" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 10002;">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); padding: 0; max-width: 400px; width: 90%; position: relative; animation: modalSlideIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); overflow: hidden;">
            
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; text-align: center; position: relative;">
                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-key" style="color: white; font-size: 20px;"></i>
                </div>
                <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; margin: 0 0 5px 0; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);">Change Password Now</h2>
                <p style="font-size: 14px; font-weight: 500; opacity: 0.9; margin: 0;">Security Requirement</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 25px; text-align: center;">
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px; text-align: left;">
                    <h4 style="color: #856404; margin: 0 0 8px 0; font-size: 14px; font-weight: 600;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i>
                        Security Notice
                    </h4>
                    <p style="color: #856404; margin: 0; font-size: 12px; line-height: 1.4;">
                        For security purposes, you must change your password before accessing the system. 
                        This is a one-time requirement for all new accounts.
                    </p>
                </div>
                
                <p style="color: #5D0E26; font-size: 16px; font-weight: 600; margin: 0 0 10px 0; line-height: 1.4;">
                    Welcome to Opiña Law Office!
                </p>
                <p style="color: #666; font-size: 14px; margin: 0 0 25px 0; line-height: 1.5;">
                    Please change your password to continue using your dashboard.
                </p>
                
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="redirectToProfilePasswordChange();" 
                            style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; padding: 12px 25px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);">
                        <i class="fas fa-key" style="margin-right: 6px;"></i>
                        Change Password
                    </button>
                    <button onclick="showLogoutModal();" 
                            style="background: #6c757d; color: white; border: none; padding: 12px 25px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.3s ease;">
                        <i class="fas fa-sign-out-alt" style="margin-right: 6px;"></i>
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        // Data Privacy Waiver Modal Functions
        function showDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            modal.style.display = 'block';
        }

        function closeDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            modal.style.display = 'none';
        }

        function acceptDataPrivacy() {
            const checkbox = document.getElementById('consentCheckbox');
            if (!checkbox.checked) {
                alert('Please check the consent checkbox to proceed.');
                return;
            }
            
            // Store acceptance in sessionStorage (only for current session)
            sessionStorage.setItem('dataPrivacyAccepted', 'true');
            sessionStorage.setItem('dataPrivacyAcceptedDate', new Date().toISOString());
            
            // Close the modal
            closeDataPrivacyModal();
        }

        // Handle checkbox change to enable/disable accept button
        function toggleAcceptButton() {
            const checkbox = document.getElementById('consentCheckbox');
            const acceptButton = document.getElementById('acceptButton');
            
            if (checkbox.checked) {
                acceptButton.style.background = '#5D0E26';
                acceptButton.style.color = 'white';
                acceptButton.style.cursor = 'pointer';
                acceptButton.disabled = false;
            } else {
                acceptButton.style.background = '#ccc';
                acceptButton.style.color = '#666';
                acceptButton.style.cursor = 'not-allowed';
                acceptButton.disabled = true;
            }
        }

        // First-Time Login Modal Functions
        function showFirstLoginModal() {
            const modal = document.getElementById('firstLoginModal');
            modal.style.display = 'flex';
        }

        function closeFirstLoginModal() {
            const modal = document.getElementById('firstLoginModal');
            modal.style.display = 'none';
        }

        function redirectToProfilePasswordChange() {
            // Close the first-time login modal
            closeFirstLoginModal();
            
            // Call the existing changePassword function from profile header
            setTimeout(() => {
                if (typeof changePassword === 'function') {
                    changePassword();
                } else {
                    // Fallback: try to find and click the change password link
                    const passwordChangeLink = document.querySelector('a[onclick*="changePassword"]');
                    if (passwordChangeLink) {
                        passwordChangeLink.click();
                    }
                }
            }, 100);
        }

        // Logout Modal Functions
        function showLogoutModal() {
            const modal = document.getElementById('logoutConfirmModal');
            modal.style.display = 'flex';
        }

        function closeLogoutModal() {
            const modal = document.getElementById('logoutConfirmModal');
            modal.style.display = 'none';
        }

        function confirmLogout() {
            // Close the logout modal
            closeLogoutModal();
            
            // Log out the user
            fetch('logout.php', {
                method: 'POST'
            })
            .then(() => {
                window.location.href = 'login_form.php';
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = 'login_form.php';
            });
        }

        // Legacy function for compatibility
        function logoutUser() {
            showLogoutModal();
        }

        // Check if data privacy has been accepted on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if this is a fresh login from PHP session
            const isFreshLogin = <?= isset($_SESSION['fresh_login']) && $_SESSION['fresh_login'] ? 'true' : 'false' ?>;
            
            // If this is a fresh login, clear any previous acceptance
            if (isFreshLogin) {
                sessionStorage.removeItem('dataPrivacyAccepted');
                // Clear the PHP session flag
                <?php unset($_SESSION['fresh_login']); ?>
            }
            
            // Show modal if not accepted in current login session
            if (!sessionStorage.getItem('dataPrivacyAccepted')) {
            setTimeout(function() {
                showDataPrivacyModal();
                
                // Add event listener to checkbox
                const checkbox = document.getElementById('consentCheckbox');
                if (checkbox) {
                    checkbox.addEventListener('change', toggleAcceptButton);
                }
            }, 500);
            }
        });

        // Show modal if this is a first-time login AND account was created by admin
        <?php if ($show_password_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Show waiver first, then password change modal after waiver is accepted
            const isFreshLogin = <?= isset($_SESSION['fresh_login']) && $_SESSION['fresh_login'] ? 'true' : 'false' ?>;
            
            if (isFreshLogin) {
                // Show waiver first
                setTimeout(function() {
                    showDataPrivacyModal();
                }, 500);
                
                // Override the accept button to show password change modal after waiver
                const originalAcceptFunction = window.acceptDataPrivacy;
                window.acceptDataPrivacy = function() {
                    // Call original function
                    originalAcceptFunction();
                    
                    // Show password change modal after waiver is accepted
                    setTimeout(function() {
                        showFirstLoginModal();
                    }, 300);
                };
            } else {
                // If not fresh login but first login, show password change modal directly
                showFirstLoginModal();
            }
        });
        <?php endif; ?>

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('dataPrivacyModal');
            if (event.target === modal) {
                // Don't allow closing by clicking outside - user must explicitly accept or cancel
                // closeDataPrivacyModal();
            }
        });
    </script>
</body>
</html> 