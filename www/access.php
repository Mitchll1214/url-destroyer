<?php
/**
 * Public Access Handler — validates token, serves target content, logs access
 *
 * URL: /access.php?token=<token>
 * Pretty: /access/<token>  (via .htaccess rewrite)
 */

require_once __DIR__ . '/db.php';

// ── Get token ──
$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    showError('缺少 token 参数。');
}

// ── Look up link ──
$stmt = DB::prepare("SELECT * FROM links WHERE token = :token LIMIT 1");
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
    DB::prepare("UPDATE links SET status='expired' WHERE id=:id")->execute([':id'=>$link['id']]);
    showError('此链接已超过最大有效时间，已自动失效。');
}

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// ── Autosave endpoint (silent, JSON response) ──
if ($isPost && empty($_POST['__final_submit'])) {
    $postCopy = $_POST;
    unset($postCopy['token']);
    DB::prepare("INSERT OR REPLACE INTO form_drafts (token, form_data, updated_at) VALUES (:t, :d, datetime('now', 'localtime'))")
       ->execute([':t' => $token, ':d' => json_encode($postCopy, JSON_UNESCAPED_UNICODE)]);
    // Upgrade from active to draft on first autosave
    if ($link['status'] === 'active') {
        DB::prepare("UPDATE links SET status='draft' WHERE id=:id")->execute([':id' => $link['id']]);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'saved_at' => date('Y-m-d H:i:s')]);
    exit;
}

// ── Load draft (for pre-populating form) ──
$draft = DB::prepare("SELECT form_data FROM form_drafts WHERE token = :t");
$draft->execute([':t' => $token]);
$draftData = json_decode($draft->fetchColumn() ?: '{}', true) ?: [];

// ── First access: start countdown (opening is free, only submit counts) ──
if ($link['first_accessed_at'] === null && $link['status'] === 'active') {
    $timeoutSeconds = (int)$link['access_timeout'];
    $expiresAt = (clone $now)->modify("+{$timeoutSeconds} seconds")->format('Y-m-d H:i:s');
    DB::prepare("UPDATE links SET first_accessed_at=datetime('now', 'localtime'), expires_at=:exp WHERE id=:id")
       ->execute([':exp' => $expiresAt, ':id' => $link['id']]);
    $link['first_accessed_at'] = $now->format('Y-m-d H:i:s');
}

// ── Check timeout (applies to active/draft/submitted) ──
if ($link['first_accessed_at'] !== null && $link['expires_at'] !== null) {
    $expiresAt = new DateTime($link['expires_at']);
    if ($now > $expiresAt && $link['status'] !== 'expired') {
        DB::prepare("UPDATE links SET status='expired' WHERE id=:id")->execute([':id' => $link['id']]);
        $link['status'] = 'expired';
    }
}

// ── Max access check for every GET (including draft reloads) ──
if (!$isPost) {
    if ($link['access_count'] >= $link['max_accesses']) {
        DB::prepare("UPDATE links SET status='expired' WHERE id=:id")->execute([':id' => $link['id']]);
        showError('此链接已达到最大访问次数，已自动失效。');
    }
    DB::prepare("UPDATE links SET access_count=access_count+1 WHERE id=:id")->execute([':id' => $link['id']]);
    $link['access_count']++;
}

// ── Final submit (POST with __final_submit=1) ──
$submitted = false;
if ($isPost && !empty($_POST['__final_submit'])) {
    if ($link['status'] === 'expired') {
        showError('此链接已超过访问后有效时间，已自动失效。');
    }
    // Log access first
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $postCopy = $_POST;
    unset($postCopy['token'], $postCopy['__final_submit']);
    $formData = json_encode($postCopy, JSON_UNESCAPED_UNICODE);
    DB::prepare("INSERT INTO access_logs (link_id, ip, user_agent, referer, form_data, accessed_at) VALUES (:lid, :ip, :ua, :ref, :fd, datetime('now', 'localtime'))")
       ->execute([':lid' => $link['id'], ':ip' => $ip, ':ua' => $ua, ':ref' => $ref, ':fd' => $formData]);

    // Transition: draft → submitted (or expired if expire_on_submit=1)
    if (!empty($link['expire_on_submit'])) {
        DB::prepare("UPDATE links SET status='expired' WHERE id=:id")->execute([':id' => $link['id']]);
    } else {
        DB::prepare("UPDATE links SET status='submitted' WHERE id=:id")->execute([':id' => $link['id']]);
    }

    // Delete draft after successful submit
    DB::prepare("DELETE FROM form_drafts WHERE token = :t")->execute([':t' => $token]);

    $submitted = true;
}

