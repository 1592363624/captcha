// scroll.js - lightweight UI effects: scroll reveal, shine gradients, and subtle floating FX
(function () {
    'use strict';

    // helpers
    var $ = window.jQuery;
    function qAll(sel) { return ($ ? $(sel).get() : Array.from(document.querySelectorAll(sel))); }

    function isInViewport(el, threshold) {
        var r = el.getBoundingClientRect();
        threshold = threshold || 0.9; // fraction of viewport height
        return r.top <= (window.innerHeight || document.documentElement.clientHeight) * threshold;
    }

    // inject minimal CSS for shine animation and text-clip gradients
    function injectStyles() {
        if (document.getElementById('rjyz-scroll-styles')) return;
        var css = '\n@keyframes rjyz-shine-move{0%{background-position:-200% 0;}100%{background-position:200% 0;}}\n.rjyz-shine{background-size:200% auto;-webkit-background-clip:text;background-clip:text;color:transparent;animation:rjyz-shine-move 3s linear infinite}\n.rjyz-trans-init{opacity:0;transition:opacity .7s ease,transform .7s ease}\n.rjyz-visible{opacity:1;transform:none}\n';
        var s = document.createElement('style');
        s.id = 'rjyz-scroll-styles';
        s.appendChild(document.createTextNode(css));
        document.head.appendChild(s);
    }

    // setup shine elements (elements using --color and --colorshine or --colorlt/--colorrt)
    function setupShine() {
        qAll('.shine, .jianbian').forEach(function (el) {
            // avoid re-init
            if (el._rj_shine) return;
            el._rj_shine = true;

            // read CSS variables and build gradient
            var st = getComputedStyle(el);
            var c1 = st.getPropertyValue('--color') || st.getPropertyValue('--colorlt') || '#44a8ff';
            var c2 = st.getPropertyValue('--colorshine') || st.getPropertyValue('--colorrt') || '#ee5555';
            c1 = c1.trim() || '#44a8ff';
            c2 = c2.trim() || '#ee5555';

            // default animation duration can be tuned via --speed (smaller = faster)
            var sp = parseFloat(st.getPropertyValue('--speed')) || 2.8;
            // map to seconds (safe bounds)
            var dur = Math.max(0.6, Math.min(6, Math.abs(sp) * 1.1));

            el.classList.add('rjyz-shine');
            el.style.backgroundImage = 'linear-gradient(90deg,' + c1 + ' 0%,' + c2 + ' 30%,' + c1 + ' 70%)';
            el.style.animationDuration = dur + 's';

            // for jianbian we also want the text heavy and without transparent gap
            if (el.classList.contains('jianbian')) {
                el.style.fontWeight = el.style.fontWeight || '700';
                el.style.backgroundClip = 'text';
                el.style.webkitBackgroundClip = 'text';
                el.style.color = 'transparent';
            }
        });
    }

    // setup trans (scroll reveal + float from left/right)
    function setupTrans() {
        qAll('.trans').forEach(function (el) {
            if (el._rj_trans) return;
            el._rj_trans = true;

            // initial transform depending on classes
            var initX = 0, initY = 0;
            if (el.classList.contains('float')) {
                var amt = 40; // px
                if (el.classList.contains('rt')) initX = amt; else initX = -amt;
            } else {
                initY = 20;
            }
            el.style.transform = 'translateX(' + initX + 'px) translateY(' + initY + 'px)';
            el.classList.add('rjyz-trans-init');
            // allow delays via CSS variable --delay (seconds)
            var st = getComputedStyle(el);
            var delay = st.getPropertyValue('--delay');
            if (delay) el.style.transitionDelay = delay.trim();
        });
    }

    // reveal on scroll
    function revealOnScroll() {
        qAll('.trans').forEach(function (el) {
            if (isInViewport(el, 0.9)) {
                el.classList.add('rjyz-visible');
                el.style.transform = 'translateX(0) translateY(0)';
            }
        });

        // also reveal non-trans boxes (optional)
        qAll('.box').forEach(function (el) {
            // skip if already visible or inside has trans
            if (el._rj_boxvis) return;
            if (isInViewport(el, 0.95)) {
                el._rj_boxvis = true;
                // small fade-in
                el.style.transition = 'opacity .6s ease, transform .6s ease';
                el.style.opacity = 1;
                el.style.transform = 'none';
            }
        });
    }

    // floating FX loop for elements with .fx
    var fxItems = [];
    function setupFX() {
        fxItems = qAll('.fx').map(function (el) {
            // each element may have --speed controlling frequency
            var st = getComputedStyle(el);
            var sp = parseFloat(st.getPropertyValue('--speed')) || 1;
            var amp = 6; // px amplitude
            var base = 0;
            // preserve current transform if needed (not used directly here)
            return { el: el, speed: sp, amp: amp, base: base, start: performance.now() };
        });
        if (fxItems.length) requestAnimationFrame(stepFX);
    }
    function stepFX(ts) {
        fxItems.forEach(function (it) {
            var t = (ts - it.start) / 1000;
            var y = Math.sin(t * it.speed) * it.amp;
            it.el.style.transform = 'translateY(' + y + 'px)';
        });
        requestAnimationFrame(stepFX);
    }

    // small utility: ensure range inputs which expect to reflect value into a sibling element work even if their inline handlers are brittle
    function hookRanges() {
        qAll('input[type=range]').forEach(function (r) {
            // if element has data-target attribute, use it. Otherwise look for nearest #telephone
            if (r._rj_range) return; r._rj_range = true;
            r.addEventListener('input', function () {
                try {
                    var id = r.getAttribute('data-target');
                    if (id) {
                        var tgt = document.getElementById(id) || document.querySelector('#' + id);
                        if (tgt) tgt.textContent = r.value;
                    } else {
                        // fallback: update nearest element with id 'telephone'
                        var tel = document.getElementById('telephone');
                        if (tel && tel.tagName.toLowerCase() !== 'input') tel.textContent = r.value;
                    }
                } catch (e) { /* ignore */ }
            });
        });
    }

    function init() {
        injectStyles();
        setupShine();
        setupTrans();
        setupFX();
        hookRanges();
        // initial reveal
        revealOnScroll();
        // events
        window.addEventListener('scroll', revealOnScroll, { passive: true });
        window.addEventListener('resize', function () { setupShine(); revealOnScroll(); });

        // also handle elements added later (simple mutation observer)
        if (window.MutationObserver) {
            var mo = new MutationObserver(function () {
                setupShine(); setupTrans(); setupFX(); hookRanges(); revealOnScroll();
            });
            mo.observe(document.body, { childList: true, subtree: true });
        }
    }

    // try to initialize sooner if document already loaded
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(init, 10);
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
