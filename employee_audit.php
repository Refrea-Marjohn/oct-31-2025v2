<?php
session_start();
if (!isset($_SESSION['employee_name']) || $_SESSION['user_type'] !== 'employee') {
    header('Location: login_form.php');
    exit();
}
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
$employee_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }

// Build filters for this employee's audit trail
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$module = $_GET['module'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$search = trim($_GET['search'] ?? '');
// Determine if any filter is active (used to show/hide "Filtered Results")
$filters_active = ($date_from !== '' || $date_to !== '' || $module !== 'all' || $status !== 'all' || $priority !== 'all' || $search !== '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "WHERE user_id = ?";
$params = [$employee_id];
$types = "i";

if (!empty($date_from)) { $where .= " AND DATE(timestamp) >= ?"; $params[] = $date_from; $types .= "s"; }
if (!empty($date_to)) { $where .= " AND DATE(timestamp) <= ?"; $params[] = $date_to; $types .= "s"; }
if (!empty($module) && $module !== 'all') { $where .= " AND module = ?"; $params[] = $module; $types .= "s"; }
if (!empty($status) && $status !== 'all') { $where .= " AND status = ?"; $params[] = $status; $types .= "s"; }
if (!empty($priority) && $priority !== 'all') { $where .= " AND priority = ?"; $params[] = $priority; $types .= "s"; }
if (!empty($search)) { $where .= " AND (action LIKE ? OR description LIKE ? OR module LIKE ?)"; $like = "%$search%"; $params[] = $like; $params[] = $like; $params[] = $like; $types .= "sss"; }

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM audit_trail WHERE DATE(timestamp)=CURDATE() AND user_id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$today_activities = $stmt->get_result()->fetch_assoc()['c'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM audit_trail WHERE user_id=?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$total_actions = $stmt->get_result()->fetch_assoc()['c'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM audit_trail WHERE DATE(timestamp)=CURDATE() AND user_id=? AND module='Security'");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$security_events_today = $stmt->get_result()->fetch_assoc()['c'] ?? 0;

// Total filtered count
$count_sql = "SELECT COUNT(*) AS c FROM audit_trail $where";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_filtered = $count_stmt->get_result()->fetch_assoc()['c'] ?? 0;
$total_pages = max(1, (int)ceil($total_filtered / $per_page));

// Fetch filtered rows
$sql = "SELECT id, action, module, description, ip_address, status, priority, timestamp FROM audit_trail $where ORDER BY timestamp DESC LIMIT $per_page OFFSET $offset";
$list_stmt = $conn->prepare($sql);
if (!empty($params)) { $list_stmt->bind_param($types, ...$params); }
$list_stmt->execute();
$recent_activities = $list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const submenu = this.parentElement;
                    submenu.classList.toggle('open');
                });
            });
        });
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
                <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="employee_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="employee_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li class="has-submenu">
                <a href="#" class="submenu-toggle"><i class="fas fa-file-alt"></i><span>Document Generations</span><i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu">
                    <li><a href="employee_document_generation.php"><i class="fas fa-file-plus"></i><span>Generate Documents</span></a></li>
                    <li><a href="employee_send_files.php"><i class="fas fa-paper-plane"></i><span>Send Files</span></a></li>
                </ul>
            </li>
            <li><a href="employee_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="employee_clients.php"><i class="fas fa-users"></i><span>Client Management</span></a></li>
            <li><a href="employee_request_management.php"><i class="fas fa-clipboard-check"></i><span>Request Review</span></a></li>
            <li><a href="employee_messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
            <li><a href="employee_audit.php" class="active"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        $page_title = 'Audit Trail';
        $page_subtitle = 'All actions you performed across the system';
        include 'components/profile_header.php'; 
        ?>

        <!-- Audit Trail Dashboard -->
        <div class="dashboard-section" style="margin-bottom: 30px;">
            <h1>Audit Trail Dashboard</h1>
            <p>Overview of your document activities and actions.</p>
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="card-info">
                        <h3>Today's Activities</h3>
                        <p><?= $today_activities ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-file-edit"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Actions</h3>
                        <p><?= $total_actions ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div class="card-info">
                        <h3>Security Events Today</h3>
                        <p><?= $security_events_today ?></p>
                    </div>
                </div>
                <?php if ($filters_active): ?>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="card-info">
                        <h3>Filtered Results</h3>
                        <p><?= $total_filtered ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="audit-section" style="margin-bottom: 20px;">
            <h2>Filters</h2>
            <form id="auditFilterForm" method="get" class="filter-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end;">
                <div>
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" />
                </div>
                <div>
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" />
                </div>
                <div>
                    <label>Module</label>
                    <select name="module">
                        <?php $modules = ['all','Authentication','Document Management','Case Management','User Management','Schedule Management','Communication','Security','System','Page Access'];
                        foreach ($modules as $m): ?>
                            <option value="<?= $m ?>" <?= $module===$m?'selected':'' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['all','success','warning','error'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Priority</label>
                    <select name="priority">
                        <?php foreach (['all','low','medium','high'] as $p): ?>
                            <option value="<?= $p ?>" <?= $priority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="grid-column:span 2;">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search actions, modules, description" value="<?= htmlspecialchars($search) ?>" />
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn" type="submit"><i class="fas fa-filter"></i> Apply</button>
                    <a class="btn btn-secondary" href="employee_audit.php#activities" onclick="event.stopPropagation();"><i class="fas fa-eraser"></i> Clear</a>
                </div>
            </form>
        </div>

        <!-- Recent Activities -->
        <a id="activities"></a>
        <div class="audit-section">
            <h2>Your Activities</h2>
            <div class="activity-list">
                <?php if (empty($recent_activities)): ?>
                    <div class="no-activities">
                        <i class="fas fa-info-circle"></i>
                        <p>No recent activities found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-history" style="color:#5D0E26;"></i>
                            </div>
                            <div class="activity-details">
                                <h4><?= htmlspecialchars($activity['action']) ?> <span style="color:#888;">(<?= htmlspecialchars($activity['module']) ?>)</span></h4>
                                <p><?= htmlspecialchars($activity['description'] ?? '-') ?></p>
                                <p><strong>Status:</strong> <span class="status-badge <?= htmlspecialchars($activity['status']) ?>"><?= htmlspecialchars(ucfirst($activity['status'])) ?></span> <strong style="margin-left:10px;">Priority:</strong> <?= htmlspecialchars(ucfirst($activity['priority'])) ?></p>
                                <p><strong>IP:</strong> <?= htmlspecialchars($activity['ip_address']) ?> <strong style="margin-left:10px;">Time:</strong> <?= date('M d, Y H:i:s', strtotime($activity['timestamp'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): $prev = $page-1; ?>
                        <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$prev])) ?>#activities">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    <div class="page-info">Page <?= $page ?> of <?= $total_pages ?></div>
                    <?php if ($page < $total_pages): $next = $page+1; ?>
                        <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$next])) ?>#activities">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Ensure form submission jumps to the activities section after reload
        (function(){
            var form = document.getElementById('auditFilterForm');
            if (!form) return;
            form.addEventListener('submit', function(){
                var base = form.getAttribute('action') || window.location.pathname;
                if (base.indexOf('#activities') === -1) {
                    form.setAttribute('action', base + '#activities');
                }
            });
        })();
    </script>

    <style>
        .dashboard-section {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(93, 14, 38, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(93, 14, 38, 0.05);
        }
        
        .dashboard-section h1 {
            color: #5D0E26;
            font-size: 1.8rem;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .dashboard-section p {
            color: #666;
            margin-bottom: 25px;
            font-size: 1rem;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 4px 20px rgba(93, 14, 38, 0.08);
            border: 1px solid rgba(93, 14, 38, 0.05);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(93, 14, 38, 0.15);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }
        
        .card-icon i {
            color: white;
            font-size: 24px;
        }
        
        .card-info h3 {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .card-info p {
            margin: 8px 0 0 0;
            font-size: 28px;
            font-weight: 800;
            color: #5D0E26;
        }
        
        .audit-section {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(93, 14, 38, 0.1);
            padding: 30px;
            border: 1px solid rgba(93, 14, 38, 0.05);
        }
        
        .audit-section h2 {
            margin-bottom: 25px;
            color: #5D0E26;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .audit-section h2::before {
            content: '';
            width: 4px;
            height: 25px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 2px;
        }
        
        .filter-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        .filter-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #5D0E26;
            font-size: 0.9rem;
        }
        
        .filter-form input,
        .filter-form select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .filter-form input:focus,
        .filter-form select:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }
        
        .filter-form .btn {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-form .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }
        
        .activity-list {
            max-height: 600px;
            overflow-y: auto;
            border-radius: 12px;
            background: #f8f9fa;
            padding: 5px;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(93, 14, 38, 0.05);
            border-left: 4px solid #5D0E26;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 20px rgba(93, 14, 38, 0.1);
        }
        
        .activity-item:last-child {
            margin-bottom: 0;
        }
        
        .activity-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.2);
        }
        
        .activity-icon i {
            color: white;
            font-size: 20px;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-details h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.3;
        }
        
        .activity-details p {
            margin: 6px 0;
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-badge.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .no-activities {
            text-align: center;
            padding: 60px 40px;
            color: #666;
        }
        
        .no-activities i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .no-activities p {
            font-size: 1.1rem;
            margin: 0;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
            margin-top: 25px;
            padding: 20px;
        }
        
        .pagination .btn {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .pagination .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }
        
        .pagination .page-info {
            padding: 10px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            font-weight: 600;
            color: #5D0E26;
            border: 1px solid rgba(93, 14, 38, 0.1);
        }
        
        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .card {
                padding: 20px;
                gap: 15px;
            }
            
            .card-icon {
                width: 50px;
                height: 50px;
            }
            
            .card-icon i {
                font-size: 20px;
            }
            
            .activity-item {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .activity-icon {
                width: 40px;
                height: 40px;
            }
            
            .filter-form {
                grid-template-columns: 1fr !important;
                gap: 15px;
            }
            
            .pagination {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</body>
</html> 