// ── Error for expired status on GET ──
if (!$isPost && $link['status'] === 'expired') {
    showError('此链接已超过访问后有效时间，已自动失效。');
}

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
    renderFormBuilder($formConfig, $token, $submitted, $draftData);
} else {
    // ── Legacy static HTML rendering ──
    if (empty(trim($content))) {
        renderDefaultForm($token, $submitted, $draftData);
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
function renderDefaultForm(string $token, bool $submitted, array $draftData = []): void {
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
    ], $token, $submitted, $draftData);
}

// ── Helper: render form builder ──
function renderFormBuilder(array $cfg, string $token, bool $submitted, array $draftData = []): void {
    $title   = htmlspecialchars($cfg['title'] ?? '信息收集表');
    $subtitle= htmlspecialchars($cfg['subtitle'] ?? '');
    $submit  = htmlspecialchars($cfg['submit_text'] ?? '提交');
    $okTitle = htmlspecialchars($cfg['success_title'] ?? '提交成功');
    $okText  = htmlspecialchars($cfg['success_text'] ?? '感谢您的参与，数据已记录。');
    $fields  = $cfg['fields'] ?? [];
    $hasDraft = !empty($draftData);
    $draftJS  = $hasDraft ? 'true' : 'false';
    $draftJSON = json_encode($draftData, JSON_HEX_TAG | JSON_HEX_APOS);
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?></title>
        <link rel="icon" type="image/svg+xml" href="favicon.svg">
        <style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            html{height:100%}
            body{
                font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,
                           "Helvetica Neue",Arial,"Noto Sans SC","PingFang SC","Microsoft YaHei",sans-serif;
                background:linear-gradient(155deg,#141824 0%,#1a1f30 40%,#1e2438 65%,#161a28 100%) fixed;
                min-height:100vh;min-height:100dvh;
                display:flex;align-items:center;justify-content:center;
                padding:24px 16px;-webkit-font-smoothing:antialiased;
                position:relative;
                overflow-y:auto;overflow-x:hidden;
            }
            body::before{
                content:'';position:fixed;
                width:460px;height:460px;
                background:radial-gradient(circle,rgba(201,64,58,.07),transparent 68%);
                top:-120px;right:-80px;border-radius:50%;pointer-events:none;z-index:0;
            }
            body::after{
                content:'';position:fixed;
                width:340px;height:340px;
                background:radial-gradient(circle,rgba(74,85,144,.06),transparent 68%);
                bottom:-80px;left:-60px;border-radius:50%;pointer-events:none;z-index:0;
            }
            .fb-container{
                background:#fff;border-radius:18px;
                padding:clamp(28px,5vw,44px);max-width:520px;width:100%;
                box-shadow:0 28px 80px rgba(10,12,18,.3),0 0 0 1px rgba(255,255,255,.08);
                position:relative;z-index:1;margin:auto 0;
                animation:fbSlideIn .5s cubic-bezier(.22,.61,.36,1);
            }
            @keyframes fbSlideIn{
                from{opacity:0;transform:translateY(20px) scale(.96)}
                to{opacity:1;transform:translateY(0) scale(1)}
            }
            .fb-container h2{
                text-align:center;color:#1c1e26;margin-bottom:4px;
                font-size:clamp(18px,4.5vw,24px);font-weight:800;letter-spacing:-.3px;
            }
            .fb-subtitle{text-align:center;color:#9498a4;font-size:14px;margin-bottom:28px;line-height:1.5}
            .fb-field{margin-bottom:18px}
            .fb-field label{
                display:block;font-size:13px;font-weight:600;color:#4a4d58;
                margin-bottom:6px;letter-spacing:.15px;
            }
            .fb-field .req{color:#c9403a;font-weight:700}
            .fb-field input,.fb-field select,.fb-field textarea{
                width:100%;padding:11px 14px;border:1.5px solid #e0e2ea;
                border-radius:8px;font-size:14px;font-family:inherit;
                transition:border-color .2s,box-shadow .2s;background:#fafbfd;color:#1c1e26;
            }
            .fb-field input:hover,.fb-field select:hover,.fb-field textarea:hover{border-color:#c4c8d2}
            .fb-field input:focus,.fb-field select:focus,.fb-field textarea:focus{
                outline:none;border-color:#c9403a;
                box-shadow:0 0 0 4px rgba(201,64,58,.1);background:#fff;
            }
            .fb-field textarea{min-height:100px;resize:vertical;font-size:14px;line-height:1.6}
            .fb-submit{
                width:100%;padding:14px;
                background:linear-gradient(135deg,#c9403a,#a8352e);
                color:#fff;border:none;border-radius:8px;
                font-size:16px;font-weight:700;cursor:pointer;
                transition:all .25s;letter-spacing:.3px;
                box-shadow:0 4px 16px rgba(201,64,58,.28);
                -webkit-appearance:none;
            }
            .fb-submit:hover{
                transform:translateY(-2px);
                box-shadow:0 8px 24px rgba(201,64,58,.36);
            }
            .fb-submit:active{transform:translateY(0)}
            .fb-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}
            /* ── Autosave indicator ── */
            .autosave-indicator{
                position:fixed;bottom:20px;right:20px;
                background:#fff;color:#2d7d5f;font-size:12px;font-weight:600;
                padding:8px 16px;border-radius:20px;
                box-shadow:0 4px 18px rgba(0,0,0,.14);
                opacity:0;transform:translateY(10px);
                transition:opacity .3s,transform .3s;
                pointer-events:none;z-index:999;
            }
            .autosave-indicator.show{opacity:1;transform:translateY(0)}
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
                background:linear-gradient(135deg,#2d7d5f,#3ca078);
                display:flex;align-items:center;justify-content:center;
                font-size:38px;
                box-shadow:0 12px 32px rgba(45,125,95,.28);
                animation:iconBounce .6s cubic-bezier(.22,.61,.36,1) .15s both;
            }
            @keyframes iconBounce{
                0%{transform:scale(0)}
                60%{transform:scale(1.15)}
                100%{transform:scale(1)}
            }
            .fb-success h3{color:#1c1e26;margin:0 0 6px;font-size:20px;font-weight:800}
            .fb-success p{color:#9498a4;font-size:14px;line-height:1.6;max-width:300px;margin:0 auto}
            @media(max-width:640px){
                body{padding:16px 12px;align-items:flex-start}
                .fb-container{border-radius:14px;padding:24px 18px;margin:0}
                .fb-container h2{font-size:20px}
                .fb-subtitle{font-size:13px;margin-bottom:20px}
                .fb-field{margin-bottom:16px}
                .fb-field label{font-size:14px}
                .fb-field input,.fb-field select,.fb-field textarea{
                    padding:13px 14px;font-size:16px;border-radius:8px;
                }
                .fb-field textarea{min-height:120px}
                .fb-submit{padding:16px;font-size:17px;border-radius:10px}
                .fb-success{padding:20px 0}
                .fb-success .icon-wrap{width:64px;height:64px;font-size:30px}
                .autosave-indicator{bottom:12px;right:12px;font-size:11px;padding:6px 12px}
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
        <form method="post" action="?token=<?= htmlspecialchars($token) ?>" id="fbForm">
            <input type="hidden" name="__final_submit" value="0" id="finalSubmitFlag">
            <?php foreach ($fields as $f):
                $name  = htmlspecialchars($f['name'] ?? '');
                $label = htmlspecialchars($f['label'] ?? $name);
                $type  = $f['type'] ?? 'text';
                $req   = !empty($f['required']);
                $ph    = htmlspecialchars($f['placeholder'] ?? '');
                $dv    = htmlspecialchars($f['default_value'] ?? '');
                $opts  = $f['options'] ?? [];
                // Draft value takes priority over default_value
                $val   = htmlspecialchars($draftData[$f['name']] ?? $f['default_value'] ?? '');
            ?>
            <div class="fb-field">
                <label><?= $label ?><?= $req ? ' <span class="req">*</span>' : '' ?></label>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= $name ?>" placeholder="<?= $ph ?>" <?= $req ? 'required' : '' ?> data-field><?= $val ?></textarea>
                <?php elseif ($type === 'select'): ?>
                    <select name="<?= $name ?>" <?= $req ? 'required' : '' ?> data-field>
                        <option value=""><?= $ph ?: '请选择' ?></option>
                        <?php foreach ($opts as $o): $ov = htmlspecialchars($o); ?>
                        <option value="<?= $ov ?>" <?= ($o === ($draftData[$f['name']] ?? $f['default_value'] ?? '')) ? 'selected' : '' ?>><?= $ov ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="<?= $type ?>" name="<?= $name ?>" placeholder="<?= $ph ?>" value="<?= $val ?>" <?= $req ? 'required' : '' ?> data-field>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="fb-submit" id="submitBtn">📤 <?= $submit ?></button>
        </form>
        <div class="autosave-indicator" id="autosaveIndicator">✅ 草稿已保存</div>
    </div>
    <script>
    (function(){
        var form = document.getElementById('fbForm');
        var flag = document.getElementById('finalSubmitFlag');
        var btn = document.getElementById('submitBtn');
        var indicator = document.getElementById('autosaveIndicator');
        var token = <?= json_encode($token) ?>;
        var timer = null;
        var lastSaved = '';

        // Use form submit event instead of button click — more reliable
        form.addEventListener('submit', function(e){
            flag.value = '1';
            clearTimeout(timer);
            btn.disabled = true;
            btn.textContent = '⏳ 提交中...';
        });

        function showIndicator(text){
            indicator.textContent = text || '✅ 草稿已保存';
            indicator.classList.add('show');
            clearTimeout(indicator._hideTimer);
            indicator._hideTimer = setTimeout(function(){ indicator.classList.remove('show'); }, 2000);
        }

        function autosave(){
            // Don't autosave if form is being submitted
            if (flag.value === '1') return;
            var fd = new FormData(form);
            fd.set('__final_submit', '0');
            var payload = new URLSearchParams(fd).toString();
            if (payload === lastSaved) return;
            lastSaved = payload;
            fetch('?token=' + encodeURIComponent(token), {
                method:'POST', body:fd
            }).then(function(r){ return r.json(); })
              .then(function(data){
                  if (data && data.ok) showIndicator();
              });
        }

        // Debounced autosave on any field change
        form.querySelectorAll('[data-field]').forEach(function(el){
            el.addEventListener('input', function(){
                clearTimeout(timer);
                timer = setTimeout(autosave, 1500);
            });
            el.addEventListener('change', function(){
                clearTimeout(timer);
                timer = setTimeout(autosave, 1500);
            });
        });

        <?php if ($hasDraft): ?>
        showIndicator('📋 已恢复上次填写的内容');
        <?php endif; ?>
    })();
    </script>
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
        <link rel="icon" type="image/svg+xml" href="favicon.svg">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }

            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                             "Helvetica Neue", Arial, "Noto Sans SC", "PingFang SC", sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(155deg, #0e1018 0%, #151828 35%, #1a1e30 65%, #11131e 100%);
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
                background: radial-gradient(circle, rgba(201,64,58,0.06) 0%, transparent 68%);
                top: -180px; right: -120px;
                animation: orbFloat 14s ease-in-out infinite;
            }
            .bg-orb--2 {
                width: 380px; height: 380px;
                background: radial-gradient(circle, rgba(74,85,144,0.05) 0%, transparent 68%);
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
                background: rgba(255,255,255,0.08);
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
                background: linear-gradient(180deg, rgba(255,255,255,0.97), rgba(255,255,255,0.93));
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
                border-radius: 22px;
                padding: 52px 42px 42px;
                text-align: center;
                max-width: 440px;
                width: 92vw;
                box-shadow:
                    0 32px 100px rgba(0,0,0,0.3),
                    0 0 0 1px rgba(255,255,255,0.1),
                    inset 0 1px 0 rgba(255,255,255,0.7);
                position: relative;
                overflow: hidden;
            }
            .expired-card::before {
                content: '';
                position: absolute;
                inset: 0 0 auto 0;
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(201,64,58,0.2), transparent);
                pointer-events: none;
            }
            .expired-card::after {
                content: '';
                position: absolute;
                top: -70px;
                right: -50px;
                width: 160px;
                height: 160px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(201,64,58,0.06) 0%, transparent 68%);
                pointer-events: none;
            }

            /* ── Icon ring ── */
            .expired-icon {
                width: 90px;
                height: 90px;
                margin: 0 auto 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 42px;
                position: relative;
                filter: drop-shadow(0 8px 18px rgba(201,64,58,0.14));
            }
            .expired-icon::before {
                content: '';
                position: absolute;
                inset: -4px;
                border-radius: 50%;
                background: linear-gradient(135deg, #c9403a, #a8352e);
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
                border: 3px solid rgba(201,64,58,0.1);
                animation: ringPulse 2.5s ease-in-out infinite;
            }
            @keyframes ringPulse {
                0%, 100% { transform: scale(1); opacity: 0.5; }
                50%  { transform: scale(1.12); opacity: 1; }
            }

            /* ── Typography ── */
            .expired-card h1 {
                font-size: 24px;
                font-weight: 800;
                color: #1c1e26;
                margin-bottom: 6px;
                letter-spacing: -0.4px;
            }
            .expired-card .sub {
                font-size: 14px;
                color: #9498a4;
                margin-bottom: 20px;
                line-height: 1.7;
                max-width: 280px;
                margin-left: auto;
                margin-right: auto;
            }
            .expired-card .divider {
                width: 48px;
                height: 3px;
                background: linear-gradient(90deg, #c9403a, transparent);
                margin: 0 auto 22px;
                border-radius: 2px;
            }
            .expired-card .reason {
                font-size: 14px;
                color: #7a7d88;
                line-height: 1.75;
                padding: 16px 20px;
                background: linear-gradient(180deg, #fafbfd, #f6f7fa);
                border-radius: 14px;
                border: 1px solid #eff0f4;
            }

            /* ── Footer text ── */
            .expired-footer {
                margin-top: 28px;
                font-size: 12px;
                color: rgba(255,255,255,0.3);
                text-align: center;
                position: relative;
                z-index: 1;
                letter-spacing: 0.5px;
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
