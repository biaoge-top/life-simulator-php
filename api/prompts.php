<?php
/* ============================================================
 * prompts.php — 固定提示词（仅存于服务端，前端无法查看/篡改）
 * 四种场景：birth 出生 / event 事件+选项 / outcome 结果反馈 / ending 结局
 * ============================================================ */

const ATTR_NAME_MAP = [
  'health' => '健康', 'emotion' => '情感', 'social' => '社会', 'wealth' => '资产',
  'knowledge' => '学识', 'morality' => '品德', 'luck' => '运气'
];

function attr_level($key, $v) {
  if ($v >= 90) return '极佳';
  if ($v >= 80) return '良好';
  if ($v >= 60) return '普通';
  if ($v >= 40) return '欠佳';
  return '危险';
}

function wealth_level($v) {
  if ($v >= 1000) return '巨富';
  if ($v >= 200) return '富裕';
  if ($v >= 60) return '小康';
  if ($v >= 20) return '普通';
  if ($v >= 8) return '拮据';
  return '贫困';
}

function talents_text($talents, $withDesc = true) {
  $parts = [];
  foreach ((array)$talents as $t) {
    $name = isset($t['name']) ? $t['name'] : '';
    $desc = isset($t['desc']) ? $t['desc'] : '';
    $parts[] = $withDesc ? ('《' . $name . '》(' . $desc . ')') : $name;
  }
  return implode('、', $parts);
}

function state_digest($s) {
  $a = (array)$s['attrs'];
  $fam = (array)$s['family'];
  $lines = [
    '【角色当前状态】',
    '年龄：' . $s['age'] . ' 岁（' . $s['stage'] . '）',
    '性别：' . $s['gender'],
    '天赋：' . talents_text($s['talents'] ?? []),
    '家境：' . ($fam['level'] ?? '') . '——' . ($fam['desc'] ?? '') . '（18岁前"资产"代表家庭经济状况，18岁后代表个人财富）',
    '属性（0-100）：',
    '  健康 ' . $a['health'] . '（' . attr_level('health', $a['health']) . '）',
    '  情感 ' . $a['emotion'] . '（' . attr_level('emotion', $a['emotion']) . '）',
    '  社会 ' . $a['social'] . '（' . attr_level('social', $a['social']) . '）',
    '  资产 ' . $a['wealth'] . '万（' . wealth_level($a['wealth']) . '，资产无上限，数值即存款的万元数）',
    '  学识 ' . $a['knowledge'] . '（' . attr_level('knowledge', $a['knowledge']) . '）',
    '  品德 ' . $a['morality'] . '（' . attr_level('morality', $a['morality']) . '）',
    '  运气 ' . $a['luck'] . '（' . attr_level('luck', $a['luck']) . '）'
  ];
  if ($s['age'] >= 61) $lines[] = '提示：角色已步入老年，身体机能下降是不可回避的主题。';
  if ($a['health'] <= 20) $lines[] = '提示：健康已岌岌可危，本次事件应体现疾病/病危压力。';
  if ($a['emotion'] <= 20) $lines[] = '提示：情绪处于抑郁边缘，可安排心理危机事件，但出路应包含求助选项。';
  if ($a['wealth'] <= 19 && $s['age'] >= 18) $lines[] = '提示：经济赤贫，生存压力事件优先。';
  return implode("\n", $lines);
}

function recent_digest($s) {
  $history = array_values(array_filter((array)($s['history'] ?? []), function ($h) {
    return !empty($h['title']);
  }));
  $list = array_slice($history, -3);
  if (!count($list)) return '【最近经历】这是人生第一场事件（刚刚降生）。';
  $rows = [];
  foreach ($list as $h) {
    $choice = !empty($h['choiceTitle']) ? ('；你选择了「' . $h['choiceTitle'] . '」') : '';
    $outcome = !empty($h['outcome']) ? ('；结果：' . mb_substr($h['outcome'], 0, 60)) : '';
    $rows[] = '- ' . $h['age'] . '岁：' . $h['title'] . $choice . $outcome;
  }
  return "【最近经历，注意衔接与避免重复】\n" . implode("\n", $rows);
}

