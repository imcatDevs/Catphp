<?php declare(strict_types=1);
/**
 * CatPHP 정적 사이트 빌더
 * 
 * 사용법: php build-static.php
 * 출력: docs/ 폴더 (GitHub Pages용)
 * 
 * PHP 뷰를 정적 HTML로 변환합니다.
 */

$buildDir = __DIR__ . '/docs';
$publicDir = __DIR__ . '/Public';

// 빌드 디렉토리 초기화
if (is_dir($buildDir)) {
    echo "기존 docs/ 삭제 중...\n";
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($buildDir);
}
mkdir($buildDir, 0755, true);
echo "✓ docs/ 생성\n";

// 정적 자산 복사 함수
function copyDir(string $src, string $dst): void
{
    if (is_file($src)) {
        // 파일인 경우 직접 복사
        $dir = dirname($dst);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        copy($src, $dst);
        return;
    }
    
    // 디렉토리인 경우 재귀 복사
    $dir = opendir($src);
    if ($dir === false) return;
    
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    while (($file = readdir($dir)) !== false) {
        if ($file !== '.' && $file !== '..') {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                copyDir($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
    closedir($dir);
}

// 파일 복사 함수
function copyFile(string $src, string $dst): void
{
    $dir = dirname($dst);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    copy($src, $dst);
}

// PHP 내장 서버로 페이지 렌더링
function renderPage(string $url, string $buildDir, int $port): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method'  => 'GET',
        ]
    ]);
    
    $fullUrl = "http://127.0.0.1:{$port}{$url}";
    $html = @file_get_contents($fullUrl, false, $context);
    
    if ($html === false) {
        echo "  ✗ 렌더링 실패: {$url}\n";
        return null;
    }
    
    return $html;
}

// SPA용 경로를 index.html로 변환 (hash 라우팅 지원)
function convertToStaticPath(string $url): string
{
    // 루트는 index.html
    if ($url === '/' || $url === '/home') {
        return '/index.html';
    }
    // /tool/db → /tool/db.html, /demo/basic → /demo/basic.html
    return $url . '.html';
}

// ── 1. 정적 자산 복사 ────────────────────────────────────────────────
echo "\n정적 자산 복사 중...\n";

// 이미지 (파일)
copyFile($publicDir . '/logo.svg', $buildDir . '/logo.svg');
copyFile($publicDir . '/new-php-logo.svg', $buildDir . '/new-php-logo.svg');
copyFile($publicDir . '/favicon.ico', $buildDir . '/favicon.ico');
copyFile($publicDir . '/favicon.svg', $buildDir . '/favicon.svg');
copyFile($publicDir . '/android-chrome-192x192.png', $buildDir . '/android-chrome-192x192.png');
copyFile($publicDir . '/android-chrome-512x512.png', $buildDir . '/android-chrome-512x512.png');
copyFile($publicDir . '/apple-touch-icon.png', $buildDir . '/apple-touch-icon.png');
copyFile($publicDir . '/favicon-16x16.png', $buildDir . '/favicon-16x16.png');
copyFile($publicDir . '/favicon-32x32.png', $buildDir . '/favicon-32x32.png');

// imcatui (디렉토리)
copyDir($publicDir . '/imcatui', $buildDir . '/imcatui');

echo "✓ 정적 자산 복사 완료\n";

// ── 2. 서버 확인 ────────────────────────────────────────────
echo "\nPHP 개발 서버 확인 중...\n";
$port = 8765;

// 서버 연결 테스트
$testUrl = "http://127.0.0.1:{$port}/";
$context = stream_context_create(['http' => ['timeout' => 2]]);
$testResult = @file_get_contents($testUrl, false, $context);

if ($testResult === false) {
    echo "✗ 서버가 실행 중이 아닙니다.\n";
    echo "\n다음 명령어를 먼저 실행하세요:\n";
    echo "  php cli.php serve --port={$port}\n\n";
    echo "서버가 실행된 후 다시 빌드 스크립트를 실행하세요.\n";
    exit(1);
}

echo "✓ PHP 서버 실행 중: http://127.0.0.1:{$port}\n";

// ── 3. 페이지 렌더링 ──────────────────────────────────────────────────
echo "\n페이지 렌더링 중...\n";

$pages = [
    // 홈
    ['/', 'index.html'],
    ['/home', 'home.html'],
    
    // 데모
    ['/demo/basic', 'demo/basic.html'],
    ['/demo/security', 'demo/security.html'],
    ['/demo/network', 'demo/network.html'],
    ['/demo/data', 'demo/data.html'],
    ['/demo/util', 'demo/util.html'],
    ['/demo/web', 'demo/web.html'],
    ['/demo/infra', 'demo/infra.html'],
    ['/demo/modern', 'demo/modern.html'],
    ['/demo/admin', 'demo/admin.html'],
];

// 도구 페이지 (51개)
$tools = [
    'db', 'router', 'cache', 'log',
    'auth', 'csrf', 'encrypt', 'firewall', 'ip', 'guard',
    'http', 'rate', 'cors',
    'json', 'api',
    'valid', 'upload', 'paginate', 'cookie',
    'event', 'slug', 'cli', 'spider',
    'telegram', 'image', 'flash', 'perm', 'search', 'meta', 'geo',
    'tag', 'feed', 'text',
    'redis', 'mail', 'queue', 'storage', 'schedule', 'notify', 'hash', 'excel',
    'env', 'request', 'response', 'session', 'collection', 'migration', 'debug', 'captcha', 'faker', 'user',
    'sitemap', 'backup', 'dbview', 'webhook', 'swoole',
];

foreach ($tools as $tool) {
    $pages[] = ["/tool/{$tool}", "tools/{$tool}.html"];
}

$success = 0;
$failed = 0;

foreach ($pages as [$url, $outputPath]) {
    $html = renderPage($url, $buildDir, $port);
    
    if ($html !== null) {
        $fullPath = $buildDir . '/' . $outputPath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        // SPA용 경로 수정 (정적 사이트에서도 작동하도록)
        // data-spa="/tool/db" → href="tools/db.html" (또는 hash 기반 유지)
        // 여기서는 hash 기반 SPA를 유지하므로 경로 변경 불필요
        
        file_put_contents($fullPath, $html);
        echo "  ✓ {$url} → {$outputPath}\n";
        $success++;
    } else {
        $failed++;
    }
}

// ── 4. 결과 요약 ──────────────────────────────────────────────────────
echo "\n════════════════════════════════════════\n";
echo "빌드 완료!\n";
echo "✓ 성공: {$success}개\n";
if ($failed > 0) echo "✗ 실패: {$failed}개\n";
echo "출력: {$buildDir}\n";
echo "════════════════════════════════════════\n";

// ── 6. SPA용 index.html 생성 (루트 리다이렉트) ────────────────────────
$indexHtml = file_get_contents($buildDir . '/index.html');
file_put_contents($buildDir . '/index.html', $indexHtml);

echo "\nGitHub Pages 배포 준비 완료!\n";
echo "1. git add docs/\n";
echo "2. git commit -m 'build: 정적 사이트 빌드'\n";
echo "3. git push origin main\n";
