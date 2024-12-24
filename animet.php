<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

// 替換原有的 traditionalize 和 simplify 函數
function traditionalize($text) {
    return convertText($text, 'Traditional');
}

function simplify($text) {
    return convertText($text, 'Simplified');
}

// 新增 convertText 函數
function convertText($text, $converter) {
    if (empty($text)) {
        return $text;
    }

    $apiUrl = 'https://api.zhconvert.org/convert';
    
    $data = array(
        'text' => $text,
        'converter' => $converter,
        'outputFormat' => 'json'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['code']) && $result['code'] === 0) {
        return $result['data']['text'];
    }
    
    return $text; // 如果轉換失敗,返回原文
}


// 可以添加一個測試函數
function test_conversion() {
    $test_text = "简体中文测试";
    echo "Original: $test_text\n";
    echo "Traditional: " . traditionalize($test_text) . "\n";
    
    $test_text2 = "繁體中文測試";
    echo "Original: $test_text2\n";
    echo "Simplified: " . simplify($test_text2) . "\n";
}

// 取消註釋下面的行來運行測試
//  test_conversion();



// 彈幕轉換類
class DanmakuConverter {
    private $screenWidth = 1920;
    private $screenHeight = 1080;
    private $fontSize = 45;
    private $alpha = 0.8;
    private $duration = 10;  // 彈幕持續時間
    private $lineCount = 15; // 彈幕軌道數
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
    
    // ... [保持原有的 convert、generateHeader 等方法不變] ...
    // 為了保持回答簡潔，這裡省略了相同的方法，實際使用時需要包含完整的類方法
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

// 處理彈幕下載
function handleDanmakuDownload($episodeId, $episodeTitle, $options) {
    try {
        $commentUrl = "https://api.dandanplay.net/api/v2/comment/" . urlencode($episodeId) . "?withRelated=true";
        
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception("無法初始化 CURL");
        }
        
        curl_setopt($ch, CURLOPT_URL, $commentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("CURL 錯誤: " . curl_error($ch));
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new Exception("API 返回錯誤狀態碼: " . $httpCode);
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON 解析錯誤: " . json_last_error_msg());
        }
        
        if (!isset($data['comments']) || !is_array($data['comments'])) {
            throw new Exception("未找到彈幕數據或數據格式錯誤");
        }
        
        $converter = new DanmakuConverter($options);
        $assContent = $converter->convert($data['comments']);
        
        // 將內容轉換為繁體
        $assContent = traditionalize($assContent);
        $episodeTitle = traditionalize($episodeTitle);
        
        return ['success' => true, 'content' => $assContent, 'filename' => $episodeTitle . '.ass'];
    } catch (Exception $e) {
        error_log("彈幕下載錯誤: " . $e->getMessage());
        return ['success' => false, 'message' => traditionalize($e->getMessage())];
    }
}

