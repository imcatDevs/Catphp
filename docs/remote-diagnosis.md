# 원격 서버 진단 결과 — catphp.imcat.dev

- **서버**: `root@59.11.209.216:9091` (SSH)
- **진단 일시**: 2026-04-21
- **BT 패널 사이트**: `/www/wwwroot/catphp.imcat.dev/` (Mar 27 배포 버전 존재)

## 환경 요약

| 항목 | 값 | 평가 |
| --- | --- | --- |
| OS | Rocky Linux 9.7 (Blue Onyx) | ✓ |
| 커널 | 5.14.0-611.16.1.el9_7.x86_64 | ✓ |
| CPU | **24 코어** | ✓ 충분 |
| RAM | 15 GiB (free 4.3, available 12) | ✓ 충분 |
| PHP | **8.2.28** (`/usr/bin/php`, `/www/server/php/82/`) | ✓ 8.1+ 요건 충족 |
| PHP 확장 | `swoole`, `redis`, `pgsql`, `pdo_pgsql`, `sodium`, `opcache` | ✓ 전부 설치 |
| Nginx | 1.28.0 | ✓ |
| Redis | 설치 + 인증 사용 (`NOAUTH required`) | ⚠️ 비밀번호 필요 |
| PostgreSQL | 포트 5432 개방 | ✓ |

## 네트워크 / 방화벽

### 현재 리스닝 포트

- **80, 443** — Nginx (QUIC/HTTP2/HTTP3 활성)
- **9091** — SSHD
- **3005** — **미사용** ✓ (Swoole 바인딩 가능)

### firewalld 오픈 포트

```text
80/tcp, 443/tcp, 888/tcp, 3478/tcp, 5432/tcp, 9091/tcp, 9095/tcp,
49152-65535/tcp, 3478/udp, 49152-65535/udp
```

- **3005 외부 차단 확인** ✓ — 방화벽에 없으므로 Swoole `127.0.0.1:3005` 바인딩 시 자동으로 외부 차단됨

## 기존 사이트 상태

- 경로: `/www/wwwroot/catphp.imcat.dev/`
- 배포 시점: 2026-03-27 (v1.0.8 추정)
- 구조: `catphp/`, `Public/`, `config/`, `storage/`, `cli.php` 등 (FPM 기반)
- Nginx: `include enable-php-82.conf` → PHP-FPM 라우팅
- SSL: Let's Encrypt 자동 갱신 (`/www/server/panel/vhost/cert/catphp.imcat.dev/`)
- systemd Swoole 서비스: **없음** (신규 등록 필요)

## 배포 시 필요 작업

1. 기존 디렉토리 백업 (`mv catphp.imcat.dev catphp.imcat.dev.bak.$(date +%s)`)
2. scp로 tar.gz 업로드 후 압축 해제
3. `chown -R www:www .`, `storage/` 쓰기 권한
4. systemd 유닛 `catphp-swoole.service` 등록
5. Nginx 사이트 설정 수정:
   - `include enable-php-82.conf` 제거
   - `location /` → `proxy_pass http://127.0.0.1:3005` 추가
   - 정적 파일 location 유지
6. `config/app.php` 업데이트:
   - `swoole.host = '127.0.0.1'`, `port = 3005`
   - Redis 비밀번호 설정 (기존 시스템 Redis 재사용 or 별도 DB 분리)

## 리스크

- Redis 인증 필요 — 비밀번호 확인 필요. 세션용 별도 DB 인덱스 권장 (예: `redis.db = 1`)
- BT 패널이 Nginx 설정을 주기적으로 재생성할 수 있음 — 설정 변경 후 잠금 권장
- 기존 PHP-FPM 기반 라우팅과의 병행 테스트 시나리오 없음 — 롤백 준비 필수
