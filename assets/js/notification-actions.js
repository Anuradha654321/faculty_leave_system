/**
 * Notification Actions JavaScript
 * Handles the leave application approval/rejection functionality
 */

$(document).ready(function() {
    // Prevent modal from closing when clicking outside
    $('.modal').modal({
        backdrop: 'static',
        keyboard: false
    });
    
    // Handle approve form submission
    $('.approve-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        
        // Disable button to prevent multiple submissions
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        // Submit form via AJAX
        $.ajax({
            type: 'POST',
            url: form.attr('action'),
            data: form.serialize(),
            success: function(response) {
                // Show success message
                showAlert('success', 'Leave application approved successfully.');
                
                // Close the modal
                form.closest('.modal').modal('hide');
                
                // Reload the page after a short delay
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            },
            error: function() {
                // Show error message
                showAlert('danger', 'An error occurred. Please try again.');
                
                // Re-enable the button
                submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Confirm Approval');
            }
        });
    });
    
    // Handle reject form submission
    $('.reject-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        
        // Validate that reason is provided
        var remarks = form.find('textarea[name="remarks"]').val().trim();
        if (!remarks) {
            showAlert('warning', 'Please provide a reason for rejection.');
            return false;
        }
        
        // Disable button to prevent multiple submissions
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        // Submit form via AJAX
        $.ajax({
            type: 'POST',
            url: form.attr('action'),
            data: form.serialize(),
            success: function(response) {
                // Show success message
                showAlert('success', 'Leave application rejected successfully.');
                
                // Close the modal
                form.closest('.modal').modal('hide');
                
                // Reload the page after a short delay
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            },
            error: function() {
                // Show error message
                showAlert('danger', 'An error occurred. Please try again.');
                
                // Re-enable the button
                submitBtn.prop('disabled', false).html('<i class="fas fa-times"></i> Confirm Rejection');
            }
        });
    });
    
    // Function to show alert messages
    function showAlert(type, message) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                        message +
                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                        '<span aria-hidden="true">&times;</span>' +
                        '</button>' +
                        '</div>';
        
        // Add alert to the page
        $('#alert-container').html(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
});
