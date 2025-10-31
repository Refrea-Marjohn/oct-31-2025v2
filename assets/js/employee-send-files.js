// Employee Send Files JavaScript Functions
// This file contains all JavaScript functions for employee_send_files.php

// Profile Dropdown Functions
function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('show');
}

function editProfile() {
    alert('Profile editing functionality will be implemented.');
}

// File Management Functions
function viewDocumentData(fileId, documentType) {
    // Fetch document data and display in modal
    fetch('employee_send_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_document_data&file_id=' + fileId
    })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            showDocumentDataModal(result.data, documentType);
        } else {
            alert('Error loading document data: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading document data. Please try again.');
    });
}

function generatePDF(fileId, documentType) {
    if (confirm('Generate and download PDF from this document data?')) {
        // Show loading state
        const generateBtn = event.target;
        const originalText = generateBtn.innerHTML;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        generateBtn.disabled = true;
        
        console.log('Generating PDF for file ID:', fileId, 'Document Type:', documentType);
        console.log('Form will be submitted to:', 'employee_send_files.php');
        
        // Create form to submit PDF generation request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'employee_send_files.php';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'generate_pdf_download';
        
        const fileIdInput = document.createElement('input');
        fileIdInput.type = 'hidden';
        fileIdInput.name = 'file_id';
        fileIdInput.value = fileId;
        
        const documentTypeInput = document.createElement('input');
        documentTypeInput.type = 'hidden';
        documentTypeInput.name = 'document_type';
        documentTypeInput.value = documentType;
        
        form.appendChild(actionInput);
        form.appendChild(fileIdInput);
        form.appendChild(documentTypeInput);
        document.body.appendChild(form);
        
        console.log('Form created with data:', {
            action: 'generate_pdf_download',
            file_id: fileId,
            document_type: documentType
        });
        
        // Submit form to trigger PDF download
        form.submit();
        
        // Clean up and restore button
        setTimeout(() => {
            if (document.body.contains(form)) {
                document.body.removeChild(form);
            }
            generateBtn.innerHTML = originalText;
            generateBtn.disabled = false;
        }, 3000);
    }
}

function downloadPDF(fileId) {
    // Show loading state
    const downloadBtn = event.target;
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
    downloadBtn.disabled = true;
    
    // Create form to submit download request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'employee_send_files.php';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'download_pdf';
    
    const fileIdInput = document.createElement('input');
    fileIdInput.type = 'hidden';
    fileIdInput.name = 'file_id';
    fileIdInput.value = fileId;
    
    form.appendChild(actionInput);
    form.appendChild(fileIdInput);
    document.body.appendChild(form);
    
    // Submit form to trigger download
    form.submit();
    
    // Clean up
    setTimeout(() => {
        document.body.removeChild(form);
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
    }, 2000);
}

