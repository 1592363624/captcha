/**
 * 客户端操作跟踪器
 * 用于捕获用户在页面上的各种操作，并发送到服务器进行记录
 */

var OperationTracker = {
    // 页面加载时间
    pageLoadTime: null,
    // 跟踪器配置
    config: {
        // 服务器端跟踪脚本URL
        trackerUrl: 'tracker.php',
        // 是否启用调试模式
        debug: false
    },
    
    /**
     * 初始化跟踪器
     */
    init: function() {
        this.pageLoadTime = new Date();
        
        // 记录页面访问
        this.trackView();
        
        // 绑定事件监听器
        this.bindEventListeners();
        
        // 页面离开时记录停留时间
        this.bindUnloadListener();
        
        if (this.config.debug) {
            console.log('操作跟踪器已初始化');
        }
    },
    
    /**
     * 记录页面访问
     */
    trackView: function() {
        this.sendData({
            operation_type: 'view',
            page_url: window.location.href
        });
    },
    
    /**
     * 绑定事件监听器
     */
    bindEventListeners: function() {
        // 为所有可点击元素绑定点击事件
        document.addEventListener('click', this.handleClick.bind(this), true);
        
        // 为所有输入元素绑定输入事件
        document.addEventListener('input', this.handleInput.bind(this), true);
    },
    
    /**
     * 绑定页面卸载监听器
     */
    bindUnloadListener: function() {
        window.addEventListener('beforeunload', this.handleUnload.bind(this));
    },
    
    /**
     * 处理点击事件
     * @param {Event} event 点击事件对象
     */
    handleClick: function(event) {
        var target = event.target;
        var selector = this.getElementSelector(target);
        var text = target.textContent.trim() || '';
        var value = target.value || '';
        
        this.sendData({
            operation_type: 'click',
            page_url: window.location.href,
            element_selector: selector,
            element_text: text,
            element_value: value
        });
    },
    
    /**
     * 处理输入事件
     * @param {Event} event 输入事件对象
     */
    handleInput: function(event) {
        var target = event.target;
        var selector = this.getElementSelector(target);
        var value = target.value || '';
        
        this.sendData({
            operation_type: 'input',
            page_url: window.location.href,
            element_selector: selector,
            element_value: value
        });
    },
    
    /**
     * 处理页面卸载事件
     */
    handleUnload: function() {
        var duration = Math.floor((new Date() - this.pageLoadTime) / 1000);
        
        this.sendData({
            operation_type: 'stay',
            page_url: window.location.href,
            duration: duration
        });
    },
    
    /**
     * 获取元素的选择器
     * @param {Element} element DOM元素
     * @return {string} 元素选择器
     */
    getElementSelector: function(element) {
        if (!element || element.tagName === 'HTML') {
            return 'HTML';
        }
        
        var selector = element.tagName.toLowerCase();
        
        // 如果有id，使用id选择器
        if (element.id) {
            selector += '#' + element.id;
            return selector;
        }
        
        // 如果有class，使用class选择器
        if (element.className) {
            var classes = element.className.trim().split(/\s+/);
            selector += '.' + classes.join('.');
        }
        
        // 如果是body元素，直接返回
        if (selector === 'body') {
            return selector;
        }
        
        // 否则，添加父元素选择器
        return this.getElementSelector(element.parentElement) + ' > ' + selector;
    },
    
    /**
     * 发送数据到服务器
     * @param {Object} data 要发送的数据
     */
    sendData: function(data) {
        // 创建XMLHttpRequest对象
        var xhr = new XMLHttpRequest();
        
        // 设置请求方法和URL
        xhr.open('POST', this.config.trackerUrl, true);
        
        // 设置请求头
        xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
        
        // 序列化数据为JSON字符串
        var jsonData = JSON.stringify(data);
        
        // 发送请求
        xhr.send(jsonData);
        
        if (this.config.debug) {
            console.log('发送操作数据:', data);
        }
    }
};

// 页面加载完成后初始化跟踪器
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        OperationTracker.init();
    });
} else {
    OperationTracker.init();
}