<?php
// Disable error reporting for notices and warnings
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    redirect(BASE_URL . 'unauthorized.php');
}

// Get all leave types
function getLeaveTypes() {
    $conn = connectDB();
    
    $query = "SELECT * FROM leave_types ORDER BY type_name";
    
    $result = $conn->query($query);
    
    $leaveTypes = [];
    while ($row = $result->fetch_assoc()) {
        $leaveTypes[] = $row;
    }
    
    closeDB($conn);
    
    return $leaveTypes;
}

// Add new leave type
function addLeaveType($typeName, $defaultBalance, $description, $documentRequired, $carryForward) {
    $conn = connectDB();
    
    // Check if leave type already exists
    $stmt = $conn->prepare("SELECT type_id FROM leave_types WHERE type_name = ?");
    $stmt->bind_param("s", $typeName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Leave type already exists
    }
    
    // Insert new leave type
    $stmt = $conn->prepare("INSERT INTO leave_types (type_name, default_balance, description, requires_document, carry_forward) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsii", $typeName, $defaultBalance, $description, $documentRequired, $carryForward);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $success;
}

// Update leave type
function updateLeaveType($typeId, $typeName, $defaultBalance, $description, $documentRequired, $carryForward) {
    $conn = connectDB();
    
    // Check if leave type name already exists for another leave type
    $stmt = $conn->prepare("SELECT type_id FROM leave_types WHERE type_name = ? AND type_id != ?");
    $stmt->bind_param("si", $typeName, $typeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Leave type name already exists
    }
    
    // Update leave type
    $stmt = $conn->prepare("UPDATE leave_types SET type_name = ?, default_balance = ?, description = ?, requires_document = ?, carry_forward = ? WHERE type_id = ?");
    $stmt->bind_param("sdsiii", $typeName, $defaultBalance, $description, $documentRequired, $carryForward, $typeId);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $success;
}



// Process form submissions
$error = '';
$success = '';

// Add leave type
if (isset($_POST['add_leave_type'])) {
    $typeName = sanitizeInput($_POST['type_name']);
    $defaultBalance = floatval($_POST['default_balance']);
    $description = sanitizeInput($_POST['description']);
    $documentRequired = isset($_POST['document_required']) ? 1 : 0;
    $carryForward = isset($_POST['carry_forward']) ? 1 : 0;
    
    if (empty($typeName)) {
        $error = 'Leave type name is required.';
    } else {
        if (addLeaveType($typeName, $defaultBalance, $description, $documentRequired, $carryForward)) {
            $success = 'Leave type added successfully.';
        } else {
            $error = 'Leave type already exists or an error occurred.';
        }
    }
}

// Update leave type
if (isset($_POST['update_leave_type'])) {
    $typeId = intval($_POST['type_id']);
    $typeName = sanitizeInput($_POST['type_name']);
    $defaultBalance = floatval($_POST['default_balance']);
    $description = sanitizeInput($_POST['description']);
    $documentRequired = isset($_POST['document_required']) ? 1 : 0;
    $carryForward = isset($_POST['carry_forward']) ? 1 : 0;
    
    if (empty($typeName)) {
        $error = 'Leave type name is required.';
    } else {
        if (updateLeaveType($typeId, $typeName, $defaultBalance, $description, $documentRequired, $carryForward)) {
            $success = 'Leave type updated successfully.';
        } else {
            $error = 'Leave type name already exists or an error occurred.';
        }
    }
}

// Delete leave type
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $typeId = intval($_GET['delete']);
    
    if (deleteLeaveType($typeId)) {
        $success = 'Leave type deleted successfully.';
    } else {
        $error = 'Cannot delete leave type that is being used in applications or leave balances.';
    }
}

