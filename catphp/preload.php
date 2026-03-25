<?php declare(strict_types=1);

/**
 * OPcache Preload — catphp.php 1개만 preload
 *
 * php.ini: opcache.preload=catphp/preload.php
 * 도구 파일은 preload 안 함 (사용 시에만 로드 원칙 유지).
 */

opcache_compile_file(__DIR__ . '/catphp.php');
