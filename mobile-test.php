<?php
session_start();
// Simulate session for testing
$_SESSION['user_id'] = 1;
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'User';
$_SESSION['role'] = 'admin';

include 'includes/header.php';
?>

<div class="container-fluid px-2 px-md-4">
    <div class="mobile-header d-block d-md-none mb-3">
        <div class="d-flex align-items-center justify-content-between">
            <h1 class="h4 mb-0">Mobile Test</h1>
            <span class="badge bg-success">Responsive</span>
        </div>
    </div>
    <h1 class="mt-4 d-none d-md-block">Mobile Responsiveness Test</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Mobile Test</li>
    </ol>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Responsive Test:</strong> Resize your browser window or view on mobile to see the improvements!
        Current screen width: <span id="screen-width"></span>px
    </div>
    
    <!-- Test Card with Tabs -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="nav-tabs-wrapper">
                <ul class="nav nav-tabs card-header-tabs" id="testTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general">
                            <i class="fas fa-cog me-1 d-none d-sm-inline"></i> 
                            <span class="d-none d-sm-inline">General Settings</span>
                            <span class="d-sm-none">General</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="forms-tab" data-bs-toggle="tab" data-bs-target="#forms">
                            <i class="fas fa-wpforms me-1 d-none d-sm-inline"></i> 
                            <span class="d-none d-sm-inline">Form Examples</span>
                            <span class="d-sm-none">Forms</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tables-tab" data-bs-toggle="tab" data-bs-target="#tables">
                            <i class="fas fa-table me-1 d-none d-sm-inline"></i> 
                            <span class="d-none d-sm-inline">Table Examples</span>
                            <span class="d-sm-none">Tables</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="buttons-tab" data-bs-toggle="tab" data-bs-target="#buttons">
                            <i class="fas fa-mouse-pointer me-1 d-none d-sm-inline"></i> 
                            <span class="d-none d-sm-inline">Button Examples</span>
                            <span class="d-sm-none">Buttons</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- General Tab -->
                <div class="tab-pane fade show active" id="general">
                    <h5>Responsive Form Layout</h5>
                    <form>
                        <div class="row mb-3">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Institution Name</label>
                                <input type="text" class="form-control mobile-input" value="Test College" placeholder="Enter institution name">
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control mobile-input" value="admin@test.edu" placeholder="Enter email">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select">
                                    <option>Computer Science</option>
                                    <option>Mathematics</option>
                                    <option>Physics</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select">
                                    <option>Active</option>
                                    <option>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 d-grid d-md-block">
                            <button type="submit" class="btn btn-primary me-md-2 mb-2 mb-md-0">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                            <button type="button" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Forms Tab -->
                <div class="tab-pane fade" id="forms">
                    <h5>Mobile-Optimized Form Elements</h5>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Text Input</label>
                            <input type="text" class="form-control" placeholder="Touch-friendly input">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Select Dropdown</label>
                            <select class="form-select">
                                <option>Option 1</option>
                                <option>Option 2</option>
                                <option>Option 3</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Textarea</label>
                            <textarea class="form-control" rows="3" placeholder="Large touch target"></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="switch1">
                                <label class="form-check-label" for="switch1">Enable notifications</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tables Tab -->
                <div class="tab-pane fade" id="tables">
                    <h5>Responsive Table</h5>
                    <div class="table-responsive mobile-table-wrapper">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td data-label="ID">1</td>
                                    <td data-label="Name">John Doe</td>
                                    <td data-label="Email">john@test.edu</td>
                                    <td data-label="Department">Computer Science</td>
                                    <td data-label="Status"><span class="badge bg-success">Active</span></td>
                                    <td data-label="Actions">
                                        <div class="btn-group-vertical btn-group-sm d-md-none">
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </div>
                                        <div class="btn-group d-none d-md-flex">
                                            <button class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td data-label="ID">2</td>
                                    <td data-label="Name">Jane Smith</td>
                                    <td data-label="Email">jane@test.edu</td>
                                    <td data-label="Department">Mathematics</td>
                                    <td data-label="Status"><span class="badge bg-warning">Pending</span></td>
                                    <td data-label="Actions">
                                        <div class="btn-group-vertical btn-group-sm d-md-none">
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </div>
                                        <div class="btn-group d-none d-md-flex">
                                            <button class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Buttons Tab -->
                <div class="tab-pane fade" id="buttons">
                    <h5>Touch-Friendly Buttons</h5>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <h6>Primary Actions</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                                <button class="btn btn-success">
                                    <i class="fas fa-check me-2"></i> Approve
                                </button>
                                <button class="btn btn-warning">
                                    <i class="fas fa-edit me-2"></i> Edit
                                </button>
                                <button class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <h6>Secondary Actions</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-2"></i> View Details
                                </button>
                                <button class="btn btn-outline-secondary">
                                    <i class="fas fa-download me-2"></i> Download
                                </button>
                                <button class="btn btn-outline-info">
                                    <i class="fas fa-share me-2"></i> Share
                                </button>
                                <button class="btn btn-outline-dark">
                                    <i class="fas fa-print me-2"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Device Info Card -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-mobile-alt me-2"></i> Device Information
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-md-6">
                    <p><strong>Screen Width:</strong> <span id="width-display"></span>px</p>
                    <p><strong>Screen Height:</strong> <span id="height-display"></span>px</p>
                    <p><strong>Device Type:</strong> <span id="device-type"></span></p>
                </div>
                <div class="col-12 col-md-6">
                    <p><strong>User Agent:</strong> <small id="user-agent"></small></p>
                    <p><strong>Touch Support:</strong> <span id="touch-support"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update device information
function updateDeviceInfo() {
    document.getElementById('screen-width').textContent = window.innerWidth;
    document.getElementById('width-display').textContent = window.innerWidth;
    document.getElementById('height-display').textContent = window.innerHeight;
    document.getElementById('user-agent').textContent = navigator.userAgent;
    document.getElementById('touch-support').textContent = 'ontouchstart' in window ? 'Yes' : 'No';
    
    let deviceType = 'Desktop';
    if (window.innerWidth <= 576) {
        deviceType = 'Mobile (Small)';
    } else if (window.innerWidth <= 768) {
        deviceType = 'Mobile';
    } else if (window.innerWidth <= 992) {
        deviceType = 'Tablet';
    } else if (window.innerWidth <= 1200) {
        deviceType = 'Desktop (Small)';
    }
    document.getElementById('device-type').textContent = deviceType;
}

// Update on load and resize
updateDeviceInfo();
window.addEventListener('resize', updateDeviceInfo);
</script>

<?php include 'includes/footer.php'; ?>