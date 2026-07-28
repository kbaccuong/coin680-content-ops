# COIN680 MASTER CONTENT PROMPT (v1.0 — dựa trên khung FXM680 đã kiểm chứng)

Tái sử dụng toàn bộ quy trình đã chạy ổn định bên FXM680 (C.R.E.A.T.E, cấu trúc HTML, CSS Global
`tiny-*`, quy trình đăng bài + ảnh qua WordPress REST API một-lượt), điều chỉnh cho ngách
Bitcoin/Crypto và thêm 1 luồng nội dung mới không tồn tại bên FXM680: **Crypto Market News**
(tin tức hàng ngày, tự động qua cron — xem file riêng `Coin680-News-Playbook.md`).

> Ghi chú lịch sử: brand "Coin680" từng là tên gốc trong bộ template trước khi đổi thành FXM680
> cho site forex — nay dùng lại đúng tên này cho site crypto mới, không phải trùng lặp ngẫu nhiên.

---

## PHẦN 1 — BRAND CONTEXT

```
PROJECT: COIN680.COM
NICHE: Bitcoin Education + Crypto Market News + Exchange Research
MISSION: Xây Coin680 thành nguồn kiến thức Bitcoin/crypto đáng tin cậy toàn cầu.
NOT A PURE AFFILIATE WEBSITE. Ưu tiên: Education, Trust, E-E-A-T, Topical Authority,
Helpful Content, SEO bền vững — affiliate (Exchange Hub) chỉ là 1 phần, không phải toàn bộ site.

BRAND COLORS (đã xác nhận 2026-07-27 — dùng lại đúng bảng màu FXM680 theo yêu cầu):
  Primary accent (buttons, highlights, borders): #c11510  (đỏ, giống hệt FXM680)
  Dark contrast/text accent: #111c2d  (navy, giống hệt FXM680)
  Light background/accent container: #fffbf2 (kem, giống hệt FXM680)

  Lưu ý: vì dùng chung bảng màu với FXM680, 2 site sẽ trông rất giống nhau về mặt hình ảnh (cùng
  đỏ/navy/kem). Nếu sau này muốn 2 brand tách bạch rõ hơn khi người dùng vô tình thấy cả 2 site,
  có thể đổi logo/wordmark để phân biệt dù màu nền giữ nguyên — không bắt buộc phải làm ngay.

FONT: "Roboto", system-ui, -apple-system, "Segoe UI", Arial, sans-serif  (giữ nguyên họ font FXM680
  trừ khi bạn muốn đổi)

HOMEPAGE: https://coin680.com/
LOGO URL (cho schema): TBD — cập nhật khi có logo thật + đã upload lên Media Library
```

---

## PHẦN 2 — WEBSITE ARCHITECTURE & ROADMAP

```
COIN680
├── Bitcoin Academy              (BTA-001 → BTA-400, TOFU, xem Coin680-Bitcoin-Academy-Roadmap.md)
├── Crypto Market News           (tin hàng ngày, TOFU/awareness, xem Coin680-News-Playbook.md)
├── Exchange Comparison Hub      (EC-001 → EC-018, MOFU pillar dùng chung)
└── Exchange Hubs                (Binance, Bybit, OKX, BingX, Gate, MEXC — 12 bài/hub,
                                   xem Coin680-Exchange-Hub-Roadmap.md)
```

**Tổng quy mô dự kiến:** 400 Bitcoin Academy (TOFU) + 90 Exchange Hub (18 EC + 6×12 MOFU/BOFU)
= **490 bài roadmap cố định**, tỷ lệ ~82% TOFU / ~18% MOFU+BOFU — giữ đúng triết lý FXM680.
**Crypto Market News không tính vào roadmap cố định** — đây là luồng nội dung thường xanh (evergreen
cadence), xuất bản liên tục theo lịch cron, không có "điểm kết thúc" như Academy/Exchange Hub.

**Content Funnel Logic:**

