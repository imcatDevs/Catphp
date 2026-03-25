<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatPHP Demo</title>
    <link rel="icon" href="/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/imcatui/imcat-ui.css">
    <style>
        :root { --sidebar-w: 240px; --header-h: 48px; }
        .app-header { position:fixed; top:0; left:0; right:0; height:var(--header-h); background:var(--bg-secondary,#f8fafc); border-bottom:1px solid var(--border-color,#e2e8f0); display:flex; align-items:center; padding:0 1rem; z-index:150; gap:1.5rem; }
        .app-header__brand { display:flex; align-items:center; gap:.4rem; text-decoration:none; cursor:pointer; }
        .app-header__brand img { height:24px; }
        .app-header__nav { display:flex; gap:0; height:100%; }
        .app-header__link { display:flex; align-items:center; gap:.35rem; padding:0 .85rem; font-size:.82rem; font-weight:500; color:var(--text-secondary,#64748b); text-decoration:none; border-bottom:2px solid transparent; cursor:pointer; transition:all .15s; }
        .app-header__link:hover { color:var(--text-primary,#1e293b); text-decoration:none; }
        .app-header__link.is-active { color:var(--primary,#3b82f6); border-bottom-color:var(--primary,#3b82f6); }
        .app-header__link i { font-size:16px; }
        .app-header__right { margin-left:auto; }
        .app-layout { display:flex; min-height:100vh; padding-top:var(--header-h); }
        .app-sidebar { width:var(--sidebar-w); position:fixed; top:var(--header-h); left:0; bottom:0; overflow-y:auto; background:var(--bg-secondary,#f8fafc); border-right:1px solid var(--border-color,#e2e8f0); padding:.75rem 0; z-index:100; transition:transform .3s; }
        .app-sidebar.hidden { transform:translateX(-100%); }
        .app-main { flex:1; min-width:0; transition:margin-left .3s; }
        .app-main.with-sidebar { margin-left:var(--sidebar-w); }
        .sidebar-group { padding:.25rem 0; }
        .sidebar-group-title { padding:.4rem 1rem; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted,#94a3b8); }
        .sidebar-link { display:flex; align-items:center; gap:.5rem; padding:.4rem 1rem; font-size:.82rem; color:var(--text-secondary,#64748b); text-decoration:none; transition:all .15s; border-left:3px solid transparent; cursor:pointer; }
        .sidebar-link:hover { background:var(--bg-tertiary,#f1f5f9); color:var(--text-primary,#1e293b); text-decoration:none; }
        .sidebar-link.is-active { color:var(--primary,#3b82f6); border-left-color:var(--primary,#3b82f6); background:rgba(59,130,246,.06); font-weight:600; }
        .sidebar-link i { font-size:16px; opacity:.7; }
        .sidebar-toggle { display:none; position:fixed; top:calc(var(--header-h) + .5rem); left:.75rem; z-index:200; }
        .demo-code { background:#0f172a; color:#e2e8f0; padding:1rem 1.25rem; margin:0; border-radius:0 0 8px 8px; overflow-x:auto; font-size:.8rem; line-height:1.65; white-space:pre; }
        .demo-section { padding:2rem; }
        .hl-s { color:#fde68a; } .hl-k { color:#c084fc; } .hl-f { color:#7dd3fc; } .hl-c { color:#64748b; } .hl-v { color:#86efac; } .hl-n { color:#fbbf24; }
        @media (max-width:768px) {
            .app-sidebar { transform:translateX(-100%); }
            .app-sidebar.open { transform:translateX(0); }
            .app-main.with-sidebar { margin-left:0; }
            .sidebar-toggle { display:flex; }
            .demo-section { padding:1rem; }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <a class="app-header__brand" data-spa="/home">
            <img src="/logo.svg" alt="Cat" style="height:26px;width:auto;">
            <span style="font-size:.7rem;color:var(--text-muted,#94a3b8);margin:0 .1rem;">+</span>
            <img src="/new-php-logo.svg" alt="PHP" style="height:22px;width:auto;">
        </a>
        <nav class="app-header__nav">
            <a class="app-header__link is-active" id="navIntro" href="#">
                <i class="material-icons-outlined">menu_book</i>도구 소개
            </a>
            <a class="app-header__link" id="navDemo" data-nav="demo">
                <i class="material-icons-outlined">science</i>데모
            </a>
        </nav>
        <div class="app-header__right">
            <button class="btn btn--ghost btn--icon btn--sm" id="themeBtn">
                <i class="material-icons-outlined" style="font-size:18px;">dark_mode</i>
            </button>
        </div>
    </header>

    <button class="btn btn--outline btn--icon btn--sm sidebar-toggle" id="sidebarToggle">
        <i class="material-icons-outlined">menu</i>
    </button>

    <div class="app-layout">
        <aside class="app-sidebar hidden" id="sidebar">
            <!-- 도구 소개 사이드바 -->
            <nav id="toolNav" style="display:none;">
                <div class="sidebar-group">
                    <div class="sidebar-group-title">기본</div>
                    <a data-spa="/tool/db" class="sidebar-link"><i class="material-icons-outlined">storage</i>DB</a>
                    <a data-spa="/tool/router" class="sidebar-link"><i class="material-icons-outlined">alt_route</i>Router</a>
                    <a data-spa="/tool/cache" class="sidebar-link"><i class="material-icons-outlined">cached</i>Cache</a>
                    <a data-spa="/tool/log" class="sidebar-link"><i class="material-icons-outlined">description</i>Log</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">보안</div>
                    <a data-spa="/tool/auth" class="sidebar-link"><i class="material-icons-outlined">lock</i>Auth</a>
                    <a data-spa="/tool/csrf" class="sidebar-link"><i class="material-icons-outlined">verified_user</i>Csrf</a>
                    <a data-spa="/tool/encrypt" class="sidebar-link"><i class="material-icons-outlined">enhanced_encryption</i>Encrypt</a>
                    <a data-spa="/tool/firewall" class="sidebar-link"><i class="material-icons-outlined">local_fire_department</i>Firewall</a>
                    <a data-spa="/tool/ip" class="sidebar-link"><i class="material-icons-outlined">dns</i>Ip</a>
                    <a data-spa="/tool/guard" class="sidebar-link"><i class="material-icons-outlined">shield</i>Guard</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">네트워크</div>
                    <a data-spa="/tool/http" class="sidebar-link"><i class="material-icons-outlined">http</i>Http</a>
                    <a data-spa="/tool/rate" class="sidebar-link"><i class="material-icons-outlined">speed</i>Rate</a>
                    <a data-spa="/tool/cors" class="sidebar-link"><i class="material-icons-outlined">public</i>Cors</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">API</div>
                    <a data-spa="/tool/json" class="sidebar-link"><i class="material-icons-outlined">data_object</i>Json</a>
                    <a data-spa="/tool/api" class="sidebar-link"><i class="material-icons-outlined">api</i>Api</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">데이터</div>
                    <a data-spa="/tool/valid" class="sidebar-link"><i class="material-icons-outlined">check_circle</i>Valid</a>
                    <a data-spa="/tool/upload" class="sidebar-link"><i class="material-icons-outlined">cloud_upload</i>Upload</a>
                    <a data-spa="/tool/paginate" class="sidebar-link"><i class="material-icons-outlined">last_page</i>Paginate</a>
                    <a data-spa="/tool/cookie" class="sidebar-link"><i class="material-icons-outlined">cookie</i>Cookie</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">유틸</div>
                    <a data-spa="/tool/event" class="sidebar-link"><i class="material-icons-outlined">bolt</i>Event</a>
                    <a data-spa="/tool/slug" class="sidebar-link"><i class="material-icons-outlined">link</i>Slug</a>
                    <a data-spa="/tool/cli" class="sidebar-link"><i class="material-icons-outlined">terminal</i>Cli</a>
                    <a data-spa="/tool/spider" class="sidebar-link"><i class="material-icons-outlined">bug_report</i>Spider</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">웹 / CMS</div>
                    <a data-spa="/tool/telegram" class="sidebar-link"><i class="material-icons-outlined">send</i>Telegram</a>
                    <a data-spa="/tool/image" class="sidebar-link"><i class="material-icons-outlined">image</i>Image</a>
                    <a data-spa="/tool/flash" class="sidebar-link"><i class="material-icons-outlined">flash_on</i>Flash</a>
                    <a data-spa="/tool/perm" class="sidebar-link"><i class="material-icons-outlined">admin_panel_settings</i>Perm</a>
                    <a data-spa="/tool/search" class="sidebar-link"><i class="material-icons-outlined">search</i>Search</a>
                    <a data-spa="/tool/meta" class="sidebar-link"><i class="material-icons-outlined">sell</i>Meta</a>
                    <a data-spa="/tool/geo" class="sidebar-link"><i class="material-icons-outlined">language</i>Geo</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">블로그</div>
                    <a data-spa="/tool/tag" class="sidebar-link"><i class="material-icons-outlined">label</i>Tag</a>
                    <a data-spa="/tool/feed" class="sidebar-link"><i class="material-icons-outlined">rss_feed</i>Feed</a>
                    <a data-spa="/tool/text" class="sidebar-link"><i class="material-icons-outlined">text_fields</i>Text</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">인프라</div>
                    <a data-spa="/tool/redis" class="sidebar-link"><i class="material-icons-outlined">memory</i>Redis</a>
                    <a data-spa="/tool/mail" class="sidebar-link"><i class="material-icons-outlined">email</i>Mail</a>
                    <a data-spa="/tool/queue" class="sidebar-link"><i class="material-icons-outlined">queue</i>Queue</a>
                    <a data-spa="/tool/storage" class="sidebar-link"><i class="material-icons-outlined">folder</i>Storage</a>
                    <a data-spa="/tool/schedule" class="sidebar-link"><i class="material-icons-outlined">schedule</i>Schedule</a>
                    <a data-spa="/tool/notify" class="sidebar-link"><i class="material-icons-outlined">notifications</i>Notify</a>
                    <a data-spa="/tool/hash" class="sidebar-link"><i class="material-icons-outlined">fingerprint</i>Hash</a>
                    <a data-spa="/tool/excel" class="sidebar-link"><i class="material-icons-outlined">table_chart</i>Excel</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">HTTP</div>
                    <a data-spa="/tool/env" class="sidebar-link"><i class="material-icons-outlined">settings</i>Env</a>
                    <a data-spa="/tool/request" class="sidebar-link"><i class="material-icons-outlined">input</i>Request</a>
                    <a data-spa="/tool/response" class="sidebar-link"><i class="material-icons-outlined">output</i>Response</a>
                    <a data-spa="/tool/session" class="sidebar-link"><i class="material-icons-outlined">badge</i>Session</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">데이터 / 테스트</div>
                    <a data-spa="/tool/collection" class="sidebar-link"><i class="material-icons-outlined">view_list</i>Collection</a>
                    <a data-spa="/tool/migration" class="sidebar-link"><i class="material-icons-outlined">storage</i>Migration</a>
                    <a data-spa="/tool/debug" class="sidebar-link"><i class="material-icons-outlined">bug_report</i>Debug</a>
                    <a data-spa="/tool/captcha" class="sidebar-link"><i class="material-icons-outlined">verified_user</i>Captcha</a>
                    <a data-spa="/tool/faker" class="sidebar-link"><i class="material-icons-outlined">science</i>Faker</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">관리 / 연동</div>
                    <a data-spa="/tool/sitemap" class="sidebar-link"><i class="material-icons-outlined">map</i>Sitemap</a>
                    <a data-spa="/tool/backup" class="sidebar-link"><i class="material-icons-outlined">backup</i>Backup</a>
                    <a data-spa="/tool/dbview" class="sidebar-link"><i class="material-icons-outlined">table_view</i>DbView</a>
                    <a data-spa="/tool/webhook" class="sidebar-link"><i class="material-icons-outlined">webhook</i>Webhook</a>
                    <a data-spa="/tool/swoole" class="sidebar-link"><i class="material-icons-outlined">bolt</i>Swoole</a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">유저</div>
                    <a data-spa="/tool/user" class="sidebar-link"><i class="material-icons-outlined">person</i>User</a>
                </div>
            </nav>
            <!-- 데모 사이드바 -->
            <nav id="demoNav" style="display:none;">
                <div class="sidebar-group">
                    <div class="sidebar-group-title">기본</div>
                    <a data-spa="/demo/basic" class="sidebar-link">
                        <i class="material-icons-outlined">storage</i>DB · Router · Cache · Log
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">보안</div>
                    <a data-spa="/demo/security" class="sidebar-link">
                        <i class="material-icons-outlined">shield</i>Auth · Csrf · Encrypt · Guard · Firewall · Ip · Perm
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">네트워크 / API</div>
                    <a data-spa="/demo/network" class="sidebar-link">
                        <i class="material-icons-outlined">http</i>Http · Json · Rate · Cors · Api
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">데이터</div>
                    <a data-spa="/demo/data" class="sidebar-link">
                        <i class="material-icons-outlined">check_circle</i>Valid · Upload · Paginate · Cookie · Collection
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">유틸</div>
                    <a data-spa="/demo/util" class="sidebar-link">
                        <i class="material-icons-outlined">build</i>Event · Slug · Cli · Spider · Debug
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">웹 / CMS</div>
                    <a data-spa="/demo/web" class="sidebar-link">
                        <i class="material-icons-outlined">language</i>Telegram · Image · Flash · Search · Meta · Geo · Tag · Feed · Text
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">인프라</div>
                    <a data-spa="/demo/infra" class="sidebar-link">
                        <i class="material-icons-outlined">dns</i>Redis · Mail · Queue · Storage · Schedule · Notify · Hash · Excel
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">모던</div>
                    <a data-spa="/demo/modern" class="sidebar-link">
                        <i class="material-icons-outlined">auto_awesome</i>Env · Request · Response · Session · Migration · Captcha · Faker · User
                    </a>
                </div>
                <div class="sidebar-group">
                    <div class="sidebar-group-title">관리 / 연동</div>
                    <a data-spa="/demo/admin" class="sidebar-link">
                        <i class="material-icons-outlined">admin_panel_settings</i>Sitemap · Backup · DbView · Webhook · Swoole
                    </a>
                </div>
            </nav>
        </aside>

        <main class="app-main" id="spaContent">
