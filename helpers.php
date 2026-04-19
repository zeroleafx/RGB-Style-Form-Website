<?php
/**
 * Close all expired quests that have a past end_date
 * Sets their status to 'closed' if they are currently 'published'
 */
function close_expired_quests($conn) {
    $sql = "UPDATE quests
            SET status = 'closed'
            WHERE status = 'published'
            AND end_date IS NOT NULL
            AND end_date < NOW()";

    mysqli_query($conn, $sql);
}
?>
