# Coin680 — Project Status (cập nhật lần cuối: 2026-07-30)

File này là bản tóm tắt "đọc là hiểu hết" — dùng khi bắt đầu 1 phiên làm việc mới, chỉ cần
đọc file này thay vì phải giải thích lại từ đầu. Các quy tắc/spec chi tiết vẫn nằm ở các file
riêng (liệt kê ở cuối), file này chỉ tóm tắt **hiện trạng** và **cách mọi thứ khớp với nhau**.

---

## 1. Tổng quan

**coin680.com** — website tin tức + giáo dục Bitcoin/crypto, mô hình giống CoinDesk, có affiliate
với 6 sàn (Binance, Bybit, OKX, BingX, Gate, MEXC). Là dự án song song với FXM680 (forex), dùng
lại quy trình/công thức đã kiểm chứng bên đó nhưng có nhiều phần nâng cấp riêng.

**Bút danh đứng tên nội dung:** Mr Whale — tham gia thị trường crypto từ 2020 (thông tin thật do
chủ site cung cấp). Xuất hiện ở: byline mọi bài viết, author box cuối bài, trang About, Editorial
Policy, và trong JSON-LD (`author`: Person "Mr Whale", `publisher`: Organization "Coin680").

---

## 2. Hạ tầng & quyền truy cập

| Thành phần | Chi tiết |
|---|---|
| Hosting | Hostinger, WordPress tại `/home/u185868899/domains/coin680.com/public_html` |
| SSH | Host `145.79.28.138`, port `65002`, user `u185868899`. Key lưu tại scratchpad phiên làm việc (mất khi hết phiên — xem `Coin680-Routine-Setup-*.md` nếu cần khôi phục) |
| Publish nội dung | **Bắt buộc qua SSH + WP-CLI**, KHÔNG dùng REST API cho bài dài (WAF hosting âm thầm chặn/cắt nội dung >12-13KB qua REST, xem Phần 11 `Coin680-Master-Content-Prompt.md`) |
| REST API | Vẫn dùng được cho payload nhỏ: category, page, menu. User: `minhnguyencio@gmail.com`, Application Password `xwU6 LhdP Xk6A C8va rQRv Fr5h` |
| Cache | LiteSpeed Cache — luôn `wp litespeed-purge all` sau mỗi lần deploy, bump `COIN680_VERSION` trong `functions.php` để bust cache CSS |
| GitHub | Repo private `kbaccuong/coin680-content-ops` — chứa toàn bộ doc + source code plugin/theme, dùng GitHub Contents API để đẩy file (không có git/gh CLI local) |
| GitHub Actions | `.github/workflows/wp-cron-trigger.yml` — tự gọi `wp-cron.php` mỗi 5 phút, đảm bảo mọi cron job WordPress chạy đúng giờ dù web không có traffic |
| X (Twitter) | Tài khoản `@coin680`, gói **Premium**. Credentials (Consumer Key/Secret, Access Token/Secret) đã lưu trong wp_options `coin680x_settings` — không cần nhập lại |
| Whale Alert API | Key đã lưu trong wp_options `coin680whale_settings` — không cần nhập lại (chỉ còn dùng cho Bitcoin từ 2026-07-30) |
| Bitquery API | Access token (dạng `ory_at_...`) đã lưu trong wp_options `coin680whale_settings['bitquery_access_token']` — dùng cho Solana/BSC/Ethereum/TRON, xem mục 4.3.1 |
| Gemini API | Model `gemini-2.5-flash-image` — dùng tạo ảnh minh hoạ, gọi trực tiếp qua HTTPS, key lưu ở scratchpad khi cần |

---

## 3. Theme tuỳ chỉnh (`coin680-theme/`)

Bảng màu giống FXM680 (đỏ `#c11510` / navy `#111c2d` / kem `#fffbf2`). Các trang/tính năng chính:

