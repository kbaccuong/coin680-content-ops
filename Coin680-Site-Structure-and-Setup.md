# Coin680 — Cấu trúc Website, Menu, Danh mục & Checklist Plugin WordPress

Mục tiêu: site dạng tin tức crypto giống bố cục CoinDesk (breaking news, market data, chuyên mục
rõ ràng), nhưng giữ nguyên bảng màu FXM680 (đỏ `#c11510` / navy `#111c2d` / kem `#fffbf2`) và toàn
bộ hệ thống nội dung đã lên kế hoạch (Bitcoin Academy, Crypto Market News, Exchange Hub).

> **Bài học từ FXM680 cần tránh lặp lại:** site forex hiện đang bị lệch giữa menu thật (theme demo
> cũ để lại: Top Stories, Report, Events...) và kiến trúc nội dung dự định (Forex Academy, Broker
> Hub...) — 2 khối "Insight"/"Make Money" trên trang chủ vẫn đang gắn cứng vào category cũ của
> theme demo, sửa không dễ. Với Coin680, nên **dựng menu + category đúng kiến trúc ngay từ đầu**,
> không dùng nguyên site mẫu (demo import) rồi chỉnh sau.

---

## 1. Cấu trúc Menu (thanh điều hướng chính)

```
[Logo Coin680]   Home | News ▾ | Learn ▾ | Exchanges ▾ | About ▾        [🔍 Search] [BTC $xxx ▲]
```

- **Home** — trang chủ dạng tin tức (xem bố cục Mục 3).
- **News ▾** (Crypto Market News) — dropdown:
  - Bitcoin News
  - Market & Analysis
  - Business & Institutions
  - Regulation & Policy
- **Learn ▾** (Bitcoin Academy) — dropdown, gom 10 nhóm chủ đề roadmap thành các mục điều hướng
  gọn hơn (không cần hiện đủ 10, gộp còn 5-6 mục cho menu đỡ rối):
  - Bitcoin Basics (Fundamentals + How It Works)
  - History & Market Cycles
  - Wallets & Security
  - Buying, Trading & Investing
  - Market Analysis & Economics
  - Regulation, Risk & Beyond Bitcoin
- **Exchanges ▾** (Exchange Hub) — dropdown:
  - Compare Exchanges (Exchange Comparison Hub)
  - Binance · Bybit · OKX · BingX · Gate · MEXC
- **About ▾** — dropdown (trang trust/E-E-A-T, bắt buộc phải có để đạt chuẩn Google News/Discover
  và tăng độ tin cậy):
  - About Coin680
  - Editorial Policy (quy trình biên tập, nguồn tin, tiêu chuẩn kiểm chứng)
  - Advertising Disclosure (công khai có affiliate ở Exchange Hub — bắt buộc về pháp lý/FTC)
  - Contact
  - Privacy Policy
  - Risk Disclaimer

**Widget góc phải header (giống CoinDesk):** 1 ticker giá BTC/ETH real-time nhỏ — tăng cảm giác
"site tin tức sống", xem plugin đề xuất ở Mục 4.

---

## 2. Danh mục WordPress (Categories)

Đặt tên khớp menu, để phần "Insight"/khối trang chủ nào cần gắn category thì gắn đúng ngay từ đầu:

| Category (cha) | Category con | Dùng cho |
|---|---|---|
| **Crypto Market News** | Bitcoin News | Tin BTC-specific |
| | Market & Analysis | Tin biến động thị trường, chỉ số |
| | Business & Institutions | Tin doanh nghiệp, ETF, tổ chức |
| | Regulation & Policy | Tin pháp lý, chính sách |
| **Bitcoin Academy** | Fundamentals | BTA-001–040 |
| | How It Works | BTA-041–080 |
| | History & Cycles | BTA-081–120 |
| | Wallets & Security | BTA-121–160 |
| | Buying & Trading | BTA-161–200 |
| | Market Analysis | BTA-201–240 |
| | Economics & Macro | BTA-241–280 |
| | Regulation & Tax | BTA-281–320 |
| | Risk & Psychology | BTA-321–360 |
| | Broader Crypto Market | BTA-361–400 |
| **Exchange Comparison** | *(không có con)* | EC-001–018 |
| **Exchange Reviews** | Binance / Bybit / OKX / BingX / Gate / MEXC | Từng Hub 12 bài |

