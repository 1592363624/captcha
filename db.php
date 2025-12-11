<?php
/**
 * 数据库工具类
 * 用于处理数据库连接、用户管理和游戏进度管理
 */

// 数据库配置
$db_config = array(
    'host' => 'wbt.52shell.ltd',
    'dbname' => 'captcha',
    'username' => 'captcha',
    'password' => 'ANPfPjkRrPRC2rbz'
);

/**
 * 连接数据库
 * @return PDO|null 数据库连接对象，连接失败返回null
 */
function connect_db($config = null) {
    global $db_config;
    $config = $config ?: $db_config;
    
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
 * 初始化数据库表
 * @param PDO $pdo 数据库连接对象
 */
function init_database($pdo) {
    try {
        // 创建用户表
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telephone VARCHAR(20) NOT NULL UNIQUE COMMENT '用户电话',
            password VARCHAR(255) NOT NULL COMMENT '密码哈希',
            nickname VARCHAR(50) DEFAULT NULL COMMENT '用户昵称',
            avatar VARCHAR(255) DEFAULT NULL COMMENT '用户头像',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            last_login DATETIME DEFAULT NULL COMMENT '最后登录时间',
            login_count INT DEFAULT 0 COMMENT '登录次数',
            status TINYINT DEFAULT 1 COMMENT '用户状态: 1-正常, 0-禁用',
            UNIQUE KEY idx_telephone (telephone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';
        ";
        $pdo->exec($sql);
        
        // 创建游戏进度表
        $sql = "
        CREATE TABLE IF NOT EXISTS game_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL COMMENT '用户ID',
            game_type VARCHAR(50) NOT NULL COMMENT '游戏类型',
            level_data JSON NOT NULL COMMENT '关卡数据(JSON格式)',
            achievements JSON DEFAULT NULL COMMENT '成就数据(JSON格式)',
            game_state JSON DEFAULT NULL COMMENT '游戏状态(JSON格式)',
            total_score INT DEFAULT 0 COMMENT '总分',
            last_played DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '最后游玩时间',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY idx_user_game (user_id, game_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏进度表';
        ";
        $pdo->exec($sql);
        
        // 创建用户操作记录表（如果不存在）
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
        $pdo->exec($sql);
        
        return true;
    } catch (PDOException $e) {
        error_log("初始化数据库表失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 创建或更新用户
 * @param PDO $pdo 数据库连接对象
 * @param string $telephone 用户电话
 * @param string $password 密码
 * @return array 包含user_id和status的数组
 */
function create_or_update_user($pdo, $telephone, $password) {
    try {
        // 检查用户是否已存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE telephone = ?");
        $stmt->execute([$telephone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // 更新用户信息
            $stmt = $pdo->prepare(
                "UPDATE users SET password = ?, last_login = CURRENT_TIMESTAMP, login_count = login_count + 1 WHERE id = ?"
            );
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            return ['user_id' => $user['id'], 'status' => 'updated'];
        } else {
            // 创建新用户
            $stmt = $pdo->prepare(
                "INSERT INTO users (telephone, password) VALUES (?, ?)"
            );
            $stmt->execute([$telephone, password_hash($password, PASSWORD_DEFAULT)]);
            return ['user_id' => $pdo->lastInsertId(), 'status' => 'created'];
        }
    } catch (PDOException $e) {
        error_log("创建或更新用户失败: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * 验证用户登录
 * @param PDO $pdo 数据库连接对象
 * @param string $telephone 用户电话
 * @param string $password 密码
 * @return array 包含验证结果和用户信息的数组
 */
function verify_user($pdo, $telephone, $password) {
    try {
        // 查找用户
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telephone = ?");
        $stmt->execute([$telephone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['valid' => false, 'error' => '用户不存在'];
        }
        
        // 验证密码
        if (password_verify($password, $user['password'])) {
            // 更新最后登录时间和登录次数
            $stmt = $pdo->prepare(
                "UPDATE users SET last_login = CURRENT_TIMESTAMP, login_count = login_count + 1 WHERE id = ?"
            );
            $stmt->execute([$user['id']]);
            
            // 返回用户信息（不包含密码）
            unset($user['password']);
            return ['valid' => true, 'user' => $user];
        } else {
            return ['valid' => false, 'error' => '密码错误'];
        }
    } catch (PDOException $e) {
        error_log("验证用户失败: " . $e->getMessage());
        return ['valid' => false, 'error' => '数据库错误'];
    }
}

/**
 * 保存游戏进度
 * @param PDO $pdo 数据库连接对象
 * @param int $user_id 用户ID
 * @param string $game_type 游戏类型
 * @param array $level_data 关卡数据
 * @param array $achievements 成就数据
 * @param array $game_state 游戏状态
 * @param int $total_score 总分
 * @return bool 是否保存成功
 */
function save_game_progress($pdo, $user_id, $game_type, $level_data, $achievements = [], $game_state = [], $total_score = 0) {
    try {
        // 检查是否已存在进度
        $stmt = $pdo->prepare(
            "SELECT id FROM game_progress WHERE user_id = ? AND game_type = ?"
        );
        $stmt->execute([$user_id, $game_type]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            // 更新进度
            $stmt = $pdo->prepare(
                "UPDATE game_progress SET 
                    level_data = ?, 
                    achievements = ?, 
                    game_state = ?, 
                    total_score = ?, 
                    last_played = CURRENT_TIMESTAMP 
                WHERE id = ?"
            );
            $stmt->execute([
                json_encode($level_data),
                json_encode($achievements),
                json_encode($game_state),
                $total_score,
                $progress['id']
            ]);
        } else {
            // 创建新进度
            $stmt = $pdo->prepare(
                "INSERT INTO game_progress (
                    user_id, game_type, level_data, achievements, game_state, total_score
                ) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $user_id,
                $game_type,
                json_encode($level_data),
                json_encode($achievements),
                json_encode($game_state),
                $total_score
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("保存游戏进度失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 获取游戏进度
 * @param PDO $pdo 数据库连接对象
 * @param int $user_id 用户ID
 * @param string $game_type 游戏类型
 * @return array 游戏进度数据
 */
function get_game_progress($pdo, $user_id, $game_type) {
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM game_progress WHERE user_id = ? AND game_type = ?"
        );
        $stmt->execute([$user_id, $game_type]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            // 解析JSON数据
            $progress['level_data'] = json_decode($progress['level_data'], true);
            $progress['achievements'] = json_decode($progress['achievements'], true);
            $progress['game_state'] = json_decode($progress['game_state'], true);
        }
        
        return $progress ?: null;
    } catch (PDOException $e) {
        error_log("获取游戏进度失败: " . $e->getMessage());
        return null;
    }
}

/**
 * 获取用户基本信息
 * @param PDO $pdo 数据库连接对象
 * @param int $user_id 用户ID
 * @return array 用户信息
 */
function get_user_info($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // 移除敏感信息
            unset($user['password']);
        }
        
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("获取用户信息失败: " . $e->getMessage());
        return null;
    }
}

/**
 * 获取所有用户的游戏进度概览
 * @param PDO $pdo 数据库连接对象
 * @return array 用户游戏进度概览
 */
function get_all_users_progress($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT u.id, u.telephone, u.nickname, u.avatar, u.created_at, u.last_login, 
                   gp.game_type, gp.level_data, gp.total_score, gp.last_played 
            FROM users u 
            LEFT JOIN game_progress gp ON u.id = gp.user_id 
            ORDER BY u.last_login DESC"
        );
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 解析JSON数据
        foreach ($results as &$result) {
            if ($result['level_data']) {
                $result['level_data'] = json_decode($result['level_data'], true);
            }
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("获取所有用户进度失败: " . $e->getMessage());
        return [];
    }
}
?>