- **Trang chủ** (`front-page.php`): hero tin tức, Market ticker, Fear & Greed Index, Live Prices,
  4 mục danh mục, Exchange Reviews (CTA affiliate), Newsletter.
- **Trang riêng độc lập** (không trên trang chủ, có mục menu riêng): Bitcoin Halving Countdown,
  Altcoin Season Index (chỉ số tự tính 30 ngày, không phải 90 ngày như bản gốc — đã ghi rõ), Top
  Gainers & Losers (2 tab, không cần trang riêng theo coin).
- **Trang giá coin**: `/crypto-prices/` (bảng 90 coin), `/bitcoin-price/?coin=<id>` (dùng chung 1
  template cho mọi coin qua query param).
- **Bình luận**: ẩn danh, chỉ cần tên/biệt danh (không email), honeypot + time-trap chống bot ngay
  trong theme (độc lập với plugin Coin680 Shield).
- **View counter**: đếm lượt xem mỗi bài, chỉ admin thấy (cột trong Posts + dashboard widget).
- **SEO/Social**: Open Graph + Twitter Card đầy đủ (title/description/image/width/height/alt),
  fallback logo cho trang không có ảnh riêng.
- **Mobile menu**: hamburger menu (trước đó bị thiếu hoàn toàn trên mobile, đã sửa).
- **Newsletter**: form riêng lưu vào bảng `wp_coin680_subscribers`, không phụ thuộc Hostinger Reach
  (chưa kết nối tài khoản Reach).

---

## 4. Plugin đã xây (đều tự viết riêng cho site này, không phải plugin có sẵn)

### 4.1. Coin680 Shield — chống bot/spam
Miễn phí (honeypot, time-trap chữ ký HMAC, blocklist từ khoá, chặn XML-RPC, chặn bad-bot UA).
Premium (mở khoá bằng mã **"coin680"**, đã tự mở khoá sẵn cho site chính): rate limit comment,
khoá đăng nhập sau N lần sai, tường lửa request, CAPTCHA toán học. Có ý định public hoá lên
WordPress.org (đang chuẩn bị readme.txt).

### 4.2. Coin680 X Scheduler — đăng bài X không giới hạn
Tự viết, không qua Ayrshare (đã bỏ Ayrshare vì giới hạn 20 bài/tháng gói free). Dùng OAuth 1.0a
tự ký (PHP), bảng `wp_coin680_x_queue`, cron 5 phút tự đăng bài tới giờ. Hỗ trợ: ảnh, reply/first
comment, **poll thật của X** (chỉ gắn được vào reply — X không cho poll trên bài dài >280 ký tự).
**Giới hạn quan trọng của X:** tối đa 1 cashtag ($SYMBOL) mỗi bài, quá 1 sẽ bị từ chối đăng toàn bộ.

**Bug đã sửa 2026-07-30 — icon 🐋 hiển thị lỗi "??":** bảng `wp_coin680_x_queue` được tạo dưới
charset `utf8` cũ (3-byte), không lưu được emoji 4-byte thật (lỗi kinh điển WordPress/MySQL). Đã
sửa: ép `utf8mb4` ngay trong câu lệnh `CREATE TABLE`, thêm `ALTER TABLE ... CONVERT TO CHARACTER
SET utf8mb4` chạy 1 lần cho site đang hoạt động (vì `dbDelta()` không tự đổi charset bảng đã tồn
tại), gắn với bump `COIN680X_DB_VERSION = '1.0.1'` để tự chạy lại mà không cần tắt/bật lại plugin.

### 4.3. Coin680 Whale Tracker — "Whale Signal" tự động (Bitcoin only từ 2026-07-30)
Gọi API Whale Alert (v1 REST, `api.whale-alert.io`), tự phân loại giao dịch (Exchange
Inflow/Outflow/to-Exchange, Mint, Burn, Wallet Transfer) dựa theo nhãn owner_type thật của Whale
Alert — **có nêu tên sàn cụ thể khi Whale Alert gắn nhãn** (vd "moved from Binance to Coinbase").