// Get all leave types
$leaveTypes = getLeaveTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Types - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.25rem 0.75rem;
            margin-left: 0.25rem;
            border-radius: 0.25rem;
            border: 1px solid #dee2e6;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #007bff;
            color: white !important;
            border-color: #007bff;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
            border-color: #dee2e6;
        }
        .dataTables_wrapper .dataTables_length select {
            padding: 0.25rem 1.75rem 0.25rem 0.5rem;
            margin: 0 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
        }
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            padding: 0.25rem 0.5rem;
        }
        .table th {
            white-space: nowrap;
            position: relative;
        }
        .table th.sorting:after,
        .table th.sorting_asc:after,
        .table th.sorting_desc:after {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            font-size: 0.8em;
            opacity: 0.7;
        }
        .table th.sorting:after {
            content: "\f0dc";
        }
        .table th.sorting_asc:after {
            content: "\f0de";
        }
        .table th.sorting_desc:after {
            content: "\f0dd";
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Leave Types</h1>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLeaveTypeModal">
                        <i class="fas fa-plus"></i> Add Leave Type
                    </button>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Leave Type</th>
                                        <th>Default Balance</th>
                                        <th>Document Required</th>
                                        <th>Carry Forward</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveTypes as $leaveType): ?>
                                        <tr>
                                            <td><?php echo $leaveType['type_id'] ?? ''; ?></td>
                                            <td><?php echo $leaveType['type_name'] ?? ''; ?></td>
                                            <td><?php echo ($leaveType['default_balance'] ?? 0) . ' days'; ?></td>
                                            <td>
                                                <?php if (!empty($leaveType['requires_document'])): ?>
                                                    <span class="badge badge-warning">Required</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Not Required</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($leaveType['carry_forward'])): ?>
                                                    <span class="badge badge-success">Allowed</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Not Allowed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $leaveType['description'] ?? ''; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-leave-type" 
                                                        data-id="<?php echo $leaveType['type_id'] ?? ''; ?>" 
                                                        data-name="<?php echo htmlspecialchars($leaveType['type_name'] ?? ''); ?>"
                                                        data-balance="<?php echo $leaveType['default_balance'] ?? 0; ?>"
                                                        data-doc="<?php echo $leaveType['requires_document'] ?? 0; ?>"
                                                        data-carry="<?php echo $leaveType['carry_forward'] ?? 0; ?>"
                                                        data-desc="<?php echo htmlspecialchars($leaveType['description'] ?? ''); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Leave Type Modal -->
    <div class="modal fade" id="addLeaveTypeModal" tabindex="-1" role="dialog" aria-labelledby="addLeaveTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLeaveTypeModalLabel">Add Leave Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="type_name">Leave Type Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="type_name" name="type_name" required>
                        </div>
                        <div class="form-group">
                            <label for="default_balance">Default Balance (days) <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" class="form-control" id="default_balance" name="default_balance" required value="0">
                            <small class="form-text text-muted">Number of days allocated per year</small>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="document_required" name="document_required">
                                <label class="custom-control-label" for="document_required">Require Supporting Document</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="carry_forward" name="carry_forward">
                                <label class="custom-control-label" for="carry_forward">Allow Carry Forward to Next Year</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_leave_type" class="btn btn-primary">Add Leave Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Leave Type Modal -->
    <div class="modal fade" id="editLeaveTypeModal" tabindex="-1" role="dialog" aria-labelledby="editLeaveTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLeaveTypeModalLabel">Edit Leave Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_type_id" name="type_id">
                        <div class="form-group">
                            <label for="edit_type_name">Leave Type Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_type_name" name="type_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_default_balance">Default Balance (days) <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" class="form-control" id="edit_default_balance" name="default_balance" required>
                            <small class="form-text text-muted">Number of days allocated per year</small>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="edit_document_required" name="document_required">
                                <label class="custom-control-label" for="edit_document_required">Require Supporting Document</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="edit_carry_forward" name="carry_forward">
                                <label class="custom-control-label" for="edit_carry_forward">Allow Carry Forward to Next Year</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_description">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_leave_type" class="btn btn-primary">Update Leave Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
        $(document).ready(function() {
            try {
                // Initialize DataTable with enhanced options
                var table = $('.data-table').DataTable({
                    "pageLength": 10,
                    "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                           "<'row'<'col-sm-12'tr>>" +
                           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                    "language": {
                        "search": "_INPUT_",
                        "searchPlaceholder": "Search leave types...",
                        "lengthMenu": "Show _MENU_ entries",
                        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                        "infoEmpty": "No entries found",
                        "infoFiltered": "(filtered from _MAX_ total entries)",
                        "paginate": {
                            "previous": "&laquo;",
                            "next": "&raquo;"
                        }
                    },
                    "columnDefs": [
                        { "orderable": true, "targets": [0, 1, 2, 3, 4, 5] },
                        { "orderable": false, "targets": 6, "className": 'text-center' }
                    ],
                    "order": [[0, 'asc']],
                    "responsive": true,
                    "autoWidth": false
                });
                
                // Style the DataTable elements after initialization
                $('.dataTables_length select').addClass('form-control form-control-sm');
                $('.dataTables_filter input').addClass('form-control form-control-sm');
                
            } catch (error) {
                console.error('Error initializing DataTable:', error);
            }
            
            
            
            // Edit leave type
            $('.edit-leave-type').on('click', function() {
                try {
                    const typeId = $(this).data('id');
                    const typeName = $(this).data('name');
                    const defaultBalance = $(this).data('balance');
                    const documentRequired = $(this).data('doc');
                    const carryForward = $(this).data('carry');
                    const description = $(this).data('desc');
                    
                    $('#edit_type_id').val(typeId);
                    $('#edit_type_name').val(typeName);
                    $('#edit_default_balance').val(defaultBalance);
                    $('#edit_document_required').prop('checked', documentRequired == 1);
                    $('#edit_carry_forward').prop('checked', carryForward == 1);
                    $('#edit_description').val(description);
                    
                    $('#editLeaveTypeModal').modal('show');
                } catch (error) {
                    console.error('Error in edit leave type:', error);
                }
            });
        });
    </script>
</body>
</html>
