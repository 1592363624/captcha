/*
 * 关卡配置文件
 * 用于注册所有关卡并定义它们的关系
 */

// 确保在DOM加载完成后执行
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLevelConfig);
} else {
    initLevelConfig();
}

function initLevelConfig() {
    // 确保levelManager已加载
    if (typeof levelManager === 'undefined') {
        console.error('level-manager.js 未加载');
        return;
    }
    
    // 注册所有关卡
    levelManager.registerLevel('verify', {
        name: '人机验证',
        url: 'verify.html',
        init: function(data) {
            console.log('初始化人机验证关卡', data);
        },
        complete: function(data) {
            console.log('完成人机验证关卡', data);
        }
    });
    
    levelManager.registerLevel('reg', {
        name: '注册页面',
        url: 'reg.html',
        init: function(data) {
            console.log('初始化注册关卡', data);
        },
        complete: function(data) {
            console.log('完成注册关卡', data);
        },
        requirements: ['verify']
    });
    
    levelManager.registerLevel('login', {
        name: '登录页面',
        url: 'login.html',
        init: function(data) {
            console.log('初始化登录关卡', data);
        },
        complete: function(data) {
            console.log('完成登录关卡', data);
        }
    });
    
    levelManager.registerLevel('maze', {
        name: '迷宫游戏',
        url: 'maze.html',
        init: function(data) {
            console.log('初始化迷宫游戏关卡', data);
        },
        complete: function(data) {
            console.log('完成迷宫游戏关卡', data);
        },
        requirements: ['login']
    });
    
    levelManager.registerLevel('fireworks', {
        name: '烟花效果',
        url: 'fireworks.html',
        init: function(data) {
            console.log('初始化烟花效果关卡', data);
        },
        complete: function(data) {
            console.log('完成烟花效果关卡', data);
        },
        requirements: ['login']
    });
    
    levelManager.registerLevel('robot', {
        name: '机器人页面',
        url: 'robot.html',
        init: function(data) {
            console.log('初始化机器人关卡', data);
        },
        complete: function(data) {
            console.log('完成机器人关卡', data);
        },
        requirements: ['login']
    });
    
    levelManager.registerLevel('password', {
        name: '密码页面',
        url: 'password.html',
        init: function(data) {
            console.log('初始化密码关卡', data);
        },
        complete: function(data) {
            console.log('完成密码关卡', data);
        },
        requirements: ['login']
    });
    
    // 注册关卡间通信事件监听器
    registerLevelEvents();
    
    console.log('所有关卡已注册完成');
    console.log('已注册关卡:', levelManager.getAllLevels());
}

/**
 * 注册关卡间通信事件监听器
 */
function registerLevelEvents() {
    // 监听关卡加载事件
    levelManager.on('level.load', function(event) {
        console.log('关卡加载事件:', event.detail);
    });
    
    // 监听关卡完成事件
    levelManager.on('level.complete', function(event) {
        console.log('关卡完成事件:', event.detail);
    });
    
    // 监听自定义事件示例
    levelManager.on('custom.event', function(event) {
        console.log('自定义事件:', event.detail);
    });
}

/**
 * 页面跳转辅助函数
 * @param {string} levelId - 要跳转的关卡ID
 * @param {Object} data - 传递给关卡的数据
 */
function navigateToLevel(levelId, data = {}) {
    const level = levelManager.levels.get(levelId);
    if (level) {
        // 保存当前关卡状态
        if (levelManager.currentLevel) {
            levelManager.saveLevelState(levelManager.currentLevel.id);
        }
        
        // 跳转到关卡页面
        window.location.href = level.url;
    }
}

// 导出辅助函数
if (typeof window !== 'undefined') {
    window.navigateToLevel = navigateToLevel;
}