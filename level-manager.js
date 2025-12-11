/*
 * 关卡管理框架 - 核心管理器
 * 负责关卡的注册、加载、切换、进度管理和通信
 */

class LevelManager {
    constructor() {
        // 关卡配置映射
        this.levels = new Map();
        // 当前激活的关卡
        this.currentLevel = null;
        // 关卡历史记录，用于支持返回功能
        this.levelHistory = [];
        // 关卡间通信事件总线
        this.eventBus = new EventTarget();
        // 用户进度数据
        this.progress = {
            completedLevels: [],
            currentLevelId: null,
            levelData: {}
        };
        
        // 初始化时加载用户进度
        this.loadProgress();
    }
    
    /**
     * 注册一个新关卡
     * @param {string} id - 关卡唯一标识符
     * @param {Object} config - 关卡配置
     * @param {string} config.name - 关卡名称
     * @param {string} config.url - 关卡页面URL
     * @param {Function} config.init - 关卡初始化函数
     * @param {Function} config.complete - 关卡完成回调
     * @param {Array} config.requirements - 解锁该关卡所需的前置关卡
     */
    registerLevel(id, config) {
        this.levels.set(id, {
            id,
            name: config.name,
            url: config.url,
            init: config.init || (() => {}),
            complete: config.complete || (() => {}),
            requirements: config.requirements || [],
            ...config
        });
    }
    
    /**
     * 加载指定关卡
     * @param {string} levelId - 要加载的关卡ID
     * @param {Object} data - 传递给关卡的数据
     * @param {boolean} addToHistory - 是否添加到历史记录
     */
    loadLevel(levelId, data = {}, addToHistory = true) {
        const level = this.levels.get(levelId);
        
        if (!level) {
            console.error(`关卡 ${levelId} 未注册`);
            return false;
        }
        
        // 检查关卡解锁条件
        if (!this.isLevelUnlocked(levelId)) {
            console.error(`关卡 ${levelId} 尚未解锁`);
            return false;
        }
        
        // 添加到历史记录
        if (addToHistory && this.currentLevel) {
            this.levelHistory.push(this.currentLevel.id);
        }
        
        // 保存当前关卡状态
        if (this.currentLevel) {
            this.saveLevelState(this.currentLevel.id);
        }
        
        // 更新当前关卡
        this.currentLevel = level;
        this.progress.currentLevelId = levelId;
        
        // 触发关卡加载事件
        this.eventBus.dispatchEvent(new CustomEvent('level.load', {
            detail: { levelId, data }
        }));
        
        // 执行关卡初始化
        level.init(data);
        
        // 保存进度
        this.saveProgress();
        
        console.log(`已加载关卡: ${level.name} (${levelId})`);
        return true;
    }
    
    /**
     * 跳转到下一个关卡
     * @param {Object} data - 传递给下一关的数据
     */
    nextLevel(data = {}) {
        if (!this.currentLevel) {
            console.error('当前没有激活的关卡');
            return false;
        }
        
        // 标记当前关卡为完成
        this.completeLevel(this.currentLevel.id, data);
        
        // 这里可以根据游戏逻辑实现下一关的确定
        // 简单实现：返回默认下一关，实际项目中可以根据配置或游戏逻辑确定
        const nextLevelId = this.getNextLevelId(this.currentLevel.id);
        
        if (nextLevelId) {
            return this.loadLevel(nextLevelId, data);
        }
        
        console.error('没有找到下一个关卡');
        return false;
    }
    
    /**
     * 返回上一个关卡
     * @param {Object} data - 传递给上一关的数据
     */
    previousLevel(data = {}) {
        if (this.levelHistory.length === 0) {
            console.error('没有历史关卡记录');
            return false;
        }
        
        // 获取上一个关卡ID
        const previousLevelId = this.levelHistory.pop();
        
        // 不添加到历史记录，避免循环
        return this.loadLevel(previousLevelId, data, false);
    }
    
    /**
     * 完成当前关卡
     * @param {string} levelId - 要完成的关卡ID
     * @param {Object} data - 关卡完成时的额外数据
     */
    completeLevel(levelId, data = {}) {
        const level = this.levels.get(levelId);
        
        if (!level) {
            console.error(`关卡 ${levelId} 未注册`);
            return false;
        }
        
        // 如果关卡未完成，则标记为完成
        if (!this.progress.completedLevels.includes(levelId)) {
            this.progress.completedLevels.push(levelId);
        }
        
        // 保存关卡完成数据
        this.progress.levelData[levelId] = {
            completed: true,
            completedAt: new Date().toISOString(),
            data: data,
            ...this.progress.levelData[levelId]
        };
        
        // 触发关卡完成事件
        this.eventBus.dispatchEvent(new CustomEvent('level.complete', {
            detail: { levelId, data }
        }));
        
        // 执行关卡完成回调
        level.complete(data);
        
        // 保存进度
        this.saveProgress();
        
        console.log(`已完成关卡: ${level.name} (${levelId})`);
        return true;
    }
    