// 處理下載請求
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
            if (ob_get_length()) ob_clean();
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($result['filename']) . '"');
            header('Content-Length: ' . strlen($result['content']));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            echo $result['content'];
            exit();
        } else {
            echo "<div class='error' style='color: red; padding: 10px;'>";
            echo "下載失敗: " . htmlspecialchars($result['message']);
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error' style='color: red; padding: 10px;'>";
        echo "系統錯誤: " . htmlspecialchars(traditionalize($e->getMessage()));
        echo "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>彈幕搜尋與轉換</title>
    <style>
        body {
            font-family: "Microsoft JhengHei", Arial, sans-serif;
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
</head>
<body>
    <div style="text-align: right; margin: 10px;">
        <button onclick="window.location.href='anime.php'" style="background-color: #2196F3;">切換至簡體中文</button>
    </div>

    <h1>彈幕搜尋與轉換</h1>
    
    <!-- 搜尋表單 -->
    <form method="GET" action="" class="search-form">
        <div class="option-group">
            <label for="anime">輸入動漫名稱：</label>
            <input type="text" id="anime" name="anime" required value="<?php echo isset($_GET['anime']) ? htmlspecialchars($_GET['anime']) : ''; ?>">
            <button type="submit">搜尋</button>
        </div>
    </form>

    <!-- 轉換選項 -->
    <div class="options">
        <h3>轉換選項</h3>
        <div class="option-group">
            <label for="fontSize">字體大小：</label>
            <input type="number" id="fontSize" value="45" min="1" max="100">
        </div>
        <div class="option-group">
            <label for="alpha">透明度：</label>
            <input type="range" id="alpha" min="0" max="100" value="80">
            <span id="alphaValue">80%</span>
        </div>
        <div class="option-group">
            <label for="duration">彈幕持續時間：</label>
            <input type="number" id="duration" value="10" min="1" max="20">
            <span>秒</span>
        </div>
        <div class="option-group">
            <label for="fontName">字體：</label>
            <select id="fontName">
                <option value="Microsoft JhengHei">微軟正黑體</option>
                <option value="DFKai-SB">標楷體</option>
                <option value="MingLiU">細明體</option>
                <option value="PMingLiU">新細明體</option>
            </select>
        </div>
    </div>

    <!-- 搜索結果顯示區域 -->
    <?php
    if (isset($_GET['anime']) && !isset($_GET['download'])) {
        $anime = simplify($_GET['anime']); // 將搜索關鍵詞轉為簡體
        $searchUrl = "https://api.dandanplay.net/api/v2/search/episodes?anime=" . urlencode($anime);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<div class='error'>請求失敗：" . traditionalize(curl_error($ch)) . "</div>";
        } else {
            $data = json_decode($response, true);
            
            if (isset($data['success']) && $data['success']) {
                if (empty($data['animes'])) {
                    echo "<div class='notice'>未找到相關動畫，請嘗試其他關鍵詞。</div>";
                } else {
                    echo "<div class='search-results'>";
                    echo "<h2>搜尋結果：</h2>";
                    foreach ($data['animes'] as $anime) {
                        echo "<div class='anime-item'>";
                        echo "<h3>" . traditionalize(htmlspecialchars($anime['animeTitle'])) . "</h3>";
                        echo "<ul>";
                        foreach ($anime['episodes'] as $episode) {
                            echo "<li>";
                            $episodeTitle = traditionalize($anime['animeTitle'] . " - " . $episode['episodeTitle']);
                            echo "<a href='javascript:void(0)' onclick='downloadWithOptions(\"" . 
                                htmlspecialchars($episode['episodeId'], ENT_QUOTES) . "\", \"" . 
                                htmlspecialchars($episodeTitle, ENT_QUOTES) . "\")'>";
                            echo traditionalize(htmlspecialchars($episode['episodeTitle'])) . " (下載彈幕)";
                            echo "</a>";
                            echo "</li>";
                        }
                        echo "</ul>";
                        echo "</div>";
                    }
                    echo "</div>";
                }
            } else {
                $errorMessage = isset($data['errorMessage']) ? traditionalize($data['errorMessage']) : '未知錯誤';
                echo "<div class='error'>搜尋失敗：" . htmlspecialchars($errorMessage) . "</div>";
            }
        }
        
        curl_close($ch);
    }
    ?>

    <script>
    document.getElementById('alpha').addEventListener('input', function() {
        document.getElementById('alphaValue').textContent = this.value + '%';
    });

    function downloadWithOptions(episodeId, title) {
        try {
            const fontSize = document.getElementById('fontSize').value;
            const alpha = (document.getElementById('alpha').value / 100).toFixed(2);
            const duration = document.getElementById('duration').value;
            const fontName = document.getElementById('fontName').value;
            
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = window.location.pathname;
            
            const params = {
                download: '1',
                episodeId: episodeId,
                title: title,
                fontSize: fontSize,
                alpha: alpha,
                duration: duration,
                fontName: fontName
            };
            
            Object.keys(params).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            
            setTimeout(() => {
                document.body.removeChild(form);
            }, 100);
            
        } catch (error) {
            console.error('下載出錯:', error);
            alert('下載過程中出現錯誤，請稍後重試。');
        }
    }
    
    document.getElementById('fontSize').addEventListener('change', function() {
        if (this.value < 1) this.value = 1;
        if (this.value > 100) this.value = 100;
    });
    
    document.getElementById('duration').addEventListener('change', function() {
        if (this.value < 1) this.value = 1;
        if (this.value > 20) this.value = 20;
    });
    </script>

    <div id="downloadingStatus" class="downloading">
        正在準備下載...
    </div>
    <!-- 在body結束標籤前添加 -->
    <div style="margin-top: 50px; padding: 20px; background-color: #f8f9fa; border-radius: 5px;">
        <h3>API 使用聲明</h3>
        <ul>
            <li>本程式使用了繁化姬的 API 服務</li>
            <li>繁化姬商用必須付費</li>
            <li>API 只接受 UTF-8 編碼的內容</li>
            <li>更多資訊請參考：<a href="https://docs.zhconvert.org" target="_blank">繁化姬說明文件</a></li>
        </ul>
    </div>
</body>
</html>