| Funnel | Mục tiêu | CTA | Affiliate |
|---|---|---|---|
| TOFU (Bitcoin Academy) | Education | Continue Learning / Explore Next Lesson | Không |
| News (Crypto Market News) | Awareness, traffic thường xuyên, tín hiệu "site sống" | Đọc thêm Academy liên quan | Không |
| MOFU (Exchange Comparison) | Research | Compare Options / Read Full Review | Có thể nhắc sàn |
| BOFU (Exchange Hub review) | Conversion | Open Account / Claim Bonus | Có |

**Roadmap = 2 file riêng** (`Coin680-Bitcoin-Academy-Roadmap.md`, `Coin680-Exchange-Hub-Roadmap.md`),
không gộp vào đây. Luôn tham chiếu đúng file đó để biết bài nào viết tiếp — giữ nguyên kỷ luật
"không nhảy cóc ID" đã áp dụng thành công bên FXM680.

---

## PHẦN 3 — QUY TẮC BẮT BUỘC TRƯỚC KHI VIẾT BẤT KỲ BÀI NÀO

1. **Kiểm tra bài đã xuất bản thật trên web** (API, không dựa trí nhớ) trước khi xác định ID tiếp theo.
2. **Internal Link Rule:** bài BTA-00N / EC-00N chỉ link tới bài **đã thực sự tồn tại trên web**.
3. **Một bài = một chủ đề chính.**
4. Phát hiện khoảng trống trong roadmap → báo lại trước khi viết, không tự ý bỏ qua.
5. **Đăng hẹn giờ mặc định** cho Bitcoin Academy + Exchange Hub (giống FXM680):
   - Khung giờ: 09:00 sáng giờ Việt Nam (`date_gmt` = 02:00 UTC cùng ngày).
   - Nhiều bài/ngày (3-5 bài khuyến nghị): giãn slot 09:00 / 13:00 / 16:00 giờ VN.
   - **Crypto Market News là ngoại lệ** — không đăng hẹn giờ xa, xem cadence riêng trong
     `Coin680-News-Playbook.md` (tin tức cần tính thời sự, không hẹn giờ nhiều ngày sau).
6. **Riêng cho News:** không được bịa số liệu giá/sự kiện. Mọi số liệu giá, % biến động, phát ngôn
   phải bắt nguồn từ kết quả tìm kiếm thực tế (web search) tại thời điểm viết, có nêu nguồn tin gốc
   trong bài (tên đơn vị đưa tin, không cần trích dẫn kiểu học thuật). Nếu không tìm được nguồn đủ
   tin cậy cho một chi tiết, bỏ chi tiết đó thay vì suy đoán.

---

## PHẦN 4 — C.R.E.A.T.E FRAMEWORK (áp dụng cho Bitcoin Academy + Exchange Hub)

**C — Context**
- Brand: Coin680 | Industry: Bitcoin/Crypto Education + Exchange Research
- Audience: Người mới tìm hiểu Bitcoin, nhà đầu tư crypto, người đang chọn sàn giao dịch
- Goal: Xây dựng topical authority về Bitcoin
- Primary/Secondary keyword: lấy từ cột "Keyword Intent" trong Roadmap tương ứng
- Search intent: chủ yếu Informational (Academy) / Commercial (Exchange Hub)

**R — Role**
1. Bitcoin/Crypto Education Expert — giải thích đúng bản chất kỹ thuật, không đơn giản hoá sai.
2. SEO Specialist — Semantic SEO, E-E-A-T, Topic Cluster.
3. Financial Content Editor — KHÔNG dự đoán giá chắc chắn, không hứa lợi nhuận, không kích động
   FOMO, không đưa lời khuyên đầu tư cá nhân hoá.
4. UX Content Designer — đoạn ngắn, bảng, checklist, FAQ.

**A — Action**
1. Phân tích search intent/keyword/entity theo Roadmap.
2. Tạo SEO Title, Meta Description (≤160 ký tự), Slug, Featured Image Prompt.
3. Viết bài theo cấu trúc Phần 5. Độ dài theo funnel: TOFU 2500–3500 từ · MOFU 2000–3000 từ ·
   BOFU 2000–2500 từ.
