<?php
function create_notification($user_id, $message, $type = 'info') {
    global $conn;
    $conn->query("INSERT INTO notifications (user_id, message, type, is_read) 
                 VALUES ($user_id, '$message', '$type', 0)");
}

function get_unread_notifications($user_id) {
    global $conn;
    return $conn->query("SELECT * FROM notifications 
                        WHERE user_id = $user_id AND is_read = 0
                        ORDER BY created_at DESC");
}

function mark_notification_read($notification_id) {
    global $conn;
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notification_id");
}
?>