        </div> <!-- End of Main Content Container -->
    </div> <!-- End of Main Content Wrapper -->

    <!-- Footer -->
    <footer class="footer mt-auto py-4">
        <div class="container text-center">
            <span class="text-footer">© <?php echo date('Y'); ?> ELMS - Employee Leave Management System | Developed for College Staff</span>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo $basePath; ?>js/script.js"></script>
    
    <!-- Notification Loader Script -->
    <?php if(isset($_SESSION['user_id'])): ?>
    <script>
        // Function to load notifications
        function loadNotifications() {
            $.ajax({
                url: '<?php echo $basePath; ?>modules/get_notifications.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.count > 0) {
                        $('#notification-count').text(data.count).show();
                        
                        // Clear existing notifications
                        $('#notification-list').empty();
                        
                        // Add new notifications
                        $.each(data.notifications, function(index, notification) {
                            var unreadClass = notification.is_read ? '' : ' unread';
                            var notificationHtml = '<li class="dropdown-item notification-item' + unreadClass + '">' +
                                '<a href="' + notification.link + '" class="notification-link">' +
                                '<div class="notification-title">' + notification.title + '</div>' +
                                '<div class="notification-message">' + notification.message + '</div>' +
                                '<div class="notification-time">' + notification.time_ago + '</div>' +
                                '</a></li>';
                            $('#notification-list').append(notificationHtml);
                        });
                        
                        // Add view all link
                        $('#notification-list').append(
                            '<li><hr class="dropdown-divider"></li>' +
                            '<li class="dropdown-item text-center"><a href="<?php echo $basePath; ?>modules/notifications.php">View All</a></li>'
                        );
                    } else {
                        $('#notification-count').text('0').hide();
                        $('#notification-list').html('<li class="dropdown-item text-center">No new notifications</li>');
                    }
                },
                error: function() {
                    console.error('Failed to load notifications');
                }
            });
        }
        
        // Load notifications on page load
        $(document).ready(function() {
            loadNotifications();
            
            // Refresh notifications every 60 seconds
            setInterval(loadNotifications, 60000);
        });
    </script>
    <?php endif; ?>
    
    <!-- Global Modal Accessibility Fix -->
    <script>
        // Fix accessibility issues with all Bootstrap modals
        document.addEventListener('DOMContentLoaded', function() {
            // Get all modals
            const modals = document.querySelectorAll('.modal');
            
            modals.forEach(function(modal) {
                // When modal is shown, remove aria-hidden
                modal.addEventListener('shown.bs.modal', function() {
                    this.removeAttribute('aria-hidden');
                });
                
                // When modal is hidden, add aria-hidden
                modal.addEventListener('hidden.bs.modal', function() {
                    this.setAttribute('aria-hidden', 'true');
                });
                
                // When modal is being shown, temporarily remove aria-hidden
                modal.addEventListener('show.bs.modal', function() {
                    this.removeAttribute('aria-hidden');
                });
                
                // When modal is being hidden, don't add aria-hidden yet (wait for hidden event)
                modal.addEventListener('hide.bs.modal', function() {
                    // Don't add aria-hidden here, wait for hidden.bs.modal event
                });
            });
        });
    </script>
</body>
</html>