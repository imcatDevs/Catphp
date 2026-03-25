<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--info);">science</i>
        <div><h4 class="mb-0">Faker</h4><span class="text-muted caption">Cat\Faker — 테스트 데이터 생성</span></div>
        <span class="badge badge--info badge--sm ms-auto">faker()</span>
    </div>

    <p class="mb-2"><strong>테스트 데이터 생성기</strong>입니다. 한국어/영어 이름, 주소, 이메일, 전화번호, 날짜, 텍스트 등 다양한 가짜 데이터를 생성합니다.</p>
    <p class="mb-3"><code>make()</code>로 대량 데이터를 일괄 생성하고, <code>unique()</code>로 중복 없는 데이터를 보장할 수 있습니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">주요 메서드</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th>카테고리</th><th>메서드</th></tr></thead>
                <tbody>
                    <tr><td><strong>이름</strong></td><td><code>name()</code>, <code>firstName()</code>, <code>lastName()</code></td></tr>
                    <tr><td><strong>연락처</strong></td><td><code>email()</code>, <code>safeEmail()</code>, <code>phone()</code></td></tr>
                    <tr><td><strong>주소</strong></td><td><code>address()</code>, <code>city()</code>, <code>zipCode()</code></td></tr>
                    <tr><td><strong>텍스트</strong></td><td><code>word()</code>, <code>sentence()</code>, <code>paragraph()</code>, <code>title()</code>, <code>slug()</code></td></tr>
                    <tr><td><strong>숫자</strong></td><td><code>number(min, max)</code>, <code>float()</code>, <code>boolean()</code></td></tr>
                    <tr><td><strong>날짜</strong></td><td><code>date()</code>, <code>time()</code>, <code>dateTime()</code>, <code>pastDate()</code>, <code>futureDate()</code></td></tr>
                    <tr><td><strong>인터넷</strong></td><td><code>url()</code>, <code>domain()</code>, <code>ipv4()</code>, <code>userAgent()</code></td></tr>
                    <tr><td><strong>식별자</strong></td><td><code>uuid()</code>, <code>color()</code>, <code>hash()</code>, <code>password()</code></td></tr>
                    <tr><td><strong>유틸</strong></td><td><code>make(count, callback)</code>, <code>unique(generator)</code>, <code>randomElement(array)</code></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 한국어 가짜 데이터</span>
<span class="hl-f">faker</span>()-&gt;<span class="hl-f">name</span>();       <span class="hl-c">// "김민수"</span>
<span class="hl-f">faker</span>()-&gt;<span class="hl-f">phone</span>();      <span class="hl-c">// "010-1234-5678"</span>
<span class="hl-f">faker</span>()-&gt;<span class="hl-f">address</span>();    <span class="hl-c">// "서울특별시 강남구 123-45"</span>

<span class="hl-c">// 대량 데이터 생성 (시딩)</span>
<span class="hl-v">$users</span> = <span class="hl-f">faker</span>()-&gt;<span class="hl-f">make</span>(<span class="hl-n">100</span>, <span class="hl-k">fn</span>(<span class="hl-v">$f</span>, <span class="hl-v">$i</span>) =&gt; [
    <span class="hl-s">'name'</span>  =&gt; <span class="hl-v">$f</span>-&gt;<span class="hl-f">name</span>(),
    <span class="hl-s">'email'</span> =&gt; <span class="hl-v">$f</span>-&gt;<span class="hl-f">unique</span>(<span class="hl-k">fn</span>() =&gt; <span class="hl-v">$f</span>-&gt;<span class="hl-f">safeEmail</span>()),
    <span class="hl-s">'age'</span>   =&gt; <span class="hl-v">$f</span>-&gt;<span class="hl-f">number</span>(<span class="hl-n">18</span>, <span class="hl-n">65</span>),
]);

<span class="hl-c">// 영어 로케일</span>
<span class="hl-f">faker</span>()-&gt;<span class="hl-f">locale</span>(<span class="hl-s">'en'</span>)-&gt;<span class="hl-f">name</span>();  <span class="hl-c">// "John Smith"</span></code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>팁:</strong> <code>unique()</code>는 그룹별로 중복을 추적합니다. 시딩이 끝나면 <code>resetUnique()</code>로 초기화하세요. 기본 로케일은 <code>ko</code>(한국어)입니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/migration" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Migration</a>
    </div>
</div>
