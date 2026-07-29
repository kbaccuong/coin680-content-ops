# 6 bài Crypto Market News đã soạn sẵn — chờ đăng (2026-07-29)

Đã đối chiếu ≥2 nguồn độc lập cho mỗi tin. Định dạng HTML dùng mẫu đã xác nhận đăng được qua
REST (body + link CTA đơn giản dạng `<a>` + disclaimer — KHÔNG dùng khối `tiny-cta-multi` nhiều
div lồng nhau, vì khối đó (kết hợp với disclaimer) đã bị WAF của Hostinger xoá rỗng nội dung khi
gọi qua REST API nhiều lần liên tiếp). Nếu đăng thủ công qua wp-admin (trình soạn thảo trình
duyệt), có thể đổi lại thành khối `tiny-cta-multi` đẹp hơn — không bị giới hạn này.

**JSON-LD**: để riêng bên dưới mỗi bài (dạng text, chưa bọc `<script>`) — khi đăng qua REST, thẻ
`<script>` sẽ bị WAF xoá nội dung, nên cần dán riêng qua ô "Custom HTML"/Text editor của wp-admin,
hoặc bỏ qua nếu đăng nhanh qua REST (chấp nhận thiếu structured data cho các bài này).

**Danh mục gợi ý (Crypto Market News = ID 2, luôn gắn kèm):**
1. BitMEX → ID 5 (Business & Institutions)
2. CLARITY Act → ID 6 (Regulation & Policy)
3. Cardano/Hedera DeRec → ID 5 (Business & Institutions)
4. Tokenized stocks $2.3B → ID 4 (Market & Analysis)
5. TRM Labs Iran/CoinEx → ID 6 (Regulation & Policy)
6. Coinbase Canada → ID 6 (Regulation & Policy)

**Giờ hẹn đăng đề xuất (UTC, cách nhau ~40 phút):** 07:30, 08:10, 08:50, 09:30, 10:10, 10:50 sáng
30/07 giờ UTC (tức khoảng 14:30-17:50 giờ Việt Nam ngày 30/07) — điều chỉnh lại theo giờ thực tế
lúc đăng.

---

## Bài 1 — BitMEX Shutdown

**Tiêu đề:** BitMEX Confirms Full Shutdown by September 23, Says Closure Isn't About Money
**Slug:** bitmex-shutdown-september-2026

```html
<div class="tiny-wrapper">
  <p class="tiny-sapo">The exchange that invented the leveraged perpetual swap is shutting down -- and its own operator says money had nothing to do with it.</p>
  <p>HDR Global Trading Limited, the company behind BitMEX, confirmed the exchange will close entirely, with operations ending September 23, 2026 at 04:00 UTC. New account registrations stopped the same day the announcement went out.</p>
  <p>A second cutoff matters just as much: starting August 26, 2026, BitMEX will block new positions outright -- accounts can only be reduced or closed after that point.</p>
  <p>HDR Global says this isn't about running out of money. The company reports assets exceeding liabilities, and in eleven years it has never lost customer funds to a hack. The stated reason is a strategic review, with no specific operational failure cited.</p>
  <p>What makes this closure different is what BitMEX built: the 100x-leverage perpetual swap, a derivative with no expiration date that became the template nearly every crypto derivatives exchange still runs today -- including the platforms that have since grown far larger than BitMEX itself.</p>
  <p>New to leveraged crypto trading? <a href="https://coin680.com/category/bitcoin-academy/">Explore the Bitcoin Academy</a> to learn how perpetual swaps actually work.</p>
  <p class="tiny-disclaimer">Disclaimer: The content provided on this page is for informational and educational purposes only and does not constitute financial or investment advice. Cryptocurrency markets are highly volatile and involve significant risk of loss. Always do your own research and consult a licensed financial advisor before making any investment decisions.</p>
</div>
```

**JSON-LD:**
```json
{"@context":"https://schema.org","@type":"NewsArticle","headline":"BitMEX Confirms Full Shutdown by September 23, Says Closure Isn't About Money","description":"HDR Global Trading Limited will shut down BitMEX entirely by September 23, 2026, saying assets exceed liabilities and the closure follows a strategic review, not financial trouble.","author":{"@type":"Person","name":"Mr Whale","url":"https://coin680.com/about/"},"publisher":{"@type":"Organization","name":"Coin680","url":"https://coin680.com/","logo":{"@type":"ImageObject","url":"https://coin680.com/wp-content/uploads/2026/07/coin680-logo-cropped.png"}},"datePublished":"2026-07-30T07:30:00+00:00","dateModified":"2026-07-30T07:30:00+00:00"}
```

