<?php
// @author  Jcode | ObrempongK
// api/v1/index.php — Central API Router
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Security.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];
requireCsrfForStateChange($method);
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = preg_replace('#^/api/v1#', '', $uri);
$path   = trim($path, '/');
$segments = explode('/', $path);

$user   = auth();
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$input  = array_merge($_POST, $input);

// ── ROUTER ────────────────────────────────────────────
switch (true) {

    // ── AUTH ──────────────────────────────────────────
    case $path === 'auth/check-username' && $method === 'GET':
        $un = sanitize($_GET['username'] ?? '');
        $taken = DB::count('users', 'username=?', [strtolower($un)]) > 0;
        jsonResponse(['available' => !$taken]);

    // ── VOICE FEED ────────────────────────────────────
    case $path === 'voice/feed' && $method === 'GET':
        requireAuthApi();
        $pg = max(1,(int)($_GET['page']??1));
        $channel = sanitize($_GET['channel'] ?? '');
        $limit = 12; $offset = ($pg-1)*$limit;
        $where = "p.status='active'";
        $params = [];
        if ($channel) {
            $ch = DB::first('SELECT id FROM channels WHERE slug=? AND is_active=1', [$channel]);
            if ($ch) {
                $where .= ' AND p.channel_id=?';
                $params[] = (int)$ch['id'];
            }
        }
        $posts = DB::query(
            "SELECT p.*, u.username, up.avatar,
                    (SELECT COALESCE(SUM(amount),0) FROM energy_transactions WHERE post_id=p.id) AS energy_total,
                    (SELECT COUNT(*) FROM replies WHERE post_id=p.id AND status='active') AS reply_count
             FROM posts p
             JOIN users u ON u.id=p.user_id
             LEFT JOIN user_profiles up ON up.user_id=p.user_id
             WHERE {$where} ORDER BY p.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        jsonResponse(['posts'=>$posts,'has_more'=>count($posts)===$limit]);

    // ── CREATE VOICE POST ─────────────────────────────
    case $path === 'voice/create' && $method === 'POST':
        requireAuthApi();
        $title = sanitize($input['title'] ?? '');
        if (!$title) jsonError('Title required');

        // Detect PHP upload-limit errors before checking $_FILES
        if (isset($_SERVER['CONTENT_LENGTH']) && empty($_FILES) && empty($_POST)) {
            $maxPost = ini_get('post_max_size');
            jsonError("Upload failed: server post_max_size ({$maxPost}) exceeded. Check .user.ini on server.", 413);
        }
        if (empty($_FILES['audio'])) {
            jsonError('Audio file required. If recording is too long, check server upload limits.');
        }
        if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            $errs = [
                UPLOAD_ERR_INI_SIZE   => 'File too large (upload_max_filesize limit)',
                UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL    => 'Upload was partial — try again',
                UPLOAD_ERR_NO_FILE    => 'No audio file received',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension',
            ];
            jsonError($errs[$_FILES['audio']['error']] ?? 'Upload error code ' . $_FILES['audio']['error'], 400);
        }

        $upload = uploadFile($_FILES['audio'], 'voice');
        if (!$upload['ok']) jsonError($upload['error']);

        // Optional cover image for the post
        $imageUrl = null;
        if (!empty($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $imgUp = uploadFile($_FILES['cover_image'], 'image');
            if ($imgUp['ok']) $imageUrl = $imgUp['url'];
        }

        $postType = sanitize($_POST['post_type'] ?? $input['post_type'] ?? 'voice');
        $postType = in_array($postType, ['voice','video']) ? $postType : 'voice';
        $postId = DB::insert('posts', [
            'user_id'    => (int)$user['id'],
            'channel_id' => (int)($input['channel_id']??$_POST['channel_id']??0) ?: null,
            'title'      => $title,
            'audio_url'  => $upload['url'],
            'image_url'  => $imageUrl,
            'duration'   => (int)($input['duration']??$_POST['duration']??0),
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Award points
        addPoints((int)$user['id'], (int)getSetting('points_per_post', DEFAULT_POINTS_PER_POST), 'voice_post', 'Points for voice post');
        // Audit log
        DB::insert('users_audit_logs', [
            'user_id'     => (int)$user['id'],
            'action'      => 'voice_post',
            'description' => "Posted voice post #{$postId}: {$title}",
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        jsonSuccess('Voice posted!', ['post_id' => $postId]);

    // ── PLAY TRACKING ─────────────────────────────────
    case preg_match('#^voice/(\d+)/play$#', $path, $m) && $method === 'POST':
        DB::exec('UPDATE posts SET play_count=play_count+1 WHERE id=?', [(int)$m[1]]);
        jsonSuccess();

    // ── VOICE REPLIES ─────────────────────────────────
    case preg_match('#^posts/(\d+)/replies$#', $path, $m) && $method === 'GET':
        $replies = DB::query(
            "SELECT r.*, u.username FROM replies r
             JOIN users u ON u.id=r.user_id
             WHERE r.post_id=? AND r.status='active'
             ORDER BY r.created_at ASC LIMIT 50",
            [(int)$m[1]]
        );
        jsonResponse(['replies'=>$replies]);

    case preg_match('#^posts/(\d+)/reply$#', $path, $m) && $method === 'POST':
        requireAuthApi();
        $text = sanitize($input['text'] ?? '');
        if (!$text && empty($_FILES['audio'])) jsonError('Text or audio required');
        $audioUrl = null;
        if (!empty($_FILES['audio'])) {
            $up = uploadFile($_FILES['audio'], 'voice');
            if ($up['ok']) $audioUrl = $up['url'];
        }
        $post = DB::first('SELECT * FROM posts WHERE id=? AND status="active"', [(int)$m[1]]);
        if (!$post) jsonError('Post not found', 404);
        DB::insert('replies', [
            'post_id'    => (int)$m[1],
            'user_id'    => (int)$user['id'],
            'text'       => $text,
            'audio_url'  => $audioUrl,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        DB::exec('UPDATE posts SET reply_count=reply_count+1 WHERE id=?', [(int)$m[1]]);
        addPoints((int)$user['id'], (int)getSetting('points_per_reply',DEFAULT_POINTS_PER_REPLY), 'reply', 'Points for reply');
        if ($post['user_id'] != $user['id']) {
            createNotification((int)$post['user_id'], 'reply', "@{$user['username']} replied to your post");
        }
        jsonSuccess('Reply posted');

    // ── ENERGY ────────────────────────────────────────
    case preg_match('#^posts/(\d+)/energy$#', $path, $m) && $method === 'POST':
        requireAuthApi();
        $postId = (int)$m[1];
        $amount = max(1, min(10, (int)($input['amount']??1)));
        $post = DB::first('SELECT * FROM posts WHERE id=? AND status="active"', [$postId]);
        if (!$post) jsonError('Post not found', 404);
        DB::insert('energy_transactions', [
            'giver_id'   => (int)$user['id'],
            'receiver_id'=> (int)$post['user_id'],
            'post_id'    => $postId,
            'amount'     => $amount,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $total = DB::first('SELECT COALESCE(SUM(amount),0) AS s FROM energy_transactions WHERE post_id=?', [$postId])['s'];
        jsonSuccess('Energy sent!', ['total_energy'=>$total]);

    // ── DELETE POST ───────────────────────────────────
    case preg_match('#^posts/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAuthApi();
        $post = DB::first('SELECT * FROM posts WHERE id=?', [(int)$m[1]]);
        if (!$post || ($post['user_id'] != $user['id'] && !isAdmin())) jsonError('Not found or unauthorized', 403);
        DB::update('posts', ['status'=>'removed'], ['id'=>(int)$m[1]]);
        jsonSuccess('Post deleted');

    // ── FOLLOWS ───────────────────────────────────────
    case $path === 'follow' && $method === 'POST':
        requireAuthApi();
        $targetId = (int)($input['user_id'] ?? 0);
        if ($targetId < 1 || $targetId === (int)$user['id']) jsonError('Invalid user');
        $target = DB::first('SELECT id, username FROM users WHERE id=? AND status="active"', [$targetId]);
        if (!$target) jsonError('User not found', 404);
        $exists = DB::count('followers', 'follower_id=? AND following_id=?', [(int)$user['id'], $targetId]) > 0;
        if (!$exists) {
            DB::insert('followers', [
                'follower_id'  => (int)$user['id'],
                'following_id' => $targetId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            createNotification($targetId, 'follow', "@{$user['username']} started following you");
        }
        jsonSuccess('Followed user');

    case $path === 'unfollow' && $method === 'POST':
        requireAuthApi();
        $targetId = (int)($input['user_id'] ?? 0);
        if ($targetId < 1 || $targetId === (int)$user['id']) jsonError('Invalid user');
        DB::exec('DELETE FROM followers WHERE follower_id=? AND following_id=?', [(int)$user['id'], $targetId]);
        jsonSuccess('Unfollowed user');

    // ── STATUS CREATE ─────────────────────────────────
    case $path === 'status/create' && $method === 'POST':
        requireAuthApi();
        $type = sanitize($input['type'] ?? 'text');
        if (!in_array($type, ['image','video','text','voice'])) jsonError('Invalid type');

        $mediaUrl = null;
        if (in_array($type, ['image','video','voice']) && !empty($_FILES['media_file'])) {
            $fileType = $type === 'voice' ? 'voice' : 'status';
            $up = uploadFile($_FILES['media_file'], $fileType);
            if (!$up['ok']) jsonError($up['error']);
            $mediaUrl = $up['url'];
        }

        $contactLink = sanitizeUrl($input['contact_link'] ?? '');
        if (($input['contact_link'] ?? '') !== '' && $contactLink === '') {
            jsonError('Please provide a valid http or https contact link');
        }

        $expiryHours = (int)getSetting('status_expiry_hours', 24);
        $statusId = DB::insert('status_posts', [
            'user_id'      => (int)$user['id'],
            'type'         => $type,
            'media_url'    => $mediaUrl,
            'text'         => sanitize($input['text']??''),
            'bg_color'     => sanitize($input['bg_color']??''),
            'caption'      => sanitize($input['caption']??''),
            'source_label' => sanitize($input['source_label']??''),
            'contact_link' => $contactLink,
            'status'       => 'active',
            'expires_at'   => date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours")),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        jsonSuccess('Status posted!', ['status_id'=>$statusId]);

    // ── STATUS VIEW ───────────────────────────────────
    case preg_match('#^status/(\d+)/view$#', $path, $m) && $method === 'POST':
        $sId = (int)$m[1];
        $status = DB::first('SELECT * FROM status_posts WHERE id=? AND status="active"', [$sId]);
        if (!$status) jsonError('Not found', 404);
        $viewerId = $user ? (int)$user['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // Check for duplicate view
        $alreadyViewed = false;
        if ($viewerId) {
            $alreadyViewed = DB::count('status_views','status_id=? AND viewer_id=?', [$sId, $viewerId]) > 0;
        } else {
            $alreadyViewed = DB::count('status_views','status_id=? AND ip_address=? AND DATE(created_at)=CURDATE()', [$sId, $ip]) > 0;
        }

        if (!$alreadyViewed) {
            DB::insert('status_views', [
                'status_id'  => $sId,
                'viewer_id'  => $viewerId,
                'ip_address' => $ip,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            DB::exec('UPDATE status_posts SET views_count=views_count+1 WHERE id=?', [$sId]);
            if ($status['user_id'] != $viewerId) {
                rewardStatusView((int)$status['user_id'], $sId);
            }
        }
        jsonSuccess();

    // ── STATUS CLICK ──────────────────────────────────
    case preg_match('#^status/(\d+)/click$#', $path, $m) && $method === 'POST':
        $sId = (int)$m[1];
        $status = DB::first('SELECT * FROM status_posts WHERE id=?', [$sId]);
        if (!$status) jsonError('Not found', 404);
        DB::insert('status_clicks', [
            'status_id'  => $sId,
            'user_id'    => $user ? (int)$user['id'] : null,
            'click_type' => sanitize($input['click_type']??'link'),
            'ip_address' => $_SERVER['REMOTE_ADDR']??'',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        DB::exec('UPDATE status_posts SET clicks_count=clicks_count+1 WHERE id=?', [$sId]);
        rewardStatusClick((int)$status['user_id'], $sId);
        jsonSuccess();

    // ── STATUS DELETE ─────────────────────────────────
    case preg_match('#^status/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAuthApi();
        $s = DB::first('SELECT * FROM status_posts WHERE id=?', [(int)$m[1]]);
        if (!$s || $s['user_id'] != $user['id']) jsonError('Not found', 404);
        DB::update('status_posts', ['status'=>'removed'], ['id'=>(int)$m[1]]);
        jsonSuccess('Deleted');

    // ── WALLET ────────────────────────────────────────
    case $path === 'wallet' && $method === 'GET':
        requireAuthApi();
        $w = getUserWallet((int)$user['id']);
        jsonResponse(['wallet'=>$w]);

    case $path === 'wallet/transfer' && $method === 'POST':
        requireAuthApi();
        $toUsername = sanitize($input['username'] ?? '');
        $amount     = (float)($input['amount'] ?? 0);
        $note       = sanitize($input['note'] ?? '');
        if (!$toUsername || $amount <= 0) jsonError('Invalid request');
        $recipient = DB::first('SELECT * FROM users WHERE username=? AND status="active"', [strtolower($toUsername)]);
        if (!$recipient) jsonError('User not found');
        if ($recipient['id'] == $user['id']) jsonError('Cannot transfer to yourself');
        $wallet = getUserWallet((int)$user['id']);
        if (!$wallet || $wallet['balance'] < $amount) jsonError('Insufficient balance');
        DB::beginTransaction();
        try {
            $ref = generateToken(8);
            if (!deductBalance((int)$user['id'], $amount, 'transfer_out', $ref.'_out', "Transfer to @{$toUsername}: {$note}")) {
                throw new RuntimeException('Failed to debit sender wallet');
            }
            if (!addBalance((int)$recipient['id'], $amount, 'transfer_in', $ref.'_in', "Transfer from @{$user['username']}: {$note}")) {
                throw new RuntimeException('Failed to credit recipient wallet');
            }
            DB::insert('user_transfers', ['sender_id'=>(int)$user['id'],'receiver_id'=>(int)$recipient['id'],'amount'=>$amount,'note'=>$note,'reference'=>$ref,'status'=>'completed','created_at'=>date('Y-m-d H:i:s')]);
            DB::commit();
            createNotification((int)$recipient['id'], 'transfer_received', "@{$user['username']} sent you " . formatCurrency($amount));
            jsonSuccess('Transfer successful');
        } catch (Throwable) { DB::rollback(); jsonError('Transfer failed'); }

    case $path === 'wallet/withdraw' && $method === 'POST':
        requireAuthApi();
        $pts    = (int)($input['points']??0);
        $method2 = sanitize($input['method']??'');
        $acct   = sanitize($input['account_details']??'');
        $minWith= (int)getSetting('min_withdrawal', DEFAULT_MIN_WITHDRAWAL);
        if ($pts < $minWith) jsonError("Minimum withdrawal is {$minWith} pts");
        $wallet = getUserWallet((int)$user['id']);
        if (!$wallet || $wallet['points_balance'] < $pts) jsonError('Insufficient points');
        if ($wallet['is_frozen']) jsonError('Wallet is frozen');
        $reserved = (int)(DB::first(
            'SELECT COALESCE(SUM(amount),0) AS total FROM withdrawals WHERE user_id=? AND status IN("pending","approved","processing")',
            [(int)$user['id']]
        )['total'] ?? 0);
        $availablePoints = max(0, (int)$wallet['points_balance'] - $reserved);
        if ($availablePoints < $pts) {
            jsonError('Insufficient available points after pending withdrawals');
        }
        $rate   = (int)getSetting('points_to_cash_rate', DEFAULT_POINTS_TO_CASH);
        $net    = round($pts / $rate, 2);
        DB::insert('withdrawals', [
            'user_id'        => (int)$user['id'],
            'amount'         => $pts,
            'net_amount'     => $net,
            'method'         => $method2,
            'account_details'=> $acct,
            'status'         => 'pending',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        jsonSuccess('Withdrawal request submitted');

    case $path === 'wallet/convert-points' && $method === 'POST':
        requireAuthApi();
        $pts  = (int)($input['points']??0);
        $min  = (int)getSetting('min_withdrawal', DEFAULT_MIN_WITHDRAWAL);
        if ($pts < $min) jsonError("Minimum convert is {$min} pts");
        $wallet = getUserWallet((int)$user['id']);
        if (!$wallet || $wallet['points_balance'] < $pts) jsonError('Insufficient points');
        $rate = (int)getSetting('points_to_cash_rate', DEFAULT_POINTS_TO_CASH);
        $cash = round($pts / $rate, 2);
        DB::beginTransaction();
        try {
            if (!deductPoints((int)$user['id'], $pts, 'Points converted to cash')) {
                throw new RuntimeException('Failed to deduct points');
            }
            if (!addBalance((int)$user['id'], $cash, 'points_conversion', generateToken(8), "{$pts} pts converted to cash")) {
                throw new RuntimeException('Failed to add balance');
            }
            DB::commit();
            jsonSuccess("Converted {$pts} pts to " . formatCurrency($cash));
        } catch (Throwable) { DB::rollback(); jsonError('Conversion failed'); }

    // ── TIPS ──────────────────────────────────────────
    case $path === 'tips/send' && $method === 'POST':
        requireAuthApi();
        $postId  = (int)($input['post_id']??0);
        $amount  = (int)($input['amount']??0);
        if ($amount < 1) jsonError('Invalid amount');
        $post = DB::first('SELECT * FROM posts WHERE id=?', [$postId]);
        if (!$post) jsonError('Post not found');
        // Prevent self-tipping
        if ((int)$post['user_id'] === (int)$user['id']) jsonError('You cannot tip your own post');
        $wallet = getUserWallet((int)$user['id']);
        if (!$wallet || $wallet['points_balance'] < $amount) jsonError('Insufficient points');
        DB::beginTransaction();
        try {
            if (!deductPoints((int)$user['id'], $amount, "Tip to @{$post['user_id']}")) {
                throw new RuntimeException('Could not deduct points');
            }
            if (!addPoints((int)$post['user_id'], $amount, 'tip_received', "Tip from @{$user['username']}")) {
                throw new RuntimeException('Could not add points');
            }
            DB::insert('tips', ['sender_id'=>(int)$user['id'],'receiver_id'=>(int)$post['user_id'],'amount'=>$amount,'post_id'=>$postId,'created_at'=>date('Y-m-d H:i:s')]);
            DB::commit();
        } catch (Throwable) {
            DB::rollback();
            jsonError('Could not complete the tip right now');
        }
        createNotification((int)$post['user_id'],'tip_received',"@{$user['username']} tipped you {$amount} pts!");
        jsonSuccess('Tip sent!');

    // ── DEPOSIT INITIATE ──────────────────────────────
    case $path === 'payments/deposit/initiate' && $method === 'POST':
        requireAuthApi();
        $amount    = (float)($input['amount']??0);
        $gatewayId = (int)($input['gateway_id']??0);
        if ($amount < 1) jsonError('Invalid amount');
        $gw = DB::first('SELECT * FROM payment_gateways WHERE id=? AND is_active=1', [$gatewayId]);
        if (!$gw) jsonError('Payment gateway not available');
        $ref = 'DEP_' . strtoupper(generateToken(6));
        DB::insert('deposits', [
            'user_id'    => (int)$user['id'],
            'amount'     => $amount,
            'gateway'    => $gw['code'],
            'gateway_ref'=> $ref,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // In production, redirect to gateway payment URL. Return mock for now.
        jsonResponse(['success'=>true,'message'=>'Deposit initiated. Use reference: '.$ref,'reference'=>$ref]);

    // ── ADMIN: GATEWAY UPDATE ─────────────────────────
    case $path === 'admin/gateway/update' && $method === 'POST':
        if (!isset($_SESSION['admin_id'])) jsonError('Unauthorized', 401);
        $admin = DB::first('SELECT role FROM admins WHERE id=?', [(int)$_SESSION['admin_id']]);
        if (!$admin || !in_array($admin['role'], ['super_admin', 'admin'])) jsonError('Access denied', 403);
        $gwId     = (int)($input['id']??0);
        $isActive = (int)($input['is_active']??0);
        DB::update('payment_gateways', ['is_active'=>$isActive], ['id'=>$gwId]);
        if (!empty($input['public_key'])) {
            DB::exec("INSERT INTO payment_gateway_settings (gateway_id,setting_key,setting_value,is_secret) VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE setting_value=?", [$gwId,'public_key',$input['public_key'],$input['public_key']]);
        }
        if (!empty($input['secret_key'])) {
            DB::exec("INSERT INTO payment_gateway_settings (gateway_id,setting_key,setting_value,is_secret) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE setting_value=?", [$gwId,'secret_key',$input['secret_key'],$input['secret_key']]);
        }
        logAdminAction((int)$_SESSION['admin_id'], 'gateway_update', "Updated gateway #{$gwId}");
        jsonSuccess('Gateway updated');

    case $path === 'admin/search' && $method === 'GET':
        if (!isset($_SESSION['admin_id'])) jsonError('Unauthorized', 401);
        $admin = DB::first('SELECT role FROM admins WHERE id=?', [(int)$_SESSION['admin_id']]);
        if (!$admin || !in_array($admin['role'], ['super_admin', 'admin', 'moderator'])) jsonError('Access denied', 403);
        $q = sanitize($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            jsonResponse(['results' => []]);
        }
        $results = DB::query(
            "SELECT id, username, email, status
             FROM users
             WHERE username LIKE ? OR email LIKE ?
             ORDER BY created_at DESC
             LIMIT 10",
            ["%{$q}%", "%{$q}%"]
        );
        jsonResponse(['results' => $results]);

    // ── NOTIFICATIONS ──────────────────────────────────
    case $path === 'notifications' && $method === 'GET':
        requireAuthApi();
        $notifs = DB::query('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30', [(int)$user['id']]);
        DB::exec('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0', [(int)$user['id']]);
        jsonResponse(['notifications'=>$notifs]);

    // ── FOLLOW / UNFOLLOW ────────────────────────────


    // ── VOICE REPLY (supports audio file upload) ──────
    case $path === 'voice/reply' && $method === 'POST':
        requireAuthApi();
        $postId  = (int)($input['post_id']  ?? $_POST['post_id']  ?? 0);
        $text    = sanitize($input['text']  ?? $_POST['text']     ?? '');
        $dur     = (int)($input['duration'] ?? $_POST['duration'] ?? 0);
        if (!$postId) jsonError('post_id required');
        $post = DB::first('SELECT * FROM posts WHERE id=? AND status="active"', [$postId]);
        if (!$post) jsonError('Post not found', 404);
        $audioUrl = null;
        if (!empty($_FILES['audio'])) {
            $up = uploadFile($_FILES['audio'], 'voice');
            if (!$up['ok']) jsonError($up['error']);
            $audioUrl = $up['url'];
        }
        if (!$audioUrl && !$text) jsonError('Provide audio or text for the reply');
        DB::insert('replies', [
            'post_id'    => $postId,
            'user_id'    => (int)$user['id'],
            'audio_url'  => $audioUrl,
            'text'       => $text,
            'duration'   => $dur,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        DB::exec('UPDATE posts SET reply_count=reply_count+1 WHERE id=?', [$postId]);
        addPoints((int)$user['id'], (int)getSetting('points_per_reply', DEFAULT_POINTS_PER_REPLY), 'reply', 'Points for voice reply');
        // Notify post owner
        if ($post['user_id'] != $user['id']) {
            createNotification((int)$post['user_id'], 'reply', "@{$user['username']} replied to your voice post");
        }
        // User audit log
        DB::insert('users_audit_logs', [
            'user_id'     => (int)$user['id'],
            'action'      => 'voice_reply',
            'description' => "Posted voice reply on post #{$postId}",
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        jsonSuccess('Reply posted');

    // ── AD CLICK TRACKING ────────────────────────────
    case preg_match('#^ads/(\d+)/click$#', $path, $m) && $method === 'POST':
        trackAdClick((int)$m[1]);
        jsonSuccess();

    // ── AD IMPRESSION TRACKING ───────────────────────
    case preg_match('#^ads/(\d+)/impression$#', $path, $m) && $method === 'POST':
        trackAdImpression((int)$m[1]);
        jsonSuccess();


    // ── TEXT-ONLY POST CREATE ─────────────────────────
    case $path === 'posts/text-create' && $method === 'POST':
        requireAuthApi();
        $title = sanitize($input['title'] ?? '');
        if (!$title) jsonError('Post text required');
        if (mb_strlen($title) > 280) jsonError('Too long — max 280 characters');
        $imageUrl = null;
        if (!empty($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $imgUp = uploadFile($_FILES['cover_image'], 'image');
            if ($imgUp['ok']) $imageUrl = $imgUp['url'];
        }
        // Extract and store hashtags
        $tags = extractHashtags($title);
        $postId = DB::insert('posts', [
            'user_id'    => (int)$user['id'],
            'channel_id' => (int)($input['channel_id']??0) ?: null,
            'title'      => $title,
            'audio_url'  => null,
            'image_url'  => $imageUrl,
            'duration'   => 0,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        foreach ($tags as $tag) {
            try {
                DB::exec("INSERT IGNORE INTO post_hashtags (post_id, hashtag) VALUES (?,?)", [$postId, $tag]);
            } catch (Throwable) {}
        }
        addPoints((int)$user['id'], (int)getSetting('points_per_post', DEFAULT_POINTS_PER_POST), 'text_post', 'Points for text post');
        jsonSuccess('Posted!', ['post_id' => $postId]);

    // ── BOOST POST ────────────────────────────────────
    case $path === 'posts/boost' && $method === 'POST':
        requireAuthApi();
        $postId  = (int)($input['post_id'] ?? 0);
        $points  = (int)($input['points']  ?? 0);
        $cash    = (float)($input['cash']  ?? 0);
        if (!$postId) jsonError('post_id required');
        $post = DB::first('SELECT * FROM posts WHERE id=? AND status="active"', [$postId]);
        if (!$post) jsonError('Post not found', 404);
        if ($post['user_id'] != $user['id']) jsonError('You can only boost your own posts');
        // Ensure table exists
        DB::exec("CREATE TABLE IF NOT EXISTS `post_boosts` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `post_id`    INT UNSIGNED NOT NULL,
            `user_id`    INT UNSIGNED NOT NULL,
            `type`       ENUM('points','cash') DEFAULT 'points',
            `amount`     DECIMAL(10,2) DEFAULT 0,
            `status`     ENUM('active','expired','cancelled') DEFAULT 'active',
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_post (post_id), INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Ensure post_hashtags table
        DB::exec("CREATE TABLE IF NOT EXISTS `post_hashtags` (
            `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `post_id` INT UNSIGNED NOT NULL,
            `hashtag` VARCHAR(50) NOT NULL,
            INDEX idx_post (post_id), INDEX idx_tag (hashtag)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if ($points > 0) {
            $wallet = getUserWallet((int)$user['id']);
            if (!$wallet || $wallet['points_balance'] < $points) jsonError('Insufficient points');
            deductPoints((int)$user['id'], $points, "Boost post #{$postId}");
            $type = 'points'; $amount = $points;
        } elseif ($cash > 0) {
            $wallet = getUserWallet((int)$user['id']);
            if (!$wallet || $wallet['balance'] < $cash) jsonError('Insufficient balance');
            deductBalance((int)$user['id'], $cash, 'boost', generateToken(8), "Boost post #{$postId}");
            $type = 'cash'; $amount = $cash;
        } else {
            jsonError('Specify points or cash amount');
        }
        DB::insert('post_boosts', [
            'post_id'    => $postId,
            'user_id'    => (int)$user['id'],
            'type'       => $type,
            'amount'     => $amount,
            'status'     => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        jsonSuccess('Post boosted for 24 hours!');

    // ── PODCAST CREATE ────────────────────────────────
    case $path === 'podcast/create' && $method === 'POST':
        requireAuthApi();
        $title    = sanitize($input['title']       ?? $_POST['title']       ?? '');
        $desc     = sanitize($input['description'] ?? $_POST['description'] ?? '');
        $category = sanitize($input['category']    ?? $_POST['category']    ?? 'general');
        $dur      = (int)($input['duration']       ?? $_POST['duration']    ?? 0);
        if (!$title) jsonError('Title required');
        if (empty($_FILES['audio'])) jsonError('Audio file required');
        if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            jsonError('Upload error code: ' . $_FILES['audio']['error']);
        }
        // Plan-based duration check
        $podLimit = getPodcastLimit((int)$user['id']);
        if ($podLimit > 0 && $dur > $podLimit) {
            jsonError("Episode too long for your plan. Max: " . formatDuration($podLimit));
        }
        $up = uploadFile($_FILES['audio'], 'voice');
        if (!$up['ok']) jsonError($up['error']);
        $coverUrl = null;
        if (!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $cu = uploadFile($_FILES['cover'], 'image');
            if ($cu['ok']) $coverUrl = $cu['url'];
        }
        DB::exec("CREATE TABLE IF NOT EXISTS `podcasts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL, `title` VARCHAR(200) NOT NULL,
            `description` TEXT DEFAULT NULL, `audio_url` VARCHAR(255) NOT NULL,
            `cover_url` VARCHAR(255) DEFAULT NULL, `duration` INT UNSIGNED DEFAULT 0,
            `category` VARCHAR(60) DEFAULT 'general', `play_count` INT UNSIGNED DEFAULT 0,
            `like_count` INT UNSIGNED DEFAULT 0, `status` ENUM('active','removed') DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $podId = DB::insert('podcasts', [
            'user_id'     => (int)$user['id'],
            'title'       => $title,
            'description' => $desc,
            'audio_url'   => $up['url'],
            'cover_url'   => $coverUrl,
            'duration'    => $dur,
            'category'    => $category,
            'status'      => 'active',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        jsonSuccess('Podcast published!', ['podcast_id' => $podId]);

    // ── PODCAST PLAY TRACKING ─────────────────────────
    case preg_match('#^podcast/(\d+)/play$#', $path, $m) && $method === 'POST':
        DB::exec("UPDATE podcasts SET play_count=play_count+1 WHERE id=?", [(int)$m[1]]);
        jsonSuccess();

    // ── PODCAST DELETE ────────────────────────────────
    case preg_match('#^podcast/(\d+)$#', $path, $m) && $method === 'DELETE':
        requireAuthApi();
        $pod = DB::first('SELECT * FROM podcasts WHERE id=?', [(int)$m[1]]);
        if (!$pod || $pod['user_id'] != $user['id']) jsonError('Not found', 404);
        DB::update('podcasts', ['status' => 'removed'], ['id' => (int)$m[1]]);
        jsonSuccess('Deleted');

    // ── MESSAGES: SEND ────────────────────────────────
    case $path === 'messages/send' && $method === 'POST':
        requireAuthApi();
        $recipientId = (int)($input['recipient_id'] ?? 0);
        $convIdIn    = (int)($input['conversation_id'] ?? 0);
        $body        = sanitize($input['body'] ?? '');
        if (!$body) jsonError('Message body required');
        // Resolve recipient from conversation or username
        if (!$recipientId && !empty($input['username'])) {
            $rUser = DB::first('SELECT id FROM users WHERE username=? AND status="active"', [strtolower(sanitize($input['username']))]);
            if (!$rUser) jsonError('User not found', 404);
            $recipientId = (int)$rUser['id'];
        }
        if (!$recipientId && $convIdIn) {
            $existConv = DB::first("SELECT * FROM message_conversations WHERE id=? AND (user_a=? OR user_b=?)", [$convIdIn,(int)$user['id'],(int)$user['id']]);
            if (!$existConv) jsonError('Conversation not found', 404);
            $recipientId = $existConv['user_a'] == $user['id'] ? (int)$existConv['user_b'] : (int)$existConv['user_a'];
        }
        if (!$recipientId) jsonError('Recipient required');
        $check = canSendMessage((int)$user['id'], $recipientId);
        if (!$check['allowed']) jsonError($check['reason'], 403);
        $convId2 = getOrCreateConversation((int)$user['id'], $recipientId);
        $msgId   = DB::insert('messages', [
            'conversation_id' => $convId2,
            'sender_id'       => (int)$user['id'],
            'body'            => $body,
            'is_read'         => 0,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        DB::update('message_conversations', ['last_message_id'=>$msgId,'updated_at'=>date('Y-m-d H:i:s')], ['id'=>$convId2]);
        createNotification($recipientId, 'message', "@{$user['username']} sent you a message");
        jsonSuccess('Sent', ['conversation_id'=>$convId2, 'message_id'=>$msgId, 'created_at'=>date('H:i')]);

    // ── MESSAGES: GET CONVERSATION ────────────────────
    case preg_match('#^messages/(\d+)$#', $path, $m) && $method === 'GET':
        requireAuthApi();
        $convId3 = (int)$m[1];
        $after   = (int)($_GET['after'] ?? 0);
        $conv = DB::first("SELECT * FROM message_conversations WHERE id=? AND (user_a=? OR user_b=?)", [$convId3,(int)$user['id'],(int)$user['id']]);
        if (!$conv) jsonError('Not found', 404);
        $msgs = DB::query(
            "SELECT m.*, u.username FROM messages m JOIN users u ON u.id=m.sender_id
             WHERE m.conversation_id=? AND m.id>? ORDER BY m.created_at ASC LIMIT 50",
            [$convId3, $after]
        );
        DB::exec('UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id!=?', [$convId3,(int)$user['id']]);
        jsonResponse(['messages' => $msgs]);

    // ── MESSAGES: RESPOND TO REQUEST ──────────────────
    case $path === 'messages/respond' && $method === 'POST':
        requireAuthApi();
        $convId4 = (int)($input['conversation_id'] ?? 0);
        $action  = sanitize($input['action'] ?? '');
        if (!in_array($action, ['accept','block'])) jsonError('Invalid action');
        $conv = DB::first("SELECT * FROM message_conversations WHERE id=? AND user_b=?", [$convId4,(int)$user['id']]);
        if (!$conv) jsonError('Not found or permission denied', 404);
        $newStatus = $action === 'accept' ? 'accepted' : 'blocked';
        DB::update('message_conversations', ['status'=>$newStatus], ['id'=>$convId4]);
        if ($action === 'accept') {
            createNotification((int)$conv['user_a'], 'message_accepted', "@{$user['username']} accepted your message request");
        }
        jsonSuccess(ucfirst($action) . 'ed');

    // ── MESSAGES: CONVERSATIONS LIST ──────────────────
    case $path === 'messages/conversations' && $method === 'GET':
        requireAuthApi();
        $convs = DB::query(
            "SELECT mc.*, CASE WHEN mc.user_a=? THEN mc.user_b ELSE mc.user_a END AS other_id,
                    u.username AS other_username,
                    (SELECT body FROM messages WHERE conversation_id=mc.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
                    (SELECT COUNT(*) FROM messages WHERE conversation_id=mc.id AND sender_id!=? AND is_read=0) AS unread_count
             FROM message_conversations mc
             JOIN users u ON u.id = CASE WHEN mc.user_a=? THEN mc.user_b ELSE mc.user_a END
             WHERE mc.user_a=? OR mc.user_b=?
             ORDER BY mc.updated_at DESC",
            [(int)$user['id'],(int)$user['id'],(int)$user['id'],(int)$user['id'],(int)$user['id']]
        );
        jsonResponse(['conversations' => $convs]);

    // ── SET LANGUAGE ─────────────────────────────────────
    case $path === 'user/set-lang' && $method === 'POST':
        if (!defined('VOXU_LANGUAGES')) require_once __DIR__ . '/../../core/i18n.php';
        $lang = sanitize($input['lang'] ?? 'en');
        $allowed = array_keys(VOXU_LANGUAGES);
        if (!in_array($lang, $allowed)) jsonError('Invalid language');
        $_SESSION['voxu_lang'] = $lang;
        setcookie('voxu_lang', $lang, time()+60*60*24*365, '/', '', false, false);
        jsonSuccess('Language set', ['lang' => $lang]);

    // ── GET LANGUAGE STRINGS ──────────────────────────────
    case $path === 'i18n/strings' && $method === 'GET':
        if (!defined('VOXU_LANGUAGES')) require_once __DIR__ . '/../../core/i18n.php';
        global $VOXU_STRINGS;
        $lang    = sanitize($_GET['lang'] ?? getCurrentLang());
        $allowed = array_keys(VOXU_LANGUAGES);
        if (!in_array($lang, $allowed)) $lang = 'en';
        $strings = [];
        foreach ($VOXU_STRINGS as $key => $translations) {
            $strings[$key] = $translations[$lang] ?? $translations['en'] ?? $key;
        }
        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=86400');
        echo json_encode(['success'=>true,'lang'=>$lang,'strings'=>$strings,'rtl'=> VOXU_LANGUAGES[$lang]['rtl'] ?? false]);
        exit;


    // ── EMOJI REACTIONS ──────────────────────────────────
    case preg_match('#^posts/(\d+)/react$#', $path, $m) && $method === 'POST':
        requireAuthApi();
        $emoji   = sanitize($input['emoji'] ?? '👍');
        $allowed = ['👍','❤️','🔥','😂','😮','😢','🎉','💯','🙏','👏','🎙','⚡'];
        if (!in_array($emoji, $allowed)) jsonError('Invalid emoji');
        $result = toggleReaction((int)$user['id'], (int)$m[1], $emoji);
        $reactions = getPostReactions((int)$m[1]);
        jsonSuccess('OK', ['result' => $result, 'reactions' => $reactions]);

    // ── GET POST REACTIONS ────────────────────────────────
    case preg_match('#^posts/(\d+)/reactions$#', $path, $m) && $method === 'GET':
        $reactions  = getPostReactions((int)$m[1]);
        $mine       = isset($user) ? getUserReaction((int)$user['id'], (int)$m[1]) : null;
        jsonResponse(['reactions' => $reactions, 'mine' => $mine]);

    // ── VIDEO / VOICE REPLY ───────────────────────────────
    case $path === 
    // ── DEFAULT 404 ───────────────────────────────────
    default:
        jsonError("Endpoint not found: {$method} /{$path}", 404);
}

// ── HELPERS ───────────────────────────────────────────
function requireAuthApi(): void {
    global $user;
    if (!$user) jsonError('Authentication required', 401);
}

function isAdmin(): bool {
    return isset($_SESSION['admin_id']);
}
