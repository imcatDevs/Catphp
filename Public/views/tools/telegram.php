<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--secondary);">send</i>
        <div><h4 class="mb-0">Telegram</h4><span class="text-muted caption">Cat\Telegram — 텔레그램 봇</span></div>
        <span class="badge badge--secondary badge--sm ms-auto">telegram()</span>
    </div>

    <p class="mb-2"><strong>Telegram Bot API</strong> 클라이언트입니다. 텍스트, HTML, Markdown 메시지 전송, 사진/파일 업로드, 인라인 키보드 등을 체이닝 API로 사용합니다.</p>
    <p class="mb-3">Http 도구를 내부적으로 사용하며, <code>apiUpload()</code>로 multipart 파일 전송도 지원합니다. 서버 모니터링, 에러 알림, 주문 알림 등에 활용할 수 있습니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">설정 — config/app.php</h6></div>
        <pre class="demo-code" style="border-radius:0 0 8px 8px;"><code><span class="hl-s">'telegram'</span> =&gt; [
    <span class="hl-s">'bot_token'</span> =&gt; <span class="hl-s">'123456:ABC-DEF'</span>,  <span class="hl-c">// Bot API 토큰</span>
    <span class="hl-s">'chat_id'</span> =&gt; <span class="hl-s">'@mychannel'</span>,      <span class="hl-c">// 기본 채팅 ID</span>
]</code></pre>
    </div>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:250px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>to(string $chatId)</code></td><td><code>self</code></td><td>수신자 지정</td></tr>
                    <tr><td><code>message(string $text)</code></td><td><code>self</code></td><td>일반 텍스트 메시지</td></tr>
                    <tr><td><code>html(string $html)</code></td><td><code>self</code></td><td>HTML 형식 메시지</td></tr>
                    <tr><td><code>markdown(string $md)</code></td><td><code>self</code></td><td>Markdown 형식 메시지</td></tr>
                    <tr><td><code>photo(string $url, ?string $caption)</code></td><td><code>self</code></td><td>사진 전송 (URL)</td></tr>
                    <tr><td><code>file(string $path, ?string $caption)</code></td><td><code>self</code></td><td>파일 전송 (multipart 업로드)</td></tr>
                    <tr><td><code>keyboard(array $buttons)</code></td><td><code>self</code></td><td>인라인 키보드 첨부</td></tr>
                    <tr><td><code>send()</code></td><td><code>bool</code></td><td>메시지 전송 실행</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 텍스트 메시지</span>
<span class="hl-f">telegram</span>()-&gt;<span class="hl-f">message</span>(<span class="hl-s">'서버 정상 가동 중'</span>)-&gt;<span class="hl-f">send</span>();

<span class="hl-c">// HTML 메시지 + 특정 채팅</span>
<span class="hl-f">telegram</span>()-&gt;<span class="hl-f">to</span>(<span class="hl-s">'123456789'</span>)
    -&gt;<span class="hl-f">html</span>(<span class="hl-s">'&lt;b&gt;주문 알림&lt;/b&gt;\n상품: CatPHP 라이선스'</span>)
    -&gt;<span class="hl-f">send</span>();

<span class="hl-c">// 에러 알림 (Event 연동)</span>
<span class="hl-f">event</span>()-&gt;<span class="hl-f">on</span>(<span class="hl-s">'error'</span>, <span class="hl-k">fn</span>(<span class="hl-v">$msg</span>) =&gt;
    <span class="hl-f">telegram</span>()-&gt;<span class="hl-f">html</span>(<span class="hl-s">'🚨 &lt;b&gt;에러&lt;/b&gt;: '</span> . <span class="hl-v">$msg</span>)-&gt;<span class="hl-f">send</span>()
);</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> Bot API 토큰은 <code>config/app.php</code>에 저장하고, 절대 클라이언트에 노출하지 마세요. <code>#[\SensitiveParameter]</code>가 적용되어 스택 트레이스에서 토큰이 숨겨집니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/http" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Http</a>
        <a data-spa="/tool/event" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Event</a>
    </div>
</div>
