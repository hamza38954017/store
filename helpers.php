<?php
// helpers.php — Render frontend → Firebase Realtime Database (REST API)
// Replace old env vars (API_BASE, API_KEY) with these in Render Dashboard:
//   FB_URL    = https://your-project-default-rtdb.firebaseio.com
//   FB_SECRET = your Firebase Database Secret
//              (Firebase Console → Project Settings → Service Accounts → Database secrets)

define('FB_URL',    rtrim($_ENV['FB_URL']    ?? getenv('FB_URL')    ?: '', '/'));
define('FB_SECRET', $_ENV['FB_SECRET'] ?? getenv('FB_SECRET') ?: '');

date_default_timezone_set('Asia/Kolkata');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// ════════════════════════════════════════════════════════════════════════════
// FIREBASE REST HELPERS
// ════════════════════════════════════════════════════════════════════════════

function fbRequest(string $path, string $method = 'GET', $data = null, array $query = []): mixed {
    $query['auth'] = FB_SECRET;
    $url = FB_URL . '/' . ltrim($path, '/') . '.json?' . http_build_query($query);
    $ch  = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 15,
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    }
    curl_setopt_array($ch, $opts);
    $out = curl_exec($ch);
    curl_close($ch);
    return $out ? json_decode($out, true) : null;
}

/** GET a Firebase node */
function fbGet(string $path, array $query = []): mixed {
    return fbRequest($path, 'GET', null, $query);
}

/** POST to Firebase (push), returns the auto-generated key */
function fbPush(string $path, array $data): string {
    $result = fbRequest($path, 'POST', $data);
    return $result['name'] ?? '';
}

/** PUT — set/replace a node entirely */
function fbPut(string $path, $data): void {
    fbRequest($path, 'PUT', $data);
}

/** PATCH — update specific fields only */
function fbPatch(string $path, array $data): void {
    fbRequest($path, 'PATCH', $data);
}

/** DELETE a node */
function fbDelete(string $path): void {
    fbRequest($path, 'DELETE');
}

/**
 * Convert Firebase key→object map to a flat indexed array,
 * injecting the Firebase key as $keyField on each item.
 */
function fbToArray(?array $data, string $keyField = 'id'): array {
    if (!$data) return [];
    $out = [];
    foreach ($data as $key => $val) {
        if (is_array($val)) {
            $val[$keyField] = $key;
            $out[] = $val;
        }
    }
    return $out;
}

/** Return all settings as key=>value array */
function getAllSettings(): array {
    $data = fbGet('settings');
    return is_array($data) ? $data : [];
}

/** Return one setting value */
function getSetting(string $key, string $default = ''): string {
    return getAllSettings()[$key] ?? $default;
}

// ════════════════════════════════════════════════════════════════════════════
// MAIN API ENTRY — same signature as before, no other files need to change
// ════════════════════════════════════════════════════════════════════════════

function apiCall(string $action, array $data = []): array {
    $data['action'] = $action;
    return dispatchAction($data);
}