function random_event_type($s) {
  $a = (array)$s['attrs'];
  $bag = ['随机生活事件'];
  if ($s['age'] >= 13) {
    $bag[] = '突发坎坷事件（投资失败/失业/生病/意外破财/家庭变故等，带明显损失或两难）';
    $bag[] = '突发坎坷事件（投资失败/失业/生病/意外破财/家庭变故等，带明显损失或两难）';
  }
  if ($s['age'] >= 30) {
    $bag[] = '重大变故事件（亲人生病或离世、婚姻危机、事业重创、健康亮红灯等人生重击）';
  }
  if ($a['luck'] >= 60 || $a['knowledge'] >= 70 || $a['social'] >= 70) $bag[] = '随机机遇事件（贵人、晋升、意外收获等）';
  if ($a['health'] <= 65) $bag[] = '健康危机事件';
  if ($a['wealth'] <= 20 && $s['age'] >= 16) $bag[] = '经济压力事件';
  if ($a['emotion'] <= 60) $bag[] = '情感/心理波动事件';
  if ($a['social'] <= 65) $bag[] = '人际矛盾事件';
  if ($a['morality'] >= 75) $bag[] = '道德抉择事件';
  if ($a['knowledge'] >= 75 && $a['wealth'] >= 200 && $s['age'] >= 22) $bag[] = '特殊隐藏事件（创业/科研/成名的契机）';
  if ($a['morality'] <= 25 && $s['age'] >= 16) $bag[] = '善恶分岔事件（面临歧路诱惑）';
  return $bag[array_rand($bag)];
}

function ending_tier($s) {
  $a = (array)$s['attrs'];
  $avg = ($a['health'] + $a['emotion'] + $a['social'] + min(100, $a['wealth']) + $a['knowledge'] + $a['morality']) / 6;
  if ($s['age'] < 40) return ['label' => '悲剧结局', 'hint' => '英年早逝，人生戛然而止，基调悲怆'];
  if ($avg >= 75 && $a['wealth'] >= 300) return ['label' => '传奇结局', 'hint' => '成就卓越、人生圆满，可写得传奇色彩'];
  if ($avg >= 60) return ['label' => '顺遂结局', 'hint' => '一生平稳安康，平淡中见幸福'];
  if ($avg >= 42) return ['label' => '浮沉结局', 'hint' => '有起有落，苦乐参半'];
  return ['label' => '坎坷/悲剧结局', 'hint' => '多灾多难、挣扎求生，基调沉重但保留尊严'];
}

function format_effects($effects) {
  $parts = [];
  foreach ((array)$effects as $k => $v) {
    if (!isset(ATTR_NAME_MAP[$k])) continue;
    $parts[] = ATTR_NAME_MAP[$k] . ($v > 0 ? '+' : '') . $v;
  }
  return count($parts) ? implode('，', $parts) : '无明显变化';
}