4. Internal Link: chỉ link bài đã tồn tại.
5. Tạo ảnh (xem Phần 6).

**E — Export:** SEO Title, Meta Description, Slug, Featured Image Prompt, Article HTML, FAQ,
Schema, Internal Link Suggestion, CTA.

**T — Tone:** Professional, Educational, Trustworthy, Beginner-friendly.
**Cấm dùng:** "guaranteed returns", "to the moon", "100x guaranteed", "can't lose", "get rich
quick", "financial advice" (dùng "educational information" thay thế), dự đoán giá cụ thể trong
tương lai như một sự thật chắc chắn.

**E — Example:** mỗi bài có Introduction, Main explanation, Examples, Tables, Common mistakes,
FAQ, Next learning step.

---

## PHẦN 5 — CẤU TRÚC BÀI VIẾT

### 5.1. Bitcoin Academy / Exchange Hub (long-form) — giữ nguyên khung FXM680

Golden Snippet 2 câu (45–58 từ, câu đầu chứa primary keyword, link ngữ cảnh về
`https://coin680.com/`) → TOC (`tiny-toc`) → 7 heading bắt buộc:

```
H2: What is [Primary Keyword]? An In-Depth Overview          (min 200 words)
H2: Why Does [Topic] Matter? Key Benefits and Value           (min 200 words)
H2: Detailed Analysis of [Core Components]                    (min 350 words, ≥3 H3, có bảng)
H2: Step-by-Step Guide to [Action/Process]                    (min 250 words, dùng <ol>)
H2: Common Pitfalls to Avoid in [Topic]                        (min 200 words)
H2: Frequently Asked Questions About [Topic]                   (3-5 câu hỏi, mỗi câu ≤60 từ)
H2: Continue Your Bitcoin Learning Journey with Coin680        (60-80 words, dẫn sang bài NEXT LESSON)
```

Quy tắc trình bày giống hệt FXM680 (đã kiểm chứng, không lặp lại lỗi cũ):
- KHÔNG `text-align: justify` ở bất kỳ đâu.
- Dùng class `tiny-*` (Phần 8), không inline `style="..."`.
- Câu ngắn (<20 từ), đoạn `<p>` ≤3 dòng, xen kẽ văn bản/danh sách.
- Định lượng cụ thể, tránh mơ hồ.
- Dùng "%" và "$" thay vì chữ.

### 5.2. Crypto Market News (short-form) — khung riêng, xem chi tiết đầy đủ trong
`Coin680-News-Playbook.md`. Tóm tắt cấu trúc:

```
Đoạn mở (inverted pyramid — tin quan trọng nhất trước): 40-60 từ
H2: What Happened                                    (150-250 từ, sự kiện chính)
H2: Why It Matters                                   (150-250 từ, tác động thị trường/nhà đầu tư)
H2: Market Context                                   (100-200 từ, giá/số liệu liên quan, có nguồn)
H2: What to Watch Next                               (100-150 từ)
(Không bắt buộc TOC — bài ngắn, không cần)
```

Độ dài: 600–1200 từ (ngắn hơn nhiều so với Academy). Schema dùng `NewsArticle` thay vì
`Article/BlogPosting`.

### 5.3. Disclaimer bắt buộc cuối mọi bài (Academy, Exchange Hub, News)

```
"Disclaimer: The content provided on this page is for informational and educational purposes
only and does not constitute financial or investment advice. Cryptocurrency markets are highly
volatile and involve significant risk of loss. Always do your own research and consult a
licensed financial advisor before making any investment decisions."
```

### 5.4. JSON-LD Schema

Academy/Exchange Hub/News đều dùng cùng một mẫu `author`/`publisher` cho tín hiệu E-E-A-T
(xem Phần 12 — bút danh "Mr Whale"):

