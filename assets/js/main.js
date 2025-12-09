// Custom JavaScript for EventUp
// Bootstrap form validation
(function () {
    'use strict';

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('form[novalidate]');

    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                form.classList.add('was-validated');
            }, false);
        });
})();

$(document).ready(function() {
    const eventId = new URLSearchParams(window.location.search).get('id');

    // Create Task
    $('#create-task-form').on('submit', function(e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            $(this).addClass('was-validated');
            return;
        }

        const formData = $(this).serialize() + '&action=create_task&event_id=' + eventId;

        $.post('ajax_handler.php', formData, function(response) {
            if (response.success) {
                location.reload(); // Simple reload for now, can be improved to dynamically add the task
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json');
    });

    // Update Task Status
    $('#task-list').on('change', '.task-status-select', function() {
        const taskId = $(this).data('task-id');
        const status = $(this).val();

        $.post('ajax_handler.php', { action: 'update_task_status', task_id: taskId, status: status, event_id: eventId }, function(response) {
            if (!response.success) {
                alert('Error: ' + response.message);
            }
        }, 'json');
    });

    // Delete Task
    $('#task-list').on('click', '.delete-task-btn', function() {
        const taskId = $(this).data('task-id');
        if (confirm('Are you sure you want to delete this task?')) {
            $.post('ajax_handler.php', { action: 'delete_task', task_id: taskId, event_id: eventId }, function(response) {
                if (response.success) {
                    $('#task-' + taskId).remove();
                } else {
                    alert('Error: ' . response.message);
                }
            }, 'json');
        }
    });
});