**X post kèm theo (đăng cùng giờ với bài web):**
> The exchange that invented the leveraged perpetual swap just announced it's shutting down for good -- and it's not a money problem. Full story + why this one closure is different: [link bài viết trong comment]
> #BitMEX #Crypto #Bitcoin

**Comment đầu tiên (link bài):** Full breakdown here → [URL bài viết]

---

## Bài 2 — CLARITY Act Deadline

**Tiêu đề:** The CLARITY Act's August 7 Deadline Is Now a Countdown, and the Senate Math Isn't There Yet
**Slug:** clarity-act-august-7-deadline

```html
<div class="tiny-wrapper">
  <p class="tiny-sapo">Nine votes. That's the gap standing between the CLARITY Act and a Senate that's about to leave for summer recess.</p>
  <p>The bill, which would split oversight of digital assets between the SEC and the CFTC and set rules for exchanges, token issuers, and some DeFi platforms, must clear an initial Senate vote before August 7 to stay on track for 2026 passage. Miss that date, and its odds of becoming law this year drop sharply.</p>
  <p>Right now the bill sits nine votes short of the 60 needed to advance. Republicans account for 51 of the 53 publicly declared supporters. No Democrat has openly backed the current version, and at least 12 have criticized or opposed it outright.</p>
  <p>Ethics rules have become one of the sharper sticking points. Supporters added new ethics provisions for government officials specifically to try to win over skeptical votes, alongside ongoing disagreements over a section on stablecoin yield.</p>
  <p>An initial vote may still happen before the deadline even if full passage slips to after recess -- but for an industry that has waited years for clear federal rules, even a symbolic procedural win before August 7 would matter more than the calendar suggests.</p>
  <p>Curious how U.S. crypto regulation actually works today? <a href="https://coin680.com/category/bitcoin-academy/">See the Bitcoin Academy</a> for the fundamentals.</p>
  <p class="tiny-disclaimer">Disclaimer: The content provided on this page is for informational and educational purposes only and does not constitute financial or investment advice. Cryptocurrency markets are highly volatile and involve significant risk of loss. Always do your own research and consult a licensed financial advisor before making any investment decisions.</p>
</div>
```

**JSON-LD:**
```json
{"@context":"https://schema.org","@type":"NewsArticle","headline":"The CLARITY Act's August 7 Deadline Is Now a Countdown, and the Senate Math Isn't There Yet","description":"The CLARITY Act needs an initial Senate vote before August 7 to stay on track, but remains nine votes short of the 60 needed, with ethics provisions among the last sticking points.","author":{"@type":"Person","name":"Mr Whale","url":"https://coin680.com/about/"},"publisher":{"@type":"Organization","name":"Coin680","url":"https://coin680.com/","logo":{"@type":"ImageObject","url":"https://coin680.com/wp-content/uploads/2026/07/coin680-logo-cropped.png"}},"datePublished":"2026-07-30T08:10:00+00:00","dateModified":"2026-07-30T08:10:00+00:00"}
```

**X post:**
> Nine votes. That's what's standing between the CLARITY Act and a Senate vote before its August 7 deadline. Where the math actually stands: [link trong comment]
> #Crypto #Regulation #ClarityAct

**Comment đầu tiên:** Full breakdown here → [URL bài viết]

---

## Bài 3 — Cardano/Hedera DeRec Alliance

**Tiêu đề:** Cardano and Hedera Just Joined an Alliance Built to Solve Crypto's Oldest Problem: Lost Keys
**Slug:** cardano-hedera-derec-alliance

