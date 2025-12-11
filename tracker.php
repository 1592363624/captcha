<?php
/**
 * 项目操作跟踪器
 * 用于记录项目中所有界面的停留信息、按钮输入框等控件的操作信息以及次数
 * 并将这些信息存储到指定的数据库中
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
 * 创建操作记录表
 * @param PDO $pdo 数据库连接对象
 */
function create_table($pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS user_operations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_url VARCHAR(255) NOT NULL COMMENT '页面URL',
        operation_type VARCHAR(50) NOT NULL COMMENT '操作类型: view, click, input, stay',
        element_selector VARCHAR(255) DEFAULT NULL COMMENT '元素选择器',
        element_text VARCHAR(255) DEFAULT NULL COMMENT '元素文本',
        element_value VARCHAR(255) DEFAULT NULL COMMENT '元素值',
        duration INT DEFAULT NULL COMMENT '停留时间(秒)',
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
        user_agent VARCHAR(255) NOT NULL COMMENT '用户代理',
        ip_address VARCHAR(45) NOT NULL COMMENT 'IP地址'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户操作记录表';
    ";
    
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("创建表失败: " . $e->getMessage());
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
 * 记录用户操作
 * @param array $data 操作数据
 */
function record_operation($data) {
    global $db_config;
    
    $pdo = connect_db($db_config);
    if (!$pdo) {
        return false;
    }
    
    // 创建表（如果不存在）
    create_table($pdo);
    
    // 创建或更新汇总视图
    create_view($pdo);
    
    // 准备SQL语句
    $sql = "
    INSERT INTO user_operations (
        page_url, operation_type, element_selector, element_text, 
        element_value, duration, user_agent, ip_address
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $data['page_url'],
            $data['operation_type'],
            $data['element_selector'],
            $data['element_text'],
            $data['element_value'],
            $data['duration'],
            $_SERVER['HTTP_USER_AGENT'],
            $_SERVER['REMOTE_ADDR']
        ));
        return true;
    } catch (PDOException $e) {
        error_log("插入数据失败: " . $e->getMessage());
        return false;
    }
}

// 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST数据
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data) {
        // 记录操作
        $result = record_operation($data);
        
        // 返回结果
        header('Content-Type: application/json');
        echo json_encode(array('success' => $result));
        exit;
    }
}

header('HTTP/1.1 400 Bad Request');
echo json_encode(array('success' => false, 'error' => '无效请求'));
?>