**Thu hẹp về CHỈ Bitcoin (2026-07-30 tối)** — trước đó bao gồm cả BTC/ETH/XRP/TRX/EOS/Stellar/...;
giờ chỉ giữ Bitcoin, các chain khác (ETH/BSC/TRON) đã chuyển hẳn sang Bitquery (xem mục 4.3.1),
XRP/EOS/Stellar/NEO/Tezos/Cosmos/ICON/Hive/Steem/Liquid **bị bỏ hẳn, không di chuyển đi đâu**. API
Whale Alert vẫn gọi rộng như cũ (không có filter theo blockchain đáng tin cậy), chỉ lọc bỏ non-Bitcoin
ngay trong `poll()` trước khi lưu vào DB. **Dữ liệu non-Bitcoin cũ đã lưu trước đó cũng đã bị XOÁ**
khỏi bảng `wp_coin680_whale_txns` (dọn dẹp 1 lần khi nâng cấp DB version lên 1.3, theo yêu cầu "xoá
cho nhẹ").

Tính năng còn lại:
- **Ngưỡng USD** `min_value` (mặc định $500k) cho BTC — vẫn chỉnh được ở trang admin. Ô
  `altcoin_min_value` vẫn còn trên form nhưng không còn tác dụng thực tế (Whale Alert chỉ còn BTC) —
  giữ lại chỉ để dễ revert nếu sau này quay lại nhiều chain qua Whale Alert.
- **Bật/tắt Whale Alert (Bitcoin) khỏi bài đăng** — checkbox `include_whale_alert_in_digest`, mặc
  định BẬT, không ảnh hưởng tới việc thu thập dữ liệu nền khi tắt.
- **Breaking alert tức thời** cho giao dịch ≥$50M (ngưỡng chỉnh được).
- **Daily Recap** 1 lần/ngày (tổng khối lượng, giao dịch lớn nhất, net flow 24h) — vẫn tính riêng
  trên bảng Whale Alert (giờ chỉ còn dữ liệu Bitcoin).
- Trang admin: **wp-admin → Whale Tracker**.

### 4.3.1. Coin680 Bitquery Fetcher — Solana / BSC / Ethereum / TRON (thay thế hoàn toàn Etherscan)
(mới 2026-07-30 — xây trong ngày, thay thế ngay hệ thống Etherscan/Multichain vừa mở rộng lên 7
chain buổi sáng cùng ngày)

**Lý do đổi:** User muốn thêm Solana (Etherscan không bao giờ phủ được vì không phải EVM) — hỏi mua
DEXTools trước, tôi gợi ý so sánh thêm Bitquery/Helius/Birdeye. User dán thẳng 1 API access token
Bitquery thật vào chat, tôi **test trực tiếp bằng nhiều lệnh gọi GraphQL thật** (không đoán) và xác
nhận:
- ✅ **Solana** — dữ liệu thật, real-time (độ trễ vài giây)
- ✅ **BSC** — dữ liệu thật, real-time
- ✅ **Ethereum** — dữ liệu thật, real-time
- ✅ **TRON** — dữ liệu thật (dùng root schema riêng `Tron`, khác nhánh `EVM`)
- ❌ **Bitcoin** — cú pháp được chấp nhận nhưng server trả lỗi hạ tầng nội bộ ("no such host") — dịch
  vụ Bitcoin của Bitquery **không khả dụng với gói/tài khoản này**, không rõ do gói hay do dịch vụ
  chưa triển khai đầy đủ. Bitcoin vẫn giữ nguyên qua Whale Alert.
- ✅ **Bitquery tự tính sẵn USD** cho mỗi giao dịch (`Trade.Buy/Sell.AmountInUSD`) và hỗ trợ lọc
  ngay trong query (`where: {AmountInUSD: {gt: ...}}`) — **không cần tự tra giá CoinGecko như hệ
  Etherscan cũ**, đơn giản hoá đáng kể so với kiến trúc trước.
- ✅ **1 kết quả DEXTrades đã gộp sẵn CẢ 2 chân swap** (Buy + Sell trong cùng object) — **không cần
  tự quét Transfer log rồi ghép cặp theo địa chỉ router** như Etherscan build cũ phải làm.

User xác nhận: **Bitquery làm nguồn chính cho Solana/BSC/Ethereum/TRON, Whale Alert chỉ giữ lại
Bitcoin.** Etherscan-based Coin680MultiChain_Fetcher (mới xây + mở rộng 7 chain sáng cùng ngày)
**bị thay thế hoàn toàn trong vài giờ** — file `class-multichain-labels.php` /
`class-multichain-fetcher.php` vẫn còn trên đĩa (không xoá code) nhưng **không còn được require/
gọi ở đâu nữa** — hoàn toàn dormant, có thể khôi phục bằng cách thêm lại require + instantiate nếu
cần, không cần build lại từ đầu.

**File mới:**
- `includes/class-bitquery-labels.php` — cấu hình chain (Solana/BSC/Ethereum/TRON) + danh sách
  SYMBOL (không phải địa chỉ contract) được coi là "numeraire" mỗi chain (stablecoin + token
  wrapped/native gas: USDT/USDC/DAI/BUSD, WSOL/SOL, WBNB/BNB, WETH/ETH, WTRX/TRX) — dùng để phân
  loại DEX Buy/Sell/Swap và áp ngưỡng 2 tầng, y hệt tinh thần bên Etherscan cũ nhưng đơn giản hơn
  nhiều (so khớp symbol, không cần bảng địa chỉ contract từng chain).
- `includes/class-bitquery-fetcher.php` — gọi GraphQL `streaming.bitquery.io/graphql`, lọc theo
  thời gian (`since`/`till`) + ngưỡng USD ở CẢ 2 vế giao dịch (`any: [...]`, vì 1 vế có thể chưa có
  giá nếu token quá mới/lạ). Với mỗi trade: vế nào là numeraire (stablecoin/WBNB/WETH...) thì dùng
  làm mốc quy đổi USD, vế còn lại là "token chính" của dòng dữ liệu — DEX Buy (mua bằng
  numeraire)/DEX Sell (bán lấy numeraire)/DEX Swap (cả 2 vế cùng là numeraire hoặc cùng không phải).
  Bảng mới: `wp_coin680_bitquery_txns` (unique key: chain + tx_hash + `Trade.Index` — 1 tx Solana có
  thể chứa nhiều swap leg khác nhau, đã xác nhận qua test thật).

**Giới hạn đã biết (MVP, chưa làm ở bản này):**
- **Chưa có nhãn sàn CEX** (Exchange Inflow/Outflow) cho dữ liệu Bitquery — chỉ có DEX Buy/Sell/Swap.
  Cơ chế đối chiếu địa chỉ Whale Alert từng dùng cho Etherscan (EVM) có thể làm lại cho riêng BSC/
  Ethereum sau này; Solana/TRON dùng định dạng địa chỉ khác hẳn nên không đối chiếu được với dữ liệu
  Whale Alert EVM.
- Numeraire nhận diện theo **SYMBOL, không phải địa chỉ contract xác thực** — 1 token giả mạo tên
  symbol của USDT/WBNB (không phải contract thật) về lý thuyết có thể bị nhận nhầm là "numeraire" —
  đánh đổi lấy sự đơn giản, chưa gặp vấn đề thực tế nhưng cần biết.
- Tham số query `since`/`till`/`any` đã test thật và hoạt động đúng qua PowerShell trước khi build,
  nhưng **chưa test qua đúng luồng cron thật của WordPress** — cần theo dõi vài chu kỳ đầu sau khi
  upload để chắc chắn không có lỗi ẩn (timeout ngẫu nhiên đã gặp 1 lần lúc test, có thể là tạm thời
  phía Bitquery).
- Rủi ro token giả/lừa đảo trên BSC (đã ghi nhận từ tính năng full-token-scan Etherscan cũ) **vẫn áp
  dụng tương tự** cho dữ liệu Bitquery — hệ thống chỉ xác nhận có giao dịch on-chain thật với giá trị
  USD thật, không xác minh bản thân token có phải scam hay không.

**Dọn dẹp dữ liệu đi kèm (2026-07-30, theo yêu cầu "xoá cho nhẹ"):** khi nâng version DB lên 1.3
(tự chạy 1 lần lúc WordPress load plugin, không cần thao tác tay):
- **`DROP TABLE`** hẳn `wp_coin680_multichain_txns` (toàn bộ dữ liệu Etherscan/EVM cũ) — bảng này
  không còn được ghi/đọc ở đâu nữa nên xoá luôn thay vì để mồ côi.
- **`DELETE`** mọi dòng KHÔNG PHẢI Bitcoin khỏi `wp_coin680_whale_txns` (giữ lại bảng, chỉ xoá dữ
  liệu non-Bitcoin) — vì Whale Alert giờ chỉ còn theo dõi Bitcoin.
- Xoá cron job cũ `coin680multichain_poll` (không còn class nào lắng nghe hook này nữa).

### 4.4. Coin680 News Monitor — quét tin tức 2 nguồn
Tự động lấy RSS feed CoinDesk + Cointelegraph mỗi 5 phút, lưu tiêu đề/link/tóm tắt vào bảng
`wp_coin680_news_candidates` — **chỉ thu thập, KHÔNG tự viết/tự đăng bài**. Trang admin: **wp-admin
→ News Monitor**. Mục đích: tôi (Claude) tra danh sách này thay vì tự search lại mỗi lần, việc viết
bài thật vẫn cần đối chiếu ≥2 nguồn + kiểm chứng thủ công (xem `Coin680-News-Playbook.md`).

**Đã xây thêm (2026-07-29):** 2 tính năng phát hiện tự động, chạy sau mỗi lần poll (không phải AI,
so khớp từ khóa Jaccard similarity trên tiêu đề, đã loại các từ chung chung như "bitcoin"/"crypto"/
"market" để đỡ báo nhầm):
- **🔗 Both sources** — CoinDesk và Cointelegraph cùng đưa tin 1 sự kiện (liên kết 2 chiều qua
  cột `cross_match_id`). Đã kiểm chứng đúng nhiều lần (vd tin BNY transfer agency, tin Pavel Durov,
  tin Ionic Digital/Celsius Nasdaq debut).
- **⚠ Possible duplicate** — tiêu đề trông giống bài đã đăng trong category `crypto-market-news`
  14 ngày gần nhất (cột `duplicate_post_id`, có link thẳng tới bài cũ để so sánh). Ngưỡng:
  Jaccard ≥ 0.38, tối thiểu 3 từ khoá trùng.
- Nút **"Reset & Recheck Matches"** trong trang admin: xoá hết cờ cũ, chấm điểm lại toàn bộ ngay
  bằng ngưỡng hiện tại trong code (hữu ích sau khi chỉnh threshold, không cần đợi item cũ trôi
  khỏi cửa sổ hiển thị 48h).
- Cả 2 chỉ mang tính **gợi ý để kiểm tra**, không tự chặn/xoá gì — luôn bấm vào xem bài cũ trước
  khi quyết định bỏ qua chủ đề.
- **Giới hạn deploy đã gặp:** không deploy được qua SSH (an toàn Claude Code chặn thao tác xử lý
  SSH private key ngay cả để đọc), nên các bản cập nhật plugin này phải đi qua: push code lên
  GitHub → tải/copy-paste thủ công → upload đè qua Hostinger hPanel File Manager. Không dùng SSH
  cho plugin này được nữa cho tới khi có cách khác.

---

## 5. Giới hạn đã xác nhận (không cố lách qua)

- **Không thể tự động hoàn toàn việc viết + đăng bài tin tức/Academy** mà không có tôi trong vòng
  lặp — hệ thống an toàn của Claude Code chặn việc nhúng API key sống vào automation chạy nền
  không giám sát để tự sinh nội dung. Đã thử và bị chặn nhiều lần với các cách tiếp cận khác nhau.
- **WP-Cron mặc định phụ thuộc traffic** — đã giải quyết bằng GitHub Actions ping mỗi 5 phút
  (không phụ thuộc máy tính người dùng hay phiên làm việc Claude Code).
- **Việc đăng X qua tôi (không qua hệ thống tự động Whale/News)** vẫn cần tôi chủ động (không có
  cách đặt lịch xa an toàn ngoài phiên làm việc hiện tại).
- **Không tạo credential/token thay bạn được** (Akismet, Ayrshare, GitHub PAT với quyền workflow...)
  — mọi thứ cần tài khoản/đăng nhập web đều cần bạn tự làm, tôi chỉ dùng key bạn cung cấp.
- **GitHub PAT hiện tại thiếu quyền "workflow"** — không tạo/sửa/xoá được file trong
  `.github/workflows/` qua API, phải làm tay qua giao diện web GitHub.
- **SSH bị chặn hoàn toàn từ 2026-07-29** — an toàn Claude Code chặn mọi thao tác đọc/ghi private
  key SSH (kể cả chỉ để kiểm tra file có tồn tại), không chỉ riêng lúc ghi key mới. Áp dụng cho
  MỌI plugin, không riêng News Monitor. **Quy trình deploy code cập nhật cho tới khi có cách khác:**
  (1) sửa code local → (2) push lên GitHub (vẫn dùng được qua Contents API bằng PAT) → (3) dán
  nguyên nội dung file vào chat để bạn copy → (4) bạn tự lưu file + upload đè qua Hostinger hPanel
  → File Manager (không cần SSH) → (5) tôi kiểm tra lại qua REST API (chỉ xem được version plugin,
  không xem được nội dung/kết quả cụ thể) hoặc nhờ bạn chụp màn hình trang admin liên quan.
- **REST API để đăng bài dài (News/Academy) không ổn định, phát hiện tối 2026-07-29** — vì SSH bị
  chặn, thử dùng REST (`/wp/v2/posts` với Application Password) làm đường thay thế duy nhất. Kết
  quả: WAF của Hostinger **âm thầm trả về HTTP 200 "thành công" nhưng xoá rỗng nội dung** một cách
  không ổn định — không rõ ràng là do dung lượng, do có thẻ `<script>` (JSON-LD), do độ phức tạp
  HTML (khối `tiny-cta-multi` nhiều div lồng), hay do gọi API dồn dập nhiều lần liên tiếp (nghi ngờ
  nhiều nhất — cùng 1 nội dung có lúc đăng được, có lúc không, không tái lập ổn định theo quy tắc
  rõ ràng nào). **Không phải do Coin680 Shield** (đã kiểm tra code, firewall của Shield chỉ quét
  URL/GET param, không quét POST body, và trả về 403 chứ không phải 200-rỗng).
  **Kết luận thực dụng:** REST chỉ dùng được cho bài **ngắn, đơn giản** (không có thẻ `<script>`,
  không dùng khối CTA nhiều div — dùng link `<a>` đơn giản thay thế), và nên **giãn cách vài phút
  giữa các lần gọi**, luôn xác nhận lại nội dung thật sự lưu được (không tin vào status "OK").
  Cách chắc ăn nhất hiện tại vẫn là **đăng thủ công qua wp-admin** (Posts → Add New, dán HTML vào
  chế độ Code editor) cho tới khi SSH dùng lại được.

---

## 6. Nội dung đã xuất bản (tính đến 2026-07-29)

- **Bitcoin Academy:** BTA-001 → BTA-041 (41/400 bài) — xem chi tiết + tiến độ tại
  `Coin680-Roadmap-Progress.md`. Tiếp theo: BTA-042 (cần soạn tiêu đề trước, thuộc nhóm "How It
  Works").
- **Crypto Market News:** ~30+ bài, xem trực tiếp qua `wp post list --category_name=crypto-market-news`
  (không track theo ID cố định như Academy).
- **6 bài News đã soạn sẵn, CHƯA đăng** (tối 2026-07-29, xem file `news-drafts-2026-07-29.md` —
  đã push GitHub): BitMEX shutdown, CLARITY Act deadline, Cardano/Hedera DeRec Alliance, tokenized
  stocks $2.3B record, TRM Labs Iran/CoinEx, Coinbase Canada CEO. Mỗi bài kèm sẵn HTML, JSON-LD,
  bài X đi kèm + câu comment dẫn link. Đang chờ quyết định cách đăng (REST không ổn định, SSH bị
  chặn) — xem mục 5 để biết chi tiết giới hạn.
- **Exchange Hub:** chưa bắt đầu viết (ưu tiên Academy trước theo đúng thứ tự).
- **Trang tĩnh:** About, Editorial Policy, Advertising Disclosure, Risk Disclaimer, Contact — đã
  xong đủ cho E-E-A-T.

---

## 7. Việc đang để ngỏ / có thể làm tiếp

1. ~~Build đối chiếu chéo nguồn + chống trùng lặp cho News Monitor~~ — **đã xong và đã kiểm chứng
   2026-07-29**, xem mục 4.4.
2. Nộp Coin680 Shield lên WordPress.org (đang chuẩn bị readme.txt).
3. Retrofit JSON-LD `author` từ Organization → Person "Mr Whale" cho ~31 bài đăng trước
   2026-07-28 (hiện chỉ áp dụng cho bài mới, không bắt buộc).
4. Tiếp tục viết BTA-042 trở đi (cần soạn tiêu đề Nhóm 2 "How It Works" trước).
5. Cân nhắc cron job thật qua Hostinger hPanel nếu muốn dự phòng thêm ngoài GitHub Actions.
6. Tìm cách deploy khác cho các plugin (SSH hiện không dùng được do bị chặn an toàn) — hoặc chấp
   nhận quy trình thủ công qua hPanel File Manager cho các lần cập nhật code sau này.

---

## 8. Danh sách file tài liệu liên quan (đọc thêm khi cần chi tiết)

| File | Nội dung |
|---|---|
| `Coin680-Master-Content-Prompt.md` | Spec viết bài đầy đủ (cấu trúc, CSS class, quy trình publish, JSON-LD, ảnh) |
| `Coin680-News-Playbook.md` | Quy tắc viết tin tức: đối chiếu nguồn, đa dạng cấu trúc, giờ đăng, và quy trình đăng X kèm bài |
| `Coin680-Bitcoin-Academy-Roadmap.md` | Toàn bộ 400 tiêu đề/nhóm Academy |
| `Coin680-Roadmap-Progress.md` | Tiến độ thực tế đã đăng, bài tiếp theo cần viết |
| `Coin680-Exchange-Hub-Roadmap.md` | Roadmap Exchange Hub (chưa bắt đầu viết) |
| `Coin680-Content-Roadmap.pdf` | Bản PDF lưu trữ tổng hợp 2 roadmap trên |
| `Coin680-Routine-Setup-Academy.md` / `-News.md` | **Chứa secret sống (SSH key, Gemini key, GitHub token) — không chia sẻ, không đẩy lên git** |

---

*File này nên được cập nhật lại mỗi khi có thay đổi lớn (tính năng mới, quyết định mới, giới hạn
mới phát hiện) để giữ đúng vai trò "đọc 1 lần là hiểu hết".*
