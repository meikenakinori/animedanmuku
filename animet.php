<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

class DanmakuConverter {
    private $screenWidth = 1920;
    private $screenHeight = 1080;
    private $fontSize = 45;
    private $alpha = 0.8;
    private $duration = 10;
    private $lineCount = 15;
    private $fontName = "Microsoft YaHei";
    
    public function __construct($options = []) {
        if (isset($options['screenWidth'])) $this->screenWidth = intval($options['screenWidth']);
        if (isset($options['screenHeight'])) $this->screenHeight = intval($options['screenHeight']);
        if (isset($options['fontSize'])) $this->fontSize = intval($options['fontSize']);
        if (isset($options['alpha'])) $this->alpha = floatval($options['alpha']);
        if (isset($options['duration'])) $this->duration = intval($options['duration']);
        if (isset($options['lineCount'])) $this->lineCount = intval($options['lineCount']);
        if (isset($options['fontName'])) $this->fontName = strval($options['fontName']);
    }
    
    public function convert($comments) {
        $header = $this->generateHeader();
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
        
        $styleTemplate = "Style: %s,%s,%d,&H%sFFFFFF,&H%sFFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,1,0,%d,20,20,2,0\n";
        
        $header .= sprintf($styleTemplate, "R2L", $this->fontName, $this->fontSize, $alphaHex, $alphaHex, 8);
        $header .= sprintf($styleTemplate, "TOP", $this->fontName, $this->fontSize, $alphaHex, $alphaHex, 8);
        $header .= sprintf($styleTemplate, "BTM", $this->fontName, $this->fontSize, $alphaHex, $alphaHex, 2);
        
        $header .= "\n[Events]\n";
        $header .= "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";
        
        return $header;
    }
    
    private function convertComments($comments) {
        $events = "";
        $tracks = array_fill(0, $this->lineCount, 0);
        
        foreach ($comments as $comment) {
            $p = explode(',', $comment['p']);
            $startTime = floatval($p[0]);
            $type = intval($p[1]);
            $text = $comment['m'];
            
            $endTime = $startTime + $this->duration;
            
            if ($type == 1) {
                $track = $this->findAvailableTrack($tracks, $startTime);
                if ($track !== false) {
                    $events .= $this->generateScrollingComment($startTime, $endTime, $text, $track);
                    $tracks[$track] = $endTime;
                }
            } else if ($type == 4) {
                $events .= $this->generateStaticComment($startTime, $endTime, $text, "BTM");
            } else if ($type == 5) {
                $events .= $this->generateStaticComment($startTime, $endTime, $text, "TOP");
            }
        }
        
        return $events;
    }
    
    private function findAvailableTrack($tracks, $startTime) {
        for ($i = 0; $i < count($tracks); $i++) {
            if ($tracks[$i] <= $startTime) return $i;
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
        $integerPart = floor($seconds);
        $decimalPart = $seconds - $integerPart;
        $hours = floor($integerPart / 3600);
        $minutes = floor(($integerPart % 3600) / 60);
        $secs = $integerPart % 60 + $decimalPart;
        return sprintf("%01d:%02d:%05.2f", $hours, $minutes, $secs);
    }
}

// API 請求函數
function makeApiRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode === 200),
        'response' => $response,
        'error' => $error,
        'httpCode' => $httpCode
    ];
}