```json
"author": { "@type": "Person", "name": "Mr Whale", "url": "https://coin680.com/about/" },
"publisher": {
  "@type": "Organization",
  "name": "Coin680",
  "url": "https://coin680.com/",
  "logo": { "@type": "ImageObject", "url": "LOGO URL thật khi có" }
}
```

Academy/Exchange Hub: dùng đúng khung `Article`/`BlogPosting` + `FAQPage` như FXM680.

News: dùng `NewsArticle` (`@type: ["NewsArticle"]`), thêm `datePublished` chính xác giờ đăng
thật (không hẹn giờ xa), không cần `FAQPage` trừ khi bài có mục hỏi-đáp rõ ràng.

**Lưu ý kỹ thuật đã gặp bên FXM680, áp dụng lại ở đây:** một số hosting (WAF) âm thầm chặn lưu
bài nếu JSON-LD pretty-print quá lớn dù response vẫn `200 OK`. Luôn minify JSON-LD trước khi gửi,
và sau khi POST/PUT luôn GET lại để xác nhận `content.raw` khớp nội dung vừa gửi.

---

## PHẦN 6 — TẠO ẢNH (đã xác nhận hoạt động 2026-07-27)

**Model đã test thành công:** `gemini-2.5-flash-image` (KHÔNG dùng `gemini-2.0-*` — key hiện tại
báo lỗi với bản cũ, theo xác nhận của người dùng). API key đã được cung cấp riêng, không lưu giá
trị thật trong file này — hỏi lại người dùng nếu cần dùng ở phiên làm việc khác.

**Quy trình đã kiểm chứng:**
1. Gọi `POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent?key=<API_KEY>`
   với `contents[0].parts[0].text` = mô tả ảnh (theo định hướng phong cách ở dưới).
2. Response trả về `candidates[0].content.parts[]`, tìm phần tử có `inlineData.data` (base64 PNG).
3. Decode base64 → lưu file `.png` local (`[Convert]::FromBase64String` → `WriteAllBytes`).
4. Vì REST API `/wp/v2/media` chưa được test có bị WAF chặn giống nội dung bài dài hay không
   (rủi ro tương tự Phần 11), **dùng cùng đường SSH/WP-CLI đã dùng cho bài viết**: `scp` file ảnh
   lên server, sau đó `wp media import <remote-file> --post_id=<id> --title="..." --alt-text="..."`
   để upload vào Media Library **và** gán luôn làm Featured Image của bài (`--post_id`).
5. Ảnh minh hoạ trong bài (1-2 ảnh): làm tương tự, upload rồi lấy URL trả về
   (`wp media import ... --porcelain` trả về attachment ID, dùng `wp post get <id> --field=guid`
   hoặc `wp media list` để lấy URL) chèn vào đúng vị trí HTML bằng thẻ `<img>` có `alt` mô tả đầy đủ.

**Định hướng phong cách ảnh:** tông đỏ (`#c11510`) điểm nhấn trên nền navy tối (`#111c2d`), phong
cách tài chính/công nghệ hiện đại, không chữ trong ảnh, không logo sàn giao dịch thật (tránh vi
phạm thương hiệu) trừ khi ảnh minh hoạ cho đúng bài review sàn đó và dùng đúng logo chính thức
được phép.

---

## PHẦN 7 — QUY TRÌNH XUẤT BẢN

Một lượt duy nhất: viết HTML hoàn chỉnh + tạo ảnh + đăng qua WordPress REST API, giống quy trình
đã chạy ổn định bên FXM680. Không cần bước trung gian dán qua Gemini web.

---

## PHẦN 8 — CSS GLOBAL (dán 1 lần vào Appearance → Customize → Additional CSS)