```html
<div class="tiny-wrapper">
  <p class="tiny-sapo">Losing a private key has ended more crypto holdings than any hack. A new industry alliance wants to make that mistake recoverable.</p>
  <h2>What Was Announced</h2>
  <p>IOHK, the technology company behind Cardano, and Hedera have joined as the final founding members of the Decentralized Recovery (DeRec) Alliance, alongside existing members Ripple, Algorand Foundation, Hashgraph, and XRPL Labs.</p>
  <p>As founding members, both companies will serve two-year terms on the alliance's Technical Oversight Committee, giving them a direct role in shaping the recovery standard rather than just adopting it later.</p>
  <h2>Why a Recovery Standard Matters</h2>
  <p>DeRec is an open-source protocol designed to secure recovery of private keys, passwords, and other credentials across any blockchain or ledger -- not just one network's own wallet. The goal is a shared, industry-wide standard rather than each project solving key recovery on its own.</p>
  <p>That distinction matters because lost-key losses aren't tied to any single blockchain's design flaw -- they're a user-experience problem that has quietly undermined crypto adoption across every network for over a decade, regardless of how secure the underlying chain itself is.</p>
  <p>Want to understand how private keys and wallet security actually work? <a href="https://coin680.com/category/bitcoin-academy/">Start with the Bitcoin Academy</a>.</p>
  <p class="tiny-disclaimer">Disclaimer: The content provided on this page is for informational and educational purposes only and does not constitute financial or investment advice. Cryptocurrency markets are highly volatile and involve significant risk of loss. Always do your own research and consult a licensed financial advisor before making any investment decisions.</p>
</div>
```

**JSON-LD:**
```json
{"@context":"https://schema.org","@type":"NewsArticle","headline":"Cardano and Hedera Just Joined an Alliance Built to Solve Crypto's Oldest Problem: Lost Keys","description":"IOHK and Hedera joined Ripple, Algorand, Hashgraph, and XRPL Labs as founding members of the DeRec Alliance, an open-source standard for recovering lost crypto keys and credentials.","author":{"@type":"Person","name":"Mr Whale","url":"https://coin680.com/about/"},"publisher":{"@type":"Organization","name":"Coin680","url":"https://coin680.com/","logo":{"@type":"ImageObject","url":"https://coin680.com/wp-content/uploads/2026/07/coin680-logo-cropped.png"}},"datePublished":"2026-07-30T08:50:00+00:00","dateModified":"2026-07-30T08:50:00+00:00"}
```

**X post:**
> Losing a private key has wiped out more crypto than any hack ever has. Cardano's IOHK and Hedera just joined an alliance built to fix exactly that: [link trong comment]
> #Cardano #Hedera #CryptoSecurity

**Comment đầu tiên:** Full story here → [URL bài viết]

---

## Bài 4 — Tokenized Stocks $2.3B Record

**Tiêu đề:** Tokenized Stocks Hit a Record $2.3 Billion -- Still Barely a Rounding Error in the RWA Market
**Slug:** tokenized-stocks-record-2-3-billion

```html
<div class="tiny-wrapper">
  <p class="tiny-sapo">$2.3 billion. That's the new all-time high for tokenized stocks -- and it's still only about 5.5% of the broader tokenized asset market.</p>
  <table>
    <thead><tr><th>Metric</th><th>Figure</th></tr></thead>
    <tbody>
      <tr><td>Tokenized stocks market cap</td><td>$2.3 billion (record)</td></tr>
      <tr><td>Ethereum's chain share</td><td>34%</td></tr>
      <tr><td>BNB Chain's share</td><td>30%</td></tr>
      <tr><td>Solana's share</td><td>23%</td></tr>
      <tr><td>Top issuer, Ondo Finance</td><td>$955 million onchain equities</td></tr>
      <tr><td>Share of total RWA market ($34B)</td><td>~5.5%</td></tr>
    </tbody>
  </table>
  <p>The market has nearly doubled since March 2026, when it first cleared $1 billion. Ondo Finance leads issuance with $955 million in onchain equities, followed by Kraken's xStocks at $507 million and Binance's bStocks at $334 million.</p>
  <p>Zoom out, though, and tokenized stocks are still a small piece of a much bigger trend: the total tokenized real-world asset market has surged 589% since early 2025, reaching $34 billion, led mostly by government bonds and money market funds rather than equities.</p>
  <p>That gap is the real story here -- tokenized stocks are growing fast in absolute terms, but bonds and cash-like instruments are where institutional money has actually concentrated so far.</p>
  <p>New to how tokenized assets fit into crypto? <a href="https://coin680.com/category/bitcoin-academy/">Browse the Bitcoin Academy</a>.</p>
  <p class="tiny-disclaimer">Disclaimer: The content provided on this page is for informational and educational purposes only and does not constitute financial or investment advice. Cryptocurrency markets are highly volatile and involve significant risk of loss. Always do your own research and consult a licensed financial advisor before making any investment decisions.</p>
</div>
```

