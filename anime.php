<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// 弹幕转换类
class DanmakuConverter {
    private $screenWidth = 1920;
    private $screenHeight = 1080;
    private $fontSize = 45;
    private $alpha = 0.8;
    private $duration = 10;  // 弹幕持续时间
    private $lineCount = 15; // 弹幕轨道数
    private $fontName = "Microsoft YaHei";
    
    public function __construct($options = []) {
        if (isset($options['screenWidth'])) {
            $this->screenWidth = intval($options['screenWidth']);
        }
        if (isset($options['screenHeight'])) {
            $this->screenHeight = intval($options['screenHeight']);
        }
        if (isset($options['fontSize'])) {
            $this->fontSize = intval($options['fontSize']);
        }
        if (isset($options['alpha'])) {
            $this->alpha = floatval($options['alpha']);
        }
        if (isset($options['duration'])) {
            $this->duration = intval($options['duration']);
        }
        if (isset($options['lineCount'])) {
            $this->lineCount = intval($options['lineCount']);
        }
        if (isset($options['fontName'])) {
            $this->fontName = strval($options['fontName']);
        }
    }
    
    public function convert($comments) {
        // 创建 ASS 文件头部
        $header = $this->generateHeader();
        
        // 转换弹幕
        $events = $this->convertComments($comments);
        
        return $header . $events;
    }
    
    private function generateHeader() {
        $alphaHex = str_pad(dechex(floor($this->alpha * 255)), 2, '0', STR_PAD_LEFT);
        
        $header = "[Script Info]\n";
        $header .= "ScriptType: v4.00+\n";
        $header .= "Collisions: Normal\n";
        $header .= "PlayResX: {$this->screenWidth}\n";
        $header .= "PlayResY: {$this->screenHeight}\n";
        $header .= "Timer: 100.0000\n\n";
        
        $header .= "[V4+ Styles]\n";
        $header .= "Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n";
        
        // 使用实际设定的字体大小
        $styleTemplate = "Style: %s,%s,%d,&H%sFFFFFF,&H%sFFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,1,0,%d,20,20,2,0\n";
        
        // 添加不同类型的样式
        $header .= sprintf($styleTemplate, "R2L", $this->fontName, $this->fontSize, $alphaHex, $alphaHex, 8);
        $header .= sprintf($styleTemplate, "TOP", $this->fontName, $this->fontSize, $alphaHex, $alphaHex, 8);
        $header .= sprintf($styleTemplate, "BTM", $this->fontName, $this->fontSize, $alphaHex, $alphaHex, 2);
        
        $header .= "\n[Events]\n";
        $header .= "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";
        
        return $header;
    }
    
    private function convertComments($comments) {
        $events = "";
        $tracks = array_fill(0, $this->lineCount, 0); // 轨道占用时间表
        
        foreach ($comments as $comment) {
            $p = explode(',', $comment['p']);
            $startTime = floatval($p[0]);
            $type = intval($p[1]);
            $text = $comment['m'];
            
            // 计算结束时间
            $endTime = $startTime + $this->duration;
            
            // 确定弹幕类型和轨道
            if ($type == 1) { // 滚动弹幕
                $track = $this->findAvailableTrack($tracks, $startTime);
                if ($track !== false) {
                    $events .= $this->generateScrollingComment($startTime, $endTime, $text, $track);
                    $tracks[$track] = $endTime;
                }
            } else if ($type == 4) { // 底部弹幕
                $events .= $this->generateStaticComment($startTime, $endTime, $text, "BTM");
            } else if ($type == 5) { // 顶部弹幕
                $events .= $this->generateStaticComment($startTime, $endTime, $text, "TOP");
            }
        }
        
        return $events;
    }
    
    private function findAvailableTrack($tracks, $startTime) {
        for ($i = 0; $i < count($tracks); $i++) {
            if ($tracks[$i] <= $startTime) {
                return $i;
            }
        }
        return false;
    }
    