    /**
     * 检查关卡是否已解锁
     * @param {string} levelId - 要检查的关卡ID
     * @returns {boolean} - 关卡是否已解锁
     */
    isLevelUnlocked(levelId) {
        const level = this.levels.get(levelId);
        
        if (!level) {
            console.error(`关卡 ${levelId} 未注册`);
            return false;
        }
        
        // 如果没有前置要求，则默认解锁
        if (!level.requirements || level.requirements.length === 0) {
            return true;
        }
        
        // 检查所有前置关卡是否已完成
        return level.requirements.every(reqLevelId => 
            this.progress.completedLevels.includes(reqLevelId)
        );
    }
    
    /**
     * 获取下一个关卡ID（简单实现，可根据实际需求扩展）
     * @param {string} currentLevelId - 当前关卡ID
     * @returns {string|null} - 下一个关卡ID
     */
    getNextLevelId(currentLevelId) {
        // 简单实现：返回下一个注册的关卡
        // 实际项目中可以根据配置或游戏逻辑实现
        const levelIds = Array.from(this.levels.keys());
        const currentIndex = levelIds.indexOf(currentLevelId);
        
        if (currentIndex < levelIds.length - 1) {
            return levelIds[currentIndex + 1];
        }
        
        return null;
    }
    
    /**
     * 保存关卡状态
     * @param {string} levelId - 关卡ID
     */
    saveLevelState(levelId) {
        // 这里可以扩展为保存更详细的关卡状态
        const state = {
            savedAt: new Date().toISOString()
        };
        
        this.progress.levelData[levelId] = {
            ...this.progress.levelData[levelId],
            state
        };
        
        this.saveProgress();
    }
    
    /**
     * 加载关卡状态
     * @param {string} levelId - 关卡ID
     * @returns {Object|null} - 关卡状态
     */
    loadLevelState(levelId) {
        return this.progress.levelData[levelId]?.state || null;
    }
    
    /**
     * 从本地存储加载用户进度
     */
    loadProgress() {
        try {
            const savedProgress = localStorage.getItem('level_progress');
            if (savedProgress) {
                this.progress = { ...this.progress, ...JSON.parse(savedProgress) };
                console.log('已加载用户进度:', this.progress);
            }
        } catch (error) {
            console.error('加载进度失败:', error);
        }
    }
    
    /**
     * 保存用户进度到本地存储
     */
    saveProgress() {
        try {
            localStorage.setItem('level_progress', JSON.stringify(this.progress));
            
            // 同时保存到服务器（如果有）
            this.saveProgressToServer();
        } catch (error) {
            console.error('保存进度失败:', error);
        }
    }
    
    /**
     * 将进度保存到服务器
     */
    saveProgressToServer() {
        // 检查用户是否已登录
        const userInfoStr = sessionStorage.getItem('user_info');
        if (!userInfoStr) return;
        
        const userInfo = JSON.parse(userInfoStr);
        
        // 发送进度到服务器
        fetch('api.php?action=save_progress', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: userInfo.id,
                game_type: 'levels',
                progress: this.progress
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('进度已保存到服务器');
            }
        })
        .catch(error => {
            console.error('保存进度到服务器失败:', error);
        });
    }
    
    /**
     * 重置所有进度
     */
    resetProgress() {
        this.progress = {
            completedLevels: [],
            currentLevelId: null,
            levelData: {}
        };
        this.currentLevel = null;
        this.levelHistory = [];
        this.saveProgress();
        console.log('已重置所有进度');
    }
    
    /**
     * 发送关卡间通信事件
     * @param {string} eventName - 事件名称
     * @param {Object} data - 事件数据
     */
    sendEvent(eventName, data) {
        this.eventBus.dispatchEvent(new CustomEvent(eventName, {
            detail: data
        }));
    }
    
    /**
     * 监听关卡间通信事件
     * @param {string} eventName - 事件名称
     * @param {Function} callback - 事件回调函数
     */
    on(eventName, callback) {
        this.eventBus.addEventListener(eventName, callback);
    }
    
    /**
     * 移除关卡间通信事件监听
     * @param {string} eventName - 事件名称
     * @param {Function} callback - 事件回调函数
     */
    off(eventName, callback) {
        this.eventBus.removeEventListener(eventName, callback);
    }
    
    /**
     * 获取所有已注册的关卡
     * @returns {Array} - 关卡列表
     */
    getAllLevels() {
        return Array.from(this.levels.values());
    }
    
    /**
     * 获取已完成的关卡
     * @returns {Array} - 已完成的关卡列表
     */
    getCompletedLevels() {
        return this.progress.completedLevels.map(levelId => 
            this.levels.get(levelId)
        ).filter(Boolean);
    }
    
    /**
     * 获取当前关卡信息
     * @returns {Object|null} - 当前关卡信息
     */
    getCurrentLevel() {
        return this.currentLevel;
    }
    
    /**
     * 获取关卡进度
     * @returns {Object} - 关卡进度
     */
    getProgress() {
        return this.progress;
    }
}

// 创建全局关卡管理器实例
const levelManager = new LevelManager();

// 导出关卡管理器（支持模块化和直接使用）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LevelManager;
} else if (typeof window !== 'undefined') {
    window.LevelManager = LevelManager;
    window.levelManager = levelManager;
}