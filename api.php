<?php
/**
 * API接口文件
 * 用于处理用户登录、注册和游戏进度管理等请求
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 引入数据库工具类
require_once 'db.php';

// 初始化数据库连接
$pdo = connect_db();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败，请检查MySQL服务是否运行以及数据库配置是否正确']);
    exit;
}

// 初始化数据库表
init_database($pdo);

/**
 * 处理API请求
 */
function handle_api_request() {
    global $pdo;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'login':
            handle_login($pdo);
            break;
        case 'register':
            handle_register($pdo);
            break;
        case 'save_progress':
            handle_save_progress($pdo);
            break;
        case 'get_progress':
            handle_get_progress($pdo);
            break;
        case 'get_user_info':
            handle_get_user_info($pdo);
            break;
        case 'get_all_users':
            handle_get_all_users($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'error' => '未知操作']);
            break;
    }
}

/**
 * 处理登录请求
 * @param PDO $pdo 数据库连接对象
 */
function handle_login($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $telephone = $data['telephone'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($telephone) || empty($password)) {
        echo json_encode(['success' => false, 'error' => '电话和密码不能为空']);
        return;
    }
    
    // 验证用户
    $result = verify_user($pdo, $telephone, $password);
    
    if ($result['valid']) {
        // 登录成功，返回用户信息
        echo json_encode([
            'success' => true,
            'user' => $result['user']
        ]);
    } else {
        // 登录失败，尝试自动注册
        $create_result = create_or_update_user($pdo, $telephone, $password);
        
        if (isset($create_result['error'])) {
            echo json_encode(['success' => false, 'error' => $create_result['error']]);
            return;
        }
        
        // 自动注册成功，获取用户信息
        $user = get_user_info($pdo, $create_result['user_id']);
        echo json_encode([
            'success' => true,
            'user' => $user,
            'message' => '自动注册成功'
        ]);
    }
}

/**
 * 处理注册请求
 * @param PDO $pdo 数据库连接对象
 */
function handle_register($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $telephone = $data['telephone'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($telephone) || empty($password)) {
        echo json_encode(['success' => false, 'error' => '电话和密码不能为空']);
        return;
    }
    
    // 创建或更新用户
    $result = create_or_update_user($pdo, $telephone, $password);
    
    if (isset($result['error'])) {
        echo json_encode(['success' => false, 'error' => $result['error']]);
        return;
    }
    
    // 获取用户信息
    $user = get_user_info($pdo, $result['user_id']);
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'message' => $result['status'] === 'created' ? '注册成功' : '更新成功'
    ]);
}

/**
 * 处理保存游戏进度请求
 * @param PDO $pdo 数据库连接对象
 */
function handle_save_progress($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? 0;
    $game_type = $data['game_type'] ?? '';
    $level_data = $data['level_data'] ?? [];
    $achievements = $data['achievements'] ?? [];
    $game_state = $data['game_state'] ?? [];
    $total_score = $data['total_score'] ?? 0;
    
    if (empty($user_id) || empty($game_type)) {
        echo json_encode(['success' => false, 'error' => '用户ID和游戏类型不能为空']);
        return;
    }
    
    $result = save_game_progress($pdo, $user_id, $game_type, $level_data, $achievements, $game_state, $total_score);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => '游戏进度保存成功'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '游戏进度保存失败'
        ]);
    }
}

/**
 * 处理获取游戏进度请求
 * @param PDO $pdo 数据库连接对象
 */
function handle_get_progress($pdo) {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_GET;
    $user_id = $data['user_id'] ?? 0;
    $game_type = $data['game_type'] ?? '';
    
    if (empty($user_id) || empty($game_type)) {
        echo json_encode(['success' => false, 'error' => '用户ID和游戏类型不能为空']);
        return;
    }
    
    $progress = get_game_progress($pdo, $user_id, $game_type);
    
    if ($progress) {
        echo json_encode([
            'success' => true,
            'progress' => $progress
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'progress' => null,
            'message' => '暂无游戏进度'
        ]);
    }
}

/**
 * 处理获取用户信息请求
 * @param PDO $pdo 数据库连接对象
 */
function handle_get_user_info($pdo) {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_GET;
    $user_id = $data['user_id'] ?? 0;
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'error' => '用户ID不能为空']);
        return;
    }
    
    $user = get_user_info($pdo, $user_id);
    
    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '用户不存在'
        ]);
    }
}

/**
 * 处理获取所有用户信息请求
 * @param PDO $pdo 数据库连接对象
 */
function handle_get_all_users($pdo) {
    $users = get_all_users_progress($pdo);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// 处理API请求
handle_api_request();
?>