        </div> <!-- End of Main Content Container -->
    </div> <!-- End of Main Content Wrapper -->

    <!-- Footer -->
    <footer class="footer mt-auto py-4">
        <div class="container text-center">
            <span class="text-footer">© <?php echo date('Y'); ?> ELMS - Employee Leave Management System | Developed for College Staff</span>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
</body>
</html>