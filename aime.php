<?php
// 开始会话，用于存储临时数据
session_start();

// 函数：处理弹幕下载
function handleDanmakuDownload($episodeId, $episodeTitle) {
    $commentUrl = "https://api.dandanplay.net/api/v2/comment/" . urlencode($episodeId) . "?withRelated=false";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $commentUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        return ['success' => false, 'message' => "请求失败"];
    }
    
    $data = json_decode($response, true);
    if (!isset($data['comments'])) {
        return ['success' => false, 'message' => "未找到弹幕数据"];
    }
    
    // 创建 ASS 文件内容
    $assContent = "[Script Info]\nTitle: 弹幕\nScriptType: v4.00+\nPlayResX: 1920\nPlayResY: 1080\n\n";
    $assContent .= "[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n";
    // 修改样式：
    // 1. PrimaryColour: &H80FFFFFF 保持半透明
    // 2. Alignment: 8 表示上方对齐
    // 3. MarginV: 40 调整为较小的值，确保在顶部
    $assContent .= "Style: Default,Arial,20,&H80FFFFFF,&H000000FF,&H00000000,&H64000000,-1,0,0,0,100,100,0,0,1,1,0,8,10,10,40,1\n\n";
    $assContent .= "[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";
    
    foreach ($data['comments'] as $comment) {
        $p = explode(',', $comment['p']);
        $startTime = gmdate("H:i:s", floor($p[0])) . "." . substr(($p[0] - floor($p[0])) * 100, 0, 2);
        $endTime = gmdate("H:i:s", floor($p[0] + 4)) . "." . substr(($p[0] + 4 - floor($p[0] + 4)) * 100, 0, 2);
        $text = $comment['m'];
        // MarginV 设为 0，因为已经在样式中定义了默认上边距
        $assContent .= "Dialogue: 0,$startTime,$endTime,Default,,0,0,0,,$text\n";
    }
    
    return ['success' => true, 'content' => $assContent, 'filename' => $episodeTitle . '.ass'];
}

// 检查是否是下载请求
if (isset($_GET['download']) && isset($_GET['episodeId']) && isset($_GET['title'])) {
    $result = handleDanmakuDownload($_GET['episodeId'], $_GET['title']);
    if ($result['success']) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($result['filename']) . '"');
        echo $result['content'];
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>动漫搜索与弹幕下载</title>
</head>
<body>
    <h1>搜索剧集并下载弹幕</h1>
    <form method="GET" action="">
        <label for="anime">输入动漫名称：</label>
        <input type="text" id="anime" name="anime" required>
        <button type="submit">搜索</button>
    </form>

    <?php
    if (isset($_GET['anime']) && !isset($_GET['download'])) {
        $anime = $_GET['anime'];
        $searchUrl = "https://api.dandanplay.net/api/v2/search/episodes?anime=" . urlencode($anime);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            
            if ($data['success']) {
                echo "<h2>搜索结果：</h2>";
                foreach ($data['animes'] as $anime) {
                    echo "<h3>" . htmlspecialchars($anime['animeTitle']) . "</h3>";
                    echo "<ul>";
                    foreach ($anime['episodes'] as $episode) {
                        echo "<li>";
                        echo "<a href='?download=1&episodeId=" . $episode['episodeId'] . "&title=" . urlencode($episode['episodeTitle']) . "'>";
                        echo htmlspecialchars($episode['episodeTitle']) . " (下载弹幕)";
                        echo "</a>";
                        echo "</li>";
                    }
                    echo "</ul>";
                }
            } else {
                echo "错误：" . htmlspecialchars($data['errorMessage']);
            }
        } else {
            echo "请求失败，请稍后重试。";
        }
    }
    ?>
</body>
</html>
