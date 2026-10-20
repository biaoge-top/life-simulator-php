# 人生模拟器 · 文字版（PHP 版）

一款由 AI（DeepSeek）驱动的高写实**文字人生模拟游戏**。从呱呱坠地到寿终正寝，约 20-30 次关键抉择，每一次选择都在暗中改写你的七维属性——人生不是开卷考试，选择之前你看不到任何数值。

## 玩法特色

- **完整生命周期**：婴儿 → 幼年 → 童年 → 少年 → 青年 → 成年 → 壮年 → 中年 → 暮年，九大阶段，入园、中考、高考、毕业、婚恋、退休等人生节点自然触发
- **盲选机制**：选项只描述行为与一句含蓄暗示，选中后才会在"命运结算"中揭晓属性变动——一个选择未必能预见结果
- **七维属性**：健康 / 情感 / 社会 / 资产 / 学识 / 品德 / 运气；资产以"万（W）"为单位且**无上限**，其余 0-100
- **天赋与家境**：开局随机 2 个天赋（普通/优秀/逆天/缺陷四档）与家境，影响一生走向
- **人生无常**：约半数事件带压力、损失或两难——投资失败、失业、重病、亲友离世……属性再高也会遭横祸，只是应对余地更大
- **AI 结局**：寿终正寝或中途病逝后，AI 为你的整段人生撰写 4 字标签、人物传记、总体评价与人生高光，并收录进"结局图鉴"
- **存档**：进度保存在浏览器 localStorage，随时继续上一段人生
- **手机端适配**：属性胶囊置顶、点击属性查看作用说明、新事件自动滚动到开头

## 架构

```
浏览器前端（原生 HTML/CSS/JS，无框架）
   │  POST api/deepseek.php  { kind, state, extra }
   ▼
PHP 后端（api/deepseek.php）
   │  固定提示词在服务端拼装（api/prompts.php），Key 不出服务器
   ▼
DeepSeek API（response_format: json_object）
```

- **Key 永不外泄**：前端只发送游戏场景参数，API Key 与全部系统提示词仅存于服务端
- **防盗刷**：场景白名单、请求体限 256KB、每 IP 每分钟限 30 次、max_tokens 服务端固定
- **降级兜底**：AI 超时/失败时自动使用分阶段本地事件库，游戏不卡死
- **省 token 设计**：结果反馈合并进下一次事件生成，一局（约 25 轮）仅约 27 次 API 调用

## 快速开始

要求 **PHP ≥ 7.4**（需开启 cURL 扩展）。

```bash
# 1. 配置 Key（去 https://platform.deepseek.com 申请）
cp api/config.example.php api/config.local.php   # 然后编辑填入你的 Key
# 或者设置环境变量 DEEPSEEK_API_KEY

# 2. 本地运行
php -S localhost:8000

# 3. 浏览器打开
http://localhost:8000
```

**虚拟主机 / 宝塔部署**：整目录上传即可，无需额外配置；确保 `api/config.local.php` 已填入 Key。

## 配置项（api/config.php）

| 项 | 默认值 | 说明 |
|---|---|---|
| `DEEPSEEK_API_KEY` | 环境变量或 config.local.php | DeepSeek API Key |
| `DEEPSEEK_BASE_URL` | `https://api.deepseek.com` | 兼容 OpenAI 协议的其他服务地址 |
| `DEEPSEEK_MODEL` | `deepseek-chat` | 模型名 |
| `AI_RATE_LIMIT` | `30` | 每 IP 每分钟请求上限 |
| `AI_TIMEOUT_SEC` | `90` | AI 请求超时秒数 |

## 部署提示

- 公网部署务必**使用自己的 Key 并保留默认限流**，避免余额被盗刷
- `api/config.local.php` 已在 `.gitignore` 中，切勿提交到公开仓库

## 其他版本

- Node.js 版（零依赖，适合自托管/VPS）：见 `life-simulator-node` 仓库

## License

MIT
