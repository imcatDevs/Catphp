<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\SwooleSent — Swoole 응답 전송 완료 신호 예외
 *
 * Swoole 상주 프로세스 환경에서 `Json::send()`, `Response::send()` 등이
 * `$res->end()`로 응답을 보낸 뒤 `exit` 대신 이 예외를 던져
 * 핸들러 체인을 조기 종료한다.
 *
 * `exit`를 사용하면 Swoole 워커 프로세스 자체가 종료되어 치명적이므로,
 * 반드시 예외 기반 중단을 사용해야 한다.
 *
 * `Swoole.php::handleRequest()`의 catch 블록에서 이 예외는
 * 정상 종료로 취급되어 에러 로깅에서 제외된다.
 */
final class SwooleSent extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Swoole response already sent');
    }
}