function event_system_prompt() {
  return implode("\n", [
    '你是一款高写实风格"文字人生模拟器"的 AI 事件引擎，通过 JSON 接口驱动游戏。',
    '',
    '【铁律】',
    '1. 只输出一个 JSON 对象，不要输出 JSON 以外的任何文字、解释或 markdown 代码块。',
    '2. 严格符合给定 JSON Schema，不得缺项、不得增加未定义字段。',
    '3. 剧情必须严格贴合角色的年龄、性别、天赋、家境与七维属性，符合现实人生常识：',
    '   - 0-6 岁婴幼儿没有自主行动能力，选项必须以"父母/监护人替孩子做决定"的视角书写；',
    '   - 7-18 岁学生阶段以家庭、校园、学习、同伴事件为主；',
    '   - 19 岁以后才允许出现婚恋、职场、创业等成人事件；',
    '   - 61 岁后以退休、养老、健康、代际事件为主。',
    '4. 提供 3-5 个选项，整体风格需差异化覆盖：稳扎稳打（保守）、激进冒险（搏一把）、随遇而安（佛系）、另辟蹊径（另类）；各选项导致的属性走向必须明显不同。',
    '5. 每个选项必须携带 effects 字段表示属性变动：',
    '   - 键名仅允许：health、emotion、social、wealth、knowledge、morality、luck；',
    '   - 只填写会发生变化的属性，值为非零整数；',
    '   - 单次单属性变动区间 -20 ~ +20；生死攸关/重大命运转折类事件允许 -30 ~ +30；',
    '   - 行为与增减必须逻辑自洽：好事正向、坏事负向，例如熬夜学习=学识+健康-，坑蒙拐骗=资产/社会+而品德大减。',
    '   - 【资产无上限】其余属性锁定 0-100，但 wealth 不设上限，wealth 的数值直接就是钱：1 点 = 1 万元（如 wealth 85 即 85 万存款）。发大财、企业上市、拆迁暴富等可以让 wealth 大幅超出 100。',
    '6. hint 用一句话（30字内）含蓄暗示该选择可能的短期或长期后果，提升代入感，但不要直接剧透。',
    '   【关键·防剧透】玩家在选择前看不到任何属性数字，人生不是开卷考试。title、detail、hint 三个文案字段中严禁出现属性名、数值、加号减号、"提升/降低XX点"等任何量化暗示（例如禁止写"健康+5""学识大幅提升"），也不要直白点出"这是保守/激进选项"；后果只能用生活化的、留有悬念的语言侧面烘托（如"花钱买个踏实""痛快一时，成绩堪忧"）。所有精确数值只能写在 effects 字段里，由系统在玩家选择后揭晓。',
    '7. description 为 100-200 字的事件剧情，真实、细腻、有生活气息，第二人称"你"叙事；若给出上一事件，应与之自然衔接或呼应。',
    '8. 数值极端原则：某属性 ≥80 时优先给该角色高光/顺风事件，某属性 ≤20 时优先触发低谷/危机事件；年龄越大，健康与危机类事件概率越高。',
    '8.5 【人生无常·强制坎坷】真实人生绝非一帆风顺。不要连续生成平淡温馨的琐事，要敢于给玩家"当头一棒"：投资/创业失败血本无归、失业降薪、自己或家人生重病、意外破财、被骗、亲友离世（适龄）、感情破裂、重大失误等具有冲击力的事件应占到相当比例（约一半事件带明显的压力、损失或两难冲突）。属性高的角色也会遭遇横祸，只是应对余地更大。每次抉择之间相隔数年，事件应是该阶段有分量的人生大事，而非日常鸡毛蒜皮。',
    '8.6 【金钱具象化】涉及资产变动的剧情，文案中可以用具体金额增强真实感（如"亏了三十万""攒下人生第一个十万"），金额须与 effects 中的 wealth 数值自洽：wealth 1 点 = 1 万元，wealth+5 即进账 5 万。18 岁前的 wealth 是家境，写父母/家庭的钱。',
    '9. 如给定"必触人生节点"，事件必须紧密围绕该节点展开，这是本年龄不可跳过的主线。',
    '10. 禁止低俗、色情、暴力血腥、违法犯罪美化、违背公序良俗的内容；自杀、自残只能作为心理危机背景出现，且选项必须指向求助与疗愈。',
    '11. 禁止与最近 3 条已发生事件重复主题。',
    '',
    '【输出 Schema】',
    '{',
    '  "title": "事件标题（8字以内）",',
    '  "description": "事件剧情描述，100-200字",',
    '  "is_milestone": false,',
    '  "choices": [',
    '    {',
    '      "title": "选项短标题（8字以内）",',
    '      "detail": "具体打算怎么做，20-50字",',
    '      "effects": {"health": 5, "emotion": -3}',
    '      "hint": "后果预判，30字内"',
    '    }',
    '  ]',
    '}'
  ]);
}

/* ---------------- 四种场景的 messages 构建 ---------------- */

