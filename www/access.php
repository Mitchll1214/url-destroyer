<?php
/**
 * Public Access Handler — validates token, serves target content, logs access
 *
 * URL: /access.php?token=<token>
 * Pretty: /access/<token>  (via .htaccess rewrite)
 */

require_once __DIR__ . '/db.php';

$db = getDB();

// ── Get token ──
$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    showError('缺少 token 参数。');
}

// ── Look up link ──
$stmt = $db->prepare("SELECT * FROM links WHERE token = :token LIMIT 1");
$stmt->execute([':token' => $token]);
$link = $stmt->fetch();

if (!$link) {
    showError('链接不存在或已被删除。');
}

// ── Check pre-expired status ──
if ($link['status'] === 'expired') {
    showError('此链接已失效。');
}

$now = new DateTime('now');
$createdAt = new DateTime($link['created_at']);

// ── Check absolute expiry (24h or custom) ──
$absExpiryHours = (int)$link['absolute_expiry_hours'];
$absDeadline = (clone $createdAt)->modify("+{$absExpiryHours} hours");
if ($now > $absDeadline) {
    $db->prepare("UPDATE links SET status='expired' WHERE id=:id")->execute([':id'=>$link['id']]);
    showError('此链接已超过最大有效时间，已自动失效。');
}

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// ── First access: start the countdown ──
if ($link['first_accessed_at'] === null) {
    $timeoutSeconds = (int)$link['access_timeout'];
    $expiresAt = (clone $now)->modify("+{$timeoutSeconds} seconds")->format('Y-m-d H:i:s');

    // Only count the first GET as an "access"; POST on first hit is edge-case but count it too
    $db->prepare("UPDATE links SET first_accessed_at=datetime('now', 'localtime'), expires_at=:exp, status='opened', access_count=1 WHERE id=:id")
       ->execute([':exp'=>$expiresAt, ':id'=>$link['id']]);
    $link['status'] = 'opened';
    $link['access_count'] = 1;
    $link['first_accessed_at'] = $now->format('Y-m-d H:i:s');
} else {
    // ── Subsequent access: always check timeout ──
    $expiresAt = new DateTime($link['expires_at']);
    if ($now > $expiresAt) {
        $db->prepare("UPDATE links SET status='expired' WHERE id=:id")->execute([':id'=>$link['id']]);
        showError('此链接已超过访问后有效时间，已自动失效。');
    }

    // POST (form submission): don't count against max_accesses, just check timeout above
    // GET (page reload / re-open): enforce max_accesses and increment
    if (!$isPost) {
        if ($link['access_count'] >= $link['max_accesses']) {
            $db->prepare("UPDATE links SET status='expired' WHERE id=:id")->execute([':id'=>$link['id']]);
            showError('此链接已达到最大访问次数，已自动失效。');
        }
        $db->prepare("UPDATE links SET access_count=access_count+1 WHERE id=:id")->execute([':id'=>$link['id']]);
        $link['access_count']++;
    }
}

// ── Log the access ──
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ref = $_SERVER['HTTP_REFERER'] ?? '';

// Capture form data if POST
$formData = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $postCopy = $_POST;
    unset($postCopy['token']); // don't log the token itself
    $formData = json_encode($postCopy, JSON_UNESCAPED_UNICODE);
}

$logStmt = $db->prepare("INSERT INTO access_logs (link_id, ip, user_agent, referer, form_data, accessed_at) VALUES (:lid, :ip, :ua, :ref, :fd, datetime('now', 'localtime'))");
$logStmt->execute([
    ':lid' => $link['id'],
    ':ip'  => $ip,
    ':ua'  => $ua,
    ':ref' => $ref,
    ':fd'  => $formData,
]);

// ── Render target content ──
$content = $link['target_content'];
$formConfig = null;

// Detect form builder JSON
if (!empty(trim($content))) {
    $decoded = json_decode($content, true);
    if (is_array($decoded) && ($decoded['type'] ?? '') === 'form_builder') {
        $formConfig = $decoded;
    }
}

