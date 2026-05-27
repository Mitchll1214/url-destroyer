<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>表单提交</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .form-container {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .form-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 8px;
            font-size: 22px;
        }
        .form-container .subtitle {
            text-align: center;
            color: #888;
            font-size: 13px;
            margin-bottom: 28px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }
        .success-message {
            text-align: center;
            padding: 20px;
        }
        .success-message .icon { font-size: 48px; margin-bottom: 12px; }
        .success-message h3 { color: #27ae60; margin-bottom: 8px; }
        .success-message p { color: #888; font-size: 14px; }
        .required { color: #e74c3c; }
    </style>
</head>
<body>

<?php
// $token is available from access.php context
$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
}
?>

<?php if ($submitted): ?>
<div class="form-container">
    <div class="success-message">
        <div class="icon">✅</div>
        <h3>提交成功！</h3>
        <p>感谢您的参与，您的数据已记录。</p>
    </div>
</div>
<?php else: ?>
<div class="form-container">
    <h2>📋 信息收集表</h2>
    <p class="subtitle">请填写以下信息，提交后链接将自动失效</p>
    <form method="post" action="?token=<?= htmlspecialchars($token ?? '') ?>">
        <div class="form-group">
            <label>姓名 <span class="required">*</span></label>
            <input type="text" name="name" required placeholder="请输入您的姓名">
        </div>
        <div class="form-group">
            <label>邮箱 <span class="required">*</span></label>
            <input type="email" name="email" required placeholder="example@mail.com">
        </div>
        <div class="form-group">
            <label>手机号</label>
            <input type="tel" name="phone" placeholder="请输入手机号">
        </div>
        <div class="form-group">
            <label>备注</label>
            <textarea name="note" placeholder="其他想说的话..."></textarea>
        </div>
        <button type="submit" class="btn-submit">📤 提交</button>
    </form>
</div>
<?php endif; ?>

</body>
</html>
