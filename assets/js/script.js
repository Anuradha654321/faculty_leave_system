// Main JavaScript for Faculty Leave Management System

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-toggle="popover"]').popover();
    
    // Date range picker initialization
    if ($.fn.daterangepicker) {
        $('.date-range-picker').daterangepicker({
            opens: 'left',
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'DD-MM-YYYY'
            }
        });
        
        $('.date-range-picker').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('DD-MM-YYYY') + ' to ' + picker.endDate.format('DD-MM-YYYY'));
            
            // Calculate number of days
            const startDate = picker.startDate.format('YYYY-MM-DD');
            const endDate = picker.endDate.format('YYYY-MM-DD');
            
            // Update the total days field if it exists
            if ($('#total_days').length) {
                calculateTotalDays(startDate, endDate);
            }
        });
        
        $('.date-range-picker').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            
            // Clear the total days field if it exists
            if ($('#total_days').length) {
                $('#total_days').val('');
            }
        });
    }
    
    // Single date picker initialization
    if ($.fn.datepicker) {
        $('.date-picker').datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Leave type change handler
    $('#leave_type_id').on('change', function() {
        const leaveType = $(this).val();
        
        // Show/hide class adjustment section for casual leave
        if (leaveType === '1' || leaveType === '2') { // Casual leave prior or emergency
            $('.class-adjustment-section').removeClass('d-none');
        } else {
            $('.class-adjustment-section').addClass('d-none');
        }
        
        // Show document upload for medical leave
        if (leaveType === '4') { // Medical leave
            $('.document-upload-section').removeClass('d-none');
        } else {
            $('.document-upload-section').addClass('d-none');
        }
    });
    
    // Add class adjustment row
    $('#add_class_adjustment').on('click', function() {
        const template = $('#class_adjustment_template').html();
        $('#class_adjustments_container').append(template);
        
        // Initialize date picker for the new row
        $('#class_adjustments_container .date-picker:last').datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true
        });
        
        // Update row numbers
        updateClassAdjustmentRows();
        
        return false;
    });
    
    // Remove class adjustment row
    $(document).on('click', '.remove-class-adjustment', function() {
        $(this).closest('.class-adjustment-row').remove();
        
        // Update row numbers
        updateClassAdjustmentRows();
        
        return false;
    });
    
    // Form validation
    if ($.fn.validate) {
        $('#leave_application_form').validate({
            rules: {
                leave_type_id: {
                    required: true
                },
                date_range: {
                    required: true
                },
                reason: {
                    required: true,
                    minlength: 10
                }
            },
            messages: {
                leave_type_id: {
                    required: "Please select a leave type"
                },
                date_range: {
                    required: "Please select the leave date range"
                },
                reason: {
                    required: "Please provide a reason for your leave",
                    minlength: "Your reason must be at least 10 characters long"
                }
            },
            errorElement: 'div',
            errorPlacement: function(error, element) {
                error.addClass('invalid-feedback');
                element.closest('.form-group').append(error);
            },
            highlight: function(element, errorClass, validClass) {
                $(element).addClass('is-invalid').removeClass('is-valid');
            },
            unhighlight: function(element, errorClass, validClass) {
                $(element).removeClass('is-invalid').addClass('is-valid');
            }
        });
    }
    
    // Confirmation dialogs
    $(document).on('click', 'a.confirm-action:not(#sidebar a, #sidebar *)', function(e) {
        e.preventDefault();
        const message = $(this).data('confirm') || 'Are you sure you want to perform this action?';
        const href = $(this).attr('href');
        
        if (confirm(message)) {
            window.location.href = href;
        }
        
        return false;
    });
    
    // DataTables initialization
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            responsive: true,
            order: [[0, 'desc']]
        });
    }
    
    // Print functionality
    $('.print-btn').on('click', function() {
        window.print();
        return false;
    });
    
    // File input customization
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
});

// Helper function to update class adjustment row numbers
function updateClassAdjustmentRows() {
    $('.class-adjustment-row').each(function(index) {
        $(this).find('.row-number').text(index + 1);
        
        // Update input names with correct index
        $(this).find('input, select, textarea').each(function() {
            const name = $(this).attr('name');
            if (name) {
                const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                $(this).attr('name', newName);
            }
        });
    });
}

// Helper function to calculate total days between two dates (excluding weekends)
function calculateTotalDays(startDate, endDate) {
    // Make AJAX request to get the total days
    $.ajax({
        url: 'ajax/calculate_days.php',
        type: 'POST',
        data: {
            start_date: startDate,
            end_date: endDate
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#total_days').val(response.days);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error calculating days. Please try again.');
        }
    });
}

// Notification functions
function markNotificationAsRead(notificationId) {
    $.ajax({
        url: 'ajax/mark_notification_read.php',
        type: 'POST',
        data: {
            notification_id: notificationId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update UI if needed
                $('#notification-' + notificationId).removeClass('unread');
            }
        }
    });
}

// Leave approval functions
function approveLeave(applicationId, status, remarks) {
    $.ajax({
        url: 'ajax/update_leave_status.php',
        type: 'POST',
        data: {
            application_id: applicationId,
            status: status,
            remarks: remarks
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Redirect or show success message
                window.location.href = 'pending_approvals.php?success=1';
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error updating leave status. Please try again.');
        }
    });
}

// Class adjustment approval functions
function approveClassAdjustment(adjustmentId, status, remarks) {
    $.ajax({
        url: 'ajax/update_adjustment_status.php',
        type: 'POST',
        data: {
            adjustment_id: adjustmentId,
            status: status,
            remarks: remarks
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Redirect or show success message
                window.location.href = 'class_adjustments.php?success=1';
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error updating adjustment status. Please try again.');
        }
    });
}