function build_birth_messages($s) {
  $sys = implode("\n", [
    '你是"文字人生模拟器"的出生叙事引擎，只输出一个 JSON 对象，不要输出任何多余文字或 markdown。',
    '任务：根据随机生成的角色基础信息，撰写一段出生剧情，交代家庭背景、父母状况、出生环境与一个有记忆点的降生细节，为一生埋下基调。',
    '要求：',
    '1. 第二人称"你"叙事，120-180 字，文笔克制、真实、有烟火气，不浮夸；',
    '2. 家境描述必须与给定资产水平相符，天赋可以被家人隐约察觉或以民间说法体现；',
    '3. 年代做模糊化处理（可写"这一年""二十多年前"等），不涉及真实政治人物与事件；',
    '4. 输出 Schema：{"title":"降生","description":"..."}'
  ]);
  $a = (array)$s['attrs'];
  $fam = (array)$s['family'];
  $user = implode("\n", [
    '性别：' . $s['gender'],
    '天赋：' . talents_text($s['talents'] ?? []),
    '家境：' . ($fam['level'] ?? '') . '——' . ($fam['desc'] ?? ''),
    '初始属性：健康' . $a['health'] . ' 情感' . $a['emotion'] . ' 社会' . $a['social'] .
      ' 资产' . $a['wealth'] . ' 学识' . $a['knowledge'] . ' 品德' . $a['morality'] . ' 运气' . $a['luck']
  ]);
  return [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]];
}

function build_event_messages($s, $extra) {
  $milestone = isset($extra['milestone']) ? $extra['milestone'] : null;
  $prev = isset($extra['prev']) && is_array($extra['prev']) ? $extra['prev'] : null;
  $userParts = [state_digest($s), '', recent_digest($s), ''];
  if ($milestone && !empty($milestone['label'])) {
    $userParts[] = '【必触人生节点】本次事件必须围绕「' . $milestone['label'] . '」展开，is_milestone 设为 true。';
  } else {
    $userParts[] = '请生成一个符合当前年龄与状态的' . random_event_type($s) . '，is_milestone 设为 false。';
  }
  // 合并结果反馈：顺带为玩家上一次抉择撰写结果文案（省一次请求）
  if ($prev && !empty($prev['eventTitle'])) {
    $userParts[] = '【上一次抉择的结果】上一事件「' . $prev['eventTitle'] . '」中，玩家选择了「' .
      ($prev['choiceTitle'] ?? '') . '」（' . ($prev['choiceDetail'] ?? '') . '），系统判定属性变动：' .
      format_effects($prev['effects'] ?? []) . '。';
    $userParts[] = '请在输出 JSON 的最前面增加 "outcome" 字段：90-160 字，第二人称，必须紧扣该选择的具体行为（' . ($prev['choiceTitle'] ?? '') . '）写出直接结果、旁人反应与主角感受，可自然埋下伏笔；不得出现与选择相反或无关的结果，不要在文案中罗列属性数字。';
    $userParts[] = '本次输出 Schema：{"outcome":"...","title":"...","description":"...","is_milestone":false,"choices":[{"title":"","detail":"","effects":{},"hint":""}]}';
  }
  $userParts[] = '记住：只输出 JSON 对象。';
  return [
    ['role' => 'system', 'content' => event_system_prompt()],
    ['role' => 'user', 'content' => implode("\n", $userParts)]
  ];
}

function build_outcome_messages($s, $extra) {
  $event = (array)($extra['event'] ?? []);
  $choice = (array)($extra['choice'] ?? []);
  $sys = implode("\n", [
    '你是"文字人生模拟器"的结果叙事引擎，只输出一个 JSON 对象，禁止输出多余文字或 markdown。',
    '任务：根据角色状态、刚遭遇的事件以及玩家做出的选择，撰写选择之后发生的结果。',
    '要求：',
    '1. outcome 字段：90-160 字，第二人称，交代选择带来的直接结果、旁人反应与主角感受，可自然埋下后续伏笔；',
    '2. 必须与选项逻辑一致，不得出现与选择相反的结果；',
    '3. 不要在文案中罗列属性数字（属性变动由系统另行计算）；',
    '4. 真实克制，不编造超自然情节。',
    '输出 Schema：{"outcome":"..."}'
  ]);
  $user = implode("\n", [
    state_digest($s),
    '',
    '【遭遇事件】' . ($event['title'] ?? '') . '——' . ($event['description'] ?? ''),
    '【玩家选择】' . ($choice['title'] ?? '') . '：' . ($choice['detail'] ?? ''),
    '【系统判定的属性变动】' . format_effects($choice['effects'] ?? [])
  ]);
  return [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]];
}