    private function generateScrollingComment($start, $end, $text, $track) {
        $startTime = $this->formatTime($start);
        $endTime = $this->formatTime($end);
        $marginV = ($track * ($this->fontSize + 2)) + 2;
        return "Dialogue: 0,{$startTime},{$endTime},R2L,,20,20,{$marginV},,{$text}\n";
    }
    
    private function generateStaticComment($start, $end, $text, $style) {
        $startTime = $this->formatTime($start);
        $endTime = $this->formatTime($end);
        return "Dialogue: 0,{$startTime},{$endTime},{$style},,20,20,2,,{$text}\n";
    }
    
    private function formatTime($seconds) {
        // 將秒數轉換為整數和小數部分
        $integerPart = floor($seconds);
        $decimalPart = $seconds - $integerPart;
        
        // 計算時、分、秒
        $hours = floor($integerPart / 3600);
        $minutes = floor(($integerPart % 3600) / 60);
        $secs = $integerPart % 60 + $decimalPart;
        
        // 格式化輸出，確保秒數保留兩位小數
        return sprintf("%01d:%02d:%05.2f", $hours, $minutes, $secs);
    }
}

// 处理弹幕下载
function handleDanmakuDownload($episodeId, $episodeTitle, $options) {
    try {
        $commentUrl = "https://api.dandanplay.net/api/v2/comment/" . urlencode($episodeId) . "?withRelated=true";
        
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception("无法初始化 CURL");
        }
        
        curl_setopt($ch, CURLOPT_URL, $commentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用 SSL 验证，如果有证书问题可以使用
        
        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("CURL 错误: " . curl_error($ch));
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new Exception("API 返回错误状态码: " . $httpCode);
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON 解析错误: " . json_last_error_msg());
        }
        
        if (!isset($data['comments']) || !is_array($data['comments'])) {
            throw new Exception("未找到弹幕数据或数据格式错误");
        }
        
        $converter = new DanmakuConverter($options);
        $assContent = $converter->convert($data['comments']);
        
        return ['success' => true, 'content' => $assContent, 'filename' => $episodeTitle . '.ass'];
    } catch (Exception $e) {
        error_log("弹幕下载错误: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// 处理下载请求
if (isset($_GET['download']) && isset($_GET['episodeId']) && isset($_GET['title'])) {
    try {
        $options = [
            'fontSize' => isset($_GET['fontSize']) ? intval($_GET['fontSize']) : 25,
            'alpha' => isset($_GET['alpha']) ? floatval($_GET['alpha']) : 0.8,
            'duration' => isset($_GET['duration']) ? intval($_GET['duration']) : 10,
            'screenWidth' => isset($_GET['screenWidth']) ? intval($_GET['screenWidth']) : 1920,
            'screenHeight' => isset($_GET['screenHeight']) ? intval($_GET['screenHeight']) : 1080,
            'fontName' => isset($_GET['fontName']) ? $_GET['fontName'] : "Microsoft YaHei"
        ];
        
        $result = handleDanmakuDownload($_GET['episodeId'], $_GET['title'], $options);
        
        if ($result['success']) {
            // 确保清除任何之前的输出
            if (ob_get_length()) ob_clean();
            
            // 设置适当的响应头
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($result['filename']) . '"');
            header('Content-Length: ' . strlen($result['content']));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // 输出内容并立即结束脚本
            echo $result['content'];
            exit();
        } else {
            // 输出错误信息并继续显示页面
            echo "<div class='error' style='color: red; padding: 10px;'>";
            echo "下载失败: " . htmlspecialchars($result['message']);
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error' style='color: red; padding: 10px;'>";
        echo "系统错误: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-2TGCNE1N8Q"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-2TGCNE1N8Q');
</script>
    <meta charset="UTF-8">
    <title>动画弹幕线上下载</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .options {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .option-group {
            margin-bottom: 10px;
        }
        label {
            display: inline-block;
            width: 120px;
            font-weight: bold;
        }
        input[type="text"], 
        input[type="number"], 
        select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
            min-width: 150px;
        }
        button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            color: red;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ffebee;
            background-color: #ffebee;
            border-radius: 3px;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            margin: 10px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 3px;
        }
        a {
            text-decoration: none;
            color: #2196F3;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
       <div style="text-align: right; margin: 10px;">
        <button onclick="window.location.href='animet.php'" style="background-color: #2196F3;">切換至繁體中文</button>
    </div>
    <h1>动画弹幕线上下载</h1>
    
    <!-- 搜索表单 -->
    <form method="GET" action="" class="search-form">
        <div class="option-group">
            <label for="anime">输入动漫名称：</label>
            <input type="text" id="anime" name="anime" required value="<?php echo isset($_GET['anime']) ? htmlspecialchars($_GET['anime']) : ''; ?>">
            <button type="submit">搜索</button>
        </div>
    </form>

    <!-- 转换选项 -->
    <div class="options">
        <h3>转换选项</h3>
        <div class="option-group">
            <label for="fontSize">字体大小：</label>
            <input type="number" id="fontSize" value="45" min="1" max="100">
        </div>
        <div class="option-group">
            <label for="alpha">透明度：</label>
            <input type="range" id="alpha" min="0" max="100" value="80">
            <span id="alphaValue">80%</span>
        </div>
        <div class="option-group">
            <label for="duration">弹幕持续时间：</label>
            <input type="number" id="duration" value="10" min="1" max="20">
            <span>秒</span>
        </div>
        <div class="option-group">
            <label for="fontName">字体：</label>
            <select id="fontName">
                <option value="Microsoft YaHei">微软雅黑</option>
                <option value="SimHei">黑体</option>
                <option value="KaiTi">楷体</option>
                <option value="SimSun">宋体</option>
            </select>
        </div>
        <div id="content-cn" style="display: block;">
            <h1>动画弹幕一键下载工具</h1>
            <p>喜欢弹幕的朋友看过来！</p>
            <p>这是一个线上动画弹幕下载工具，让你轻松将 Bilibili、巴哈动画疯、弹弹Play 的弹幕下载成 <strong>ASS 字幕文件</strong>，在任何播放器中都能完整还原弹幕体验！</p>
            <h2>✨ 功能亮点</h2>
            <ul>
                <li>支持几乎所有动画弹幕下载</li>
                <li><strong>自动将弹幕转换为简体中文</strong></li>
                <li>简单方便，无需安装软件</li>
                <li>直接生成 ASS 字幕文件，随取随用</li>
        </div>
    </div>

<!-- 搜索结果显示区域 -->
    <?php
    if (isset($_GET['anime']) && !isset($_GET['download'])) {
        $anime = $_GET['anime'];
        $searchUrl = "https://api.dandanplay.net/api/v2/search/episodes?anime=" . urlencode($anime);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<div class='error'>请求失败：" . curl_error($ch) . "</div>";
        } else {
            $data = json_decode($response, true);
            
            if (isset($data['success']) && $data['success']) {
                if (empty($data['animes'])) {
                    echo "<div class='notice'>未找到相关动画，请尝试其他关键词。</div>";
                } else {
                    echo "<div class='search-results'>";
                    echo "<h2>搜索结果：</h2>";
                    foreach ($data['animes'] as $anime) {
                        echo "<div class='anime-item'>";
                        echo "<h3>" . htmlspecialchars($anime['animeTitle']) . "</h3>";
                        echo "<ul>";
                        foreach ($anime['episodes'] as $episode) {
                            echo "<li>";
                            $episodeTitle = $anime['animeTitle'] . " - " . $episode['episodeTitle'];
                            echo "<a href='javascript:void(0)' onclick='downloadWithOptions(\"" . 
                                htmlspecialchars($episode['episodeId'], ENT_QUOTES) . "\", \"" . 
                                htmlspecialchars($episodeTitle, ENT_QUOTES) . "\")'>";
                            echo htmlspecialchars($episode['episodeTitle']) . " (下载弹幕)";
                            echo "</a>";
                            echo "</li>";
                        }
                        echo "</ul>";
                        echo "</div>";
                    }
                    echo "</div>";
                }
            } else {
                $errorMessage = isset($data['errorMessage']) ? $data['errorMessage'] : '未知错误';
                echo "<div class='error'>搜索失败：" . htmlspecialchars($errorMessage) . "</div>";
            }
        }
        
        curl_close($ch);
    }
    ?>

    <script>
    // 在頁面加載時讀取保存的設定
document.addEventListener('DOMContentLoaded', function() {
    // 讀取保存的設定
    const savedFontSize = localStorage.getItem('fontSize') || 45;
    const savedAlpha = localStorage.getItem('alpha') || 80;
    const savedDuration = localStorage.getItem('duration') || 10;
    const savedFontName = localStorage.getItem('fontName') || 'Microsoft YaHei';
    
    // 設置表單值
    document.getElementById('fontSize').value = savedFontSize;
    document.getElementById('alpha').value = savedAlpha;
    document.getElementById('duration').value = savedDuration;
    document.getElementById('fontName').value = savedFontName;
    document.getElementById('alphaValue').textContent = savedAlpha + '%';
});

// 當設定改變時保存
function saveSettings() {
    const fontSize = document.getElementById('fontSize').value;
    const alpha = document.getElementById('alpha').value;
    const duration = document.getElementById('duration').value;
    const fontName = document.getElementById('fontName').value;
    
    localStorage.setItem('fontSize', fontSize);
    localStorage.setItem('alpha', alpha);
    localStorage.setItem('duration', duration);
    localStorage.setItem('fontName', fontName);
}

// 為每個輸入添加事件監聽器
document.getElementById('fontSize').addEventListener('change', saveSettings);
document.getElementById('alpha').addEventListener('input', saveSettings);
document.getElementById('duration').addEventListener('change', saveSettings);
document.getElementById('fontName').addEventListener('change', saveSettings);
    // 更新透明度显示值
    document.getElementById('alpha').addEventListener('input', function() {
        document.getElementById('alphaValue').textContent = this.value + '%';
    });

    function downloadWithOptions(episodeId, title) {
        try {
            // 获取所有选项的值
            const fontSize = document.getElementById('fontSize').value;
            const alpha = (document.getElementById('alpha').value / 100).toFixed(2);
            const duration = document.getElementById('duration').value;
            const fontName = document.getElementById('fontName').value;
            
            // 创建一个表单来提交下载请求
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = window.location.pathname;
            
            // 添加所需的参数
            const params = {
                download: '1',
                episodeId: episodeId,
                title: title,
                fontSize: fontSize,
                alpha: alpha,
                duration: duration,
                fontName: fontName
            };
            
            // 为每个参数创建隐藏的输入字段
            Object.keys(params).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            });
            
            // 添加到文档并提交
            document.body.appendChild(form);
            form.submit();
            
            // 清理表单
            setTimeout(() => {
                document.body.removeChild(form);
            }, 100);
            
        } catch (error) {
            console.error('下载出错:', error);
            alert('下载过程中出现错误，请稍后重试。');
        }
    }
    
    // 添加输入验证
    document.getElementById('fontSize').addEventListener('change', function() {
        if (this.value < 1) this.value = 1;
        if (this.value > 100) this.value = 100;
    });
    
    document.getElementById('duration').addEventListener('change', function() {
        if (this.value < 1) this.value = 1;
        if (this.value > 20) this.value = 20;
    });
    </script>

    <style>
    .search-results {
        margin-top: 20px;
    }
    
    .anime-item {
        margin: 20px 0;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        background-color: #fff;
    }
    
    .anime-item h3 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }
    
    .notice {
        padding: 10px;
        background-color: #e1f5fe;
        border: 1px solid #b3e5fc;
        border-radius: 3px;
        color: #0277bd;
        margin: 10px 0;
    }
    
    .downloading {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 20px;
        border-radius: 5px;
        display: none;
    }
    </style>

    <!-- 下载状态提示 -->
    <div id="downloadingStatus" class="downloading">
        正在准备下载...
    </div>
</body>
</html>
