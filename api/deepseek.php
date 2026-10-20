<?php
/* ============================================================
 * deepseek.php — 前端代理接口
 * POST JSON: { "kind": "birth|event|outcome|ending", "state": {...}, "extra": {...} }
 * 返回: { "content": "<模型输出的JSON字符串>" }  或  { "error": "..." }
 * Key 与提示词均只在服务端，前端无法窃取/篡改，防止盗刷余额
 * ============================================================ */
require __DIR__ . '/config.php';
require __DIR__ . '/prompts.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fail($code, $msg) {
  http_response_code($code);
  echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, '仅支持 POST');
if (empty(DEEPSEEK_API_KEY) || strpos(DEEPSEEK_API_KEY, 'sk-') !== 0) fail(500, '服务端未配置 API Key');

/* ---------- 简单限流：每 IP 每分钟 N 次（文件计数，防盗刷） ---------- */
$rateDir = sys_get_temp_dir() . '/lts_rate';
if (!is_dir($rateDir)) @mkdir($rateDir, 0700, true);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $rateDir . '/' . md5($ip) . '.json';
$now = time();
$bucket = ['start' => $now, 'count' => 0];
if (is_file($rateFile)) {
  $bucket = json_decode(@file_get_contents($rateFile), true) ?: $bucket;
  if ($now - ($bucket['start'] ?? 0) >= AI_RATE_WINDOW) $bucket = ['start' => $now, 'count' => 0];
}
$bucket['count']++;
@file_put_contents($rateFile, json_encode($bucket), LOCK_EX);
if ($bucket['count'] > AI_RATE_LIMIT) fail(429, '请求过于频繁，请稍后再试');

/* ---------- 解析并校验请求体 ---------- */
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) === 0) fail(400, '请求体为空');
if (strlen($raw) > AI_MAX_BODY_BYTES) fail(413, '请求体过大');

$body = json_decode($raw, true);
if (!is_array($body)) fail(400, '请求体不是合法 JSON');

$kind = $body['kind'] ?? '';
if (!in_array($kind, ['birth', 'event', 'outcome', 'ending'], true)) fail(400, '未知的场景类型');

$state = $body['state'] ?? null;
if (!is_array($state) || !isset($state['attrs']) || !isset($state['age'])) fail(400, '角色状态不完整');

// 服务端约束：履历只取最近 40 条，防止超长请求刷 token
if (isset($state['history']) && is_array($state['history'])) {
  $state['history'] = array_slice($state['history'], -40);
}
$extra = isset($body['extra']) && is_array($body['extra']) ? $body['extra'] : [];

/* ---------- 构建固定提示词并调用 DeepSeek ---------- */
$req = build_request($kind, $state, $extra);
if (!$req) fail(400, '未知的场景类型');

$payload = json_encode([
  'model' => DEEPSEEK_MODEL,
  'messages' => $req['messages'],
  'temperature' => $req['temperature'],
  'max_tokens' => $req['max_tokens'],
  'response_format' => ['type' => 'json_object'],
  'stream' => false
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(rtrim(DEEPSEEK_BASE_URL, '/') . '/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => AI_TIMEOUT_SEC,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'Authorization: Bearer ' . DEEPSEEK_API_KEY
  ],
  CURLOPT_POSTFIELDS => $payload
]);
$respBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($respBody === false) fail(502, '无法连接 AI 服务：' . $curlErr);

$data = json_decode($respBody, true);
if ($httpCode === 401) fail(502, '服务端 API Key 无效或已过期');
if ($httpCode === 429) fail(429, 'AI 服务繁忙或额度不足，请稍后再试');
if ($httpCode < 200 || $httpCode >= 300) {
  $msg = isset($data['error']['message']) ? $data['error']['message'] : substr($respBody, 0, 200);
  fail(502, 'AI 服务返回错误 ' . $httpCode . '：' . $msg);
}

$content = $data['choices'][0]['message']['content'] ?? '';
if (!is_string($content) || trim($content) === '') fail(502, 'AI 返回内容为空');

echo json_encode(['content' => $content], JSON_UNESCAPED_UNICODE);