function updateDocumentStatus(fileId, status) {
    const action = status === 'approved' ? 'approve' : 'reject';
    const message = status === 'approved' ? 'approve' : 'reject';
    const actionText = status === 'approved' ? 'Approve' : 'Reject';
    
    if (confirm(`Are you sure you want to ${message} this document?`)) {
        // Show loading state on the button
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        button.disabled = true;
        
        let body = 'action=' + action + '_document&file_id=' + fileId;
        
        if (status === 'rejected') {
            const reason = prompt('Please provide a reason for rejection:');
            if (!reason || reason.trim() === '') {
                alert('Rejection reason is required.');
                button.innerHTML = originalText;
                button.disabled = false;
                return;
            }
            body += '&reason=' + encodeURIComponent(reason);
        }
        
        fetch('employee_send_files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                // Show success message
                showNotification(`Document ${message}d successfully!`, 'success');
                
                // Refresh the page after a short delay to show updated status
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                alert(`Error ${message}ing document: ` + result.message);
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(`Error ${message}ing document. Please try again.`);
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
}

function deleteDocument(fileId) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone and will permanently remove the document from the system.')) {
        // Show loading state on the button
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        button.disabled = true;
        
        fetch('employee_send_files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete_document&file_id=' + fileId
        })
        .then(response => {
            console.log('Delete response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            console.log('Delete result:', result);
            if (result.status === 'success') {
                // Show success message
                showNotification('Document deleted successfully!', 'success');
                
                // Remove the document card from the UI
                const fileCard = button.closest('.file-card');
                if (fileCard) {
                    fileCard.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => {
                        fileCard.remove();
                    }, 300);
                }
                
                // Refresh the page after a short delay to update statistics
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                console.error('Delete error:', result);
                alert('Error deleting document: ' + result.message);
                if (result.debug_info) {
                    console.log('Debug info:', result.debug_info);
                }
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Delete fetch error:', error);
            alert('Error deleting document. Please try again. Check console for details.');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

function showDocumentDataModal(data, documentType) {
    // Create modal to display document in proper format
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'block';
    
    // Generate document HTML based on type
    let documentHTML = generateDocumentPreviewHTML(documentType, data);
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-alt"></i> Document Preview - ${getDocumentTypeName(documentType)}</h2>
                <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div style="background: white; padding: 40px; border-radius: 8px; font-family: 'Times New Roman', serif; font-size: 13pt; line-height: 1.7; min-height: 800px;">
                    ${documentHTML}
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

function getDocumentTypeName(documentType) {
    const typeNames = {
        'affidavitLoss': 'Affidavit of Loss',
        'soloParent': 'Sworn Affidavit of Solo Parent',
        'pwdLoss': 'Affidavit of Loss (PWD ID)',
        'boticabLoss': 'Affidavit of Loss (Boticab Booklet/ID)'
    };
    return typeNames[documentType] || documentType;
}

function generateDocumentPreviewHTML(documentType, data) {
    switch (documentType) {
        case 'affidavitLoss':
            return generateAffidavitLossPreview(data);
        case 'soloParent':
            return generateSoloParentPreview(data);
        case 'pwdLoss':
            return generatePWDLossPreview(data);
        case 'boticabLoss':
            return generateBoticabLossPreview(data);
        default:
            return '<p>Document type not supported</p>';
    }
}

function generateAffidavitLossPreview(data) {
    const fullName = data.fullName || '[FULL NAME]';
    const completeAddress = data.completeAddress || '[COMPLETE ADDRESS]';
    const specifyItemLost = data.specifyItemLost || '[SPECIFY ITEM LOST]';
    const itemLost = data.itemLost || '[ITEM LOST]';
    const itemDetails = data.itemDetails || '[ITEM DETAILS]';
    const dateOfNotary = data.dateOfNotary || '[DATE OF NOTARY]';
    
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-left: 150px; margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            <br/>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
                AFFIDAVIT OF LOSS
            </div>
            <br/>
            
            <div style="margin-left: 150px; text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
                post office address at <u><b>${completeAddress}</b></u>, after <br/>
                being duly sworn in accordance with law hereby depose and say that:
            </div>
            <br/>
            
            <div style="margin-left: 150px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That &nbsp;&nbsp;&nbsp; I &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; am &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; the &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; true &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and lawful owner/possessor of <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${specifyItemLost}</b></u>;<br>
            </div>
            <br/>    
            
            <div style="margin-left: 150px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That unfortunately the said <u><b>${itemLost}</b></u> was lost under the following<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; circumstance:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${itemDetails}</b></u><br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ________________________________________________________________;
            </div>
            <br/>
            
            <div style="margin-left: 150px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;&nbsp;Despite diligent effort to search for the missing item, the same can no longer <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;be found;
            </div>
            <br/>
            
            <div style="margin-left: 150px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;&nbsp;I am executing this affidavit to attest the truth of the foregoing facts and<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;for whatever intents it may serve in accordance with law.
            </div>
            <br/>
            <br>
            
            <div style="margin-left: 150px; text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, &nbsp; I &nbsp; have &nbsp; hereunto &nbsp; set my hand this ________ day of<br/>
                <u><b>${dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
            </div>
            
            <br/>
            <div style="margin-left: 150px; text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <br/>
            <div style="margin-left: 150px; text-align:justify; margin-bottom:15px;">
SUBSCRIBED AND SWORN TO before me this date above mentioned at the City of <br>
Cabuyao, Laguna, affiant exhibiting to me his/her respective proofs of identity, <br>
indicated below their names personally attesting that the foregoing statements is true <br>
to their best of knowledge and belief.
            </div>
            
            <br/>
            <div style="margin-left: 150px; text-align:left;">
            Doc. No._______<br/>
            Page No._______<br/>
            Book No._______<br/>
            Series of _______
            </div>
        </div>
    `;
}

function generateSoloParentPreview(data) {
    const fullName = data.fullName || '[FULL NAME]';
    const completeAddress = data.completeAddress || '[COMPLETE ADDRESS]';
    const childrenNames = data.childrenNames || '[CHILDREN NAMES]';
    const yearsUnderCase = data.yearsUnderCase || '[YEARS]';
    const reasonSection = data.reasonSection || '[REASON]';
    const employmentStatus = data.employmentStatus || '[EMPLOYMENT STATUS]';
    const dateOfNotary = data.dateOfNotary || '[DATE OF NOTARY]';
    
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold;">
                SWORN AFFIDAVIT OF SOLO PARENT
            </div>
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; post office address at <u><b>${completeAddress}</b></u>, after <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; being duly sworn in accordance with law hereby depose and say that:
            </div>
            <br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That I am a solo parent with the following children: <u><b>${childrenNames}</b></u>;<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That I have been a solo parent for <u><b>${yearsUnderCase}</b></u>;<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;That the reason for being a solo parent is: <u><b>${reasonSection}</b></u>;<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;That my employment status is: <u><b>${employmentStatus}</b></u>;<br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, &nbsp; I &nbsp; have &nbsp; hereunto &nbsp; set my hand this ________ day of<br/>
                <u><b>${dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
            </div>
            
            <br/>
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
        </div>
    `;
}

function generatePWDLossPreview(data) {
    const fullName = data.fullName || '[FULL NAME]';
    const fullAddress = data.fullAddress || '[FULL ADDRESS]';
    const detailsOfLoss = data.detailsOfLoss || '[DETAILS OF LOSS]';
    const dateOfNotary = data.dateOfNotary || '[DATE OF NOTARY]';
    
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="margin-top:10px;">
                REPUBLIC OF THE PHILIPPINES)<br/>&nbsp;
                PROVINCE OF LAGUNA;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>S.S</b><br/>&nbsp;
                CITY OF CABUYAO&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)<br/>
            </div>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold; margin-top:-15px 0;">
                AFFIDAVIT OF LOSS
            </div>
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; post office address at <u><b>${fullAddress}</b></u>, after <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; being duly sworn in accordance with law hereby depose and say that:
            </div>
            <br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That I am the lawful holder of a Person with Disability (PWD) ID Card issued<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; by the appropriate government agency;
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That the said PWD ID Card was lost under the following circumstances:<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${detailsOfLoss}</b></u><br>
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3. &nbsp;That I have exerted diligent efforts to locate and recover the said PWD ID<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Card but to no avail;
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4. &nbsp;That I am executing this affidavit to attest the truth of the foregoing facts<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; and for whatever intents it may serve in accordance with law;
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5. &nbsp;That I am executing this affidavit to support my application for the<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; replacement of my lost PWD ID Card.
            </div>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, I have hereunto set my hand this<br/>
                <u><b>${dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
            </div>
            
            <br/>
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
            
            <br/>
            <div style="text-align:justify; margin-bottom:15px;">
                SUBSCRIBED AND SWORN TO before me this date above mentioned at the City of <br>
                Cabuyao, Laguna, affiant exhibiting to me his/her respective proofs of identity, <br>
                indicated below their names personally attesting that the foregoing statements is true <br>
                to their best of knowledge and belief.
            </div>
            
            <br/>
            <div style="text-align:left; margin-left: -5px;">
                Doc. No._______<br/>
                Page No._______<br/>
                Book No._______<br/>
                Series of _______
            </div>
        </div>
    `;
}

function generateBoticabLossPreview(data) {
    const fullName = data.fullName || '[FULL NAME]';
    const fullAddress = data.fullAddress || '[FULL ADDRESS]';
    const detailsOfLoss = data.detailsOfLoss || '[DETAILS OF LOSS]';
    const dateOfNotary = data.dateOfNotary || '[DATE OF NOTARY]';
    
    return `
        <div style="font-size:11pt; line-height:1.2;">
            <br/>
            
            <div style="text-align:center; font-size:12pt; font-weight:bold;">
                AFFIDAVIT OF LOSS (BOTICAB BOOKLET/ID)
            </div>
            <br/>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I, <u><b>${fullName}</b></u>, Filipino, of legal age, and with residence and <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; post office address at <u><b>${fullAddress}</b></u>, after <br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; being duly sworn in accordance with law hereby depose and say that:
            </div>
            <br>
            
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1. &nbsp;That I am the lawful owner of a Boticab Booklet/ID;<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2. &nbsp;That the said Boticab Booklet/ID was lost under the following circumstances:<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <u><b>${detailsOfLoss}</b></u><br>
            
            <div style="text-align:justify; margin-bottom:15px;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;IN WITNESS WHEREOF, &nbsp; I &nbsp; have &nbsp; hereunto &nbsp; set my hand this ________ day of<br/>
                <u><b>${dateOfNotary}</b></u>, in the City of Cabuyao, Laguna.
            </div>
            
            <br/>
            <div style="text-align:center; margin:15px 0;">
                <u><b>${fullName}</b></u><br/>
                <b>AFFIANT</b>
            </div>
        </div>
    `;
}

// Search and Filter Functions
function filterFiles() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const fileCards = document.querySelectorAll('.file-card');

    fileCards.forEach(card => {
        const clientName = card.querySelector('h3').textContent.toLowerCase();
        const clientEmail = card.querySelector('.client-email').textContent.toLowerCase();
        const requestId = card.querySelector('.request-id').textContent.toLowerCase();
        const documentType = card.querySelector('.document-type') ? card.querySelector('.document-type').textContent.toLowerCase() : '';
        const fileStatus = card.getAttribute('data-status').toLowerCase();

        const matchesSearch = clientName.includes(searchTerm) || 
                            clientEmail.includes(searchTerm) || 
                            requestId.includes(searchTerm);
        const matchesType = !typeFilter || documentType.includes(typeFilter);
        const matchesStatus = !statusFilter || fileStatus.includes(statusFilter);

        if (matchesSearch && matchesType && matchesStatus) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('img') && !event.target.closest('.profile-dropdown')) {
        const dropdowns = document.getElementsByClassName('profile-dropdown-content');
        for (let dropdown of dropdowns) {
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    }
}

// Expandable Submenu Functionality
document.addEventListener('DOMContentLoaded', function() {
    const submenuToggle = document.querySelector('.submenu-toggle');
    const hasSubmenu = document.querySelector('.has-submenu');
    
    if (submenuToggle && hasSubmenu) {
        submenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            hasSubmenu.classList.toggle('active');
        });
    }
    
    // Auto-filter to show pending documents on page load
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.value = 'pending';
        filterFiles();
    }
});