// 處理下載請求
if (isset($_GET['download']) && isset($_GET['episodeId']) && isset($_GET['title'])) {
    header('Content-Type: application/json');
    try {
        $episodeId = $_GET['episodeId'];
        $commentUrl = "https://api.dandanplay.net/api/v2/comment/" . urlencode($episodeId) . "?withRelated=true";
        
        $apiResult = makeApiRequest($commentUrl);
        
        if (!$apiResult['success']) {
            throw new Exception("API 請求失敗: HTTP " . $apiResult['httpCode'] . " - " . $apiResult['error']);
        }
        
        $data = json_decode($apiResult['response'], true);
        if (!$data || !isset($data['comments'])) {
            throw new Exception("無效的 API 響應");
        }
        
        $options = [
            'fontSize' => isset($_GET['fontSize']) ? intval($_GET['fontSize']) : 45,
            'alpha' => isset($_GET['alpha']) ? floatval($_GET['alpha']) : 0.8,
            'duration' => isset($_GET['duration']) ? intval($_GET['duration']) : 10,
            'fontName' => isset($_GET['fontName']) ? $_GET['fontName'] : "Microsoft YaHei"
        ];
        
        $converter = new DanmakuConverter($options);
        $content = $converter->convert($data['comments']);
        
        echo json_encode([
            'success' => true,
            'content' => $content,
            'filename' => $_GET['title'] . '.ass'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>彈幕搜尋與轉換</title>
    <script src="js/full.js"></script>
    <style>
        body {
            font-family: "Microsoft JhengHei", Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .search-form {
            margin: 20px 0;
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
    </style>
</head>
<body>
    <div style="text-align: right; margin: 10px;">
        <button onclick="window.location.href='anime.php'" style="background-color: #2196F3;">切換至簡體中文</button>
    </div>
    <h1>彈幕搜尋與轉換</h1>
    
    <form method="GET" action="" class="search-form" onsubmit="return handleSearch(event)">
    <div class="option-group">
        <label for="anime">動漫名稱：</label>
        <input type="text" id="anime" name="anime" required 
               value="<?php echo isset($_GET['anime']) ? htmlspecialchars($_GET['anime']) : ''; ?>">
        <button type="submit">搜尋</button>
    </div>
    </form>

    <div class="options">
        <h3>下載選項</h3>
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

    <?php
    if (isset($_GET['anime']) && !empty($_GET['anime'])) {
    $searchUrl = "https://api.dandanplay.net/api/v2/search/episodes?anime=" . urlencode($_GET['anime']);
    $apiResult = makeApiRequest($searchUrl);
    
    if (!$apiResult['success']) {
        echo "<div class='error needs-convert'>搜尋失敗：" . htmlspecialchars($apiResult['error']) . "</div>";
    } else {
        $data = json_decode($apiResult['response'], true);
        
        if (isset($data['success']) && $data['success']) {
            if (empty($data['animes'])) {
                echo "<div class='notice needs-convert'>未找到相關動畫，請嘗試其他關鍵詞。</div>";
            } else {
                echo "<div class='search-results'>";
                echo "<h2 class='needs-convert'>搜尋結果：</h2>";
                foreach ($data['animes'] as $anime) {
                    echo "<div class='anime-item'>";
                    echo "<h3 class='needs-convert'>" . htmlspecialchars($anime['animeTitle']) . "</h3>";
                    echo "<ul>";
                    foreach ($anime['episodes'] as $episode) {
                        echo "<li>";
                        $episodeTitle = $anime['animeTitle'] . " - " . $episode['episodeTitle'];
                        echo "<a href='javascript:void(0)' onclick='downloadWithOptions(\"" . 
                            htmlspecialchars($episode['episodeId'], ENT_QUOTES) . "\", \"" . 
                            htmlspecialchars($episodeTitle, ENT_QUOTES) . "\")'>";
                        echo "<span class='needs-convert'>" . htmlspecialchars($episode['episodeTitle']) . " (下載彈幕)</span>";
                        echo "</a>";
                        echo "</li>";
                    }
                    echo "</ul>";
                    echo "</div>";
                }
                echo "</div>";
            }
        } else {
            $errorMessage = isset($data['errorMessage']) ? $data['errorMessage'] : '未知錯誤';
            echo "<div class='error needs-convert'>搜尋失敗：" . htmlspecialchars($errorMessage) . "</div>";
        }
    }
}
    ?>

    <script>
        // 初始化 OpenCC
        const converter = OpenCC.Converter({ from: 'cn', to: 'tw' });
        const simplifier = OpenCC.Converter({ from: 'tw', to: 'cn' });

        function convertToTraditional(text) {
            return converter(text);
        }

        function convertToSimplified(text) {
            return simplifier(text);
        }
        function handleSearch(event) {
    event.preventDefault(); // 防止表單直接提交
    
    const searchInput = document.getElementById('anime');
    const originalText = searchInput.value;
    
    // 將繁體轉換為簡體
    const simplifiedText = convertToSimplified(originalText);
    
    // 使用簡體文字進行搜尋
    window.location.href = `${window.location.pathname}?anime=${encodeURIComponent(simplifiedText)}`;
    
    return false;
}
        // 更新透明度顯示
        document.getElementById('alpha').addEventListener('input', function() {
            document.getElementById('alphaValue').textContent = this.value + '%';
        });

        // 下載函數
function downloadWithOptions(episodeId, title) {
    try {
        // 轉換標題為繁體中文
        title = convertToTraditional(title);
        
        const fontSize = document.getElementById('fontSize').value;
        const alpha = (document.getElementById('alpha').value / 100).toFixed(2);
        const duration = document.getElementById('duration').value;
        const fontName = document.getElementById('fontName').value;

        // 顯示載入提示
        const loadingDiv = document.createElement('div');
        loadingDiv.style.position = 'fixed';
        loadingDiv.style.top = '50%';
        loadingDiv.style.left = '50%';
        loadingDiv.style.transform = 'translate(-50%, -50%)';
        loadingDiv.style.padding = '20px';
        loadingDiv.style.background = 'rgba(0,0,0,0.8)';
        loadingDiv.style.color = 'white';
        loadingDiv.style.borderRadius = '5px';
        loadingDiv.style.zIndex = '9999';
        loadingDiv.textContent = '正在下載彈幕...';
        document.body.appendChild(loadingDiv);

        // 建立 XMLHttpRequest
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `${window.location.pathname}?download=1&episodeId=${encodeURIComponent(episodeId)}&title=${encodeURIComponent(title)}&fontSize=${fontSize}&alpha=${alpha}&duration=${duration}&fontName=${encodeURIComponent(fontName)}`, true);

        xhr.onload = function() {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // 轉換內容為繁體中文
                    const convertedContent = convertToTraditional(response.content);
                    
                    // 創建下載
                    const blob = new Blob([convertedContent], {type: 'text/plain;charset=utf-8'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.filename;
                    document.body.appendChild(a);
                    a.click();
                    
                    // 清理
                    setTimeout(() => {
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    }, 100);
                } else {
                    alert(convertToTraditional('下載失敗：' + response.error));
                }
            } catch (error) {
                console.error('處理響應時出錯:', error);
                alert(convertToTraditional('處理響應時出錯，請稍後重試'));
            }
            // 移除載入提示
            document.body.removeChild(loadingDiv);
        };

        xhr.onerror = function() {
            console.error('請求失敗');
            alert(convertToTraditional('網絡請求失敗，請稍後重試'));
            document.body.removeChild(loadingDiv);
        };

        xhr.send();

    } catch (error) {
        console.error('下載出錯:', error);
        alert(convertToTraditional('下載過程中出現錯誤，請稍後重試'));
    }
}

// 確保所有文字元素在加載時轉換為繁體中文
function convertAllText() {
    const textNodes = document.evaluate(
        "//text()[not(ancestor::script)][not(ancestor::style)]",
        document,
        null,
        XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE,
        null
    );

    for (let i = 0; i < textNodes.snapshotLength; i++) {
        const node = textNodes.snapshotItem(i);
        if (node.nodeValue.trim()) {
            node.nodeValue = convertToTraditional(node.nodeValue);
        }
    }
}

// 頁面加載完成後執行轉換
document.addEventListener('DOMContentLoaded', function() {
    // 轉換所有需要轉換的文字
    const elementsToConvert = document.getElementsByClassName('needs-convert');
    for(let element of elementsToConvert) {
        element.textContent = convertToTraditional(element.textContent);
    }
    
    // 如果是搜尋結果頁面，將輸入框的值轉換回繁體
    const searchInput = document.getElementById('anime');
    if (searchInput && searchInput.value) {
        searchInput.value = convertToTraditional(searchInput.value);
    }
});
    </script>

</body>
</html>