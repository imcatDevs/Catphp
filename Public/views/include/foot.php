<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
        </main>
    </div>

    <script src="/imcatui/imcat-ui.min.js"></script>
    <script>
    (async function() {
        const content = document.getElementById('spaContent');

        // ── hash 기반 SPA 라우팅 ──
        function getHashPath() {
            const h = location.hash.replace(/^#/, '');
            return h || '';
        }

        function setHash(path) {
            const clean = path.replace(/^#/, '');
            if (getHashPath() !== clean) {
                location.hash = clean;
            }
        }

        let currentPath = '';

        async function spaNavigate(path, updateHash = true) {
            if (path === currentPath) return;
            currentPath = path;
            try {
                content.style.opacity = '0.5';
                content.insertAdjacentHTML('afterbegin',
                    '<div class="progress progress--primary progress--indeterminate" id="spaLoader" style="position:sticky;top:0;z-index:10;border-radius:0;"><div class="progress__bar"></div></div>');
                const res = await fetch(path);
                if (!res.ok) throw new Error(res.status + ' ' + res.statusText);
                const html = await res.text();
                content.innerHTML = html;
                content.style.opacity = '1';

                // 프래그먼트 내 <script> 재실행
                content.querySelectorAll('script').forEach(old => {
                    try {
                        const s = document.createElement('script');
                        if (old.src) { s.src = old.src; }
                        else { s.textContent = old.textContent; }
                        old.replaceWith(s);
                    } catch (se) { console.warn('Script error:', se); }
                });

                // 메뉴 활성화
                document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('is-active'));
                const active = document.querySelector(`#toolNav [data-spa="${path}"]`)
                    || document.querySelector(`#demoNav [data-spa="${path}"]`);
                if (active) active.classList.add('is-active');
                document.getElementById('sidebar').classList.remove('open');
                window.scrollTo(0, 0);

                // 헤더 메뉴 상태 동기화
                if (path === '/home') setMode('home', false);
                else if (path.startsWith('/tool/')) setMode('tool', false);
                else setMode('demo', false);

                if (updateHash) setHash(path);

                // CatUI data-imcat 속성 재처리
                if (typeof IMCAT !== 'undefined' && IMCAT.init) IMCAT.init();
            } catch (e) {
                content.style.opacity = '1';
                content.innerHTML = '<div class="demo-section"><div class="alert alert--danger"><span class="alert__message">' +
                    (IMCAT.security?.escapeHTML(e.message) ?? e.message) + '</span></div></div>';
            }
        }

        // data-spa 클릭 이벤트 위임
        document.addEventListener('click', (e) => {
            const link = e.target.closest('[data-spa]');
            if (!link) return;
            e.preventDefault();
            spaNavigate(link.getAttribute('data-spa'));
        });

        // 브라우저 뒤로/앞으로 (hash 변경 감지)
        window.addEventListener('hashchange', () => {
            const path = getHashPath();
            if (path && path !== currentPath) spaNavigate(path, false);
        });

        // 모드 전환: home / tool / demo
        const sidebar = document.getElementById('sidebar');
        const mainEl = document.getElementById('spaContent');
        const navIntro = document.getElementById('navIntro');
        const navDemo = document.getElementById('navDemo');
        const toolNav = document.getElementById('toolNav');
        const demoNav = document.getElementById('demoNav');

        function setMode(mode, navigate = true) {
            navIntro.classList.toggle('is-active', mode === 'tool' || mode === 'home');
            navDemo.classList.toggle('is-active', mode === 'demo');
            if (mode === 'tool') {
                sidebar.classList.remove('hidden');
                mainEl.classList.add('with-sidebar');
                toolNav.style.display = '';
                demoNav.style.display = 'none';
                if (navigate && !document.querySelector('#toolNav .sidebar-link.is-active')) {
                    spaNavigate('/tool/db');
                }
            } else if (mode === 'demo') {
                sidebar.classList.remove('hidden');
                mainEl.classList.add('with-sidebar');
                toolNav.style.display = 'none';
                demoNav.style.display = '';
                if (navigate && !document.querySelector('#demoNav .sidebar-link.is-active')) {
                    spaNavigate('/demo/basic');
                }
            } else {
                sidebar.classList.add('hidden');
                mainEl.classList.remove('with-sidebar');
                toolNav.style.display = 'none';
                demoNav.style.display = 'none';
                if (navigate) spaNavigate('/home');
            }
        }

        navIntro.addEventListener('click', (e) => { e.preventDefault(); setMode('tool'); });
        navDemo.addEventListener('click', () => setMode('demo'));

        // 사이드바 토글 (모바일)
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // CatUI 테마 모듈
        const TM = await IMCAT.use('theme');
        const theme = TM.createTheme({
            defaultTheme: 'system',
            transition: 'fade',
            transitionDuration: 200,
            onChange: (resolved) => {
                IMCAT('#themeBtn i').text(resolved === 'dark' ? 'light_mode' : 'dark_mode');
            }
        });
        IMCAT('#themeBtn i').text(theme.getResolved() === 'dark' ? 'light_mode' : 'dark_mode');
        IMCAT('#themeBtn').on('click', () => theme.toggle());

        // 초기 로드: 해시가 있으면 해당 페이지 자동 로드
        const initPath = getHashPath();
        if (initPath && initPath !== '/home') {
            content.innerHTML = '';
            spaNavigate(initPath, false);
        } else if (initPath === '/home') {
            currentPath = '/home';
        }
    })();
    </script>
</body>
</html>