**JSON-LD:**
```json
{"@context":"https://schema.org","@type":"NewsArticle","headline":"Tokenized Stocks Hit a Record $2.3 Billion -- Still Barely a Rounding Error in the RWA Market","description":"Tokenized stocks reached a record $2.3 billion market cap, nearly doubling since March 2026, though they remain about 5.5% of the broader $34 billion tokenized RWA market.","author":{"@type":"Person","name":"Mr Whale","url":"https://coin680.com/about/"},"publisher":{"@type":"Organization","name":"Coin680","url":"https://coin680.com/","logo":{"@type":"ImageObject","url":"https://coin680.com/wp-content/uploads/2026/07/coin680-logo-cropped.png"}},"datePublished":"2026-07-30T09:30:00+00:00","dateModified":"2026-07-30T09:30:00+00:00"}
```

**X post:**
> $2.3 billion -- a new record for tokenized stocks. Still, that's barely 5.5% of the broader tokenized asset market. Where the money's actually concentrated: [link trong comment]
> #RWA #Tokenization #Crypto

**Comment đầu tiên:** Full numbers here → [URL bài viết]

---

## Bài 5 — TRM Labs Iran/CoinEx

**Tiêu đề:** How $3.8 Billion in Iran-Linked Crypto Flowed Through a Single Exchange
**Slug:** trm-labs-coinex-iran-sanctions

```html
<div class="tiny-wrapper">
  <p class="tiny-sapo">Blockchain analytics firm TRM Labs says one exchange handled illicit transaction volume roughly 27 times higher than its compliant peers.</p>
  <p>According to TRM Labs, wallets linked to about 60 sanctioned Iranian entities have moved roughly $3.8 billion through crypto exchange CoinEx since 2019 -- with some $2.7 billion of that flowing specifically between CoinEx and Nobitex, Iran's largest domestic exchange.</p>
  <p>The scale of the pattern is what stands out most: TRM says CoinEx's illicit transaction share sits near 8% of the volume it reviewed, compared to a roughly 0.3% benchmark TRM cites for exchanges it considers compliant.</p>
  <p>CoinEx has denied any commercial relationship with Iranian exchanges or government entities, and says it has already begun exiting Iran-related business. The report, first published in late June 2026, has continued drawing attention as a case study in how sanctioned funds move through exchanges that operate outside direct U.S. jurisdiction.</p>
  <p>For an industry pushing regulators for clearer rules, cases like this one are exactly what skeptical lawmakers point to when arguing crypto still needs tighter guardrails, not fewer.</p>
  <p>Want the basics on how crypto exchanges actually work? <a href="https://coin680.com/category/bitcoin-academy/">Visit the Bitcoin Academy</a>.</p>
  <p class="tiny-disclaimer">Disclaimer: The content provided on this page is for informational and educational purposes only and does not constitute financial or investment advice. Cryptocurrency markets are highly volatile and involve significant risk of loss. Always do your own research and consult a licensed financial advisor before making any investment decisions.</p>
</div>
```

**JSON-LD:**
```json
{"@context":"https://schema.org","@type":"NewsArticle","headline":"How $3.8 Billion in Iran-Linked Crypto Flowed Through a Single Exchange","description":"TRM Labs says roughly $3.8 billion tied to sanctioned Iranian entities moved through CoinEx since 2019, with an illicit-transaction share far above compliant exchange benchmarks.","author":{"@type":"Person","name":"Mr Whale","url":"https://coin680.com/about/"},"publisher":{"@type":"Organization","name":"Coin680","url":"https://coin680.com/","logo":{"@type":"ImageObject","url":"https://coin680.com/wp-content/uploads/2026/07/coin680-logo-cropped.png"}},"datePublished":"2026-07-30T10:10:00+00:00","dateModified":"2026-07-30T10:10:00+00:00"}
```

**X post:**
> $3.8 billion in Iran-linked crypto moved through one exchange since 2019 -- at an illicit-transaction rate ~27x higher than compliant peers, per TRM Labs. Full details: [link trong comment]
> #Crypto #Compliance #Sanctions

