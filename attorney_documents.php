<?php
require_once 'session_manager.php';
validateUserAccess('attorney');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

// Initialize messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$attorney_id = $_SESSION['user_id'];
$res = $conn->query("SELECT profile_image FROM user_form WHERE id=$attorney_id");
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }

// Log activity function for document actions
function log_attorney_activity($conn, $doc_id, $action, $user_id, $user_name, $file_name, $category) {
    $stmt = $conn->prepare("INSERT INTO attorney_document_activity (document_id, action, user_id, user_name, file_name, category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssss', $doc_id, $action, $user_id, $user_name, $file_name, $category);
    $stmt->execute();
}

function truncate_document_name($name, $max_length = 20) {
    if (strlen($name) <= $max_length) {
        return $name;
    }
    return substr($name, 0, $max_length) . '...';
}

// Function to convert Word file to PDF using PhpOffice/PhpWord + DomPDF
function convertWordToPDF($wordFilePath) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $pathInfo = pathinfo($wordFilePath);
    $directory = $pathInfo['dirname'];
    $filename = $pathInfo['filename'];
    $extension = strtolower($pathInfo['extension']);
    
    // Only convert .docx files (PhpWord doesn't support old .doc format well)
    if ($extension !== 'docx') {
        return $wordFilePath; // Not a .docx file, return original
    }
    
    $pdfFilePath = $directory . '/' . $filename . '.pdf';
    
    try {
        // Load the Word document
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($wordFilePath);
        
        // Create PDF writer using DomPDF
        \PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');
        \PhpOffice\PhpWord\Settings::setPdfRendererPath(__DIR__ . '/vendor/dompdf/dompdf');
        
        // Save as PDF
        $pdfWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');
        $pdfWriter->save($pdfFilePath);
        
        // Check if PDF was created successfully
        if (file_exists($pdfFilePath) && filesize($pdfFilePath) > 0) {
            // Delete the original Word file
            unlink($wordFilePath);
            return $pdfFilePath;
        }
    } catch (Exception $e) {
        // Conversion failed, keep original file
        error_log("Word to PDF conversion failed: " . $e->getMessage());
    }
    
    // If conversion failed, keep original file
    return $wordFilePath;
}

// Handle multiple document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documents'])) {
    $uploaded_count = 0;
    $errors = [];
    
    foreach ($_FILES['documents']['name'] as $key => $filename) {
        if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
            // Check if the corresponding form data exists
            if (!isset($_POST['doc_names'][$key]) || !isset($_POST['categories'][$key])) {
                $errors[] = "Missing form data for file: " . $filename;
                continue;
            }
            
            $doc_name = trim($_POST['doc_names'][$key]);
            $category = trim($_POST['categories'][$key]);
            
            if (empty($doc_name)) {
                $errors[] = "Document name is required for file: " . $filename;
                continue;
            }
            
            if (empty($category)) {
                $errors[] = "Category is required for file: " . $filename;
                continue;
            }
            
            $fileInfo = pathinfo($filename);
    $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
            $safeDocName = preg_replace('/[^A-Za-z0-9 _\-]/', '', $doc_name);
    $fileName = $safeDocName . $extension;
            
    $targetDir = 'uploads/attorney/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
            
            $targetFile = $targetDir . time() . '_' . $key . '_' . $fileName;
            $file_size = $_FILES['documents']['size'][$key];
            $file_type = $_FILES['documents']['type'][$key];
            
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$key], $targetFile)) {
                // Convert Word files to PDF automatically
                $finalFilePath = convertWordToPDF($targetFile);
                
                $uploadedBy = $_SESSION['user_id'] ?? 1;
    $user_name = $_SESSION['attorney_name'] ?? 'Attorney';
                
                $stmt = $conn->prepare("INSERT INTO attorney_documents (file_name, file_path, category, uploaded_by, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssisi', $fileName, $finalFilePath, $category, $uploadedBy, $file_size, $file_type);
        $stmt->execute();
                
        $doc_id = $conn->insert_id;
                
                // log_attorney_activity($conn, $doc_id, 'Uploaded', $uploadedBy, $user_name, $fileName, $category);
        
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $uploadedBy,
            $user_name,
            'attorney',
            'Document Upload',
            'Document Management',
                    "Uploaded document: $fileName (Category: $category)",
            'success',
            'medium'
        );
        
                $uploaded_count++;
    } else {
                $errors[] = "Failed to upload file: " . $filename;
            }
        }
    }
    
    if ($uploaded_count > 0) {
        $success = "Successfully uploaded $uploaded_count document(s)!";
        // Return JSON response for AJAX handling
        echo json_encode([
            'success' => true,
            'message' => "Successfully uploaded $uploaded_count document(s)!",
            'count' => $uploaded_count
        ]);
        exit();
    }
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
        // Return JSON response for AJAX handling
        echo json_encode([
            'success' => false,
            'message' => implode('\n', $errors),
            'errors' => $errors
        ]);
        exit();
    }
}

// Handle edit
if (isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $new_name = trim($_POST['edit_document_name']);
    $new_category = trim($_POST['edit_category']);
    $uploadedBy = $_SESSION['user_id'] ?? 1;
    $user_name = $_SESSION['attorney_name'] ?? 'Attorney';
    
    $stmt = $conn->prepare("UPDATE attorney_documents SET file_name=?, category=? WHERE id=?");
    $stmt->bind_param('ssi', $new_name, $new_category, $edit_id);
    $stmt->execute();
    
    // log_attorney_activity($conn, $edit_id, 'Edited', $uploadedBy, $user_name, $new_name, $new_category);
    
    // Log to audit trail
    global $auditLogger;
    $auditLogger->logAction(
        $uploadedBy,
        $user_name,
        'attorney',
        'Document Edit',
        'Document Management',
        "Edited document: $new_name (Category: $new_category)",
        'success',
        'medium'
    );
    
    header('Location: attorney_documents.php?scroll=documents&doc_id=' . $edit_id);
    exit();
}

// Fetch documents for display (only user's own documents)
$user_id = $_SESSION['user_id'] ?? $attorney_id ?? 1;
$documents = [];
$stmt = $conn->prepare("SELECT * FROM attorney_documents WHERE uploaded_by = ? AND is_deleted = 0 ORDER BY upload_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}

// Count documents per category
$category_counts = [
    'All Documents' => count($documents),
    'Case Files' => 0,
    'Court Documents' => 0,
    'Client Documents' => 0
];
foreach ($documents as $doc) {
    if (isset($category_counts[$doc['category']])) {
        $category_counts[$doc['category']]++;
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT file_path, file_name, uploaded_by, category FROM attorney_documents WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $user_name = $_SESSION['attorney_name'] ?? 'Attorney';
        // log_attorney_activity($conn, $id, 'Deleted', $row['uploaded_by'], $user_name, $row['file_name'], $row['category']);
        
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $_SESSION['user_id'] ?? $row['uploaded_by'],
            $user_name,
            'attorney',
            'Document Delete',
            'Document Management',
            "Deleted document: {$row['file_name']} (Category: {$row['category']})",
            'success',
            'high' // HIGH priority for deletions
        );
    }
    $stmt = $conn->prepare("UPDATE attorney_documents SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: attorney_documents.php?scroll=documents&deleted=1');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <style>
        /* Profile Modal Override - Ensure consistent compact modal */
        .modal#editProfileModal .modal-content {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            margin: 2% auto !important;
            width: 98% !important;
            max-width: 800px !important;
        }
        
        .modal#passwordVerificationModal .modal-content {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            margin: 2% auto !important;
            width: 98% !important;
            max-width: 800px !important;
        }
        
        .modal#editProfileModal .modal-body {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            padding: 12px !important;
        }
        
        .modal#passwordVerificationModal .modal-body {
            max-height: none !important;
            height: auto !important;
            min-height: auto !important;
            overflow-y: visible !important;
            overflow-x: visible !important;
            padding: 12px !important;
        }
        
        /* Compact modal elements */
        .modal#editProfileModal .form-section {
            margin-bottom: 6px !important;
            padding: 0 !important;
        }
        
        .modal#editProfileModal .form-group {
            margin-bottom: 4px !important;
        }
        
        .modal#editProfileModal .modal-header h2 {
            font-size: 1.1rem !important;
            padding: 8px 12px !important;
        }
        
        .modal#editProfileModal .modal-header {
            padding: 8px 12px !important;
        }
        
        .modal#editProfileModal .form-section h3 {
            font-size: 0.9rem !important;
            margin-bottom: 6px !important;
            padding-bottom: 2px !important;
        }
        
        .modal#editProfileModal .form-group label {
            font-size: 0.75rem !important;
            margin-bottom: 2px !important;
        }
        
        .modal#editProfileModal .form-group input {
            padding: 4px 6px !important;
            font-size: 0.8rem !important;
            border-radius: 4px !important;
        }
        
        .modal#editProfileModal .upload-btn {
            padding: 4px 8px !important;
            font-size: 0.7rem !important;
        }
        
        .modal#editProfileModal .upload-hint {
            font-size: 0.65rem !important;
        }
        
        .modal#editProfileModal .current-profile-image {
            width: 50px !important;
            height: 50px !important;
        }
        
        .modal#editProfileModal .form-actions button {
            padding: 4px 8px !important;
            font-size: 0.75rem !important;
        }
        
        .modal#editProfileModal small {
            font-size: 0.6rem !important;
        }
    </style>
