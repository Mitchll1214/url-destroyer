<?php
/**
 * Export — CSV export for form submission data
 * Only exports opened/expired links; respects current search filters
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();

// ── Collect filters from query string ──
$statusFilter = $_GET['status'] ?? '';
$searchCampaign = trim($_GET['campaign'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

// ── Build query: only opened or expired links (those that have been accessed) ──
$where = ["status IN ('opened', 'expired')"];
$params = [];

if ($statusFilter && in_array($statusFilter, ['opened', 'expired'])) {
    $where = ["status = :status"];
    $params[':status'] = $statusFilter;
}

if ($searchCampaign !== '') {
    $where[] = "campaign_name LIKE :campaign";
    $params[':campaign'] = '%' . $searchCampaign . '%';
}
if ($dateFrom !== '') {
    $where[] = "created_at >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = "created_at <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// ── Fetch matching links ──
$stmt = $db->prepare("SELECT * FROM links $whereClause ORDER BY campaign_name, id");
$stmt->execute($params);
$links = $stmt->fetchAll();

if (empty($links)) {
    adminHeader('导出数据', 'links');
    echo '<h1 class="page-title">📥 导出数据</h1>';
    echo '<div class="alert alert-error">没有符合条件的已打开/已过期链接可导出。</div>';
    echo '<a href="links.php" class="btn btn-outline">← 返回链接列表</a>';
    adminFooter();
    exit;
}

// ── Collect all unique field names across all form configs ──
$allFieldNames = [];
$linkFields = []; // link_id => [field_name => field_label]

foreach ($links as $link) {
    $content = $link['target_content'];
    if (empty(trim($content))) continue;
    $cfg = json_decode($content, true);
    if (!is_array($cfg) || ($cfg['type'] ?? '') !== 'form_builder') continue;
    $linkFields[$link['id']] = [];
    foreach ($cfg['fields'] ?? [] as $f) {
        $name = $f['name'] ?? '';
        $label = $f['label'] ?? $name;
        if ($name === '') continue;
        $linkFields[$link['id']][$name] = $label;
        $allFieldNames[$name] = $label;
    }
}

if (empty($allFieldNames)) {
    adminHeader('导出数据', 'links');
    echo '<h1 class="page-title">📥 导出数据</h1>';
    echo '<div class="alert alert-error">链接中没有可视化表单数据可导出（仅支持表单构建器创建的链接）。</div>';
    echo '<a href="links.php" class="btn btn-outline">← 返回链接列表</a>';
    adminFooter();
    exit;
}

// ── Fetch all access logs with form_data for these links ──
$linkIds = array_column($links, 'id');
$placeholders = implode(',', array_fill(0, count($linkIds), '?'));
$logStmt = $db->prepare("
    SELECT al.*, l.campaign_name, l.token 
    FROM access_logs al 
    JOIN links l ON al.link_id = l.id 
    WHERE al.link_id IN ($placeholders) AND al.form_data != '' 
    ORDER BY l.campaign_name, al.accessed_at
");
$logStmt->execute($linkIds);
$logs = $logStmt->fetchAll();

// ── Build CSV ──
$fieldNamesOrdered = array_keys($allFieldNames);

// Headers: 第1行字段标签，第2行字段名，之后为数据
$headerLabels = array_merge(
    ['活动名称', '链接ID', 'Token'],
    array_values($allFieldNames),
    ['提交时间']
);
$headerNames = array_merge(
    ['campaign_name', 'link_id', 'token'],
    array_keys($allFieldNames),
    ['accessed_at']
);

$csvFilename = 'export_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $csvFilename . '"');

// BOM for Excel UTF-8 compatibility
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write headers
fputcsv($output, $headerLabels);
fputcsv($output, $headerNames);

// Write rows
foreach ($logs as $log) {
    $formData = json_decode($log['form_data'], true) ?: [];
    $row = [
        $log['campaign_name'],
        $log['link_id'],
        $log['token'],
    ];
    foreach ($fieldNamesOrdered as $fn) {
        $row[] = $formData[$fn] ?? '';
    }
    $row[] = $log['accessed_at'];
    fputcsv($output, $row);
}

fclose($output);
exit;
