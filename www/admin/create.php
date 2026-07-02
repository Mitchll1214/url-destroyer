<?php
/**
 * Create Links — batch generate time-limited URLs with visual form builder
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();

// Load defaults from settings
$defaultTimeout = (int)($db->query("SELECT value FROM settings WHERE key='default_access_timeout'")->fetchColumn() ?: 600);
$defaultExpiry  = (int)($db->query("SELECT value FROM settings WHERE key='default_absolute_expiry_hours'")->fetchColumn() ?: 24);

$createdLinks = [];
$error = '';

// Handle copy_from: pre-load config from existing link
$copyConfig = null;
$copyId = (int)($_GET['copy_from'] ?? 0);
if ($copyId > 0) {
    $src = $db->prepare("SELECT target_content, campaign_name FROM links WHERE id = :id");
    $src->execute([':id' => $copyId]);
    $srcRow = $src->fetch();
    if ($srcRow && !empty(trim($srcRow['target_content']))) {
        $decoded = json_decode($srcRow['target_content'], true);
        if (is_array($decoded) && ($decoded['type'] ?? '') === 'form_builder') {
            $copyConfig = $srcRow['target_content'];
        }
    }
}

// Default form builder config
$defaultFormConfig = json_encode([
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
], JSON_UNESCAPED_UNICODE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaign    = trim($_POST['campaign_name'] ?? '');
    $count       = (int)($_POST['count'] ?? 1);
    $timeout     = (int)($_POST['access_timeout'] ?? $defaultTimeout);
    $absExpiry   = (int)($_POST['absolute_expiry_hours'] ?? $defaultExpiry);
    $targetContent = $_POST['target_content'] ?? $defaultFormConfig;
    $maxAccesses = (int)($_POST['max_accesses'] ?? 1);

    if ($campaign === '') {
        $error = '活动名称不能为空';
    } elseif ($count < 1 || $count > 500) {
        $error = '数量必须在 1 ~ 500 之间';
    } elseif ($timeout < 10) {
        $error = '访问后超时不能少于 10 秒';
    } elseif ($absExpiry < 1) {
        $error = '绝对过期时间不能少于 1 小时';
    } else {
        $stmt = $db->prepare("
            INSERT INTO links (token, campaign_name, target_content, access_timeout, absolute_expiry_hours, max_accesses, status, created_at)
            VALUES (:token, :campaign, :content, :timeout, :abs_expiry, :max_accesses, 'active', datetime('now', 'localtime'))
        ");
        for ($i = 0; $i < $count; $i++) {
            $token = bin2hex(random_bytes(16));
            $stmt->execute([
                ':token'       => $token,
                ':campaign'    => $campaign,
                ':content'     => $targetContent,
                ':timeout'     => $timeout,
                ':abs_expiry'  => $absExpiry,
                ':max_accesses'=> $maxAccesses,
            ]);
            $createdLinks[] = ['id' => $db->lastInsertId(), 'token' => $token];
        }
    }
}

adminHeader('创建链接', 'create');
?>

<h1 class="page-title">➕ 批量创建链接</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($createdLinks)): ?>
    <div class="alert alert-success">✅ 成功创建 <strong><?= count($createdLinks) ?></strong> 个链接！</div>
    <div class="card">
        <div class="card-header">🔗 生成的链接 (请立即复制保存)</div>
        <?php foreach ($createdLinks as $link): ?>
            <div style="margin-bottom:8px;">
                <div class="url-display"><?= htmlspecialchars(BASE_URL . '/access.php?token=' . $link['token']) ?></div>
                <span class="text-muted">Token: <?= htmlspecialchars($link['token']) ?> | ID: #<?= $link['id'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">📝 链接配置</div>
    <form method="post" id="createForm">
        <div class="form-group">
            <label>活动名称 (用于后台标记)</label>
            <input type="text" name="campaign_name" placeholder="例如：2024用户调研" maxlength="200" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>生成数量</label>
                <input type="number" name="count" value="1" min="1" max="500" required>
                <span class="text-muted">一次最多 500 个</span>
            </div>
            <div class="form-group">
                <label>最大访问次数 (默认 1 = 打开即失效)</label>
                <input type="number" name="max_accesses" value="1" min="1" max="100" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>访问后超时 (秒)</label>
                <input type="number" name="access_timeout" value="<?= $defaultTimeout ?>" min="10" required>
                <span class="text-muted">首次访问后 <?= round($defaultTimeout/60,1) ?> 分钟失效</span>
            </div>
            <div class="form-group">
                <label>未打开自动过期 (小时)</label>
                <input type="number" name="absolute_expiry_hours" value="<?= $defaultExpiry ?>" min="1" required>
                <span class="text-muted">创建后超过此时间未访问则自动失效</span>
            </div>
        </div>

        <!-- Hidden field for form config JSON -->
        <input type="hidden" name="target_content" id="targetContentInput" value="<?= htmlspecialchars($defaultFormConfig) ?>">

        <!-- Tab: visual builder / advanced -->
        <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="btn btn-sm btn-primary tab-btn" data-tab="visual">🎨 可视化构建器</button>
            <button type="button" class="btn btn-sm btn-outline tab-btn" data-tab="advanced">💻 高级模式 (HTML代码)</button>
        </div>

        <!-- === VISUAL BUILDER TAB === -->
        <div id="tab-visual">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="builder-grid">
                <!-- Left: editor -->
                <div>
                    <div class="card" style="padding:16px;margin-bottom:12px;">
                        <div class="card-header" style="margin-bottom:8px;">📋 表单设置</div>
                        <div class="form-group"><label>表单标题</label><input type="text" id="cfgTitle" value="信息收集表"></div>
                        <div class="form-group"><label>副标题</label><input type="text" id="cfgSubtitle" value="请填写以下信息，提交后链接将自动失效"></div>
                        <div class="form-row">
                            <div class="form-group"><label>提交按钮文字</label><input type="text" id="cfgSubmit" value="提交"></div>
                        </div>
                        <div class="form-group"><label>提交成功标题</label><input type="text" id="cfgOkTitle" value="提交成功！"></div>
                        <div class="form-group"><label>提交成功提示</label><input type="text" id="cfgOkText" value="感谢您的参与，您的数据已记录。"></div>
                    </div>

                    <div class="card" style="padding:16px;">
                        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span>📌 表单字段</span>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addField()">＋ 添加字段</button>
                        </div>
                        <div id="fieldsContainer"></div>
                    </div>
                </div>

                <!-- Right: preview -->
                <div>
                    <div class="card" style="padding:16px;position:sticky;top:16px;">
                        <div class="card-header" style="margin-bottom:8px;">👁 实时预览</div>
                        <div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:8px;padding:16px;">
                            <div style="background:#fff;border-radius:12px;padding:24px;max-width:100%;" id="previewBox">
                                <h2 style="text-align:center;color:#333;margin-bottom:4px;font-size:18px;" id="prevTitle">信息收集表</h2>
                                <p style="text-align:center;color:#888;font-size:12px;margin-bottom:16px;" id="prevSubtitle">请填写以下信息，提交后链接将自动失效</p>
                                <div id="prevFields"></div>
                                <button style="width:100%;padding:10px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;" id="prevSubmit">📤 提交</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- === ADVANCED TAB === -->
        <div id="tab-advanced" style="display:none;">
            <div class="form-group">
                <label>目标页面内容 (HTML)</label>
                <textarea name="target_content_legacy" id="advancedContent" placeholder="粘贴 HTML 代码..." style="min-height:200px;"></textarea>
                <span class="text-muted">此模式仅按静态 HTML 输出；为防止远程代码执行，PHP 代码不会被执行。</span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:16px;">🚀 生成链接</button>
    </form>
</div>

<!-- === FIELD TEMPLATE (hidden) === -->
<template id="fieldTemplate">
    <div class="field-editor" style="background:#f8f9fa;border-radius:8px;padding:12px;margin-bottom:10px;border:1px solid #e0e0e0;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong style="font-size:13px;" class="field-label-preview">新字段</strong>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.field-editor').remove();syncFields();">✕</button>
        </div>
        <div class="form-row" style="grid-template-columns:1fr 1fr;">
            <div class="form-group"><label>标签</label><input type="text" class="f-label" oninput="syncFields()" placeholder="姓名"></div>
            <div class="form-group"><label>字段名 (英文)</label><input type="text" class="f-name" oninput="syncFields()" placeholder="name"></div>
        </div>
        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
            <div class="form-group"><label>类型</label><select class="f-type" onchange="syncFields()">
                <option value="text">文本</option><option value="email">邮箱</option><option value="tel">电话</option>
                <option value="number">数字</option><option value="date">日期</option>
                <option value="select">下拉框</option><option value="textarea">多行文本</option>
            </select></div>
            <div class="form-group"><label>默认值</label><input type="text" class="f-default" oninput="syncFields()" placeholder="预填内容..."></div>
            <div class="form-group"><label>必填</label><select class="f-required" onchange="syncFields()"><option value="0">否</option><option value="1">是</option></select></div>
        </div>
        <div class="form-row" style="grid-template-columns:1fr 1fr;">
            <div class="form-group"><label>占位文字</label><input type="text" class="f-placeholder" oninput="syncFields()" placeholder="请输入..."></div>
        </div>
        <div class="f-options-row" style="display:none;">
            <div class="form-group"><label>下拉选项 (一行一个)</label><textarea class="f-options" oninput="syncFields()" rows="3" placeholder="选项1&#10;选项2&#10;选项3"></textarea></div>
        </div>
    </div>
</template>

<script>
// ── Field management ──
let fields = <?= $copyConfig ?: $defaultFormConfig ?>;

function buildFieldData() {
    return Array.from(document.querySelectorAll('#fieldsContainer .field-editor')).map(el => {
        const type = el.querySelector('.f-type').value;
        const data = {
            name: el.querySelector('.f-name').value || 'field_' + Date.now(),
            label: el.querySelector('.f-label').value || '未命名字段',
            type: type,
            required: el.querySelector('.f-required').value === '1',
            placeholder: el.querySelector('.f-placeholder').value || '',
            default_value: el.querySelector('.f-default').value || ''
        };
        if (type === 'select') {
            data.options = (el.querySelector('.f-options').value || '').split('\n').filter(s => s.trim());
        }
        return data;
    });
}

function buildConfig() {
    return {
        type: 'form_builder',
        title: document.getElementById('cfgTitle').value,
        subtitle: document.getElementById('cfgSubtitle').value,
        submit_text: document.getElementById('cfgSubmit').value,
        success_title: document.getElementById('cfgOkTitle').value,
        success_text: document.getElementById('cfgOkText').value,
        fields: buildFieldData()
    };
}

function syncFields() {
    const cfg = buildConfig();
    document.getElementById('targetContentInput').value = JSON.stringify(cfg);

    // Update preview
    document.getElementById('prevTitle').textContent = cfg.title;
    document.getElementById('prevSubtitle').textContent = cfg.subtitle;
    document.getElementById('prevSubmit').textContent = '📤 ' + cfg.submit_text;

    const prevFields = document.getElementById('prevFields');
    prevFields.innerHTML = cfg.fields.map(f => {
        let html = '<div style="margin-bottom:10px;"><label style="font-size:11px;font-weight:600;color:#444;">' + f.label + (f.required ? ' <span style="color:#e74c3c;">*</span>' : '') + '</label>';
        const dv = (f.default_value || '').replace(/"/g, '&quot;');
        if (f.type === 'textarea') {
            html += '<textarea style="width:100%;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;min-height:50px;resize:vertical;" placeholder="' + f.placeholder + '">' + dv + '</textarea>';
        } else if (f.type === 'select') {
            html += '<select style="width:100%;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;"><option>' + (f.placeholder || '请选择') + '</option>';
            (f.options || []).forEach(o => { html += '<option' + (o === f.default_value ? ' selected' : '') + '>' + o + '</option>'; });
            html += '</select>';
        } else {
            html += '<input type="' + f.type + '" style="width:100%;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;" placeholder="' + f.placeholder + '" value="' + dv + '">';
        }
        html += '</div>';
        return html;
    }).join('');

    // Update field label previews in editor
    document.querySelectorAll('#fieldsContainer .field-editor').forEach(el => {
        const lbl = el.querySelector('.f-label').value || '新字段';
        el.querySelector('.field-label-preview').textContent = lbl;
        // Show/hide options row
        const optsRow = el.querySelector('.f-options-row');
        if (el.querySelector('.f-type').value === 'select') {
            optsRow.style.display = 'block';
        } else {
            optsRow.style.display = 'none';
        }
    });
}

function addField(data) {
    data = data || { name: 'field_' + (fields.fields.length + 1), label: '新字段', type: 'text', required: false, placeholder: '', default_value: '', options: [] };
    const tpl = document.getElementById('fieldTemplate');
    const clone = tpl.content.cloneNode(true);
    clone.querySelector('.f-label').value = data.label;
    clone.querySelector('.f-name').value = data.name;
    clone.querySelector('.f-type').value = data.type;
    clone.querySelector('.f-required').value = data.required ? '1' : '0';
    clone.querySelector('.f-placeholder').value = data.placeholder || '';
    clone.querySelector('.f-default').value = data.default_value || '';
    if (data.options && data.options.length) {
        clone.querySelector('.f-options').value = data.options.join('\n');
    }
    document.getElementById('fieldsContainer').appendChild(clone);
    syncFields();
}

// Load default fields
(function init() {
    fields.fields.forEach(f => addField(f));
})();

// ── Tab switching ──
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => { b.className = 'btn btn-sm btn-outline tab-btn'; });
        this.className = 'btn btn-sm btn-primary tab-btn';
        const tab = this.dataset.tab;
        document.getElementById('tab-visual').style.display = tab === 'visual' ? 'block' : 'none';
        document.getElementById('tab-advanced').style.display = tab === 'advanced' ? 'block' : 'none';
    });
});

// ── Form submit handling ──
document.getElementById('createForm').addEventListener('submit', function(e) {
    const visualTab = document.getElementById('tab-visual').style.display !== 'none';
    if (visualTab) {
        // Use form builder JSON
        document.getElementById('targetContentInput').value = JSON.stringify(buildConfig());
        // Clear legacy textarea so it doesn't interfere
        document.getElementById('advancedContent').name = '';
    } else {
        // Use legacy PHP content
        document.getElementById('targetContentInput').name = '';
        document.getElementById('advancedContent').name = 'target_content';
    }
});

// ── Input sync triggers ──
['cfgTitle','cfgSubtitle','cfgSubmit','cfgOkTitle','cfgOkText'].forEach(id => {
    document.getElementById(id).addEventListener('input', syncFields);
});
</script>

<?php adminFooter(); ?>
