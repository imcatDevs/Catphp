<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--info);">email</i>
        <div><h4 class="mb-0">Mail</h4><span class="text-muted caption">Cat\Mail — 순수 소켓 SMTP</span></div>
        <span class="badge badge--info badge--sm ms-auto">mailer()</span>
    </div>

    <p class="mb-2"><strong>순수 PHP 소켓 SMTP</strong> 클라이언트입니다. 외부 라이브러리 없이 이메일을 발송합니다. HTML 본문, 첨부파일, CC/BCC, 뷰 템플릿을 지원합니다.</p>
    <p class="mb-3"><code>config/app.php</code>의 <code>mail</code> 키에서 SMTP 서버, 포트, 인증 정보를 설정합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>to(string ...$emails)</code></td><td><code>self</code></td><td>수신자 추가</td></tr>
                    <tr><td><code>cc(string ...$emails)</code></td><td><code>self</code></td><td>참조 추가</td></tr>
                    <tr><td><code>bcc(string ...$emails)</code></td><td><code>self</code></td><td>숨은 참조 추가</td></tr>
                    <tr><td><code>replyTo(string $email)</code></td><td><code>self</code></td><td>답장 주소</td></tr>
                    <tr><td><code>subject(string $subject)</code></td><td><code>self</code></td><td>제목</td></tr>
                    <tr><td><code>body(string $html)</code></td><td><code>self</code></td><td>HTML 본문 (text/plain 자동 생성)</td></tr>
                    <tr><td><code>text(string $plain)</code></td><td><code>self</code></td><td>순수 텍스트 본문</td></tr>
                    <tr><td><code>template(string $name, array $data)</code></td><td><code>self</code></td><td>뷰 템플릿 기반 본문</td></tr>
                    <tr><td><code>attach(string $path, ?string $name)</code></td><td><code>self</code></td><td>파일 첨부</td></tr>
                    <tr><td><code>send()</code></td><td><code>bool</code></td><td>이메일 발송</td></tr>
                    <tr><td><code>preview()</code></td><td><code>string</code></td><td>MIME 문자열 반환 (디버그)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// HTML 이메일 발송</span>
<span class="hl-f">mailer</span>()
    -&gt;<span class="hl-f">to</span>(<span class="hl-s">'user@example.com'</span>)
    -&gt;<span class="hl-f">subject</span>(<span class="hl-s">'가입을 환영합니다'</span>)
    -&gt;<span class="hl-f">body</span>(<span class="hl-s">'&lt;h1&gt;환영합니다!&lt;/h1&gt;&lt;p&gt;CatPHP에 오신 것을 환영합니다.&lt;/p&gt;'</span>)
    -&gt;<span class="hl-f">send</span>();

<span class="hl-c">// 뷰 템플릿 + 첨부파일</span>
<span class="hl-f">mailer</span>()
    -&gt;<span class="hl-f">to</span>(<span class="hl-s">'admin@example.com'</span>)
    -&gt;<span class="hl-f">cc</span>(<span class="hl-s">'manager@example.com'</span>)
    -&gt;<span class="hl-f">subject</span>(<span class="hl-s">'월간 리포트'</span>)
    -&gt;<span class="hl-f">template</span>(<span class="hl-s">'emails/report'</span>, [<span class="hl-s">'month'</span> =&gt; <span class="hl-s">'3월'</span>])
    -&gt;<span class="hl-f">attach</span>(<span class="hl-s">'storage/reports/march.pdf'</span>)
    -&gt;<span class="hl-f">send</span>();</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> SMTP 비밀번호는 <code>config/app.php</code>에 저장하세요. <code>#[\SensitiveParameter]</code>로 스택 트레이스에서 보호됩니다. 첨부파일명과 MIME 타입은 CRLF/NULL/따옴표 인젝션으로부터 자동 살균됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/notify" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Notify</a>
        <a data-spa="/tool/event" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Event</a>
    </div>
</div>
