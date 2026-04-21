#!/bin/bash
# CatPHP Swoole 원격 배포 스크립트
set -euo pipefail

SITE_DIR="/www/wwwroot/catphp.imcat.dev"
BACKUP_DIR="/www/wwwroot/catphp.imcat.dev.bak.$(date +%Y%m%d_%H%M%S)"
TAR_FILE="/tmp/catphp-deploy.tar.gz"

echo "==> [1/6] 기존 사이트 백업: $BACKUP_DIR"
if [ -d "$SITE_DIR" ]; then
    mv "$SITE_DIR" "$BACKUP_DIR"
    echo "    백업 완료"
else
    echo "    기존 사이트 없음 — 신규 배포"
fi

echo "==> [2/6] 새 사이트 디렉토리 생성"
mkdir -p "$SITE_DIR"
cd "$SITE_DIR"

echo "==> [3/6] tar.gz 해제"
tar -xzf "$TAR_FILE" -C "$SITE_DIR"
echo "    해제 완료"

echo "==> [4/6] 기존 config/storage 복원"
if [ -d "$BACKUP_DIR/storage" ]; then
    rm -rf "$SITE_DIR/storage"
    cp -r "$BACKUP_DIR/storage" "$SITE_DIR/storage"
    echo "    storage 복원 완료"
else
    mkdir -p "$SITE_DIR/storage/"{cache,logs,firewall,rate,app,backup}
    echo "    신규 storage 디렉토리 생성"
fi

if [ -f "$BACKUP_DIR/config/app.php" ]; then
    cp "$BACKUP_DIR/config/app.php" "$SITE_DIR/config/app.php"
    echo "    기존 config/app.php 복원 완료 (DB/Auth/Encrypt 시크릿 유지)"
fi

echo "==> [5/6] config/app.php Swoole/Session 섹션 패치 (PHP로 안전 편집)"
php -r '
$file = "'"$SITE_DIR"'/config/app.php";
$src = file_get_contents($file);

// session 섹션: driver=redis, cookie 이름, redis_prefix 추가
$newSession = "[\"driver\" => \"redis\", \"lifetime\" => 7200, \"path\" => \"/\", \"secure\" => true, \"httponly\" => true, \"samesite\" => \"Lax\", \"cookie\" => \"CATPHP_SID\", \"redis_prefix\" => \"sess:\"]";
$src = preg_replace("/\"session\"\s*=>\s*\[[^\]]*\]/", "\"session\"   => $newSession", $src);

// swoole 섹션: 127.0.0.1:3005, daemonize=true, pool=32
$newSwoole = "[\"host\" => \"127.0.0.1\", \"port\" => 3005, \"mode\" => \"process\", \"worker_num\" => 0, \"task_worker_num\" => 4, \"max_request\" => 10000, \"max_conn\" => 10000, \"daemonize\" => true, \"dispatch_mode\" => 2, \"open_tcp_nodelay\" => true, \"enable_coroutine\" => true, \"log_file\" => __DIR__ . \"/../storage/logs/swoole.log\", \"log_level\" => 2, \"pid_file\" => __DIR__ . \"/../storage/swoole.pid\", \"static_handler\" => false, \"document_root\" => \"\", \"hot_reload\" => false, \"hot_reload_paths\" => [], \"heartbeat_idle\" => 600, \"heartbeat_check\" => 60, \"ssl_cert\" => \"\", \"ssl_key\" => \"\", \"buffer_output_size\" => 2097152, \"package_max_length\" => 2097152, \"pool\" => [\"db\" => 32, \"redis\" => 32]]";
$src = preg_replace("/\"swoole\"\s*=>\s*\[[^\]]*(?:\[[^\]]*\][^\]]*)*\]/", "\"swoole\"    => $newSwoole", $src);

file_put_contents($file, $src);
echo "    config/app.php 패치 완료\n";

// 검증
$cfg = include $file;
echo "    session.driver = " . ($cfg["session"]["driver"] ?? "?") . "\n";
echo "    swoole.host:port = " . ($cfg["swoole"]["host"] ?? "?") . ":" . ($cfg["swoole"]["port"] ?? "?") . "\n";
echo "    swoole.daemonize = " . ($cfg["swoole"]["daemonize"] ? "true" : "false") . "\n";
echo "    redis.password = " . (!empty($cfg["redis"]["password"]) ? "(set)" : "(empty)") . "\n";
echo "    db.pass = " . (!empty($cfg["db"]["pass"]) ? "(set)" : "(empty)") . "\n";
'

echo "==> [6/6] 권한 설정 (www:www)"
chown -R www:www "$SITE_DIR"
chmod -R 755 "$SITE_DIR"
chmod -R 775 "$SITE_DIR/storage"

echo
echo "=========================================="
echo "배포 완료: $SITE_DIR"
echo "백업 위치: $BACKUP_DIR"
echo "=========================================="
