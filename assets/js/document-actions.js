// Document Actions Functions (Save/View)
// This file contains functions for saving and viewing documents

// Save Affidavit Loss function (Local save only - no database submission)
function saveAffidavitLoss() {
    const form = document.getElementById('affidavitLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        specifyItemLost: formData.get('specifyItemLost'),
        itemLost: formData.get('itemLost'),
        itemDetails: formData.get('itemDetails'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.specifyItemLost || !data.itemLost || !data.itemDetails || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="saveAffidavitLoss()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Document saved locally! Click "View" to preview and "Send" to submit to employee.');
    }, 1000);
}

// View Affidavit Loss function
function viewAffidavitLoss() {
    const form = document.getElementById('affidavitLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        specifyItemLost: formData.get('specifyItemLost'),
        itemLost: formData.get('itemLost'),
        itemDetails: formData.get('itemDetails'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.specifyItemLost || !data.itemLost || !data.itemDetails || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewAffidavitLoss()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_loss.php', 'affidavitLossForm');
        window.currentFormType = 'affidavitLoss';
    }, 1500);
}

// Save Solo Parent function (Local save only - no database submission)
function saveSoloParent() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        childrenNames: formData.get('childrenNames'),
        yearsUnderCase: formData.get('yearsUnderCase'),
        reasonSection: formData.get('reasonSection'),
        otherReason: formData.get('otherReason'),
        employmentStatus: formData.get('employmentStatus'),
        employeeAmount: formData.get('employeeAmount'),
        selfEmployedAmount: formData.get('selfEmployedAmount'),
        unemployedDependent: formData.get('unemployedDependent'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.childrenNames || !data.yearsUnderCase || !data.reasonSection || !data.employmentStatus || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="saveSoloParent()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Document saved locally! Click "View" to preview and "Send" to submit to employee.');
    }, 1000);
}

// View Solo Parent function
function viewSoloParent() {
    const form = document.getElementById('soloParentForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        completeAddress: formData.get('completeAddress'),
        childrenNames: formData.get('childrenNames'),
        yearsUnderCase: formData.get('yearsUnderCase'),
        reasonSection: formData.get('reasonSection'),
        otherReason: formData.get('otherReason'),
        employmentStatus: formData.get('employmentStatus'),
        employeeAmount: formData.get('employeeAmount'),
        selfEmployedAmount: formData.get('selfEmployedAmount'),
        unemployedDependent: formData.get('unemployedDependent'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.completeAddress || !data.childrenNames || !data.yearsUnderCase || !data.reasonSection || !data.employmentStatus || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewSoloParent()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_solo_parent.php', 'soloParentForm');
        window.currentFormType = 'soloParent';
    }, 1500);
}

// Save PWD Loss function (Local save only - no database submission)
function savePWDLoss() {
    const form = document.getElementById('pwdLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="savePWDLoss()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Document saved locally! Click "View" to preview and "Send" to submit to employee.');
    }, 1000);
}

// View PWD Loss function
function viewPWDLoss() {
    const form = document.getElementById('pwdLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewPWDLoss()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_loss_pwd_id.php', 'pwdLossForm');
        window.currentFormType = 'pwdLoss';
    }, 1500);
}

// Save Boticab Loss function (Local save only - no database submission)
function saveBoticabLoss() {
    const form = document.getElementById('boticabLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state and prevent double-clicking
    const saveBtn = document.querySelector('button[onclick="saveBoticabLoss()"]');
    if (saveBtn.disabled) {
        return; // Already processing
    }
    
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Simulate local save (no server call)
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Document saved locally! Click "View" to preview and "Send" to submit to employee.');
    }, 1000);
}

// View Boticab Loss function
function viewBoticabLoss() {
    const form = document.getElementById('boticabLossForm');
    const formData = new FormData(form);
    const data = {
        fullName: formData.get('fullName'),
        fullAddress: formData.get('fullAddress'),
        detailsOfLoss: formData.get('detailsOfLoss'),
        dateOfNotary: formData.get('dateOfNotary')
    };

    // Validate required fields
    if (!data.fullName || !data.fullAddress || !data.detailsOfLoss || !data.dateOfNotary) {
        alert('Please fill in all required fields.');
        return;
    }

    // Show loading state
    const viewBtn = document.querySelector('button[onclick="viewBoticabLoss()"]');
    const originalText = viewBtn.innerHTML;
    viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    viewBtn.disabled = true;

    // Simulate viewing (replace with actual API call)
    setTimeout(() => {
        viewBtn.innerHTML = originalText;
        viewBtn.disabled = false;
        
        // Open document in modal for viewing only (no download)
        openDocumentViewer('files-generation/generate_affidavit_of_loss_boticab.php', 'boticabLossForm');
        window.currentFormType = 'boticabLoss';
    }, 1500);
}
