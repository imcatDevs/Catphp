<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--success);">fact_check</i>
        <div><h4 class="mb-0">Valid</h4><span class="text-muted caption">Cat\Valid — 폼 입력 검증</span></div>
        <span class="badge badge--success badge--sm ms-auto">valid()</span>
    </div>

    <p class="mb-2"><strong>규칙 기반</strong> 입력 데이터 검증 도구입니다. 파이프(<code>|</code>)로 규칙을 체이닝하고, 각 필드별 커스텀 에러 메시지를 지정할 수 있습니다.</p>
    <p class="mb-3">내장 규칙: <code>required</code>, <code>nullable</code>, <code>email</code>, <code>min:N</code>, <code>max:N</code>, <code>numeric</code>, <code>in:a,b,c</code>, <code>regex:/pattern/</code> 등. <code>nullable</code> 규칙이 있으면 빈 값일 때 나머지 규칙을 건너뜁니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:260px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>rules(array $rules)</code></td><td><code>self</code></td><td>검증 규칙 정의 (clone 기반)</td></tr>
                    <tr><td><code>check(array $data)</code></td><td><code>self</code></td><td>데이터 검증 실행 (규칙 적용)</td></tr>
                    <tr><td><code>fails()</code></td><td><code>bool</code></td><td>검증 실패 여부 (<code>!fails()</code> 로 성공 확인)</td></tr>
                    <tr><td><code>errors()</code></td><td><code>array</code></td><td>전체 에러 배열 <code>[field =&gt; [msg,...]]</code></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 폼 검증</h6>
    <pre class="demo-code mb-3"><code><span class="hl-v">$v</span> = <span class="hl-f">valid</span>()-&gt;<span class="hl-f">rules</span>([
    <span class="hl-s">'name'</span>     =&gt; <span class="hl-s">'required|min:2|max:50'</span>,
    <span class="hl-s">'email'</span>    =&gt; <span class="hl-s">'required|email'</span>,
    <span class="hl-s">'age'</span>      =&gt; <span class="hl-s">'nullable|numeric|min:0|max:150'</span>,
    <span class="hl-s">'role'</span>     =&gt; <span class="hl-s">'required|in:user,admin,moderator'</span>,
    <span class="hl-s">'password'</span> =&gt; <span class="hl-s">'required|min:8'</span>,
])-&gt;<span class="hl-f">check</span>(<span class="hl-v">$_POST</span>);

<span class="hl-k">if</span> (<span class="hl-v">$v</span>-&gt;<span class="hl-f">fails</span>()) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'검증 실패'</span>, <span class="hl-v">$v</span>-&gt;<span class="hl-f">errors</span>(), <span class="hl-n">422</span>);
    <span class="hl-c">// errors(): ['name' =&gt; ['최소 2자 이상'], ...]</span>
}</code></pre>

    <h6 class="mb-2">API 검증 패턴</h6>
    <pre class="demo-code mb-3"><code><span class="hl-f">router</span>()-&gt;<span class="hl-f">post</span>(<span class="hl-s">'/api/users'</span>, <span class="hl-k">function</span>() {
    <span class="hl-v">$v</span> = <span class="hl-f">valid</span>()-&gt;<span class="hl-f">rules</span>([
        <span class="hl-s">'email'</span> =&gt; <span class="hl-s">'required|email'</span>,
        <span class="hl-s">'name'</span>  =&gt; <span class="hl-s">'required|min:2'</span>,
    ])-&gt;<span class="hl-f">check</span>(<span class="hl-f">input</span>());

    <span class="hl-k">if</span> (<span class="hl-v">$v</span>-&gt;<span class="hl-f">fails</span>()) {
        <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'검증 실패'</span>, <span class="hl-v">$v</span>-&gt;<span class="hl-f">errors</span>(), <span class="hl-n">422</span>);
    }
    <span class="hl-v">$id</span> = <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">insert</span>(<span class="hl-f">input</span>());
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>([<span class="hl-s">'id'</span> =&gt; <span class="hl-v">$id</span>], <span class="hl-n">201</span>);
});</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>내장 규칙:</strong> <code>required</code>, <code>nullable</code>, <code>email</code>, <code>numeric</code>, <code>integer</code>, <code>string</code>, <code>array</code>, <code>min:N</code>, <code>max:N</code>, <code>between:N,M</code>, <code>in:a,b,c</code>, <code>url</code>, <code>regex:/pattern/</code>, <code>confirmed</code>, <code>unique:table,col</code>. 커스텀: <code>Valid::extend()</code></span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/json" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Json</a>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
    </div>
</div>
