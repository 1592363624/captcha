<?php
/**
 * 用户操作汇总信息展示页面
 * 用于展示用户操作的汇总数据，包括访问次数、点击次数、输入次数、停留时间等
 */

// 数据库配置
$db_config = array(
    'host' => 'localhost',
    'dbname' => 'captcha',
    'username' => 'captcha',
    'password' => 'ANPfPjkRrPRC2rbz'
);

/**
 * 连接数据库
 * @return PDO|null 数据库连接对象，连接失败返回null
 */
function connect_db($config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("数据库连接失败: " . $e->getMessage());
        return null;
    }
}

/**
 * 创建用户操作汇总视图
 * @param PDO $pdo 数据库连接对象
 */
function create_view($pdo) {
    $sql = "
    CREATE OR REPLACE VIEW user_operations_summary AS
    SELECT 
        ip_address,
        user_agent,
        page_url,
        COUNT(CASE WHEN operation_type = 'view' THEN 1 END) AS view_count,
        COUNT(CASE WHEN operation_type = 'click' THEN 1 END) AS click_count,
        COUNT(CASE WHEN operation_type = 'input' THEN 1 END) AS input_count,
        SUM(CASE WHEN operation_type = 'stay' THEN duration ELSE 0 END) AS total_stay_seconds,
        MIN(timestamp) AS first_visit_time,
        MAX(timestamp) AS last_visit_time,
        COUNT(DISTINCT element_selector) AS unique_elements_interacted
    FROM 
        user_operations
    GROUP BY 
        ip_address, user_agent, page_url
    ORDER BY 
        last_visit_time DESC;
    ";
    
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("创建视图失败: " . $e->getMessage());
    }
}

/**
 * 获取用户操作汇总数据
 * @param PDO $pdo 数据库连接对象
 * @return array 用户操作汇总数据数组
 */
function get_operations_summary($pdo) {
    try {
        // 先创建或更新视图
        create_view($pdo);
        
        $sql = "SELECT * FROM user_operations_summary";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取操作汇总数据失败: " . $e->getMessage());
        return array();
    }
}

/**
 * 格式化时间
 * @param string $datetime 时间字符串
 * @return string 格式化后的时间字符串
 */
function format_datetime($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}

/**
 * 格式化停留时间
 * @param int $seconds 秒数
 * @return string 格式化后的时间字符串
 */
function format_stay_time($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    $parts = array();
    if ($hours > 0) {
        $parts[] = "{$hours}小时";
    }
    if ($minutes > 0) {
        $parts[] = "{$minutes}分钟";
    }
    if ($seconds > 0 || empty($parts)) {
        $parts[] = "{$seconds}秒";
    }
    
    return implode(' ', $parts);
}

// 连接数据库
$pdo = connect_db($db_config);

// 获取汇总数据
$summary_data = array();
if ($pdo) {
    $summary_data = get_operations_summary($pdo);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户操作汇总信息</title>
    <link rel="icon" href="logo.jpg" type="image/jpg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .summary-stats {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .summary-table {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #3498db;
            color: #fff;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #e3f2fd;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
            font-style: italic;
        }
        
        .user-agent {
            font-size: 12px;
            color: #666;
        }
        
        .duration {
            color: #27ae60;
        }
        
        .count {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>用户操作汇总信息</h1>
        
        <!-- 统计概览 -->
        <div class="summary-stats">
            <h2>统计概览</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($summary_data); ?></div>
                    <div class="stat-label">总记录数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_unique(array_column($summary_data, 'ip_address'))); ?></div>
                    <div class="stat-label">独立IP数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_unique(array_column($summary_data, 'page_url'))); ?></div>
                    <div class="stat-label">页面数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo array_sum(array_column($summary_data, 'click_count')); ?></div>
                    <div class="stat-label">总点击次数</div>
                </div>
            </div>
        </div>
        
        <!-- 详细汇总表格 -->
        <div class="summary-table">
            <h2 style="padding: 20px 20px 0;">详细汇总数据</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>IP地址</th>
                            <th>用户代理</th>
                            <th>页面URL</th>
                            <th>访问次数</th>
                            <th>点击次数</th>
                            <th>输入次数</th>
                            <th>停留时间</th>
                            <th>首次访问</th>
                            <th>最后访问</th>
                            <th>交互元素数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($summary_data)): ?>
                            <tr>
                                <td colspan="10" class="no-data">暂无数据</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($summary_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                    <td class="user-agent"><?php echo htmlspecialchars($row['user_agent']); ?></td>
                                    <td><?php echo htmlspecialchars($row['page_url']); ?></td>
                                    <td class="count"><?php echo $row['view_count']; ?></td>
                                    <td class="count"><?php echo $row['click_count']; ?></td>
                                    <td class="count"><?php echo $row['input_count']; ?></td>
                                    <td class="duration"><?php echo format_stay_time($row['total_stay_seconds']); ?></td>
                                    <td><?php echo format_datetime($row['first_visit_time']); ?></td>
                                    <td><?php echo format_datetime($row['last_visit_time']); ?></td>
                                    <td><?php echo $row['unique_elements_interacted']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>