**Comment đầu tiên:** Full report breakdown here → [URL bài viết]

---

## Bài 6 — Coinbase Canada CEO

**Tiêu đề:** Coinbase Wants to Be Canada's "Everything Exchange" -- But Its New CEO Says Temporary Rules Won't Cut It
**Slug:** coinbase-canada-everything-exchange

```html
<div class="tiny-wrapper">
  <p class="tiny-sapo">Eric Richmond has run Coinbase's Canadian operation for barely two months, and he's already telling regulators their current approach won't support what he wants to build.</p>
  <p>Richmond, who became Coinbase Canada's country director and CEO in June, is pushing for permanent, harmonized federal rules rather than the case-by-case exemptions the company currently relies on to operate. His argument: expanding into derivatives, tokenized assets, and decentralized finance requires a stable rulebook, not one-off approvals.</p>
  <p>The push comes as Coinbase Financial Markets, the firm's CFTC-regulated U.S. arm, has already secured an international exemption to offer futures contracts to eligible Canadian clients -- with that product expected to go live within weeks under the current temporary framework.</p>
  <p>Richmond's broader goal is what he calls an "everything exchange": a single platform where Canadians manage crypto alongside other financial assets, not just digital tokens. Coinbase is targeting CIRO dealer status in early 2027 as part of that plan.</p>
  <p>Whether Canada moves toward the harmonized framework Coinbase wants remains an open question -- but the derivatives launch alone will test how much a temporary exemption can actually support before permanent rules become unavoidable.</p>
  <p>New to how crypto exchanges are regulated? <a href="https://coin680.com/category/bitcoin-academy/">Check out the Bitcoin Academy</a>.</p>
  <p class="tiny-disclaimer">Disclaimer: The content provided on this page is for informational and educational purposes only and does not constitute financial or investment advice. Cryptocurrency markets are highly volatile and involve significant risk of loss. Always do your own research and consult a licensed financial advisor before making any investment decisions.</p>
</div>
```

**JSON-LD:**
```json
{"@context":"https://schema.org","@type":"NewsArticle","headline":"Coinbase Wants to Be Canada's \"Everything Exchange\" -- But Its New CEO Says Temporary Rules Won't Cut It","description":"Coinbase Canada's new CEO Eric Richmond is pushing for permanent federal crypto rules as the company prepares a derivatives launch and pursues a broader everything-exchange strategy.","author":{"@type":"Person","name":"Mr Whale","url":"https://coin680.com/about/"},"publisher":{"@type":"Organization","name":"Coin680","url":"https://coin680.com/","logo":{"@type":"ImageObject","url":"https://coin680.com/wp-content/uploads/2026/07/coin680-logo-cropped.png"}},"datePublished":"2026-07-30T10:50:00+00:00","dateModified":"2026-07-30T10:50:00+00:00"}
```

**X post:**
> Coinbase Canada's new CEO has been in the job two months -- and he's already telling regulators temporary exemptions won't cut it. The "everything exchange" plan: [link trong comment]
> #Coinbase #Canada #Crypto

**Comment đầu tiên:** Full story here → [URL bài viết]

---

## Ghi chú kỹ thuật quan trọng (đọc trước khi đăng)

1. **Không dùng SSH được** (bị chặn bởi an toàn Claude Code phiên này).
2. **REST API không ổn định**: đăng qua REST đôi khi thành công, đôi khi bị WAF Hostinger âm thầm
   xoá rỗng nội dung (trả về status 200 nhưng `content` rỗng) — xảy ra nhiều hơn khi gọi liên tiếp
   nhiều request trong thời gian ngắn. Nếu Claude thử đăng qua REST, nên **cách nhau vài phút mỗi
   bài** và luôn xác nhận lại nội dung thật sự đã lưu (không chỉ tin vào status "thành công").
3. **An toàn nhất:** đăng thủ công qua wp-admin (Posts → Add New) — copy nội dung HTML ở trên vào
   chế độ "Code editor" (không phải visual), dán tiêu đề, chọn danh mục, hẹn giờ đăng (Publish →
   Immediately → đổi thành giờ mong muốn), giữ nguyên schema JSON-LD dán vào 1 khối "Custom HTML"
   riêng hoặc bỏ qua nếu không quan trọng bằng việc đăng đúng giờ.