Ghi chú: category con của Bitcoin Academy chỉ để lọc/điều hướng nội bộ (breadcrumb, sidebar "bài
liên quan"), **không cần hiện hết trong menu chính** — menu chỉ hiện bản gộp 5-6 mục ở Mục 1 để
tránh rối, nhưng category thật trong WordPress vẫn nên tách đủ chi tiết để SEO/URL sạch
(`/bitcoin-academy/wallets-security/...`).

---

## 3. Gợi ý bố cục trang chủ (kiểu CoinDesk, dùng đúng bảng màu FXM680)

```
┌─────────────────────────────────────────────────────────┐
│ Breaking/Ticker bar (đỏ #c11510): 1 dòng tin mới nhất     │
├─────────────────────────────────────────────────────────┤
│ HERO: 1 tin nổi bật lớn (ảnh full-width) + 4 tin phụ nhỏ  │
├─────────────────────────────────────────────────────────┤
│ Market Data strip: giá BTC/ETH + vài coin lớn, % 24h      │
├───────────────────────────┬───────────────────────────────┤
│ Cột chính: Latest News    │ Sidebar: Trending / Most Read  │
│ (danh sách bài News mới)  │ + widget "BTC Price Today"     │
├───────────────────────────┴───────────────────────────────┤
│ Section: "Learn Bitcoin" — 3-4 bài Academy nổi bật          │
├─────────────────────────────────────────────────────────┤
│ Section: "Compare Exchanges" — card 6 sàn (Binance...MEXC)  │
├─────────────────────────────────────────────────────────┤
│ Footer: menu phụ + About/Editorial/Disclosure/Privacy       │
└─────────────────────────────────────────────────────────┘
```

Vai trò từng khối đúng với funnel đã thiết kế: Hero + Latest News (traffic thời sự) → Learn Bitcoin
(kéo sang TOFU evergreen) → Compare Exchanges (kéo sang MOFU/BOFU affiliate) — đúng nguyên tắc
"mũi tên tàng hình" đã áp dụng bên FXM680.

---

## 4. Danh sách Plugin WordPress cần cài

### Bắt buộc (nền tảng)

| Plugin | Vai trò |
|---|---|
| **Rank Math SEO** (hoặc Yoast SEO) | Meta title/description, sitemap XML, **News Sitemap** module (bắt buộc nếu muốn vào Google News), breadcrumb. ⚠️ Tắt phần schema tự động của plugin cho các bài Academy/Hub nếu đã tự chèn JSON-LD trong content để tránh trùng schema. |
| **LiteSpeed Cache** (nếu host dùng LiteSpeed, vd Hostinger) hoặc **WP Rocket** | Cache + tối ưu tốc độ tải — quan trọng cho Core Web Vitals, ảnh hưởng trực tiếp tới xếp hạng Google Discover/News. |
| **ShortPixel** hoặc **Smush** | Nén ảnh + chuyển WebP tự động — cần thiết vì mỗi bài đều có Featured Image + ảnh minh hoạ (490+ bài). |
| **UpdraftPlus** | Backup tự động — bắt buộc trước khi đăng hàng loạt bài qua API, phòng lỗi ghi đè/mất dữ liệu. |
| **Pretty Links** | Quản lý link affiliate 6 sàn dạng `coin680.com/binance` thay vì link ref dài — giống cách FXM680 đang dùng, sửa link 1 chỗ áp dụng toàn site. |
| **Wordfence Security** (hoặc bản nhẹ hơn: **All In One WP Security**) | Firewall, chặn brute-force login — cần thiết khi site có API mở để đăng bài tự động. |
| **Site Kit by Google** | Kết nối nhanh Google Analytics 4 + Search Console (+ AdSense nếu sau này muốn chạy quảng cáo hiển thị, phổ biến với site tin tức crypto). |
| **Redirection** | Quản lý 301 redirect khi đổi slug/xoá bài — tránh lỗi 404 tích luỹ như từng gặp bên FXM680 (bài bị xoá/link nội bộ hỏng). |

### Nên cài (nâng cao trải nghiệm, đúng phong cách CoinDesk)

| Plugin | Vai trò |
|---|---|
| **Widget giá crypto real-time** (vd "Coinlib Cryptocurrency Widgets", "CoinMarketCap widget" nhúng, hoặc gọi thẳng CoinGecko API qua block HTML) | Ticker giá BTC/ETH ở header + widget "Market Data" trang chủ — yếu tố hình ảnh đặc trưng của CoinDesk. |
| **TablePress** | Dựng bảng so sánh phí/spec sàn giao dịch đẹp hơn bảng HTML thuần, dễ sửa số liệu khi phí sàn thay đổi mà không phải sửa lại từng bài. |
| **WPForms Lite** (hoặc Contact Form 7) | Form trang Contact. |
| **Complianz** hoặc **CookieYes** | Banner cookie consent (GDPR) — cần nếu có traffic từ EU và dùng Google Analytics/AdSense. |
| **WP Mail SMTP** | Đảm bảo email từ site (form liên hệ, thông báo) gửi được, không rơi vào spam. |

### Không cần (tránh cài thừa gây nặng site)

- Không cần plugin "Related Posts" tự động — internal link đã được kiểm soát thủ công theo roadmap.
- Không cần Elementor Pro nếu chỉ dùng để dán HTML/CSS như FXM680 đang làm — theme + block Gutenberg
  cơ bản là đủ, tránh nặng trang.
- Cân nhắc kỹ trước khi cài AMP — làm phức tạp việc quản lý CSS `tiny-*` tuỳ chỉnh, lợi ích cho
  Discover hiện không còn lớn như trước; bỏ qua trừ khi có lý do cụ thể.

---

## 5. Theme đề xuất

Tránh lặp lại vấn đề FXM680 (theme demo áp sẵn category/menu không khớp kiến trúc dự định). Đề xuất
2 hướng:

1. **GeneratePress + GenerateBlocks** (nhẹ, nhanh, không có "demo content" áp đặt cấu trúc) — tự
   dựng homepage theo đúng bố cục Mục 3 bằng block, kiểm soát hoàn toàn menu/category ngay từ đầu.
   Phù hợp nếu ưu tiên tốc độ tải (quan trọng cho News/Discover) và tránh lệch kiến trúc như FXM680.
2. **Theme News/Magazine chuyên dụng** (vd Newspaper - tagDiv, hoặc Soledad) — có sẵn layout kiểu
   CoinDesk (breaking bar, ticker, grid tin tức) dựng nhanh hơn, nhưng **phải tự tạo lại toàn bộ
   menu + category theo đúng bảng ở Mục 1-2**, không import nguyên site demo của theme — đây chính
   là điều đã gây lệch bên FXM680.

Khuyến nghị: nếu ưu tiên tốc độ triển khai và không ngại tự dựng thủ công → GeneratePress. Nếu
muốn có ngay giao diện "giống CoinDesk" trực quan mà không cần tự thiết kế nhiều → theme News
chuyên dụng, nhưng dựng menu/category thủ công đúng theo file này.

---

## 6. Việc cần bạn quyết định

1. Chọn hướng theme: GeneratePress (tự dựng) hay theme News chuyên dụng (dựng nhanh, cần kỷ luật
   không dùng demo content)?
2. Widget giá crypto real-time: dùng plugin có sẵn (thường miễn phí, có thể có logo/link ngược về
   nhà cung cấp widget) hay muốn tôi viết đoạn gọi CoinGecko API riêng (miễn phí, không giới hạn
   bởi plugin, nhưng cần chèn code) khi tới lúc build?
3. Có cần trang "Advertising Disclosure" công khai affiliate ngay từ đầu không — khuyến nghị có,
   vì tăng trust và là yêu cầu phổ biến ở nhiều thị trường khi có affiliate link.
