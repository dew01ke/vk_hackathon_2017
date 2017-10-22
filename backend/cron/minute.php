<?php
include __DIR__ . '/../../frontend/lt_core.php';

return (bool)\DB::query("INSERT INTO l_notifications (type, time, date, user_from, user_to, news_id, text, data, is_deleted, is_fresh, reference) SELECT 0, UNIX_TIMESTAMP(),  NOW(), 0, l_users.id, l_news.id, '', '', 0, 1, MD5(CONCAT(l_news.id, '_', l_users.id, '_', l_news.stage_id)) md5 FROM l_news JOIN l_users ON l_users.role_id IN (2) HAVING md5 NOT IN (SELECT reference FROM l_notifications)");