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
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
            .fb-container{background:#fff;border-radius:16px;padding:clamp(24px,5vw,40px);max-width:520px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.15)}
            .fb-container h2{text-align:center;color:#333;margin-bottom:4px;font-size:clamp(18px,4vw,22px)}
            .fb-subtitle{text-align:center;color:#888;font-size:13px;margin-bottom:24px}
            .fb-field{margin-bottom:16px}
            .fb-field label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:4px}
            .fb-field .req{color:#e74c3c}
            .fb-field input,.fb-field select,.fb-field textarea{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;font-family:inherit;transition:border-color .2s,box-shadow .2s;background:#fff}
            .fb-field input:focus,.fb-field select:focus,.fb-field textarea:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.15)}
            .fb-field textarea{min-height:90px;resize:vertical}
            .fb-submit{width:100%;padding:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:opacity .2s}
            .fb-submit:hover{opacity:.9}
            .fb-success{text-align:center;padding:20px}
            .fb-success .icon{font-size:48px;margin-bottom:12px}
            .fb-success h3{color:#27ae60;margin-bottom:8px}
            .fb-success p{color:#888;font-size:14px}
            @media(max-width:480px){.fb-container{border-radius:12px;padding:20px}}
        </style>
    </head>
    <body>
    <?php if ($submitted): ?>
    <div class="fb-container">
        <div class="fb-success">
            <div class="icon">✅</div>
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
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f0f2f5; margin:0; }
            .expired-box { text-align:center; background:#fff; padding:48px; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.08); max-width:420px; }
            .expired-box .icon { font-size:56px; margin-bottom:16px; }
            .expired-box h2 { color:#e94560; margin:0 0 8px; }
            .expired-box p { color:#888; margin:0; }
        </style>
    </head>
    <body>
    <div class="expired-box">
        <div class="icon">⏰</div>
        <h2>链接已失效</h2>
        <p><?= htmlspecialchars($msg) ?></p>
    </div>
    </body></html>
    <?php
    exit;
}