Vì bảng màu Coin680 giống hệt FXM680 (đỏ `#c11510` / navy `#111c2d` / kem `#fffbf2`), **copy
nguyên khối CSS Global từ `FXM680-Master-Content-Prompt.md` Phần 8 và dán thẳng, không cần đổi màu
gì cả.** Chỉ cần đổi phần chữ/link thương hiệu xuất hiện trong nội dung bài viết (không phải trong
CSS) từ "FXM680"/"fxm680.com" sang "Coin680"/"coin680.com" khi viết bài — CSS thì dùng chung y hệt.

Toàn bộ 4 khối CTA (`tiny-cta-multi`, `tiny-cta-category`, `tiny-product-best`, `tiny-cross-sell`)
giữ nguyên vai trò như FXM680.

---

## PHẦN 9 — CHECKLIST TRƯỚC KHI XUẤT BẢN

- [ ] Đã dán CSS Global (Phần 8, màu Coin680) vào Additional CSS chưa (chỉ cần 1 lần)
- [ ] Đúng ID roadmap tiếp theo (đã kiểm tra bài thật trên web, không đoán)
- [ ] Không có `text-align: justify` ở bất kỳ đâu
- [ ] Không còn chữ "FXM680"/link fxm680.com nào sót lại (copy nhầm từ template gốc)
- [ ] HTML dùng class `tiny-*`, không inline `style="..."`
- [ ] Internal link chỉ trỏ tới bài đã tồn tại
- [ ] Có Featured Image + 1-2 ảnh minh hoạ đã upload + gán đúng
- [ ] Disclaimer crypto đúng bản ở Phần 5.3
- [ ] JSON-LD đúng loại (Article/BlogPosting cho Academy/Hub, NewsArticle cho News), đúng domain
- [ ] Không dùng từ cấm (to the moon, guaranteed returns...)
- [ ] Riêng bài News: mọi số liệu/sự kiện có nguồn thật từ tìm kiếm, không bịa

---

## PHẦN 10 — EXCHANGE HUB (MOFU/BOFU, có affiliate)

Xem chi tiết đầy đủ trong **`Coin680-Exchange-Hub-Roadmap.md`** (18 bài Exchange Comparison Hub +
6 Exchange Hub × 12 bài: Binance, Bybit, OKX, BingX, Gate, MEXC). Tóm tắt quy tắc:

- **CÓ affiliate**, dùng đúng Pretty Link (`coin680.com/<slug>`) của từng sàn — **không** dán thẳng
  link ref gốc dài trong bài, luôn dùng link rút gọn qua Pretty Links plugin (xem bảng dưới), đúng
  cách FXM680 đã làm. Nếu cần sửa link đích sau này, chỉ sửa 1 chỗ trong Pretty Links, không phải
  sửa lại từng bài đã đăng.
- Bài MOFU: `tiny-cta-category` hoặc `tiny-product-best` — khách quan.
- Bài BOFU: `tiny-cta-multi` hoặc `tiny-cross-sell` — CTA rõ ("Open Account", "Claim Bonus").
- Áp dụng nguyên tắc 3 điểm chạm CTA (giống FXM680 Phần 10.7): CTA1 (~20% đầu, khơi vấn đề) →
  CTA2 (giữa bài, sau bảng phí/số liệu) → CTA3 (cuối bài, chốt CTA).
- Luôn link ngược 1-2 bài Bitcoin Academy liên quan + link chéo Exchange Comparison Hub.
- Tuân thủ Tone cấm dù có affiliate. Không cam kết lợi nhuận từ trading trên sàn.

**Bảng affiliate link — đã cấu hình qua Pretty Links plugin (xác nhận 2026-07-28):**

| Sàn | Link dùng trong bài (Pretty Link) | Trạng thái |
|---|---|---|
| Binance | `https://coin680.com/Binance` | ✅ Đã có |
| Bybit | `https://coin680.com/Bybit` | ✅ Đã có (bổ sung 2026-07-28) |
| OKX | `https://coin680.com/okx` | ✅ Đã có |
| BingX | `https://coin680.com/bingx` | ✅ Đã có |
| Gate | `https://coin680.com/Gate` | ✅ Đã có |
| MEXC | `https://coin680.com/Mexc` | ✅ Đã có |