if ($formConfig) {
    // ── Dynamic form builder rendering ──
    renderFormBuilder($formConfig, $token, $isPost);
} else {
    // ── Legacy static HTML rendering ──
    // SECURITY: never execute stored content as PHP. Existing legacy PHP blocks are stripped
    // before output so stored content cannot become remote code execution.
    if (empty(trim($content))) {
        renderDefaultForm($token, $isPost);
    } else {
        renderStaticHtmlContent($content);
    }
}

// ── Helper: render static HTML safely without PHP execution ──
function renderStaticHtmlContent(string $content): void {
    $content = preg_replace('/<\?(?:php|=)?[\s\S]*?\?>/i', '', $content) ?? '';
    header('Content-Type: text/html; charset=UTF-8');
    echo $content;
}

// ── Helper: render fallback form ──
function renderDefaultForm(string $token, bool $submitted): void {
    renderFormBuilder([
        'type' => 'form_builder',
        'title' => '信息收集表',
        'subtitle' => '请填写以下信息，提交后链接将自动失效',
        'submit_text' => '提交',
        'success_title' => '提交成功！',
        'success_text' => '感谢您的参与，您的数据已记录。',
        'fields' => [
            ['name' => 'name',  'label' => '姓名',   'type' => 'text',     'required' => true,  'placeholder' => '请输入您的姓名', 'default_value' => ''],
            ['name' => 'email', 'label' => '邮箱',   'type' => 'email',    'required' => true,  'placeholder' => 'example@mail.com', 'default_value' => ''],
            ['name' => 'phone', 'label' => '手机号', 'type' => 'tel',      'required' => false, 'placeholder' => '请输入手机号', 'default_value' => ''],
            ['name' => 'note',  'label' => '备注',   'type' => 'textarea', 'required' => false, 'placeholder' => '其他想说的话...', 'default_value' => ''],
        ],
    ], $token, $submitted);
}

