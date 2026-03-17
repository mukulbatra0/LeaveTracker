        </div> <!-- End of Main Content Container -->
    </div> <!-- End of Main Content Wrapper -->

    <!-- Footer -->
    <footer class="footer mt-auto py-4">
        <div class="container text-center">
            <span class="text-footer">© <?php echo date('Y'); ?> LeaveTracker - Employee Leave Management System | Developed for College Staff | Developed by Jatin & <a style = "text-decoration: none; color: white" href ="https://mukulbatra.netlify.app/">Mukul Batra</a></span>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <?php 
    // Determine the correct path based on current directory
    $base_path = '';
    $current_dir = dirname($_SERVER['PHP_SELF']);
    if (strpos($current_dir, '/modules') !== false || strpos($current_dir, '/admin') !== false || strpos($current_dir, '/reports') !== false) {
        $base_path = '../';
    }
    ?>
    <script src="<?php echo $base_path; ?>js/script.js"></script>
    
    <!-- Notification Loader Script -->
    <?php if(isset($_SESSION['user_id'])): ?>
    <script>
        // Set base path for AJAX calls
        window.basePath = '<?php echo $base_path; ?>';
        
        // Function to load notifications
        function loadNotifications() {
            $.ajax({
                url: window.basePath + 'api/get_notifications.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.unread_count > 0) {
                        $('#notification-count').text(data.unread_count).show();
                        
                        // Clear existing notifications
                        $('#notification-list').empty();
                        
                        // Add new notifications
                        $.each(data.notifications.slice(0, 5), function(index, notification) {
                            var unreadClass = notification.is_read == 0 ? ' unread' : '';
                            var notificationHtml = '<li class="dropdown-item notification-item' + unreadClass + '">' +
                                '<div class="notification-content">' +
                                '<div class="notification-title">' + notification.title + '</div>' +
                                '<div class="notification-message">' + notification.message + '</div>' +
                                '<div class="notification-time">' + new Date(notification.created_at).toLocaleDateString() + '</div>' +
                                '</div></li>';
                            $('#notification-list').append(notificationHtml);
                        });
                        
                        // Add view all link
                        $('#notification-list').append(
                            '<li><hr class="dropdown-divider"></li>' +
                            '<li class="dropdown-item text-center"><a href="' + window.basePath + 'modules/notifications.php">View All</a></li>'
                        );
                    } else {
                        $('#notification-count').text('0').hide();
                        $('#notification-list').html('<li class="dropdown-item text-center">No new notifications</li>');
                    }
                },
                error: function() {
                    console.error('Failed to load notifications');
                    $('#notification-count').hide();
                    $('#notification-list').html('<li class="dropdown-item text-center text-muted">Unable to load notifications</li>');
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