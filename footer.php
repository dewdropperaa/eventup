    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    if (!notificationsDropdown) return;
    
    const notificationMenu = document.getElementById('notification-menu');
    const notificationBadge = notificationsDropdown.querySelector('.badge');

    notificationsDropdown.addEventListener('show.bs.dropdown', function () {
        notificationMenu.innerHTML = '<li class="dropdown-header">Notifications</li><li><div class="px-3 py-5 text-center text-muted">Loading...</div></li>';

        fetch('ajax_handler.php?action=get_notifications', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationUI(data.notifications);
            } else {
                notificationMenu.innerHTML = '<li class="dropdown-header">Notifications</li><li><div class="px-3 py-2 text-center text-danger">Failed to load.</div></li>';
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
            notificationMenu.innerHTML = '<li class="dropdown-header">Notifications</li><li><div class="px-3 py-2 text-center text-danger">Error.</div></li>';
        });
    });

    function updateNotificationUI(notifications) {
        notificationMenu.innerHTML = '<li class="dropdown-header">Notifications</li>';

        if (notifications.length === 0) {
            notificationMenu.innerHTML += '<li class="px-3 py-2 text-muted small">No new notifications</li>';
            const badge = notificationsDropdown.querySelector('.badge');
            if (badge) {
                badge.remove();
            }
        } else {
            notifications.forEach(notif => {
                const notifItem = `
                    <li class="px-3 py-2 small border-bottom">
                        <div class="fw-bold text-truncate">${escapeHTML(notif.type)}</div>
                        <div class="text-muted text-wrap">${escapeHTML(notif.message)}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">${timeAgo(notif.created_at)}</div>
                    </li>`;
                notificationMenu.innerHTML += notifItem;
            });
            let badge = notificationsDropdown.querySelector('.badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                notificationsDropdown.appendChild(badge);
            }
            badge.textContent = notifications.length;
        }

        notificationMenu.innerHTML += '<li><hr class="dropdown-divider"></li>';
        notificationMenu.innerHTML += '<li class="text-center"><a class="dropdown-item small" href="notifications_list.php">View all notifications</a></li>';
        notificationMenu.innerHTML += '<li class="text-center"><a class="dropdown-item small" href="notifications_mark_read.php">Mark all as read</a></li>';
    }

    function escapeHTML(str) {
        return str.replace(/[&<>"\']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return Math.floor(seconds) + " seconds ago";
    }
});
</script>

<script>
$(document).ready(function() {
    const searchInput = $('#live-search-input');
    const resultsContainer = $('#live-search-results');
    const originalEventList = $('#original-event-list');
    const noResultsAlert = $('#no-live-results');
    const loadingTemplate = '<div class="col-12 text-center py-4 text-muted"><div class="spinner-border text-primary" role="status"></div><p class="mt-3 mb-0">Searching events...</p></div>';
    let debounceTimer = null;

    const resetLiveSearch = () => {
        clearTimeout(debounceTimer);
        resultsContainer.empty().addClass('d-none');
        noResultsAlert.addClass('d-none');
        originalEventList.removeClass('d-none');
    };

    const formatDateTime = (dateTimeString) => {
        const eventDate = new Date(dateTimeString.replace(' ', 'T'));
        if (Number.isNaN(eventDate.getTime())) {
            return dateTimeString;
        }
        return eventDate.toLocaleString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    searchInput.on('keyup', function() {
        const query = $(this).val().trim();

        if (query.length === 0) {
            resetLiveSearch();
            return;
        }

        originalEventList.addClass('d-none');
        noResultsAlert.addClass('d-none');
        resultsContainer.removeClass('d-none').html(loadingTemplate);

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            $.ajax({
                url: 'search_events.php',
                type: 'GET',
                data: { query: query },
                dataType: 'json'
            })
            .done(function(events) {
                resultsContainer.empty();

                if (Array.isArray(events) && events.length > 0) {
                    events.forEach(function(event) {
                        const eventCard = `
                            <div class="col">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h5 class="card-title">${escapeHTML(event.titre)}</h5>
                                        <p class="card-text mb-2">
                                            <i class="bi bi-calendar-event text-primary"></i>
                                            <strong>Date:</strong> ${formatDateTime(event.date)}
                                        </p>
                                        <p class="card-text mb-0">
                                            <i class="bi bi-geo-alt text-danger"></i>
                                            <strong>Location:</strong> ${escapeHTML(event.lieu)}
                                        </p>
                                    </div>
                                    <div class="card-footer bg-white border-top">
                                        <a href="event_details.php?id=${event.id}" class="btn btn-primary btn-sm w-100">
                                            <i class="bi bi-eye"></i> See details
                                        </a>
                                    </div>
                                </div>
                            </div>`;
                        resultsContainer.append(eventCard);
                    });
                    noResultsAlert.addClass('d-none');
                } else {
                    resultsContainer.addClass('d-none');
                    noResultsAlert.removeClass('d-none');
                }
            })
            .fail(function() {
                resultsContainer.addClass('d-none');
                noResultsAlert.removeClass('d-none').html('<i class="bi bi-exclamation-triangle"></i> <strong>Unable to fetch events.</strong> Please try again.');
            });
        }, 250);
    });

    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }
});
</script>

<script>
$(document).ready(function() {
    $('.permission-switch').on('change', function() {
        const checkbox = $(this);
        const eventId = checkbox.data('event-id');
        const userId = checkbox.data('user-id');
        const permissionName = checkbox.data('permission-name');
        const isAllowed = checkbox.is(':checked') ? 1 : 0;

        $.ajax({
            url: 'update_event_permission.php',
            type: 'POST',
            data: {
                event_id: eventId,
                user_id: userId,
                permission_name: permissionName,
                is_allowed: isAllowed
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const toastEl = $('#permissionToast');
                    const toast = new bootstrap.Toast(toastEl);
                    toastEl.find('.toast-body').text(response.message || 'Permission updated successfully.');
                    toast.show();
                } else {
                    checkbox.prop('checked', !isAllowed);
                    alert('Failed to update permission: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                checkbox.prop('checked', !isAllowed);
                alert('An error occurred while communicating with the server.');
            }
        });
    });
});
</script>

</body>
</html>
