<?php
/* ============================================================
 * config.php — 服务端配置
 * API Key 通过环境变量或 api/config.local.php 提供，本文件不含任何密钥
 * ============================================================ */

// 本地私密配置（config.local.php 不入库，见 .gitignore / config.example.php）
$local = __DIR__ . '/config.local.php';
if (is_file($local)) require $local;

// 优先级：config.local.php 中已定义 > 环境变量 > 空
if (!defined('DEEPSEEK_API_KEY')) {
  define('DEEPSEEK_API_KEY', getenv('DEEPSEEK_API_KEY') ?: '');
}
if (!defined('DEEPSEEK_BASE_URL')) {
  define('DEEPSEEK_BASE_URL', getenv('DEEPSEEK_BASE_URL') ?: 'https://api.deepseek.com');
}
if (!defined('DEEPSEEK_MODEL')) {
  define('DEEPSEEK_MODEL', getenv('DEEPSEEK_MODEL') ?: 'deepseek-chat');
}

define('AI_TIMEOUT_SEC', 90);        // 单次请求超时
define('AI_MAX_BODY_BYTES', 262144); // 前端请求体上限 256KB，防滥用
define('AI_RATE_LIMIT', 30);         // 每 IP 每分钟最多请求次数，防盗刷
define('AI_RATE_WINDOW', 60);