Cả 6 sàn đã có Pretty Link đầy đủ. Mọi bài Exchange Hub viết từ nay dùng đúng các Pretty Link ở
trên làm href cho nút CTA ("Open Account"/"Claim Bonus"), không dùng link ref gốc trực tiếp.

**Danh mục WordPress (đã tạo, xác nhận 2026-07-27):** Crypto Market News = 2 (con: Bitcoin News=3,
Market & Analysis=4, Business & Institutions=5, Regulation & Policy=6), Bitcoin Academy = 7
(Fundamentals=8, ...), Exchange Comparison = 18, Exchange Reviews = 19 (con: Binance=20, Bybit=21,
OKX=22, BingX=23, Gate=24, MEXC=25).

---

## PHẦN 11 — QUY TRÌNH ĐĂNG BÀI THỰC TẾ (đã đổi so với FXM680: SSH/WP-CLI, không dùng REST API)

**Phát hiện quan trọng (2026-07-27, qua bài test BTA-001):** khác với FXM680 (đăng qua WordPress
REST API `/wp-json/`), site coin680.com có một lớp bảo mật (WAF, khả năng cao ở tầng Hostinger)
**âm thầm chặn/cắt nội dung khi POST/PUT qua REST API** một khi bài vượt khoảng 12-13KB — đúng
ngưỡng của mọi bài Academy/Exchange Hub thật (2500-3500 từ). Request vẫn trả về 200/201, nhưng
`content` bị lưu rỗng hoặc cắt cụt. Đã xác nhận qua wp-admin (trình soạn thảo thường) đăng bài
15KB+ vẫn bình thường — nên chỉ riêng đường REST API bị chặn, không phải giới hạn PHP/hosting
chung. Đã thử tắt/nới WAF qua hPanel nhưng chưa dứt điểm.

**Giải pháp đang dùng: SSH + WP-CLI**, hoàn toàn không đi qua `/wp-json/` nên né được giới hạn trên.

```
SSH Host: 145.79.28.138
SSH Port: 65002
SSH Username: u185868899
Auth: SSH key riêng (ed25519, không mật khẩu) đã thêm vào Hostinger hPanel SSH Access
WordPress path: /home/u185868899/domains/coin680.com/public_html
```

**Quy trình đăng 1 bài:**
1. Viết nội dung HTML đầy đủ (đúng Phần 5) ra 1 file local (vd `scratchpad/BTA-0xx-content.html`).
2. `scp` file đó lên server (vd `/home/u185868899/bta-0xx-content.html`).
3. `wp --path=<wp-path> post create <remote-file> --post_title="..." --post_name=<slug> --post_status=publish --post_category=<id1,id2> --porcelain` → trả về post ID.
4. Verify: `wp post get <id> --field=post_content | wc -c` phải khớp đúng byte-size file gốc (kiểm
   tra bằng `scp` xong so `wc -c` trên server với `(Get-Item file).Length` ở local) — không được
   suy luận từ độ dài ký tự (character length) một mình vì UTF-8 có ký tự nhiều byte (em-dash...).
5. Xoá file tạm trên server sau khi xong (`rm`).
6. Gắn category kép nếu là bài News/Exchange (xem lưu ý dual-tag ở Phần 10.5 tương đương — child +
   parent category để trang chủ hiển thị đúng).

**Lỗi đã gặp và cách tránh:**
- **Không truyền lệnh `wp` có chứa dấu nháy đơn `'` (như "Beginner's") trực tiếp qua
  `ssh ... "wp post update ... --post_title='...'"` từ PowerShell** — việc PowerShell chuyển tiếp
  chuỗi có quote lồng nhau cho `ssh.exe` (một native exe) hay bị lỗi mất/nhân đôi dấu nháy, khiến
  ký tự `'` trong title bị rớt mất. **Luôn viết lệnh ra 1 file `.sh`, `scp` lên rồi `ssh ... bash
  file.sh`** thay vì nhét lệnh dài có quote trực tiếp vào tham số `ssh`.