</head>
<body>
     <!-- Sidebar -->
     <div class="sidebar">
                <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="attorney_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>
            <li><a href="attorney_documents.php" class="active"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>Scheduling</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>Client Management</span></a></li>
            <li><a href="attorney_messages.php" class="has-badge"><i class="fas fa-envelope"></i><span>Messages</span><span class="unread-message-badge hidden" id="unreadMessageBadge">0</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $page_title = 'Document Management';
        $page_subtitle = 'Manage and organize your case documents';
        include 'components/profile_header.php'; 
        ?>

        <!-- Success/Error Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" style="margin: 20px; padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Upload Section -->
        <div class="upload-section">
            <h2><i class="fas fa-upload"></i> Upload Documents</h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" onsubmit="return handleUploadSubmit(event)">
                <div class="upload-area" id="uploadArea">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #6b7280; margin-bottom: 10px;"></i>
                    <h3 style="font-size: 1.1rem; margin-bottom: 5px;">Drag & Drop Files Here</h3>
                    <p style="font-size: 0.9rem; color: #6b7280;">or click to select files (PDF, Word documents only - up to 10 documents)</p>
                    <input type="file" name="documents[]" id="fileInput" multiple accept=".pdf,.doc,.docx" style="display: none;">
                </div>
                
                <div class="file-preview" id="filePreview">
                    <h4>Document Details</h4>
                    <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 8px; margin-bottom: 15px; font-size: 0.8rem;">
                        <strong>📚 Document Types:</strong> Case Files, Court Documents, Client Documents
                    </div>
                    <div id="previewList"></div>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn-primary" id="uploadBtn" style="display: none; background: #5D0E26; color: white; border: none; border-radius: 8px; padding: 12px 24px; font-size: 1rem; font-weight: 600; cursor: pointer; box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3); transition: all 0.2s ease;">
                        <i class="fas fa-upload" style="margin-right: 8px;"></i> Upload Documents
            </button>
                </div>
            </form>
        </div>

        <!-- Document Categories with Search -->
        <div class="document-categories">
            <div class="category active" onclick="filterByCategory('All Documents')">
                <span class="badge"><?= $category_counts['All Documents'] ?></span>
                <span>All Documents</span>
            </div>
            <div class="category" onclick="filterByCategory('Case Files')">
                <span class="badge"><?= $category_counts['Case Files'] ?></span>
                <span>Case Files</span>
            </div>
            <div class="category" onclick="filterByCategory('Court Documents')">
                <span class="badge"><?= $category_counts['Court Documents'] ?></span>
                <span>Court Documents</span>
            </div>
            <div class="category" onclick="filterByCategory('Client Documents')">
                <span class="badge"><?= $category_counts['Client Documents'] ?></span>
                <span>Client Documents</span>
            </div>
            
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search documents by name...">
                <button type="button" onclick="document.getElementById('searchInput').value='';filterDocuments();" title="Clear search"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <!-- Documents Grid -->
        <div class="document-grid">
            <?php if (empty($documents)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; color: #d1d5db; margin-bottom: 15px;"></i>
                    <h3 style="color: #6b7280;">No documents found</h3>
                    <p style="color: #9ca3af;">Try uploading some documents.</p>
                </div>
            <?php else: ?>
            <?php foreach ($documents as $doc): ?>
                <div class="document-card" data-doc-id="<?= $doc['id'] ?>">
                    <div class="card-header">
                        <div class="document-icon" style="margin-right: 8px !important; padding-right: 0px !important;">
                            <?php 
                            // Check actual file extension from file_path (not file_name) for converted files
                            $actualExt = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                            if($actualExt === 'pdf'): ?>
                                <i class="fas fa-file-pdf" style="color: #d32f2f;"></i>
                            <?php elseif($actualExt === 'doc' || $actualExt === 'docx'): ?>
                                <i class="fas fa-file-word" style="color: #2196f3;"></i>
                            <?php elseif($actualExt === 'xls' || $actualExt === 'xlsx'): ?>
                                <i class="fas fa-file-excel" style="color: #388e3c;"></i>
                            <?php else: ?>
                                <i class="fas fa-file-alt"></i>
                            <?php endif; ?>
                        </div>
                        <div class="document-info" style="margin-left: 0px !important; padding-left: 0px !important;">
                            <?php 
                            // Show .pdf if file was converted from Word
                            $displayName = $doc['file_name'];
                            if (pathinfo($doc['file_path'], PATHINFO_EXTENSION) === 'pdf' && preg_match('/\.(docx?|DOCX?)$/i', $doc['file_name'])) {
                                $displayName = preg_replace('/\.(docx?|DOCX?)$/i', '.pdf', $doc['file_name']);
                            }
                            ?>
                            <h3 title="<?= htmlspecialchars($displayName) ?>"><?= htmlspecialchars(truncate_document_name(pathinfo($displayName, PATHINFO_FILENAME))) ?></h3>
                            <div class="document-meta">
                                <div><strong><?= htmlspecialchars($doc['category']) ?></strong> | <?= date('M d, Y', strtotime($doc['upload_date'])) ?></div>
                            </div>
                            </div>
                        </div>

                    <div class="document-actions">
                        <button onclick="openViewModal('<?= htmlspecialchars($doc['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['file_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($doc['category'], ENT_QUOTES) ?>')" class="btn-action btn-view" title="View Document">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="showDownloadConfirmModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME))) ?>', '<?= addslashes(htmlspecialchars($doc['file_path'])) ?>')" class="btn-action btn-view" title="Download Document">
                            <i class="fas fa-download"></i>
                        </button>
                        <button onclick="openEditModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME))) ?>', '<?= addslashes(htmlspecialchars($doc['category'])) ?>')" class="btn-action btn-edit" title="Edit Document">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="showDeleteConfirmModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars(pathinfo($doc['file_name'], PATHINFO_FILENAME))) ?>')" class="btn-action btn-delete" title="Delete Document">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Documents Pagination -->
        <div class="pagination-container pagination-bottom" id="documentsPaginationContainer" style="display: none;">
            <div class="pagination-info">
                <span id="paginationInfo">Showing documents</span>
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="prevBtn" onclick="changePage(-1)">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-numbers" id="paginationNumbers"></div>
                <button class="pagination-btn" id="nextBtn" onclick="changePage(1)">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="pagination-settings">
                <label for="itemsPerPage">Per page:</label>
                <select id="itemsPerPage" onchange="updateItemsPerPage()">
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content view-modal" style="max-width: 900px !important; width: 85% !important;">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> View Document</h2>
                <button class="close-modal-btn" onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="document-details" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div class="detail-row" style="display: flex; flex-direction: column;">
                        <label style="font-size: 14px; font-weight: 600; color: #5D0E26; margin-bottom: 8px;">
                            <i class="fas fa-file-alt"></i> Document Name
                        </label>
                        <span id="viewDocumentName" style="font-size: 16px; font-weight: 500; color: #333;"></span>
                    </div>
                    <div class="detail-row" style="display: flex; flex-direction: column;">
                        <label style="font-size: 14px; font-weight: 600; color: #5D0E26; margin-bottom: 8px;">
                            <i class="fas fa-folder"></i> Category
                        </label>
                        <span id="viewCategory" style="font-size: 16px; font-weight: 500; color: #333;"></span>
                    </div>
                </div>
                <div class="document-preview">
                    <iframe id="documentFrame" src="" width="100%" height="600px" style="border: 1px solid #ddd; border-radius: 8px;"></iframe>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <a id="downloadLinkView" href="" download class="btn btn-primary">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal-overlay" style="display: none;">
        <div class="modal-content" style="max-width: 950px !important; width: 85% !important; max-height: 90vh; overflow: auto; position: relative; border-radius: 16px; box-shadow: 0 25px 50px rgba(93, 14, 38, 0.25);">
            <button class="close-modal-btn" onclick="closePreviewModal()" title="Close" style="position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 35px; height: 35px; font-size: 18px; cursor: pointer; z-index: 1000; display: flex; align-items: center; justify-content: center;">&times;</button>
            <h2 id="previewTitle" style="margin-top: 10px;">Document Preview</h2>
            <div id="previewContent" style="text-align: center;"></div>
        </div>
    </div>

    <!-- Save Changes Confirmation Modal -->
    <div id="saveConfirmModal" class="modal">
        <div class="modal-content confirmation-modal">
            <div class="confirmation-content">
                <div class="confirmation-icon warning">
                    <i class="fas fa-save"></i>
                </div>
                <h3>Save Changes?</h3>
                <p>Are you sure you want to save these changes? This will update the document information.</p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeSaveConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-primary" onclick="proceedSave()">
                    <i class="fas fa-check"></i> Yes, Save Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Cancel Edit Confirmation Modal -->
    <div id="cancelEditConfirmModal" class="modal">
        <div class="modal-content confirmation-modal">
            <div class="confirmation-content">
                <div class="confirmation-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Cancel Editing?</h3>
                <p>Are you sure you want to cancel? Any unsaved changes will be lost.</p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeCancelEditConfirmModal()">
                    <i class="fas fa-times"></i> No, Continue Editing
                </button>
                <button class="btn btn-danger" onclick="proceedCancelEdit()">
                    <i class="fas fa-check"></i> Yes, Discard Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Document Modal -->
    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content edit-modal">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Document</h2>
                <button class="close-modal-btn" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <?php if (!empty($error)) echo '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> ' . $error . '</div>'; ?>
                <form method="POST" class="modern-form" id="editForm" onsubmit="return handleEditSubmit(event)">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="form-group">
                        <label for="edit_document_name">
                            <i class="fas fa-file-alt"></i> Document Name
                        </label>
                        <input type="text" name="edit_document_name" id="edit_document_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_category">
                            <i class="fas fa-folder"></i> Category
                        </label>
                        <select name="edit_category" id="edit_category" required>
                            <option value="">Select Category</option>
                            <option value="Case Files">Case Files</option>
                            <option value="Court Documents">Court Documents</option>
                            <option value="Client Documents">Client Documents</option>
                        </select>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Old Edit Save Confirmation Modal (keeping for compatibility) -->
    <div id="editSaveConfirmModal" class="modal" style="display:none;">
        <div class="modal-content edit-save-confirm-modal">
            <div class="modal-header edit-save-confirm-header">
                <h2><i class="fas fa-save"></i> Confirm Save</h2>
                <button class="close-modal-btn" onclick="closeEditSaveConfirmModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body edit-save-confirm-body">
                <div class="edit-save-confirm-content">
                    <div class="edit-save-confirm-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3>Save Changes?</h3>
                    <p id="editSaveConfirmMessage">Are you sure you want to save these changes?</p>
                </div>
            </div>
            <div class="modal-actions edit-save-confirm-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditSaveConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmEditSave()">
                    <i class="fas fa-check"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal" style="display:none;">
        <div class="modal-content delete-confirm-modal">
            <div class="modal-header delete-confirm-header">
                <h2><i class="fas fa-trash"></i> Confirm Delete</h2>
                <button class="close-modal-btn" onclick="closeDeleteConfirmModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body delete-confirm-body">
                <div class="delete-confirm-content">
                    <div class="delete-confirm-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Delete Document?</h3>
                    <p id="deleteConfirmMessage">Are you sure you want to delete this document?</p>
                    <div class="delete-warning">
                        <strong>⚠️ Warning:</strong> This action cannot be undone!
                    </div>
                </div>
            </div>
            <div class="modal-actions delete-confirm-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteDocument()">
                    <i class="fas fa-trash"></i> Proceed
                </button>
            </div>
        </div>
    </div>

    <!-- Final Delete Confirmation Modal -->
    <div id="finalDeleteModal" class="modal" style="display:none;">
        <div class="modal-content final-delete-modal">
            <div class="modal-header final-delete-header">
                <h2><i class="fas fa-exclamation-circle"></i> FINAL CONFIRMATION</h2>
                <button class="close-modal-btn" onclick="closeFinalDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body final-delete-body">
                <div class="final-delete-content">
                    <div class="final-delete-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Permanently Delete?</h3>
                    <p id="finalDeleteMessage">You are about to PERMANENTLY DELETE this document!</p>
                    <div class="final-delete-warning">
                        <strong>⚠️ CRITICAL:</strong> This action is IRREVERSIBLE!
                    </div>
                </div>
            </div>
            <div class="modal-actions final-delete-actions">
                <button type="button" class="btn btn-secondary" onclick="closeFinalDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="executeDeleteDocument()">
                    <i class="fas fa-trash"></i> YES, DELETE PERMANENTLY
                </button>
            </div>
        </div>
    </div>

    <!-- Download Confirmation Modal -->
    <div id="downloadConfirmModal" class="modal" style="display:none;">
        <div class="modal-content download-confirm-modal">
            <div class="modal-header download-confirm-header">
                <h2><i class="fas fa-download"></i> Confirm Download</h2>
                <button class="close-modal-btn" onclick="closeDownloadConfirmModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body download-confirm-body">
                <div class="download-confirm-content">
                    <div class="download-confirm-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3>Download Document?</h3>
                    <p id="downloadConfirmMessage">Are you sure you want to download this document?</p>
                    <div class="download-info">
                        <strong>📄 File:</strong> <span id="downloadFileName"></span>
                    </div>
                </div>
            </div>
            <div class="modal-actions download-confirm-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDownloadConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmDownloadDocument()">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>
    </div>
    <!-- Set Access Permissions Modal -->
    <div id="accessModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeAccessModal()">&times;</span>
            <h2>Set Access Permissions</h2>
            <form>
                <label>Grant Access To:</label>
                <select required>
                    <option value="">Select User Type</option>
                    <option value="Attorney">Attorney</option>
                    <option value="Admin Employee">Admin Employee</option>
                </select>
                <button type="submit" class="btn btn-primary">Set Access</button>
            </form>
        </div>
    </div>

    <!-- Success Modal - Very Small -->
    <div id="uploadSuccessModal" class="modal" style="display:none;">
        <div class="modal-content upload-success-modal">
            <div class="modal-header upload-success-header">
                <h2><i class="fas fa-check-circle"></i> Success</h2>
                <button class="close-modal-btn" onclick="closeUploadSuccessModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body upload-success-body">
                <div class="upload-success-content">
                    <div class="upload-success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 id="uploadSuccessMessage">Uploaded!</h3>
                    <p id="uploadSuccessDetails">Document uploaded successfully.</p>
                </div>
            </div>
            <div class="modal-actions upload-success-actions">
                <button type="button" class="btn btn-primary" onclick="closeUploadSuccessModal()">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>
    <script>
        // Auto scroll to documents section if scroll parameter is present
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            console.log('URL params:', urlParams.toString());
            console.log('Scroll param:', urlParams.get('scroll'));
            console.log('Doc ID param:', urlParams.get('doc_id'));
            console.log('Deleted param:', urlParams.get('deleted'));
            
            if (urlParams.get('scroll') === 'documents') {
                console.log('Scroll parameter detected, scrolling to documents...');
                setTimeout(function() {
                    const docId = urlParams.get('doc_id');
                    const deleted = urlParams.get('deleted');
                    
                    if (docId) {
                        // Try to find the specific document card
                        const specificDoc = document.querySelector(`[data-doc-id="${docId}"]`);
                        console.log('Specific document found:', specificDoc);
                        
                        if (specificDoc) {
                            specificDoc.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            console.log('Scrolled to specific document');
                            
                            // Add a highlight effect
                            specificDoc.style.border = '2px solid #8B1538';
                            specificDoc.style.boxShadow = '0 0 20px rgba(139, 21, 56, 0.3)';
                            
                            // Remove highlight after 3 seconds
                            setTimeout(() => {
                                specificDoc.style.border = '';
                                specificDoc.style.boxShadow = '';
                            }, 3000);
                        } else {
                            // Fallback to documents grid
                            const documentsSection = document.querySelector('.document-grid');
                            if (documentsSection) {
                                documentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                console.log('Scrolled to documents section (fallback)');
                            }
                        }
                    } else {
                        // No specific doc ID, scroll to documents grid
                        const documentsSection = document.querySelector('.document-grid');
                        if (documentsSection) {
                            documentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            console.log('Scrolled to documents section');
                            
                            // If it was a delete operation, show a brief success message
                            if (deleted === '1') {
                                console.log('Document was deleted, showing success indication');
                                // You could add a temporary success message here if needed
                            }
                        }
                    }
                }, 500);
                
                // Clean up URL by removing the scroll parameter
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        });

        // Store file data for persistent preview
        let fileDataStore = new Map();

        // File upload handling
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.getElementById('uploadArea');
        const filePreview = document.getElementById('filePreview');
        const previewList = document.getElementById('previewList');
        const uploadBtn = document.getElementById('uploadBtn');

        // Check if elements exist before adding event listeners
        if (uploadArea && fileInput) {
            // Drag and drop functionality
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#1976d2';
                uploadArea.style.backgroundColor = '#f0f9ff';
            });

            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#d1d5db';
                uploadArea.style.backgroundColor = '#f9fafb';
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#d1d5db';
                uploadArea.style.backgroundColor = '#f9fafb';
                const files = e.dataTransfer.files;
                handleFiles(files);
            });

            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });
        }

        function handleFiles(files) {
            if (files.length > 50) {
                alert('Maximum 50 files allowed');
                return;
            }

            previewList.innerHTML = '';
            fileDataStore.clear(); // Clear previous data
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.setAttribute('data-file-index', i);
                
                // Store file data for persistent preview
                const fileId = 'file_' + Date.now() + '_' + i;
                fileDataStore.set(fileId, {
                    file: file,
                    url: URL.createObjectURL(file),
                    name: file.name,
                    type: file.type
                });
                
                // Create preview based on file type
                let previewContent = '';
                if (file.type.startsWith('image/')) {
                    previewContent = `
                        <div style="position: relative; margin-right: 10px;">
                            <img src="${fileDataStore.get(fileId).url}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 44px; border: 1px solid #d1d5db;">
                            <button type="button" onclick="openPreviewModal('${fileId}')" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">👁</button>
                        </div>
                    `;
                } else if (file.type === 'application/pdf') {
                    previewContent = `
                        <div style="position: relative; margin-right: 10px;">
                            <iframe src="${fileDataStore.get(fileId).url}" style="width: 80px; height: 80px; border-radius: 4px; border: 1px solid #d1d5db;"></iframe>
                            <button type="button" onclick="openPreviewModal('${fileId}')" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">👁</button>
                        </div>
                    `;
                } else {
                    previewContent = `
                        <div style="position: relative; margin-right: 10px;">
                            <i class="fas fa-file" style="font-size: 48px; color: #6b7280; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border: 1px solid #d1d5db; border-radius: 4px;"></i>
                            <button type="button" onclick="openPreviewModal('${fileId}')" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">👁</button>
                        </div>
                    `;
                }
                
                previewItem.innerHTML = `
                    <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                        <div style="position: relative;">
                            ${previewContent.replace('<div style="position: relative; margin-right: 10px;">', '<div style="position: relative;">')}
                        </div>
                        <div style="flex: 1; display: flex; flex-direction: column; gap: 8px;">
                            <div style="font-size: 0.7rem; color: #6b7280; word-break: break-all; line-height: 1.2;">${file.name}</div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" name="doc_names[]" placeholder="Document Name" required style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; height: 36px; font-size: 0.85rem;">
                                <select name="categories[]" required style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; height: 36px; font-size: 0.85rem;">
                                    <option value="">Select Type</option>
                                    <option value="Case Files">Case Files</option>
                                    <option value="Court Documents">Court Documents</option>
                                    <option value="Client Documents">Client Documents</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
                            <button type="button" onclick="removePreviewItem(this)" style="background: #dc2626; color: white; border: none; border-radius: 4px; padding: 8px 12px; cursor: pointer; height: 36px; display: flex; align-items: center; font-size: 0.8rem; font-weight: 500;">Remove</button>
                        </div>
                    </div>
                `;
                previewList.appendChild(previewItem);
            }
            
            filePreview.style.display = 'block';
            uploadBtn.style.display = 'inline-flex';
        }

        function removePreviewItem(button) {
            const previewItem = button.closest('.preview-item');
            const fileIndex = previewItem.getAttribute('data-file-index');
            
            // Remove the preview item
            previewItem.remove();
            
            // Create a new FileList without the removed file
            const currentFiles = fileInput.files;
            const newFiles = [];
            for (let i = 0; i < currentFiles.length; i++) {
                if (i != fileIndex) {
                    newFiles.push(currentFiles[i]);
                }
            }
            
            // Create a new DataTransfer object to update the file input
            const dt = new DataTransfer();
            newFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            
            // Update preview indices for remaining items
            const remainingItems = previewList.children;
            for (let i = 0; i < remainingItems.length; i++) {
                remainingItems[i].setAttribute('data-file-index', i);
            }
            
            if (previewList.children.length === 0) {
                filePreview.style.display = 'none';
                uploadBtn.style.display = 'none';
            }
        }

        // Handle form submission with AJAX
        function handleUploadSubmit(event) {
            event.preventDefault();
            
            // First validate the form
            if (!validateUploadForm()) {
                return false;
            }
            
            // Show loading state
            const uploadBtn = document.getElementById('uploadBtn');
            const originalText = uploadBtn.innerHTML;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            uploadBtn.disabled = true;
            
            // Create FormData
            const formData = new FormData(document.getElementById('uploadForm'));
            
            // Submit via AJAX
            fetch('attorney_documents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                uploadBtn.innerHTML = originalText;
                uploadBtn.disabled = false;
                
                if (data.success) {
                    // Show success modal
                    showUploadSuccessModal(data.message, data.count);
                        // Clear the form
                        document.getElementById('filePreview').style.display = 'none';
                        document.getElementById('uploadBtn').style.display = 'none';
                        document.getElementById('fileInput').value = '';
                        fileDataStore.clear();
                } else {
                    // Show error modal
                    alert('Upload Error:\n\n' + data.message);
                }
            })
            .catch(error => {
                // Reset button
                uploadBtn.innerHTML = originalText;
                uploadBtn.disabled = false;
                alert('Upload failed: ' + error.message);
            });
            
            return false;
        }

        // Form validation with detailed error messages
        function validateUploadForm() {
            const docNames = document.querySelectorAll('input[name="doc_names[]"]');
            const categories = document.querySelectorAll('select[name="categories[]"]');
            const errors = [];
            
            for (let i = 0; i < docNames.length; i++) {
                if (!docNames[i].value.trim()) {
                    errors.push(`File ${i + 1}: Document name is required`);
                }
                if (!categories[i].value) {
                    errors.push(`File ${i + 1}: Category is required`);
                }
            }
            
            if (errors.length > 0) {
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
            
            return true;
        }

        // Preview functions
        function openPreviewModal(fileId) {
            const fileData = fileDataStore.get(fileId);
            if (!fileData) {
                alert('File data not found. Please reselect the files.');
                return;
            }
            
            document.getElementById('previewTitle').textContent = `Preview: ${fileData.name}`;
            const previewContent = document.getElementById('previewContent');
            
            if (fileData.type.startsWith('image/')) {
                previewContent.innerHTML = `<img src="${fileData.url}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">`;
            } else if (fileData.type === 'application/pdf') {
                previewContent.innerHTML = `<iframe src="${fileData.url}" style="width: 100%; height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></iframe>`;
            } else {
                previewContent.innerHTML = `
                    <div style="padding: 40px;">
                        <i class="fas fa-file" style="font-size: 4rem; color: #6b7280; margin-bottom: 20px;"></i>
                        <h3>${fileData.name}</h3>
                        <p>This file type cannot be previewed in the browser.</p>
                        <p>Please download the file to view its contents.</p>
                    </div>
                `;
            }
            
            document.getElementById('previewModal').style.display = 'block';
        }

        function closePreviewModal() {
            document.getElementById('previewModal').style.display = 'none';
        }

        // View Modal functions
        function openViewModal(filePath, documentName, category) {
            // If file was converted to PDF (file_path ends with .pdf but name ends with .docx), update display name
            let displayName = documentName;
            if (filePath.toLowerCase().endsWith('.pdf') && (documentName.toLowerCase().endsWith('.docx') || documentName.toLowerCase().endsWith('.doc'))) {
                displayName = documentName.replace(/\.(docx?|DOCX?)$/i, '.pdf');
            }
            
            // Set document details
            document.getElementById('viewDocumentName').textContent = displayName;
            document.getElementById('viewCategory').textContent = category;
            
            // Set iframe source and download link
            // Word files are auto-converted to PDF, so just display normally
            document.getElementById('documentFrame').src = filePath;
            document.getElementById('downloadLinkView').href = filePath;
            document.getElementById('downloadLinkView').download = displayName;
            
            // Show modal
            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            if (modal) {
                modal.style.display = 'none';
            }
            // Clear iframe to stop loading
            document.getElementById('documentFrame').src = '';
        }

        // Modal functions (Edit)
        function openEditModal(id, name, category) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_document_name').value = name;
            document.getElementById('edit_category').value = category;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            // Show custom cancel confirmation modal
            const modal = document.getElementById('cancelEditConfirmModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('show');
            }
        }

        function closeSaveConfirmModal() {
            const modal = document.getElementById('saveConfirmModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
        }

        function proceedSave() {
            // Close confirmation modal
            closeSaveConfirmModal();
            
            // Get the form and submit it
            const editForm = document.getElementById('editForm');
            if (editForm) {
                // Temporarily remove onsubmit to avoid infinite loop
                editForm.onsubmit = null;
                editForm.submit();
            }
        }

        function closeCancelEditConfirmModal() {
            const modal = document.getElementById('cancelEditConfirmModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
        }

        function proceedCancelEdit() {
            closeCancelEditConfirmModal();
            const editModal = document.getElementById('editModal');
            if (editModal) {
                editModal.style.display = 'none';
            }
        }

        // Upload Success Modal Functions
        function showUploadSuccessModal(message, count) {
            document.getElementById('uploadSuccessMessage').textContent = message;
            document.getElementById('uploadSuccessDetails').textContent = 
                `${count} document(s) uploaded successfully.`;
            document.getElementById('uploadSuccessModal').style.display = 'block';
        }

        function closeUploadSuccessModal() {
            document.getElementById('uploadSuccessModal').style.display = 'none';
            // Refresh to show new documents
            window.location.reload();
        }

        // Edit Save Confirmation Modal Functions
        function handleEditSubmit(event) {
            event.preventDefault();
            
            // Show new custom save confirmation modal
            const modal = document.getElementById('saveConfirmModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('show');
            }
            
            return false;
        }

        function closeEditSaveConfirmModal() {
            document.getElementById('editSaveConfirmModal').style.display = 'none';
        }

        function confirmEditSave() {
            // Close confirmation modal
            document.getElementById('editSaveConfirmModal').style.display = 'none';
            
            // Submit the actual form
            document.getElementById('editForm').submit();
        }

        // Download Confirmation Modal Functions
        let currentDownloadData = null;

        function showDownloadConfirmModal(id, name, filePath) {
            console.log('Download button clicked! ID:', id, 'Name:', name, 'Path:', filePath);
            currentDownloadData = { id, name, filePath };
            console.log('currentDownloadData set to:', currentDownloadData);
            
            const messageElement = document.getElementById('downloadConfirmMessage');
            const fileNameElement = document.getElementById('downloadFileName');
            const modal = document.getElementById('downloadConfirmModal');
            
            console.log('Download message element found:', messageElement);
            console.log('Download fileName element found:', fileNameElement);
            console.log('Download modal element found:', modal);
            
            if (messageElement) {
                messageElement.textContent = `Are you sure you want to download "${name}"?`;
            }
            
            if (fileNameElement) {
                fileNameElement.textContent = name;
            }
            
            if (modal) {
                modal.style.display = 'flex';
                console.log('Download modal should be visible now');
            } else {
                console.error('Download modal not found!');
            }
        }

        function closeDownloadConfirmModal() {
            console.log('closeDownloadConfirmModal called');
            document.getElementById('downloadConfirmModal').style.display = 'none';
            // Don't clear currentDownloadData here - it's needed for the download
        }

        function confirmDownloadDocument() {
            console.log('confirmDownloadDocument called, currentDownloadData:', currentDownloadData);
            if (currentDownloadData) {
                console.log('Starting download for:', currentDownloadData.filePath);
                
                // Create a temporary link and trigger download
                const link = document.createElement('a');
                link.href = currentDownloadData.filePath;
                link.download = currentDownloadData.name;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                console.log('Download initiated');
                
                // Close modal and clear data after download
                document.getElementById('downloadConfirmModal').style.display = 'none';
                currentDownloadData = null;
            } else {
                console.error('currentDownloadData is null! Cannot download.');
            }
        }

        function showDeleteConfirmModal(id, name) {
            console.log('Delete button clicked! ID:', id, 'Name:', name);
            currentDeleteData = { id, name };
            
            const messageElement = document.getElementById('deleteConfirmMessage');
            const modal = document.getElementById('deleteConfirmModal');
            
            console.log('Message element found:', messageElement);
            console.log('Modal element found:', modal);
            
            if (messageElement) {
                messageElement.textContent = `Are you sure you want to delete "${name}"?`;
            }
            
            if (modal) {
                modal.style.display = 'flex';
                modal.style.visibility = 'visible';
                modal.style.opacity = '1';
                modal.style.zIndex = '99999';
                console.log('Delete modal should be visible now');
            } else {
                console.error('Delete modal not found!');
            }
        }

        function closeDeleteConfirmModal() {
            console.log('closeDeleteConfirmModal called');
            document.getElementById('deleteConfirmModal').style.display = 'none';
            // Don't clear currentDeleteData here - it's needed for the final modal
        }

        function confirmDeleteDocument() {
            console.log('confirmDeleteDocument called, currentDeleteData:', currentDeleteData);
            if (currentDeleteData) {
                closeDeleteConfirmModal();
                // Show final confirmation modal
                showFinalDeleteModal();
            }
        }

        function showFinalDeleteModal() {
            console.log('Showing final delete modal');
            if (currentDeleteData) {
                const messageElement = document.getElementById('finalDeleteMessage');
                const modal = document.getElementById('finalDeleteModal');
                
                if (messageElement) {
                    messageElement.textContent = `You are about to PERMANENTLY DELETE "${currentDeleteData.name}"!`;
                }
                
                if (modal) {
                    modal.style.display = 'flex';
                    modal.style.visibility = 'visible';
                    modal.style.opacity = '1';
                    modal.style.zIndex = '99999';
                    console.log('Final delete modal should be visible now');
                } else {
                    console.error('Final delete modal not found!');
                }
            }
        }

        function closeFinalDeleteModal() {
            document.getElementById('finalDeleteModal').style.display = 'none';
            currentDeleteData = null;
        }

        function executeDeleteDocument() {
            console.log('executeDeleteDocument called, currentDeleteData:', currentDeleteData);
            if (currentDeleteData) {
                console.log('Redirecting to delete URL:', `?delete=${currentDeleteData.id}`);
                // Redirect to delete URL
                window.location.href = `?delete=${currentDeleteData.id}`;
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const viewModal = document.getElementById('viewModal');
            const previewModal = document.getElementById('previewModal');
            const uploadSuccessModal = document.getElementById('uploadSuccessModal');
            const editSaveConfirmModal = document.getElementById('editSaveConfirmModal');
            const downloadConfirmModal = document.getElementById('downloadConfirmModal');
            const deleteConfirmModal = document.getElementById('deleteConfirmModal');
            const finalDeleteModal = document.getElementById('finalDeleteModal');
            const cancelEditConfirmModal = document.getElementById('cancelEditConfirmModal');
            const saveConfirmModal = document.getElementById('saveConfirmModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
            if (event.target === previewModal) {
                closePreviewModal();
            }
            if (event.target === uploadSuccessModal) {
                closeUploadSuccessModal();
            }
            if (event.target === editSaveConfirmModal) {
                closeEditSaveConfirmModal();
            }
            if (event.target === downloadConfirmModal) {
                closeDownloadConfirmModal();
            }
            if (event.target === deleteConfirmModal) {
                closeDeleteConfirmModal();
            }
            if (event.target === finalDeleteModal) {
                closeFinalDeleteModal();
            }
            if (event.target === cancelEditConfirmModal) {
                closeCancelEditConfirmModal();
            }
            if (event.target === saveConfirmModal) {
                closeSaveConfirmModal();
            }
        }

        // Cleanup function for page unload
        window.addEventListener('beforeunload', function() {
            // Clean up all stored URLs to free memory
            fileDataStore.forEach((fileData, fileId) => {
                if (fileData.url && fileData.url.startsWith('blob:')) {
                    URL.revokeObjectURL(fileData.url);
                }
            });
            fileDataStore.clear();
        });

        // Category filter function
        function filterByCategory(category) {
            const cards = document.querySelectorAll('.document-card');
            const categories = document.querySelectorAll('.category');
            
            // Remove active class from all categories
            categories.forEach(cat => cat.classList.remove('active'));
            
            // Add active class to clicked category
            event.target.closest('.category').classList.add('active');
            
            // Filter documents
            cards.forEach(card => {
                const cardCategory = card.querySelector('.document-meta div').textContent.split(' | ')[0].trim();
                if (category === 'All Documents' || cardCategory === category) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
        }

        // Search function
        function filterDocuments() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.document-card');
            
            cards.forEach(card => {
                const name = card.querySelector('.document-info h3').textContent.toLowerCase();
                const category = card.querySelector('.document-meta div').textContent.toLowerCase();
                
                if (name.includes(input) || category.includes(input)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Add event listener for search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', filterDocuments);
            }
            
            // Ensure all modals are hidden on page load (failsafe)
            const modals = [
                'saveConfirmModal',
                'cancelEditConfirmModal',
                'editModal',
                'viewModal',
                'previewModal',
                'uploadSuccessModal',
                'editSaveConfirmModal',
                'downloadConfirmModal',
                'deleteConfirmModal',
                'finalDeleteModal'
            ];
            
            modals.forEach(function(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                }
            });
        });

        // ========================================
        // PAGINATION LOGIC
        // ========================================
        
        let currentPage = 1;
        let itemsPerPage = 10;

        function initializePagination() {
            const cards = document.querySelectorAll('.document-card');
            if (cards.length === 0) return;
            
            document.getElementById('documentsPaginationContainer').style.display = 'flex';
            updatePagination(cards);
        }

        function updatePagination(cards = null) {
            if (!cards) cards = document.querySelectorAll('.document-card');
            const totalCards = cards.length;
            const totalPages = Math.ceil(totalCards / itemsPerPage);
            const startItem = (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, totalCards);

            // Update info
            document.getElementById('paginationInfo').textContent = 
                `Showing ${startItem}-${endItem} of ${totalCards} documents`;

            // Generate page numbers
            const numbersDiv = document.getElementById('paginationNumbers');
            numbersDiv.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('div');
                pageBtn.className = 'page-number' + (i === currentPage ? ' active' : '');
                pageBtn.textContent = i;
                pageBtn.onclick = () => goToPage(i);
                numbersDiv.appendChild(pageBtn);
            }

            // Update buttons
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage === totalPages;

            // Show/hide cards
            cards.forEach((card, index) => {
                const cardPage = Math.floor(index / itemsPerPage) + 1;
                card.style.display = (cardPage === currentPage) ? '' : 'none';
            });
        }

        function changePage(direction) {
            const cards = document.querySelectorAll('.document-card');
            const totalPages = Math.ceil(cards.length / itemsPerPage);
            currentPage += direction;
            currentPage = Math.max(1, Math.min(currentPage, totalPages));
            updatePagination(cards);
        }

        function goToPage(page) {
            currentPage = page;
            updatePagination();
        }

        function updateItemsPerPage() {
            itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
            currentPage = 1;
            updatePagination();
        }

        // Initialize pagination on page load
        setTimeout(() => {
            initializePagination();
        }, 500);

    </script>
    <style>
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 18px;
        }
        .action-buttons .btn-primary {
            font-size: 1.08em;
            padding: 10px 22px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .action-buttons .btn-secondary {
            font-size: 1.08em;
            background: #222;
            color: #fff;
            padding: 10px 22px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .action-buttons .search-box {
            position: relative;
            max-width: 220px;
            width: 220px;
            margin-left: 0;
        }
        .action-buttons .search-box input {
            width: 100%;
            padding: 9px 38px 9px 38px;
            border-radius: 7px;
            border: 1px solid #d0d0d0;
            font-size: 1em;
        }
        .action-buttons .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
        }
        .action-buttons .search-box button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #888;
            font-size: 1.1em;
            cursor: pointer;
        }

        .document-categories {
            display: flex;
            flex-direction: row;
            gap: 12px;
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }

        .category {
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex: 1;
            justify-content: center;
        }

        .category.active {
            background-color: var(--secondary-color);
            color: white;
        }

        .badge {
            background-color: #6b7280;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
        }

        .category.active .badge {
            background-color: white;
            color: var(--secondary-color);
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .custom-doc-card {
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(25,118,210,0.08);
            background: #fff;
            padding: 18px 18px 18px 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            min-height: 120px;
        }
        .doc-card-main {
            display: flex;
            align-items: center;
            width: 100%;
        }
        .doc-card-icon {
            font-size: 2.7rem;
            margin-right: 18px;
            color: #1976d2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
        }
        .doc-card-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 2px;
            color: #222;
        }
        .doc-card-form {
            font-size: 1rem;
            color: #444;
            margin-bottom: 8px;
        }
        .doc-card-meta {
            display: flex;
            gap: 16px;
            font-size: 0.95rem;
            color: #555;
            align-items: center;
        }
        .doc-card-meta i {
            margin-right: 4px;
        }
        .doc-card-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-left: auto;
            align-items: center;
        }
        .doc-card-actions .btn-icon i {
            color: #1a2edb !important;
            font-size: 1.5rem;
            transition: color 0.2s;
        }
        .doc-card-actions .btn-icon:hover i {
            color: #0d1a8c !important;
        }
        @media (max-width: 700px) {
            .doc-card-main { flex-direction: column; align-items: flex-start; }
            .doc-card-actions { flex-direction: row; margin-left: 0; margin-top: 10px; }
        }
        .recent-activity.recent-activity-scroll {
            max-height: 340px;
            overflow-y: auto;
            box-shadow: 0 4px 24px rgba(25, 118, 210, 0.08), 0 1.5px 4px rgba(0,0,0,0.04);
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            transition: box-shadow 0.2s;
        }
        .recent-activity.recent-activity-scroll:hover {
            box-shadow: 0 8px 32px rgba(25, 118, 210, 0.13), 0 2px 8px rgba(0,0,0,0.06);
        }
        .recent-activity.recent-activity-scroll::-webkit-scrollbar {
            width: 10px;
            background: #f3f6fa;
            border-radius: 8px;
        }
        .recent-activity.recent-activity-scroll::-webkit-scrollbar-thumb {
            background: #c5d6ee;
            border-radius: 8px;
            border: 2px solid #f3f6fa;
        }
        .recent-activity.recent-activity-scroll::-webkit-scrollbar-thumb:hover {
            background: #90b4e8;
        }
        .recent-activity.recent-activity-scroll table {
            border-collapse: collapse;
            width: 100%;
            min-width: 600px;
        }
        .recent-activity.recent-activity-scroll th, .recent-activity.recent-activity-scroll td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 1em;
        }
        .recent-activity.recent-activity-scroll thead th {
            background: #f8f8f8;
            position: sticky;
            top: 0;
            z-index: 1;
            font-weight: 600;
            color: #1976d2;
            letter-spacing: 0.5px;
        }
        .recent-activity.recent-activity-scroll tbody tr:hover {
            background: #f5faff;
        }
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.45);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(93, 14, 38, 0.3);
            border: 1px solid rgba(93, 14, 38, 0.1);
            overflow: hidden;
            max-width: 90vw;
            max-height: 90vh;
        }

        /* Edit Save Confirmation Modal Styles */
        #editSaveConfirmModal .edit-save-confirm-modal {
            max-width: 350px !important;
            max-height: 90vh !important;
            display: flex !important;
            flex-direction: column !important;
        }

        #editSaveConfirmModal .edit-save-confirm-header {
            padding: 12px 16px !important;
            background: linear-gradient(135deg, #5D0E26, #8B1538) !important;
            color: white !important;
        }

        #editSaveConfirmModal .edit-save-confirm-body {
            padding: 16px !important;
        }

        #editSaveConfirmModal .edit-save-confirm-content {
            text-align: center !important;
        }

        #editSaveConfirmModal .edit-save-confirm-icon i {
            font-size: 2.5rem !important;
            color: #2196f3 !important;
            margin-bottom: 12px !important;
        }

        #editSaveConfirmModal .edit-save-confirm-content h3 {
            color: #2196f3 !important;
            margin-bottom: 8px !important;
            font-size: 1.1rem !important;
        }

        #editSaveConfirmModal .edit-save-confirm-content p {
            color: #666 !important;
            font-size: 0.9rem !important;
            margin-bottom: 0 !important;
        }

        #editSaveConfirmModal .edit-save-confirm-actions {
            padding: 12px 16px !important;
            gap: 12px !important;
        }

        #editSaveConfirmModal .btn-primary {
            background: #8B1538 !important;
            color: white !important;
            border: 1px solid #8B1538 !important;
        }

        #editSaveConfirmModal .btn-primary:hover {
            background: #5D0E26 !important;
            border: 1px solid #5D0E26 !important;
        }

        #editSaveConfirmModal .btn-secondary {
            background: #6c757d !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        #editSaveConfirmModal .btn-secondary:hover {
            background: #545b62 !important;
            border: 1px solid #545b62 !important;
        }

        .edit-modal {
            max-width: 500px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .edit-modal .btn-primary {
            background: #8B1538 !important;
            color: white !important;
            border: 1px solid #8B1538 !important;
        }

        .edit-modal .btn-primary:hover {
            background: #5D0E26 !important;
            border: 1px solid #5D0E26 !important;
        }

        .edit-modal .btn-secondary {
            background: #6c757d !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        /* Download Confirmation Modal Styles */
        #downloadConfirmModal .download-confirm-modal {
            max-width: 380px !important;
            max-height: 90vh !important;
            display: flex !important;
            flex-direction: column !important;
        }

        #downloadConfirmModal .download-confirm-header {
            padding: 12px 16px !important;
            background: linear-gradient(135deg, #8B1538, #5D0E26) !important;
            color: white !important;
        }

        #downloadConfirmModal .download-confirm-body {
            padding: 16px !important;
        }

        #downloadConfirmModal .download-confirm-content {
            text-align: center !important;
        }

        #downloadConfirmModal .download-confirm-icon i {
            font-size: 2.5rem !important;
            color: #8B1538 !important;
            margin-bottom: 12px !important;
        }

        #downloadConfirmModal .download-confirm-content h3 {
            color: #8B1538 !important;
            margin-bottom: 8px !important;
            font-size: 1.1rem !important;
        }

        #downloadConfirmModal .download-confirm-content p {
            color: #666 !important;
            font-size: 0.9rem !important;
            margin-bottom: 12px !important;
        }

        #downloadConfirmModal .download-info {
            background: #f8f9fa !important;
            color: #495057 !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            font-size: 0.85rem !important;
            border: 1px solid #dee2e6 !important;
        }

        #downloadConfirmModal .download-confirm-actions {
            padding: 12px 16px !important;
            gap: 12px !important;
        }

        #downloadConfirmModal .btn-primary {
            background: #8B1538 !important;
            color: white !important;
            border: 1px solid #8B1538 !important;
        }

        #downloadConfirmModal .btn-primary:hover {
            background: #5D0E26 !important;
            border: 1px solid #5D0E26 !important;
        }

        #downloadConfirmModal .btn-secondary {
            background: #6c757d !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        #downloadConfirmModal .btn-secondary:hover {
            background: #545b62 !important;
            border: 1px solid #545b62 !important;
        }

        /* Delete Confirmation Modal Styles */
        #deleteConfirmModal .delete-confirm-modal {
            max-width: 380px !important;
            max-height: 90vh !important;
            display: flex !important;
            flex-direction: column !important;
        }

        #deleteConfirmModal .delete-confirm-header {
            padding: 12px 16px !important;
            background: linear-gradient(135deg, #d32f2f, #f44336) !important;
            color: white !important;
        }

        #deleteConfirmModal .delete-confirm-body {
            padding: 16px !important;
        }

        #deleteConfirmModal .delete-confirm-content {
            text-align: center !important;
        }

        #deleteConfirmModal .delete-confirm-icon i {
            font-size: 2.5rem !important;
            color: #d32f2f !important;
            margin-bottom: 12px !important;
        }

        #deleteConfirmModal .delete-confirm-content h3 {
            color: #d32f2f !important;
            margin-bottom: 8px !important;
            font-size: 1.1rem !important;
        }

        #deleteConfirmModal .delete-confirm-content p {
            color: #666 !important;
            font-size: 0.9rem !important;
            margin-bottom: 12px !important;
        }

        #deleteConfirmModal .delete-warning {
            background: #ffebee !important;
            color: #d32f2f !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            font-size: 0.85rem !important;
            border: 1px solid #ffcdd2 !important;
        }

        #deleteConfirmModal .delete-confirm-actions {
            padding: 12px 16px !important;
            gap: 12px !important;
        }

        #deleteConfirmModal .btn-danger {
            background: #d32f2f !important;
            color: white !important;
            border: 1px solid #d32f2f !important;
        }

        #deleteConfirmModal .btn-danger:hover {
            background: #b71c1c !important;
            border: 1px solid #b71c1c !important;
        }

        #deleteConfirmModal .btn-secondary {
            background: #6c757d !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        #deleteConfirmModal .btn-secondary:hover {
            background: #545b62 !important;
            border: 1px solid #545b62 !important;
        }

        /* Final Delete Confirmation Modal Styles */
        #finalDeleteModal .final-delete-modal {
            max-width: 420px !important;
            max-height: 90vh !important;
            display: flex !important;
            flex-direction: column !important;
        }

        #finalDeleteModal .final-delete-header {
            padding: 12px 16px !important;
            background: linear-gradient(135deg, #d32f2f, #f44336) !important;
            color: white !important;
        }

        #finalDeleteModal .final-delete-body {
            padding: 16px !important;
        }

        #finalDeleteModal .final-delete-content {
            text-align: center !important;
        }

        #finalDeleteModal .final-delete-icon i {
            font-size: 3rem !important;
            color: #d32f2f !important;
            margin-bottom: 12px !important;
        }

        #finalDeleteModal .final-delete-content h3 {
            color: #d32f2f !important;
            margin-bottom: 8px !important;
            font-size: 1.2rem !important;
        }

        #finalDeleteModal .final-delete-content p {
            color: #666 !important;
            font-size: 0.9rem !important;
            margin-bottom: 12px !important;
        }

        #finalDeleteModal .final-delete-warning {
            background: #ffebee !important;
            color: #d32f2f !important;
            padding: 10px 12px !important;
            border-radius: 6px !important;
            font-size: 0.9rem !important;
            border: 1px solid #ffcdd2 !important;
            margin-bottom: 16px !important;
        }

        #finalDeleteModal .final-delete-actions {
            padding: 12px 16px !important;
            gap: 12px !important;
        }

        #finalDeleteModal .btn-danger {
            background: #d32f2f !important;
            color: white !important;
            border: 1px solid #d32f2f !important;
        }

        #finalDeleteModal .btn-danger:hover {
            background: #b71c1c !important;
            border: 1px solid #b71c1c !important;
        }

        #finalDeleteModal .btn-secondary {
            background: #6c757d !important;
            color: white !important;
            border: 1px solid #6c757d !important;
        }

        #finalDeleteModal .btn-secondary:hover {
            background: #545b62 !important;
            border: 1px solid #545b62 !important;
        }

        /* Upload Success Modal - Very Small with High Specificity */
        #uploadSuccessModal .upload-success-modal {
            max-width: 320px !important;
            max-height: 200px !important;
            display: flex !important;
            flex-direction: column !important;
        }

        #uploadSuccessModal .upload-success-header {
            padding: 8px 12px !important;
            background: linear-gradient(135deg, #5D0E26, #8B1538) !important;
            color: white !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
        }

        #uploadSuccessModal .upload-success-header h2 {
            margin: 0 !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }

        #uploadSuccessModal .upload-success-body {
            padding: 8px !important;
            background: white !important;
            flex: 1 !important;
            overflow-y: auto !important;
        }

        #uploadSuccessModal .upload-success-content {
            text-align: center !important;
            padding: 4px 0 !important;
        }

        #uploadSuccessModal .upload-success-icon {
            margin-bottom: 4px !important;
        }

        #uploadSuccessModal .upload-success-icon i {
            font-size: 1.5rem !important;
            color: #4caf50 !important;
        }

        #uploadSuccessModal .upload-success-content h3 {
            color: #4caf50 !important;
            margin-bottom: 2px !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
        }

        #uploadSuccessModal .upload-success-content p {
            color: #666 !important;
            font-size: 0.75rem !important;
            line-height: 1.1 !important;
        }

        #uploadSuccessModal .upload-success-actions {
            padding: 6px 12px !important;
            margin-top: 4px !important;
            border-top: 1px solid #f0f0f0 !important;
            background: #fafbfc !important;
            display: flex !important;
            gap: 8px !important;
            justify-content: flex-end !important;
            align-items: center !important;
        }

        #uploadSuccessModal .upload-success-actions .btn {
            padding: 6px 12px !important;
            font-size: 0.8rem !important;
            min-width: 60px !important;
            height: 32px !important;
        }

        .modal-header {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 24px;
            background: white;
            flex: 1;
            overflow-y: auto;
        }

        .modern-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #5D0E26;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .form-group input,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5D0E26;
            background: white;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }

        .form-group input:hover,
        .form-group select:hover {
            border-color: #8B1538;
        }

        .modal-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            align-items: center;
            margin-top: 20px;
            padding: 20px 24px;
            border-top: 1px solid #f0f0f0;
            background: #fafbfc;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
            height: 44px;
            text-decoration: none;
        }

        .btn-secondary {
            background: white !important;
            color: #6c757d !important;
            border: 1px solid #e0e0e0 !important;
        }

        .btn-secondary:hover {
            background: #f8f9fa !important;
            color: #495057 !important;
            border-color: #d0d0d0 !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4A0B1E, #6B0F2A);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(93, 14, 38, 0.4);
        }

        .modern-modal {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            padding: 22px 18px 18px 18px;
            min-width: 0;
            max-width: 400px;
            width: 100%;
            position: relative;
            animation: modalPop 0.2s;
            margin: 0 auto;
        }
        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .close-modal-btn {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 1.7rem;
            color: #888;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 2;
        }
        .close-modal-btn:hover {
            color: #d32f2f;
        }
        .alert-error {
            background: #ffeaea;
            color: #d32f2f;
            border: 1px solid #d32f2f;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .alert-error i {
            font-size: 1.2em;
        }
        .alert-success {
            background: #eaffea;
            color: #388e3c;
            border: 1px solid #388e3c;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .alert-success i {
            font-size: 1.2em;
        }
        @media (max-width: 600px) {
            .modern-modal {
                padding: 12px 4vw 12px 4vw;
                max-width: 95vw;
            }
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1em;
            gap: 7px;
            text-align: center;
            vertical-align: middle;
            box-sizing: border-box;
        }
        .status-badge i {
            font-size: 1.1em;
            margin-right: 6px;
            vertical-align: middle;
        }

        /* Upload Section Styles */
        .upload-section {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .upload-section h2 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            color: #5D0E26;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 100px;
        }

        .upload-area:hover {
            border-color: #5D0E26;
            background: #fef2f2;
        }

        .file-preview {
            margin-top: 20px;
            display: none;
        }

        .file-preview h4 {
            margin-bottom: 15px;
            color: #374151;
        }

        .preview-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 12px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background: #1976d2;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.4);
        }

        /* Document Grid Styles */
        .document-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .document-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .document-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .document-grid {
                grid-template-columns: 1fr;
            }
        }

        .document-card {
            background: #fff;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .document-card .card-header {
            display: flex !important;
            align-items: center !important;
            margin-bottom: 12px !important;
            justify-content: flex-start !important;
        }

        .document-card .document-icon {
            width: 45px !important;
            height: 45px !important;
            margin-right: 8px !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .document-icon i {
            font-size: 20px;
        }

        .document-info {
            min-height: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .document-info h3 {
            margin: 0 0 3px 0;
            font-size: 0.95rem;
            height: 1.2em;
            line-height: 1.2em;
            display: flex;
            align-items: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .document-meta {
            font-size: 0.8rem;
            height: 18px;
            display: flex;
            align-items: center;
            color: #6b7280;
        }

        .document-actions {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
        }

        .btn-action {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-view {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .btn-view:hover {
            background: #bbdefb;
            border: 1px solid #bbdefb;
        }

        .btn-edit {
            background: #fff3e0;
            color: #f57c00;
            border: none;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #ffe0b2;
        }

        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }

        .btn-delete:hover {
            background: #ffcdd2;
            border: 1px solid #ffcdd2;
        }

        /* Preview Modal Close Button */
        .close-modal-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            font-size: 18px;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .close-modal-btn:hover {
            background: rgba(0,0,0,0.9);
            transform: scale(1.1);
        }

        .document-categories {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            align-items: center;
        }

        .category {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            height: 44px;
            box-sizing: border-box;
        }

        .category:hover {
            background: #5D0E26;
            color: white;
            border-color: #5D0E26;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }

        .category:hover .badge {
            background: white;
            color: #5D0E26;
        }

        .category.active {
            background: #5D0E26;
            color: white;
            border-color: #5D0E26;
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }

        .category .badge {
            background: rgba(255,255,255,0.2);
            color: inherit;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .category.active .badge {
            background: white;
            color: #5D0E26;
        }

        .search-box {
            position: relative;
            min-width: 300px;
            max-width: 400px;
            height: 44px;
            margin-left: auto;
        }

        .search-box input {
            width: 100%;
            height: 44px;
            padding: 0 40px 0 40px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 0.9rem;
            background: #f9fafb;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .search-box input:focus {
            outline: none;
            border-color: #5D0E26;
            background: white;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 1rem;
            z-index: 1;
        }

        .search-box button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
            z-index: 1;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-box button:hover {
            background: #5D0E26;
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .document-categories {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                margin-left: 0;
                min-width: auto;
                max-width: none;
            }
        }

        /* Edit Modal Confirmation Modals - Higher z-index */
        #saveConfirmModal,
        #cancelEditConfirmModal {
            z-index: 100001 !important;
        }

        .modal-content.confirmation-modal {
            max-width: 480px !important;
            width: 90% !important;
            border-radius: 12px !important;
            box-shadow: 0 20px 60px rgba(93, 14, 38, 0.3) !important;
            animation: modalSlideIn 0.3s ease-out !important;
            margin: 10% auto !important;
            padding: 0 !important;
            z-index: 100001 !important;
        }

        .confirmation-modal .confirmation-content {
            text-align: center !important;
            padding: 30px 20px !important;
        }

        .confirmation-modal .confirmation-icon {
            width: 60px !important;
            height: 60px !important;
            margin: 0 auto 15px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.5rem !important;
            color: white !important;
        }

        .confirmation-modal .confirmation-icon.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3) !important;
        }

        .confirmation-modal .confirmation-content h3 {
            margin: 0 0 10px 0 !important;
            color: #333 !important;
            font-size: 1.3rem !important;
            font-weight: 600 !important;
        }

        .confirmation-modal .confirmation-content p {
            margin: 0 !important;
            color: #666 !important;
            font-size: 0.95rem !important;
            line-height: 1.5 !important;
        }

        .confirmation-modal .modal-actions {
            display: flex !important;
            flex-direction: row !important;
            gap: 15px !important;
            padding: 0 20px 20px 20px !important;
            justify-content: center !important;
            align-items: center !important;
        }

        .confirmation-modal .modal-actions .btn {
            flex: 1 !important;
            max-width: 220px !important;
            padding: 14px 28px !important;
            border-radius: 8px !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            white-space: nowrap !important;
        }

        .confirmation-modal .btn-secondary {
            background: #6c757d !important;
            color: white !important;
        }

        .confirmation-modal .btn-secondary:hover {
            background: #5a6268 !important;
            transform: translateY(-2px) !important;
        }

        .confirmation-modal .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            color: white !important;
        }

        .confirmation-modal .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%) !important;
            transform: translateY(-2px) !important;
        }

        .confirmation-modal .btn-primary {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%) !important;
            color: white !important;
        }

        .confirmation-modal .btn-primary:hover {
            background: linear-gradient(135deg, #8B1538 0%, #5D0E26 100%) !important;
            transform: translateY(-2px) !important;
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.75rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }
        
        .pagination-bottom {
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .pagination-info {
            text-align: center;
            color: #5a6c7d;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
            font-size: 0.9rem;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #8B1538 0%, #5D0E26 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.4);
        }
        
        .pagination-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .pagination-numbers {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .page-number {
            background: white;
            color: #5D0E26;
            border: 2px solid #e9ecef;
            padding: 0.5rem 0.8rem;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .page-number:hover {
            background: #f8f9fa;
            border-color: #5D0E26;
            transform: translateY(-1px);
        }
        
        .page-number.active {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            border-color: #5D0E26;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.3);
        }
        
        .pagination-settings {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .pagination-settings label {
            color: #5a6c7d;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .pagination-settings select {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 4px;
            padding: 0.5rem 0.8rem;
            color: #5D0E26;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .pagination-settings select:focus {
            outline: none;
            border-color: #5D0E26;
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
        }
    </style>
</body>
</html> 