function build_ending_messages($s, $extra) {
  $tier = ending_tier($s);
  $condensedParts = [];
  foreach ((array)($s['history'] ?? []) as $h) {
    $parts = [($h['age'] ?? '?') . '岁', $h['title'] ?? ''];
    if (!empty($h['choiceTitle'])) $parts[] = '选择「' . $h['choiceTitle'] . '」';
    $condensedParts[] = implode(' ', $parts);
  }
  $condensed = implode(' / ', $condensedParts);
  if (mb_strlen($condensed) > 5200) $condensed = mb_substr($condensed, -5200);

  $sys = implode("\n", [
    '你是"文字人生模拟器"的结局叙事引擎，只输出一个 JSON 对象，禁止输出多余文字或 markdown。',
    '任务：为走完一生的角色撰写墓志铭式的人生总结。',
    '要求：',
    '1. ending_title：4字左右的结局标签（如：寿终正寝、商界传奇、潦倒而终、桃李天下、归隐山林），须与死因和一生轨迹相符；',
    '2. summary：300-500 字人物传记体，第二人称，串联出身、关键转折、高光与低谷、晚年，呼应生平中的具体事件，避免空话；',
    '3. evaluation：80-120 字，对这一生的总体评价，克制而有温度；',
    '4. highlights：5-8 条数组，每条 12-24 字，摘取人生最关键节点与抉择；',
    '5. 倾向判定参考："' . $tier['label'] . '"——' . $tier['hint'],
    '6. 输出 Schema：{"ending_title":"","summary":"","evaluation":"","highlights":[""]}'
  ]);

  $a = (array)$s['attrs'];
  $peaks = [];
  foreach ((array)($s['peak'] ?? []) as $k => $v) { if (isset(ATTR_NAME_MAP[$k])) $peaks[] = ATTR_NAME_MAP[$k] . $v; }
  $troughs = [];
  foreach ((array)($s['trough'] ?? []) as $k => $v) { if (isset(ATTR_NAME_MAP[$k])) $troughs[] = ATTR_NAME_MAP[$k] . $v; }

  $user = implode("\n", [
    '【死亡信息】' . ($extra['cause'] ?? '寿终正寝') . '，享年 ' . $s['age'] . ' 岁',
    '【性别】' . $s['gender'],
    '【天赋】' . talents_text($s['talents'] ?? [], false),
    '【最终属性】健康' . $a['health'] . ' 情感' . $a['emotion'] . ' 社会' . $a['social'] . ' 资产' . $a['wealth'] .
      ' 学识' . $a['knowledge'] . ' 品德' . $a['morality'] . ' 运气' . $a['luck'],
    '属性峰值：' . implode(' ', $peaks),
    '属性谷值：' . implode(' ', $troughs),
    '',
    '【一生履历（按时间）】',
    $condensed
  ]);
  return [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]];
}

/* 入口：按场景构建 messages 与采样参数 */
function build_request($kind, $state, $extra) {
  switch ($kind) {
    case 'birth':   return ['messages' => build_birth_messages($state),  'max_tokens' => 800,  'temperature' => 1.05];
    case 'event':   return ['messages' => build_event_messages($state, $extra),  'max_tokens' => 2000, 'temperature' => 1.05];
    case 'outcome': return ['messages' => build_outcome_messages($state, $extra), 'max_tokens' => 700,  'temperature' => 1.0];
    case 'ending':  return ['messages' => build_ending_messages($state, $extra), 'max_tokens' => 2000, 'temperature' => 1.05];
    default:        return null;
  }
}