- **Không dùng `ConvertTo-Json -Depth N` (N≥3, hoặc thậm chí mặc định) khi object có cả field
  string dài lẫn field khác (array, nhiều key)** — đã từng thấy JSON output phóng đại từ ~18KB lên
  461KB trong 1 lần test, gây lỗi gửi. Nếu còn dùng REST API cho việc gì đó (vd category/page/menu,
  vẫn hoạt động bình thường vì các payload này nhỏ), giữ nguyên cách gọi `ConvertTo-Json` không
  kèm `-Depth` cho object phẳng.
- Category/Page/Menu vẫn tạo qua REST API bình thường (payload nhỏ, không chạm ngưỡng WAF) — **chỉ
  riêng nội dung bài dài (post content) mới cần chuyển sang SSH/WP-CLI.**

---

## PHẦN 12 — TÍN HIỆU E-E-A-T: BÚT DANH "MR WHALE" (chốt 2026-07-28)

Coin680 đứng tên nội dung dưới bút danh **Mr Whale** thay vì chỉ đứng tên tổ chức "Coin680" —
đây là thông tin thật do chủ site cung cấp (tham gia thị trường crypto từ 2020), **không phải
credential bịa ra**. Tuyệt đối không tự thêm chức danh/bằng cấp/kinh nghiệm nào khác ngoài chi
tiết này trừ khi chủ site cung cấp thêm.

**Nơi bút danh xuất hiện:**
- Byline trên mọi bài viết (`single.php`): "By Mr Whale" ngay dưới tiêu đề, link tới `/about/`.
- Author box cuối mỗi bài (`coin680_author_box()` trong `functions.php`): tên + 1 câu bio + link
  `/about/`.
- Trang **About Coin680** (ID 200): mục "Meet Mr Whale" giới thiệu là Founder & Content Lead.
- Trang **Editorial Policy** (ID 199): câu mở đầu ghi rõ Mr Whale phụ trách tiêu chuẩn biên tập.
- JSON-LD mọi bài (xem mẫu ở Phần 5.4): `author` = `Person` "Mr Whale" (`url` trỏ `/about/`),
  `publisher` vẫn giữ `Organization` "Coin680" — đúng chuẩn schema.org tách author cá nhân khỏi
  publisher tổ chức.

**Tên hiển thị tài khoản WordPress** (user đăng bài, id 1) đã đổi `display_name` → "Mr Whale" qua
`wp user update 1 --display_name="Mr Whale"` để tên tác giả WP khớp bút danh nếu theme/plugin nào
đó tự lấy `the_author()`.

**Việc còn để ngỏ (chưa làm, không bắt buộc):** ~31 bài Academy/News đã xuất bản trước ngày
2026-07-28 vẫn còn JSON-LD `author` = Organization "Coin680" cũ. Chưa retrofit lại vì phải sửa
từng bài (JSON-LD nhúng thẳng trong nội dung, không phải field riêng) — có thể làm dần nếu chủ
site muốn đồng bộ toàn bộ, nhưng mọi bài viết **từ nay trở đi** phải dùng mẫu Person "Mr Whale".

---

## VIỆC CẦN BẠN QUYẾT ĐỊNH / CUNG CẤP TRƯỚC KHI BẮT ĐẦU VIẾT BÀI THẬT

1. ~~Xác nhận bảng màu~~ — đã chốt 2026-07-27: dùng đúng bảng màu FXM680 (đỏ/navy/kem).
2. Domain + WordPress đã cài xong chưa, và API credentials để tôi đăng bài.
3. Link affiliate thật cho 6 sàn (bảng ở Phần 10) — có thể bổ sung dần, không cần đủ ngay từ đầu.
4. Logo thật (URL sau khi upload) để điền vào schema.

**Cấu trúc site, menu, danh mục WordPress và danh sách plugin cần cài:** xem file riêng
`Coin680-Site-Structure-and-Setup.md`.
