# CatPHP 배포 체크리스트

## 필수 설정 검증

### config/app.php

```php
return [
    // 필수 설정
    'url' => 'https://example.com',           // Host 헤더 검증용
    
    // 보안 설정
    'auth' => [
        'secret' => env('AUTH_SECRET'),       // JWT 서명 키
    ],
    'encrypt' => [
        'key' => env('ENCRYPT_KEY'),          // 암호화 키
    ],
    'ip' => [
        'trusted_proxies' => ['10.0.0.1'],    // 신뢰 프록시 IP
    ],
    
    // 권한 설정
    'perm' => [
        'roles' => ['admin', 'editor', 'user'],
        'super_role' => 'admin',
    ],
];
```

### .env 파일

```env
# 환경
APP_ENV=production
APP_DEBUG=false

# 보안 키
AUTH_SECRET=your-256-bit-secret-key
ENCRYPT_KEY=base64:your-base64-encoded-key

# 데이터베이스
DB_HOST=localhost
DB_NAME=production_db
DB_USER=db_user
DB_PASS=secure_password

# 캐시/세션
CACHE_PATH=/var/www/storage/cache
SESSION_PATH=/var/www/storage/sessions
```

---

## 디렉토리 권한

```bash
# storage 디렉토리 쓰기 권한
chmod -R 775 storage/
chown -R www-data:www-data storage/

# 하위 디렉토리 자동 생성 확인
ls -la storage/
# cache/  sessions/  logs/  firewall/  uploads/
```

---

## PHP 확장 검증

```bash
# 필수 확장
php -m | grep -E 'pdo|curl|json|mbstring|sodium|openssl'

# 권장 확장
php -m | grep -E 'opcache|zip|gd|redis'
```

| 확장 | 용도 | 필수 |
| --- | --- | --- |
| pdo | DB 연결 | ✅ |
| curl | HTTP 클라이언트 | ✅ |
| json | JSON 처리 | ✅ |
| mbstring | 멀티바이트 문자열 | ✅ |
| sodium | 암호화 | ✅ |
| openssl | HTTPS | ✅ |
| opcache | 성능 최적화 | 권장 |
| zip | Excel XLSX | 권장 |
| gd | 이미지 처리 | 권장 |

---

## 웹 서버 설정

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    root /var/www/public;
    index index.php;

    # SSL
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    # 보안 헤더
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Content-Security-Policy "default-src 'self'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # 업로드 크기 제한
    client_max_body_size 10M;

    # PHP 처리
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 정적 파일 캐시
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # storage 디렉토리 접근 차단
    location ~ ^/storage {
        deny all;
    }

    # .env, .htaccess 등 숨김 파일 차단
    location ~ /\. {
        deny all;
    }
}
```

### Apache

```apache
<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /var/www/public

    # SSL
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/example.com/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/example.com/privkey.pem

    # 보안 헤더
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Strict-Transport-Security "max-age=31536000"

    # 업로드 크기
    LimitRequestBody 10485760

    <Directory /var/www/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # storage 디렉토리 접근 차단
    <Directory /var/www/storage>
        Require all denied
    </Directory>
</VirtualHost>
```

---

## OPcache 설정 (php.ini)

```ini
; 운영 환경
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0

; JIT (PHP 8.0+)
opcache.jit=1255
opcache.jit_buffer_size=100M
```

> **주의**: `validate_timestamps=0` 설정 시 코드 변경 후 OPcache 재시작 필요

```bash
# OPcache 초기화
sudo systemctl reload php8.2-fpm
```

---

## 배포 전 체크리스트

### 보안

- [ ] `app.url` 설정 완료
- [ ] `ip.trusted_proxies` 설정 완료
- [ ] `encrypt.key` 설정 완료
- [ ] `auth.secret` 설정 완료
- [ ] `.env` 파일 git에서 제외 확인
- [ ] `APP_DEBUG=false` 설정
- [ ] SSL/HTTPS 적용
- [ ] 보안 헤더 설정

### 권한

- [ ] `storage/` 디렉토리 쓰기 권한
- [ ] `storage/` 웹 접근 차단
- [ ] `.env` 파일 읽기 권한 제한

### 환경

- [ ] PHP 8.1+ 설치
- [ ] 필수 확장 설치
- [ ] OPcache 활성화
- [ ] 데이터베이스 연결 확인

### 모니터링

- [ ] 에러 로그 경로 확인
- [ ] 로그 순환 설정
- [ ] 백업 스케줄 설정

---

## 배포 후 검증

```bash
# 1. 기본 동작 확인
curl -I https://example.com

# 2. API 헬스체크
curl https://example.com/api/health

# 3. 보안 헤더 확인
curl -I https://example.com | grep -E 'X-Frame|X-Content|Strict'

# 4. SSL 인증서 확인
openssl s_client -connect example.com:443 -servername example.com

# 5. PHP 확장 확인
php -r "echo 'Sodium: ' . (extension_loaded('sodium') ? 'OK' : 'MISSING') . PHP_EOL;"

# 6. storage 쓰기 권한 확인
php -r "echo is_writable('storage/') ? 'OK' : 'FAIL';"
```

---

## 롤백 절차

```bash
# 1. 이전 버전으로 복원
git checkout v1.0.0

# 2. OPcache 초기화
sudo systemctl reload php8.2-fpm

# 3. 마이그레이션 롤백 (필요 시)
php cli.php migrate:rollback

# 4. 캐시 삭제
rm -rf storage/cache/*
```

---

## 성능 최적화

### 데이터베이스

```sql
-- 인덱스 확인
SHOW INDEX FROM users;

-- 슬로우 쿼리 로그 활성화
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
```

### 캐시

```php
// 자주 사용하는 쿼리 캐시
$users = cache()->remember('active_users', 300, fn() => 
    db()->table('users')->where('active', 1)->all()
);
```

### 큐

```bash
# 백그라운드 워커 실행
php cli.php queue:work
```

---

## 모니터링 설정

### 로그 파일

- `storage/logs/error.log` — 에러 로그
- `storage/logs/access.log` — 접근 로그 (선택)
- `/var/log/nginx/error.log` — Nginx 에러
- `/var/log/php8.2-fpm.log` — PHP-FPM 로그

### 알림 설정

```php
// 공격 감지 시 알림
guard()->onAttack(function (string $type, string $ip) {
    notify()
        ->channel('telegram')
        ->to(config('notify.telegram.chat_id'))
        ->message("🚨 공격 감지: {$type} from {$ip}")
        ->send();
});
```

---

## 추가 참고

- [SECURITY.md](SECURITY.md) — 보안 가이드
- [review-report.md](review-report.md) — 보안 분석 보고서