// ── Helper: render form builder ──
function renderFormBuilder(array $cfg, string $token, bool $submitted): void {
    $title   = htmlspecialchars($cfg['title'] ?? '信息收集表');
    $subtitle= htmlspecialchars($cfg['subtitle'] ?? '');
    $submit  = htmlspecialchars($cfg['submit_text'] ?? '提交');
    $okTitle = htmlspecialchars($cfg['success_title'] ?? '提交成功');
    $okText  = htmlspecialchars($cfg['success_text'] ?? '感谢您的参与，数据已记录。');
    $fields  = $cfg['fields'] ?? [];
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?></title>
        <style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            body{
                font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,
                           "Helvetica Neue",Arial,"Noto Sans SC",sans-serif;
                background:linear-gradient(155deg,#667eea 0%,#764ba2 50%,#5a3f8a 100%);
                min-height:100vh;display:flex;align-items:center;justify-content:center;
                padding:16px;-webkit-font-smoothing:antialiased;
                position:relative;overflow:hidden;
            }
            body::before{
                content:'';position:absolute;
                width:400px;height:400px;
                background:radial-gradient(circle,rgba(255,255,255,.06),transparent 70%);
                top:-120px;right:-80px;border-radius:50%;pointer-events:none;
            }
            body::after{
                content:'';position:absolute;
                width:300px;height:300px;
                background:radial-gradient(circle,rgba(255,255,255,.05),transparent 70%);
                bottom:-80px;left:-60px;border-radius:50%;pointer-events:none;
            }
            .fb-container{
                background:#fff;border-radius:20px;
                padding:clamp(28px,5vw,44px);max-width:520px;width:100%;
                box-shadow:0 28px 80px rgba(0,0,0,.22),0 0 0 1px rgba(255,255,255,.1);
                position:relative;z-index:1;
                animation:fbSlideIn .5s cubic-bezier(.22,.61,.36,1);
            }
            @keyframes fbSlideIn{
                from{opacity:0;transform:translateY(20px) scale(.96)}
                to{opacity:1;transform:translateY(0) scale(1)}
            }
            .fb-container h2{
                text-align:center;color:#1a1a2e;margin-bottom:4px;
                font-size:clamp(18px,4.5vw,24px);font-weight:800;letter-spacing:-.3px;
            }
            .fb-subtitle{text-align:center;color:#999;font-size:13px;margin-bottom:28px;line-height:1.5}
            .fb-field{margin-bottom:18px}
            .fb-field label{
                display:block;font-size:13px;font-weight:600;color:#4a4a5a;
                margin-bottom:5px;letter-spacing:.2px;
            }
            .fb-field .req{color:#e74c3c}
            .fb-field input,.fb-field select,.fb-field textarea{
                width:100%;padding:11px 14px;border:1.5px solid #e0e0ea;
                border-radius:10px;font-size:14px;font-family:inherit;
                transition:border-color .2s,box-shadow .2s;background:#fafbfd;color:#1a1a2e;
            }
            .fb-field input:hover,.fb-field select:hover,.fb-field textarea:hover{border-color:#c8c8d8}
            .fb-field input:focus,.fb-field select:focus,.fb-field textarea:focus{
                outline:none;border-color:#667eea;
                box-shadow:0 0 0 4px rgba(102,126,234,.12);background:#fff;
            }
            .fb-field textarea{min-height:100px;resize:vertical;font-size:14px;line-height:1.6}
            .fb-submit{
                width:100%;padding:14px;
                background:linear-gradient(135deg,#667eea,#5a4fcf);
                color:#fff;border:none;border-radius:10px;
                font-size:16px;font-weight:700;cursor:pointer;
                transition:all .25s;letter-spacing:.3px;
                box-shadow:0 4px 16px rgba(102,126,234,.3);
            }
            .fb-submit:hover{
                transform:translateY(-2px);
                box-shadow:0 8px 24px rgba(102,126,234,.4);
            }
            .fb-submit:active{transform:translateY(0)}
            .fb-success{
                text-align:center;padding:28px 10px;
                animation:successPop .5s cubic-bezier(.22,.61,.36,1);
            }
            @keyframes successPop{
                0%{transform:scale(.7);opacity:0}
                60%{transform:scale(1.05)}
                100%{transform:scale(1);opacity:1}
            }
            .fb-success .icon-wrap{
                width:80px;height:80px;margin:0 auto 20px;
                border-radius:50%;
                background:linear-gradient(135deg,#27ae60,#2ecc71);
                display:flex;align-items:center;justify-content:center;
                font-size:38px;
                box-shadow:0 12px 32px rgba(39,174,96,.3);
                animation:iconBounce .6s cubic-bezier(.22,.61,.36,1) .15s both;
            }
            @keyframes iconBounce{
                0%{transform:scale(0)}
                60%{transform:scale(1.15)}
                100%{transform:scale(1)}
            }
            .fb-success h3{color:#1a1a2e;margin:0 0 6px;font-size:20px;font-weight:800}
            .fb-success p{color:#999;font-size:14px;line-height:1.6;max-width:300px;margin:0 auto}
            @media(max-width:480px){
                .fb-container{border-radius:14px;padding:24px 20px}
                .fb-success{padding:20px 0}
                .fb-success .icon-wrap{width:64px;height:64px;font-size:30px}
            }
        </style>
    </head>
    <body>
    <?php if ($submitted): ?>
    <div class="fb-container">
        <div class="fb-success">
            <div class="icon-wrap">✅</div>
            <h3><?= $okTitle ?></h3>
            <p><?= $okText ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="fb-container">
        <h2><?= $title ?></h2>
        <?php if ($subtitle): ?><p class="fb-subtitle"><?= $subtitle ?></p><?php endif; ?>
        <form method="post" action="?token=<?= htmlspecialchars($token) ?>">
            <?php foreach ($fields as $f):
                $name  = htmlspecialchars($f['name'] ?? '');
                $label = htmlspecialchars($f['label'] ?? $name);
                $type  = $f['type'] ?? 'text';
                $req   = !empty($f['required']);
                $ph    = htmlspecialchars($f['placeholder'] ?? '');
                $dv    = htmlspecialchars($f['default_value'] ?? '');
                $opts  = $f['options'] ?? [];
            ?>
            <div class="fb-field">
                <label><?= $label ?><?= $req ? ' <span class="req">*</span>' : '' ?></label>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= $name ?>" placeholder="<?= $ph ?>" <?= $req ? 'required' : '' ?>><?= $dv ?></textarea>
                <?php elseif ($type === 'select'): ?>
                    <select name="<?= $name ?>" <?= $req ? 'required' : '' ?>>
                        <option value=""><?= $ph ?: '请选择' ?></option>
                        <?php foreach ($opts as $o): $ov = htmlspecialchars($o); ?>
                        <option value="<?= $ov ?>" <?= ($o === ($f['default_value'] ?? '')) ? 'selected' : '' ?>><?= $ov ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="<?= $type ?>" name="<?= $name ?>" placeholder="<?= $ph ?>" value="<?= $dv ?>" <?= $req ? 'required' : '' ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="fb-submit">📤 <?= $submit ?></button>
        </form>
    </div>
    <?php endif; ?>
    </body></html>
    <?php
    exit;
}

// ── Helper ──
function showError(string $msg): void {
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>链接已失效</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }

            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                             "Helvetica Neue", Arial, "Noto Sans SC", "PingFang SC", sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(160deg, #0d0d2a 0%, #141438 35%, #1a1a45 65%, #0f0f28 100%);
                overflow: hidden;
                position: relative;
                -webkit-font-smoothing: antialiased;
            }

            /* ── Ambient glow orbs ── */
            .bg-orb {
                position: absolute;
                border-radius: 50%;
                pointer-events: none;
                z-index: 0;
            }
            .bg-orb--1 {
                width: 500px; height: 500px;
                background: radial-gradient(circle, rgba(233,69,96,0.07) 0%, transparent 70%);
                top: -180px; right: -120px;
                animation: orbFloat 14s ease-in-out infinite;
            }
            .bg-orb--2 {
                width: 380px; height: 380px;
                background: radial-gradient(circle, rgba(102,126,234,0.06) 0%, transparent 70%);
                bottom: -100px; left: -80px;
                animation: orbFloat 18s ease-in-out infinite reverse;
            }
            @keyframes orbFloat {
                0%, 100% { transform: translate(0, 0) scale(1); }
                33%  { transform: translate(30px, -20px) scale(1.08); }
                66%  { transform: translate(-15px, 15px) scale(0.94); }
            }

            /* ── Floating dots ── */
            .dot {
                position: absolute;
                width: 3px; height: 3px;
                background: rgba(255,255,255,0.1);
                border-radius: 50%;
                pointer-events: none;
                animation: dotRise linear infinite;
            }
            @keyframes dotRise {
                0%   { transform: translateY(105vh) scale(0); opacity: 0; }
                10%  { opacity: 1; }
                85%  { opacity: 1; }
                100% { transform: translateY(-10vh) scale(1.2); opacity: 0; }
            }

            /* ── Card ── */
            .expired-wrapper {
                position: relative;
                z-index: 1;
                animation: cardEntry 0.7s cubic-bezier(0.22, 0.61, 0.36, 1);
            }
            @keyframes cardEntry {
                from { opacity: 0; transform: translateY(28px) scale(0.94); }
                to   { opacity: 1; transform: translateY(0) scale(1); }
            }

            .expired-card {
                background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(255,255,255,0.94));
                backdrop-filter: blur(22px);
                -webkit-backdrop-filter: blur(22px);
                border-radius: 28px;
                padding: 56px 46px 46px;
                text-align: center;
                max-width: 460px;
                width: 92vw;
                box-shadow:
                    0 34px 100px rgba(0,0,0,0.34),
                    0 0 0 1px rgba(255,255,255,0.14),
                    inset 0 1px 0 rgba(255,255,255,0.78);
                position: relative;
                overflow: hidden;
            }
            .expired-card::before {
                content: '';
                position: absolute;
                inset: 0 0 auto 0;
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(233,69,96,0.24), transparent);
                pointer-events: none;
            }
            .expired-card::after {
                content: '';
                position: absolute;
                top: -80px;
                right: -60px;
                width: 180px;
                height: 180px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(233,69,96,0.08) 0%, transparent 70%);
                pointer-events: none;
            }

            /* ── Icon ring ── */
            .expired-icon {
                width: 94px;
                height: 94px;
                margin: 0 auto 26px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 44px;
                position: relative;
                filter: drop-shadow(0 8px 18px rgba(233,69,96,0.16));
            }
            .expired-icon::before {
                content: '';
                position: absolute;
                inset: -4px;
                border-radius: 50%;
                background: linear-gradient(135deg, #e94560, #d63852);
                z-index: -1;
            }
            .expired-icon::after {
                content: '';
                position: absolute;
                inset: 2px;
                border-radius: 50%;
                background: #fff;
                z-index: -1;
            }
            .expired-icon .ring {
                position: absolute;
                inset: -12px;
                border-radius: 50%;
                border: 3px solid rgba(233,69,96,0.12);
                animation: ringPulse 2.5s ease-in-out infinite;
            }
            @keyframes ringPulse {
                0%, 100% { transform: scale(1); opacity: 0.6; }
                50%  { transform: scale(1.12); opacity: 1; }
            }

            /* ── Typography ── */
            .expired-card h1 {
                font-size: 24px;
                font-weight: 800;
                color: #1a1a2e;
                margin-bottom: 6px;
                letter-spacing: -0.4px;
            }
            .expired-card .sub {
                font-size: 14px;
                color: #8f8f9e;
                margin-bottom: 20px;
                line-height: 1.7;
                max-width: 290px;
                margin-left: auto;
                margin-right: auto;
            }
            .expired-card .divider {
                width: 50px;
                height: 3px;
                background: linear-gradient(90deg, #e94560, transparent);
                margin: 0 auto 22px;
                border-radius: 2px;
            }
            .expired-card .reason {
                font-size: 14px;
                color: #6f6f7e;
                line-height: 1.75;
                padding: 16px 20px;
                background: linear-gradient(180deg, #fcfcfd, #f7f7fa);
                border-radius: 16px;
                border: 1px solid #f0f0f4;
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
            }

            /* ── Footer text ── */
            .expired-footer {
                margin-top: 28px;
                font-size: 12px;
                color: rgba(255,255,255,0.35);
                text-align: center;
                position: relative;
                z-index: 1;
            }
        </style>
    </head>
    <body>
    <!-- Ambient orbs -->
    <div class="bg-orb bg-orb--1"></div>
    <div class="bg-orb bg-orb--2"></div>

    <!-- Floating dots -->
    <span class="dot" style="left:10%;animation-duration:12s;animation-delay:0s;"></span>
    <span class="dot" style="left:25%;animation-duration:16s;animation-delay:1.5s;"></span>
    <span class="dot" style="left:42%;animation-duration:13s;animation-delay:3s;"></span>
    <span class="dot" style="left:58%;animation-duration:19s;animation-delay:0.8s;"></span>
    <span class="dot" style="left:72%;animation-duration:14s;animation-delay:2.2s;"></span>
    <span class="dot" style="left:88%;animation-duration:17s;animation-delay:1s;"></span>

    <div class="expired-wrapper">
        <div class="expired-card">
            <div class="expired-icon">
                <span class="ring"></span>
                ⏰
            </div>
            <h1>链接已失效</h1>
            <p class="sub">此链接可能已过期、被系统销毁，或已经达到访问上限</p>
            <div class="divider"></div>
            <p class="reason"><?= htmlspecialchars($msg) ?></p>
        </div>
        <p class="expired-footer">Link Destroyer · 链接销毁系统</p>
    </div>
    </body></html>
    <?php
    exit;
}