function dispatchAction(array $input): array {
    $action = $input['action'] ?? '';

    switch ($action) {

    // ════════════════════════════════════════════════════════════════════════
    // INDEX.PHP ACTIONS
    // ════════════════════════════════════════════════════════════════════════

    case 'log_visitor':
        fbPush('visitor_logs', [
            'session_id'   => $input['session_id'] ?? '',
            'ip_address'   => $input['ip']         ?? '',
            'country'      => '',
            'user_agent'   => $input['ua']         ?? '',
            'browser_name' => $input['browser']    ?? '',
            'os_name'      => $input['os']         ?? '',
            'referrer'     => $input['referer']    ?? '',
            'page_url'     => $input['page_url']   ?? '',
            'visited_at'   => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true];

    case 'get_page_data':
        $settings = getAllSettings();
        $search   = strtolower(trim($input['q']       ?? ''));
        $fType    = $input['type']    ?? '';
        $fCountry = $input['country'] ?? '';

        $allPhotos = fbToArray(fbGet('photos'));

        $photos = array_values(array_filter($allPhotos, function ($p) use ($search, $fType, $fCountry) {
            if (($p['status'] ?? '') !== 'active') return false;
            if ($fType    && ($p['type']    ?? '') !== $fType)    return false;
            if ($fCountry && ($p['country'] ?? '') !== $fCountry) return false;
            if ($search) {
                $hay = strtolower(($p['title'] ?? '') . ' ' . ($p['tags'] ?? '') . ' ' . ($p['country'] ?? ''));
                if (strpos($hay, $search) === false) return false;
            }
            return true;
        }));

        usort($photos, fn($a, $b) =>
            ($b['featured'] ?? 0) <=> ($a['featured'] ?? 0)
            ?: strcmp($b['created_at'] ?? '', $a['created_at'] ?? '')
        );

        $activePhotos = array_values(array_filter($allPhotos, fn($p) => ($p['status'] ?? '') === 'active'));
        $types        = array_values(array_unique(array_filter(array_column($activePhotos, 'type'))));
        $countries    = array_values(array_unique(array_filter(array_column($activePhotos, 'country'))));
        sort($types); sort($countries);

        $allSlides = fbToArray(fbGet('carousel_slides'));
        $slides    = array_values(array_filter($allSlides, fn($s) => ($s['status'] ?? '') === 'active'));
        usort($slides, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        $slides = array_slice($slides, 0, 15);

        return ['settings' => $settings, 'photos' => $photos, 'types' => $types, 'countries' => $countries, 'slides' => $slides];

    case 'get_counts':
        $sid   = $input['session_id'] ?? '';
        $cart  = $sid ? count((array)(fbGet("cart/$sid") ?? [])) : 0;
        $notifs = fbToArray(fbGet('notifications'));
        $notif  = count(array_filter($notifs, fn($n) => empty($n['is_read'])));
        return ['cart_count' => $cart, 'notif_count' => $notif];

    case 'get_photo':
        $slug  = $input['slug'] ?? '';
        $all   = fbToArray(fbGet('photos'));
        $photo = null;
        foreach ($all as $p) {
            if (($p['slug'] ?? '') === $slug && ($p['status'] ?? '') === 'active') {
                $photo = $p; break;
            }
        }
        if (!$photo) return ['photo' => null, 'related' => [], 'reviews' => []];

        // Track view
        fbPush('photo_analytics', [
            'photo_id'   => $photo['id'],
            'photo_slug' => $photo['slug'] ?? '',
            'event_type' => 'view',
            'session_id' => $input['session_id'] ?? '',
            'ip_address' => $input['ip'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Related
        $related = array_values(array_filter($all, fn($p) =>
            ($p['id'] ?? '') !== $photo['id'] &&
            ($p['status'] ?? '') === 'active' &&
            (($p['type'] ?? '') === ($photo['type'] ?? '') || ($p['country'] ?? '') === ($photo['country'] ?? ''))
        ));
        if (count($related) < 6) {
            $more    = array_values(array_filter($all, fn($p) => ($p['id'] ?? '') !== $photo['id'] && ($p['status'] ?? '') === 'active'));
            $merged  = array_values(array_unique(array_merge($related, $more), SORT_REGULAR));
            $related = $merged;
        }
        $related = array_slice($related, 0, 6);

        // Reviews
        $allReviews = fbToArray(fbGet('reviews'));
        $reviews    = array_values(array_filter($allReviews, fn($r) =>
            ($r['photo_id'] ?? '') === ($photo['id'] ?? '') && ($r['status'] ?? '') === 'approved'
        ));
        usort($reviews, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return ['photo' => $photo, 'related' => $related, 'reviews' => $reviews];

    case 'get_notifications':
        $notifs = fbToArray(fbGet('notifications'));
        usort($notifs, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_slice($notifs, 0, 10); // returns array directly (JS expects array)

    case 'add_cart':
        $sid = $input['session_id'] ?? '';
        $pid = $input['photo_id']   ?? '';
        if (!$sid || !$pid) return ['success' => false, 'count' => 0];
        if (!fbGet("cart/$sid/$pid")) {
            fbPut("cart/$sid/$pid", ['added_at' => date('Y-m-d H:i:s')]);
            fbPush('photo_analytics', [
                'photo_id'   => $pid,
                'photo_slug' => '',
                'event_type' => 'add_to_cart',
                'session_id' => $sid,
                'ip_address' => $input['ip'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $count = count((array)(fbGet("cart/$sid") ?? []));
        return ['success' => true, 'count' => $count];

    case 'get_cart':
        return ['items' => _getCartItems($input['session_id'] ?? '')];

    case 'remove_cart':
        $sid = $input['session_id'] ?? '';
        $pid = $input['photo_id']   ?? '';
        if ($sid && $pid) fbDelete("cart/$sid/$pid");
        return ['success' => true];

    case 'clear_cart':
        $sid = $input['session_id'] ?? '';
        if ($sid) fbDelete("cart/$sid");
        return ['success' => true];

    case 'mark_notif_read':
        $id = $input['id'] ?? '';
        if ($id) fbPatch("notifications/$id", ['is_read' => 1]);
        return ['success' => true];

    case 'track_event':
        $pid   = $input['photo_id'] ?? '';
        $event = in_array($input['event'] ?? '', ['view', 'purchase_click', 'add_to_cart'])
                 ? $input['event'] : 'view';
        fbPush('photo_analytics', [
            'photo_id'   => $pid,
            'photo_slug' => '',
            'event_type' => $event,
            'session_id' => $input['session_id'] ?? '',
            'ip_address' => $input['ip'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true];

    // ════════════════════════════════════════════════════════════════════════
    // CART.PHP ACTIONS
    // ════════════════════════════════════════════════════════════════════════

    case 'get_cart_page':
        return [
            'items'    => _getCartItems($input['session_id'] ?? ''),
            'settings' => getAllSettings(),
        ];

    // ════════════════════════════════════════════════════════════════════════
    // PAYMENT.PHP ACTIONS
    // ════════════════════════════════════════════════════════════════════════

    case 'get_payment_data':
        $sid      = $input['session_id'] ?? '';
        $pid      = $input['photo_id']   ?? '';
        $fromCart = !empty($input['from_cart']);
        $s        = getAllSettings();
        $photo = null; $cartPhotos = []; $total = 0;

        if ($fromCart && $sid) {
            $cartPhotos = _getCartItems($sid);
            foreach ($cartPhotos as $cp) $total += (float)($cp['price'] ?? 0);
        } elseif ($pid) {
            $data = fbGet("photos/$pid");
            if ($data && ($data['status'] ?? '') === 'active') {
                $data['id'] = $pid;
                $photo = $data;
                $total = (float)($photo['price'] ?? 0);
            }
        }
        return ['photo' => $photo, 'cart_photos' => $cartPhotos, 'total' => $total, 'settings' => $s];

    case 'log_payment_session':
        $orderId = $input['order_id'] ?? '';
        $token   = md5($orderId . FB_SECRET);
        fbPut("payment_sessions/$orderId", [
            'order_id'      => $orderId,
            'photo_id'      => $input['photo_id']     ?? null,
            'photo_title'   => $input['photo_title']  ?? '',
            'amount'        => (float)($input['amount'] ?? 0),
            'session_token' => $token,
            'customer_ip'   => $input['ip']           ?? '',
            'browser_info'  => $input['browser_info'] ?? '',
            'utr_submitted' => false,
            'timed_out'     => false,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'session_token' => $token];

    case 'submit_utr':
        $orderId  = $input['order_id']           ?? '';
        $utr      = preg_replace('/\D/', '', $input['utr'] ?? '');
        $sid      = $input['session_id']         ?? '';
        $fromCart = !empty($input['from_cart']);
        $timeout  = (int)($input['countdown_total']     ?? 300);
        $cntAt    = (int)($input['countdown_at_submit'] ?? 0);

        if (strlen($utr) !== 12) return ['success' => false, 'msg' => 'UTR must be exactly 12 digits.'];

        fbPush('purchases', [
            'order_id'            => $orderId,
            'photo_id'            => $input['photo_id']     ?? null,
            'photo_title'         => $input['photo_title']  ?? '',
            'amount'              => (float)($input['amount'] ?? 0),
            'utr_number'          => $utr,
            'payment_method'      => $input['method']       ?? 'UPI',
            'payment_status'      => 'pending',
            'customer_ip'         => $input['ip']           ?? '',
            'browser_info'        => $input['browser_info'] ?? '',
            'device_info'         => $input['device_info']  ?? '',
            'session_token'       => md5($orderId . FB_SECRET),
            'countdown_total'     => $timeout,
            'countdown_at_submit' => $cntAt,
            'time_taken_seconds'  => $timeout - $cntAt,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
        fbPatch("payment_sessions/$orderId", ['utr_submitted' => true]);
        if ($fromCart && $sid) fbDelete("cart/$sid");
        return ['success' => true];

    case 'timeout_notify':
        $orderId = $input['order_id'] ?? '';
        if ($orderId) fbPatch("payment_sessions/$orderId", ['timed_out' => true]);
        return ['ok' => true];

    // ════════════════════════════════════════════════════════════════════════
    // ADMIN.PHP ACTIONS
    // ════════════════════════════════════════════════════════════════════════

    case 'admin_login':
        $u = $input['username'] ?? '';
        $p = $input['password'] ?? '';
        if ($u === getSetting('admin_username', 'admin') && $p === getSetting('admin_password', 'admin123'))
            return ['success' => true];
        return ['success' => false, 'msg' => 'Invalid credentials.'];

    case 'get_admin_data':
        $allPhotos    = fbToArray(fbGet('photos'));
        $allPurchases = fbToArray(fbGet('purchases'));
        $allSessions  = fbToArray(fbGet('payment_sessions'));
        $allReviews   = fbToArray(fbGet('reviews'));
        $allNotifs    = fbToArray(fbGet('notifications'));
        $allSlides    = fbToArray(fbGet('carousel_slides'));
        $allVisitors  = fbToArray(fbGet('visitor_logs'));
        $allAnalytics = fbToArray(fbGet('photo_analytics'));

        $activePhotos  = array_values(array_filter($allPhotos,    fn($p) => ($p['status'] ?? '') === 'active'));
        $verifiedPurch = array_values(array_filter($allPurchases, fn($p) => ($p['payment_status'] ?? '') === 'verified'));
        $pendingPurch  = array_values(array_filter($allPurchases, fn($p) => ($p['payment_status'] ?? '') === 'pending'));
        $utrPurchases  = array_values(array_filter($allPurchases, fn($p) => !empty($p['utr_number'])));
        $noUtrSessions = array_values(array_filter($allSessions,  fn($s) => empty($s['utr_submitted'])));

        $verifiedRev = array_sum(array_column($verifiedPurch, 'amount'));
        $uniqueIPs   = count(array_unique(array_filter(array_column($allVisitors, 'ip_address'))));
        $totalViews  = count(array_filter($allAnalytics, fn($a) => ($a['event_type'] ?? '') === 'view'));

        // Photo analytics per photo
        $photoStats = [];
        foreach ($allPhotos as $p) {
            $pid = $p['id'];
            $photoStats[] = [
                'id'              => $pid,
                'title'           => $p['title'] ?? '',
                'slug'            => $p['slug']  ?? '',
                'views'           => count(array_filter($allAnalytics, fn($a) => ($a['photo_id'] ?? '') === $pid && ($a['event_type'] ?? '') === 'view')),
                'purchase_clicks' => count(array_filter($allAnalytics, fn($a) => ($a['photo_id'] ?? '') === $pid && ($a['event_type'] ?? '') === 'purchase_click')),
                'cart_adds'       => count(array_filter($allAnalytics, fn($a) => ($a['photo_id'] ?? '') === $pid && ($a['event_type'] ?? '') === 'add_to_cart')),
            ];
        }
        usort($photoStats, fn($a, $b) => $b['views'] <=> $a['views']);

        // Paginate visitors
        $vPage  = max(1, (int)($input['vpage'] ?? 1));
        $vLimit = 50;
        usort($allVisitors, fn($a, $b) => strcmp($b['visited_at'] ?? '', $a['visited_at'] ?? ''));
        $vTotal   = count($allVisitors);
        $vPages   = max(1, (int)ceil($vTotal / $vLimit));
        $visitors = array_slice($allVisitors, ($vPage - 1) * $vLimit, $vLimit);

        // Sort datasets
        usort($allPhotos,    fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        usort($allPurchases, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        usort($allNotifs,    fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        usort($allReviews,   fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        usort($allSlides,    fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return [
            'stats' => [
                'totalPhotos'    => count($activePhotos),
                'totalPurchases' => count($allPurchases),
                'pendingPurch'   => count($pendingPurch),
                'verifiedRev'    => $verifiedRev,
                'totalVisitors'  => $uniqueIPs,
                'totalViews'     => $totalViews,
                'utrSubmitted'   => count($utrPurchases),
                'utrNotSubmitted'=> count($noUtrSessions),
            ],
            'photos'         => $allPhotos,
            'purchases'      => array_slice($allPurchases, 0, 100),
            'purchasesUTR'   => array_slice($utrPurchases, 0, 100),
            'sessionsNoUTR'  => array_slice($noUtrSessions, 0, 100),
            'reviews'        => $allReviews,
            'notifications'  => array_slice($allNotifs, 0, 30),
            'slides'         => $allSlides,
            'photoStats'     => $photoStats,
            'visitors'       => $visitors,
            'vTotal'         => $vTotal,
            'vPages'         => $vPages,
            'vPage'          => $vPage,
            'recentRevUsers' => array_slice($verifiedPurch, 0, 20),
            'settings'       => getAllSettings(),
        ];

    case 'save_settings':
        $fields = ['site_name','site_title','copyright_name','copyright_year','upi_id','upi_name',
                   'telegram_url','telegram_support_user','instagram_url','youtube_url','facebook_url',
                   'twitter_url','primary_color','secondary_color','accent_color','bg_color','text_color',
                   'payment_timeout','meta_description','privacy_policy','terms_conditions','guide_image_url',
                   'faq_1_q','faq_1_a','faq_2_q','faq_2_a','faq_3_q','faq_3_a',
                   'faq_4_q','faq_4_a','faq_5_q','faq_5_a','faq_6_q','faq_6_a',
                   'admin_username','admin_password'];
        $patch = [];
        foreach ($fields as $f) {
            if (!isset($input[$f])) continue;
            if ($f === 'admin_password' && $input[$f] === '') continue;
            $patch[$f] = $input[$f];
        }
        if ($patch) fbPatch('settings', $patch);
        return ['success' => true, 'msg' => 'Settings saved successfully!'];

    case 'save_photo':
        $id    = $input['photo_id'] ?? '';
        $title = trim($input['title'] ?? '');
        $slug  = trim($input['slug'] ?? '') ?: generateSlug($title);

        // Ensure slug is unique
        $all = fbToArray(fbGet('photos'));
        foreach ($all as $p) {
            if (($p['slug'] ?? '') === $slug && ($p['id'] ?? '') !== $id) {
                $slug .= '-' . time(); break;
            }
        }

        $data = [
            'title'            => $title,
            'slug'             => $slug,
            'description'      => $input['description']      ?? '',
            'price'            => (float)($input['price']        ?? 0),
            'actual_price'     => (float)($input['actual_price'] ?? 0),
            'country'          => $input['country']          ?? '',
            'type'             => $input['type']             ?? '',
            'image_url'        => $input['image_url']        ?? '',
            'thumbnail_url'    => $input['thumbnail_url']    ?? '',
            'tags'             => $input['tags']             ?? '',
            'meta_title'       => $input['meta_title']       ?? '',
            'meta_description' => $input['meta_description'] ?? '',
            'featured'         => ($input['featured'] ?? 0) ? 1 : 0,
            'status'           => $input['status']           ?? 'active',
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            fbPatch("photos/$id", $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = fbPush('photos', $data);
        }
        return ['success' => true, 'id' => $id, 'msg' => 'Photo saved!'];

    case 'delete_photo':
        $id = $input['photo_id'] ?? '';
        if ($id) fbDelete("photos/$id");
        return ['success' => true];

    case 'update_purchase':
        $id = $input['purchase_id'] ?? '';
        if ($id) fbPatch("purchases/$id", [
            'payment_status' => $input['status'] ?? 'pending',
            'notes'          => $input['notes']  ?? '',
        ]);
        return ['success' => true];

    case 'add_notification':
        fbPush('notifications', [
            'title'      => $input['title']   ?? '',
            'message'    => $input['message'] ?? '',
            'type'       => $input['type']    ?? 'info',
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['success' => true];

    case 'delete_notification':
        $id = $input['notif_id'] ?? '';
        if ($id) fbDelete("notifications/$id");
        return ['success' => true];

    case 'save_review':
        $rid = $input['review_id'] ?? '';
        if ($rid) {
            fbPatch("reviews/$rid", [
                'reviewer_name' => $input['reviewer_name'] ?? '',
                'rating'        => (int)($input['rating']  ?? 5),
                'comment'       => $input['comment']       ?? '',
                'status'        => $input['status']        ?? 'approved',
            ]);
        } else {
            fbPush('reviews', [
                'photo_id'      => $input['photo_id']      ?? '',
                'reviewer_name' => $input['reviewer_name'] ?? '',
                'rating'        => (int)($input['rating']  ?? 5),
                'comment'       => $input['comment']       ?? '',
                'status'        => $input['status']        ?? 'approved',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        }
        return ['success' => true];

    case 'delete_review':
        $id = $input['review_id'] ?? '';
        if ($id) fbDelete("reviews/$id");
        return ['success' => true];

    case 'save_slide':
        $sid = $input['slide_id'] ?? '';
        if ($sid) {
            fbPatch("carousel_slides/$sid", [
                'image_url'  => $input['image_url']        ?? '',
                'caption'    => $input['caption']          ?? '',
                'sort_order' => (int)($input['sort_order'] ?? 0),
                'status'     => $input['status']           ?? 'active',
            ]);
        } else {
            $count = count(fbToArray(fbGet('carousel_slides')));
            if ($count >= 15) return ['success' => false, 'msg' => 'Maximum 15 slides allowed.'];
            fbPush('carousel_slides', [
                'image_url'  => $input['image_url']        ?? '',
                'caption'    => $input['caption']          ?? '',
                'sort_order' => (int)($input['sort_order'] ?? 0),
                'status'     => $input['status']           ?? 'active',
            ]);
        }
        return ['success' => true];

    case 'delete_slide':
        $id = $input['slide_id'] ?? '';
        if ($id) fbDelete("carousel_slides/$id");
        return ['success' => true];

    default:
        return ['error' => 'Unknown action: ' . $action];
    }
}

// ════════════════════════════════════════════════════════════════════════════
// INTERNAL HELPERS
// ════════════════════════════════════════════════════════════════════════════

/** Fetch cart items for a session, joining photo data from Firebase */
function _getCartItems(string $sid): array {
    if (!$sid) return [];
    $cartData = fbGet("cart/$sid");
    if (!is_array($cartData)) return [];
    $items = [];
    foreach (array_keys($cartData) as $pid) {
        $photo = fbGet("photos/$pid");
        if ($photo && ($photo['status'] ?? '') === 'active') {
            $photo['id'] = $pid;
            $items[] = $photo;
        }
    }
    return $items;
}

// ════════════════════════════════════════════════════════════════════════════
// UTILITY FUNCTIONS (unchanged)
// ════════════════════════════════════════════════════════════════════════════

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function getClientIP(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        return filter_var(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0], FILTER_VALIDATE_IP) ?: '0.0.0.0';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getBrowserInfo(): string {
    return json_encode([
        'user_agent'  => $_SERVER['HTTP_USER_AGENT']      ?? '',
        'accept_lang' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        'referer'     => $_SERVER['HTTP_REFERER']         ?? '',
        'timestamp'   => date('Y-m-d H:i:s'),
        'ip'          => getClientIP(),
    ]);
}

function parseBrowserName(string $ua): string {
    if (stripos($ua, 'Edg/')    !== false) return 'Edge';
    if (stripos($ua, 'OPR/')    !== false) return 'Opera';
    if (stripos($ua, 'Chrome')  !== false) return 'Chrome';
    if (stripos($ua, 'Safari')  !== false) return 'Safari';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    if (stripos($ua, 'MSIE')    !== false || stripos($ua, 'Trident') !== false) return 'IE';
    return 'Other';
}

function parseOSName(string $ua): string {
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'iPhone')  !== false || stripos($ua, 'iPad')    !== false) return 'iOS';
    if (stripos($ua, 'Mac OS')  !== false) return 'macOS';
    if (stripos($ua, 'Linux')   !== false) return 'Linux';
    return 'Other';
}

function generateOrderId(): string {
    return 'PS' . date('ymd') . strtoupper(substr(uniqid(), -6));
}

function generateSlug(string $title): string {
    $s = strtolower(trim($title));
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    $s = preg_replace('/[\s-]+/', '-', $s);
    return trim($s, '-');
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}
?>
