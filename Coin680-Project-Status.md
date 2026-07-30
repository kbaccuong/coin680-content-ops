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
| Whale Alert API | Key đã lưu trong wp_options `coin680whale_settings` — không cần nhập lại |
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

### 4.3. Coin680 Whale Tracker — "Whale Signal" tự động
Gọi API Whale Alert (v1 REST, `api.whale-alert.io`), tự phân loại giao dịch (Exchange
Inflow/Outflow/to-Exchange, Mint, Burn, Wallet Transfer) dựa theo nhãn owner_type thật của Whale
Alert — **có nêu tên sàn cụ thể khi Whale Alert gắn nhãn** (vd "moved from Binance to Coinbase").

Tính năng:
- **KHÔNG còn mốc thời gian cố định** (đổi 2026-07-29 tối, thay cho "tối đa 30 phút/bài" trước đó
  theo yêu cầu ban đầu) — giờ **chỉ đăng khi gom đủ 3 coin khác nhau** (không quan tâm mất bao lâu),
  ưu tiên nội dung phong phú hơn tần suất cố định. Vẫn kiểm tra mỗi 5 phút qua cron, nhưng chỉ thực
  sự đăng khi đủ điều kiện. Tiêu đề bài tự tính đúng khoảng thời gian thực tế (vd "last 47 min"),
  và **tự đổi sang giờ khi >60 phút** (vd "last 4 hours", "last 4.5 hours" — sửa 2026-07-30 theo
  phản hồi, trước đó hiển thị nguyên phút kiểu "last 1137 min" khó đọc).
- **Bật/tắt Whale Alert khỏi bài đăng** (thêm 2026-07-30) — checkbox riêng ở trang admin
  (`include_whale_alert_in_digest`, mặc định BẬT). Lý do: user đang dùng thử Whale Alert 7 ngày
  (còn 1 ngày quyết định lúc thêm tính năng này) — tắt checkbox này khiến bài đăng chỉ lấy dữ liệu
  từ Multichain/Etherscan, nhưng Whale Alert **vẫn tiếp tục thu thập dữ liệu nền** (không tắt
  hẳn code) — nếu ngừng gói Whale Alert trả phí, chỉ cần bật lại checkbox, không cần xây lại từ
  đầu. Lưu ý: BTC/XRP/TRON/Solana không phải chain EVM, Etherscan (dù trả phí) không bao giờ phủ
  được các chain này — tắt Whale Alert nghĩa là mất hẳn các coin đó khỏi bài đăng.
- **Chọn đa dạng COIN trước, loại giao dịch sau, KHÔNG BAO GIỜ trùng coin trong 1 bài** (sửa
  2026-07-29 tối, 2 lần — lần đầu còn bug để lọt trùng coin ở bước lấp chỗ trống, đã vá) — lấy giao
  dịch lớn nhất của MỖI coin khác nhau trong pool (tối đa 60 giao dịch top theo USD), tối đa
  **3 coin/bài** (hạ từ 6→3 ngày 30/07 theo yêu cầu — đăng thường xuyên hơn), không đủ 3 thì đăng
  ít hơn chứ không lặp coin để lấp đầy. Lý do: BTC/ETH/USDT/USDC
  luôn có nhiều giao dịch >$500k hơn hẳn altcoin trong bất kỳ khung giờ nào, nên nếu chỉ ưu tiên
  theo classification hoặc size thô, các coin lớn sẽ chiếm hết chỗ, altcoin dù đủ điều kiện cũng
  không bao giờ được đăng. Người dùng phản hồi thấy dữ liệu "nhàm chán, chỉ toàn BTC/USDT/ETH"
  trước khi sửa.
- **Ngưỡng USD tách riêng theo nhóm coin** (thêm 2026-07-29 tối, hạ xuống $100k ngày 30/07): BTC/
  ETH/USDT/USDC dùng ngưỡng `min_value` (mặc định $500k), **mọi coin khác dùng ngưỡng
  `altcoin_min_value` = $100k** — đây là **mức sàn cứng của chính API Whale Alert** (đã test trực
  tiếp: gọi API với ngưỡng thấp hơn bị chính Whale Alert từ chối "value out of range"), không phải
  do code tự giới hạn — không thể hạ thấp hơn được nữa với gói Whale Alert hiện tại. Whale Alert
  API chỉ nhận 1 `min_value` toàn cục nên code luôn gọi API ở ngưỡng THẤP HƠN trong 2 ngưỡng, rồi tự
  lọc lại theo từng nhóm coin trước khi lưu vào DB. Cả 2 ngưỡng chỉnh được ở trang admin.
- **Whale Alert KHÔNG có BSC hoặc Solana** (xác nhận qua endpoint `/status`: chỉ 15 blockchain, hầu
  hết chain cũ — Bitcoin, Ethereum, Ripple, Tron, EOS, Stellar, NEO, Tezos, Cosmos, ICON, Hive,
  Steem, Liquid, và "binancechain" — đây là Binance Chain cũ BEP2, KHÔNG phải BSC/BEP20 hiện tại).
  Đây là gốc rễ của tính năng multichain mới bên dưới.
- **Không còn poll/first-comment kèm bài** (bỏ 2026-07-29 tối, theo phản hồi trực tiếp: "đơn bị
  spam kênh") — mỗi chu kỳ giờ chỉ đăng **1 bài duy nhất**, không có reply/poll nữa. (Bài mega
  alert và daily recap vốn đã không có poll từ trước, không đổi.)
- **Net Exchange Flow** thật (tổng inflow trừ outflow toàn bộ khung giờ, không chỉ 5 giao dịch hiển thị).
- **So sánh lịch sử thật** — lưu giá BTC tại đúng thời điểm mỗi giao dịch, so sánh với giao dịch
  tương tự trong quá khứ (không bịa tương quan).
- **Breaking alert tức thời** cho giao dịch ≥$50M (ngưỡng chỉnh được), không chờ chu kỳ 30 phút.
- **Daily Recap** 1 lần/ngày (tổng khối lượng, giao dịch lớn nhất, net flow 24h).
- Mọi mã coin có `$` phía trước ($BTC, $ETH...) — nhưng chỉ 1 cái/bài vì giới hạn X.
- Tiêu đề: `🐋 #COIN680 WHALE SIGNAL` (viết hoa), có link tx thật cho mỗi giao dịch.
- Trang admin: **wp-admin → Whale Tracker** (cấu hình key, 2 ngưỡng min_value, mega threshold,
  checkbox bật/tắt Whale Alert khỏi bài đăng, xem 24h gần nhất). **Đã thêm phân trang 2026-07-30**
  (30 giao dịch/trang, nút Prev/Next) cho cả 2 bảng (Whale Alert + Multichain) — trước đó hiển thị
  1 lần tối đa 100 dòng, quá dài theo phản hồi. Đã bỏ 2 khung debug tạm thời ("Price Feed
  Diagnostic", "Scan + Amount Diagnostic") dùng để chẩn đoán bug thiếu altcoin — bug đã xác nhận
  sửa xong, khung + code ghi log liên quan đã dọn sạch.

### 4.3.1. Coin680 Multichain Fetcher — Ethereum/Polygon/Arbitrum/BSC/Base/Optimism/Avalanche
(mới 2026-07-30, mở rộng lên 7 chain cùng ngày sau khi nâng cấp gói Etherscan)

**Lý do xây:** Whale Alert không có BSC/Solana (xem trên). User hỏi mua API riêng cho BSC (BscScan)
— phát hiện Etherscan.io đã hợp nhất **1 API cho 60+ chain EVM** (đổi tham số `chainid`), rẻ hơn và
gọn hơn nhiều API riêng lẻ. **Đã test trực tiếp bằng key thật của user:**
- Gói **Free**: chỉ dùng được Ethereum (chainid=1), Polygon (137), Arbitrum (42161) — BSC (56),
  Base (8453), Optimism (10), Avalanche (43114) báo lỗi "Free API access is not supported for this
  chain".
- User đồng ý build thử với 3 chain free trước (2026-07-30 sáng), sau đó **đã nâng cấp gói
  Etherscan lên trả phí ngay trong ngày** — 4 chain còn lại (BSC/Base/Optimism/Avalanche) đã được
  bật lên cùng ngày trong `class-multichain-labels.php::CHAINS` (không cần sửa gì ở
  `class-multichain-fetcher.php`, class này vốn đã lặp qua `CHAINS` một cách tổng quát — đúng như
  thiết kế ban đầu, không cần build lại từ đầu).
- Router DEX/stablecoin/token price ID cho 4 chain mới (PancakeSwap trên BSC; Uniswap V3 + địa chỉ
  WETH cố định kiểu OP-stack trên Base/Optimism; Trader Joe + Pangolin trên Avalanche) là các địa
  chỉ công khai phổ biến nhất nhưng **chưa được đối chiếu trực tiếp qua 1 giao dịch thật** như 3
  chain ban đầu từng làm lúc debug — nên soi dữ liệu vài ngày đầu (đặc biệt tên DEX/exchange hiển
  thị trong bài đăng) để chắc chắn không bị gán nhầm.

**File chính:**
- `includes/class-multichain-labels.php` — cấu hình tĩnh: danh sách chain bật/tắt, địa chỉ router
  DEX đã biết (Uniswap, SushiSwap, QuickSwap, Camelot, PancakeSwap, BaseSwap, Velodrome, Trader Joe,
  Pangolin), địa chỉ stablecoin đã biết (dùng để đọc hướng swap mua/bán), và bảng ánh xạ token →
  CoinGecko ID (token không có trong bảng này bị BỎ QUA hoàn toàn — không bao giờ đoán giá).
- `includes/class-multichain-fetcher.php` — quét `eth_getLogs` **theo từng địa chỉ token riêng**
  (không quét blanket toàn chain — sửa bug 2026-07-30: quét blanket bị USDT/USDC chiếm hết kết quả,
  token nhỏ như UNI/LINK không bao giờ lọt vào), phân loại 3 tầng: **DEX Buy/Sell/Swap** (đối chiếu
  router đã biết + hướng di chuyển so với router để biết mua hay bán) → **Exchange Inflow/Outflow**
  (đối chiếu địa chỉ với dữ liệu ĐÃ CÓ NHÃN từ Whale Alert — xem bên dưới) → **Wallet Transfer**.
  `poll()` lặp qua toàn bộ `Coin680MultiChain_Labels::CHAINS`, nên thêm/bớt chain trong tương lai chỉ
  cần sửa file labels, không cần sửa fetcher.

**Bug đã tìm & sửa 2026-07-30 (khiến altcoin gần như không hiện, chỉ toàn USDT/USDC):**
1. `coin680_get_crypto_prices()` mặc định chỉ lấy top 15 coin — UNI/LINK (hạng ~37/~18) bị bỏ qua vì
   giá trả về = 0. Sửa: luôn gọi `coin680_get_crypto_prices(250)` (mức trần thật của CoinGecko).
2. Quét `eth_getLogs` kiểu blanket (không lọc theo `address`) trả về Transfer log của MỌI token
   ERC-20 trên chain — bị USDT/USDC chiếm gần hết kết quả (xác nhận: 1000 log USDT chỉ trong 11
   block). Sửa: quét riêng từng địa chỉ token đã cấu hình.
3. Khung thời gian quét thực tế khá hẹp (~2 phút/lần bấm "Poll Now" liên tục) + giao dịch UNI/LINK
   ≥$10k vốn dĩ không nhiều (~40 lần/3 giờ mỗi coin, đo thực tế) — không phải bug, chỉ là hiếm gặp
   trong cửa sổ quét ngắn. Khuyến nghị để cron chạy tự nhiên 30-60 phút thay vì bấm Poll Now liên
   tục để kiểm tra.

**Cải tiến kèm theo trên `class-fetcher.php` (Whale Alert):** lưu thêm `from_address`/`to_address`
thật (trước đây chỉ lưu tên chủ sở hữu, không lưu địa chỉ) — vì địa chỉ ví EVM dùng chung định dạng
trên mọi chain, 1 ví từng được Whale Alert gắn nhãn "Binance" trên Ethereum có thể đối chiếu lại
trên Polygon/Arbitrum/BSC/... sau này. **Chỉ tích luỹ được từ bây giờ trở đi** (không hồi cứu được
dữ liệu cũ), và **không phải sàn nào cũng dùng chung địa chỉ giữa các chain** — chỉ bắt được một
phần, không đầy đủ như Whale Alert tự có trên chain gốc của nó.

**`class-digest.php`** gộp dữ liệu từ CẢ 2 nguồn (Whale Alert + Multichain) khi chọn bài đăng —
không trùng coin dù coin đó xuất hiện ở nhiều chain khác nhau (ưu tiên volume lớn hơn), mỗi dòng
bài đăng ghi rõ tên chain (vd "(Ethereum)", "(BSC)"). **Có thể tắt hẳn nguồn Whale Alert khỏi việc
CHỌN bài** qua checkbox admin (xem mục 4.3) mà không ảnh hưởng tới việc thu thập dữ liệu nền.

**Giới hạn đã biết (còn tồn tại sau bản mở rộng 7 chain):**
- Chỉ bắt token ERC-20/BEP-20 (Transfer event) — **chưa bắt giao dịch coin gốc** (ETH/BNB/MATIC/AVAX
  tự thân chuyển khoản, không qua hợp đồng token) — việc này cần quét toàn bộ block, để dành làm sau.
- Danh sách token có giá (CoinGecko) vẫn còn giới hạn theo từng chain (chủ yếu stablecoin + wrapped
  BTC/ETH/native + 1-2 token đặc trưng mỗi chain: UNI/LINK/ARB trên EVM gốc, CAKE trên BSC, OP trên
  Optimism...) — mở rộng dần khi thấy token nào bị bỏ qua mà muốn có.
- `wrapped-bitcoin` và `weth` (và có thể `wmatic`/tương tự trên chain mới) đôi khi báo "NOT FOUND"
  dù đã gọi top 250 coin — nghi ngờ CoinGecko liệt kê vốn hoá của bản wrapped riêng, thấp hơn top
  250 — chưa điều tra kỹ, chỉ mới ghi nhận là vấn đề nhỏ còn tồn đọng.
- Nhãn sàn CEX cho EVM chain mới **không đầy đủ bằng Whale Alert** (tự đối chiếu, không phải nhãn
  chính thức) — DEX thì nhận diện chính xác cao (địa chỉ router cố định, công khai), riêng router
  của 4 chain mới thêm ngày 30/07 (BSC/Base/Optimism/Avalanche) chưa được xác nhận qua giao dịch
  thật, cần theo dõi vài ngày đầu.
- Trang admin: cùng trang **Whale Tracker**, có ô nhập Etherscan API key + 2 ngưỡng riêng ($100k
  major / $10k token nhỏ, chỉnh được) + nút "Poll Multichain (Etherscan) Now" + bảng xem dữ liệu 24h
  riêng (có phân trang 30/trang từ 2026-07-30).

**4.3.2. Quét "full token" trên BSC — bắt cả meme coin/vốn hoá thấp (thêm 2026-07-30)**

**Lý do:** BSC có rất nhiều token meme/vốn hoá thấp mà không thể liệt kê tay từng cái vào
`TOKEN_PRICE_IDS` (không có CoinGecko ID, hoặc có nhưng không đáng để thêm thủ công từng token một).
User yêu cầu quét được cả các token này, không chỉ danh sách ~44 token đã cấu hình sẵn trên 7 chain.

**Cách làm — quét theo ROUTER thay vì theo TOKEN, định giá qua vế đối ứng:**
- Trước đây: chỉ quét Transfer event của những địa chỉ token đã khai báo sẵn (`address` filter trên
  `eth_getLogs`) — token không có trong danh sách sẽ không bao giờ được thấy.
- Giờ (chỉ bật cho BSC qua cờ `full_token_scan` trong `class-multichain-labels.php::CHAINS['bsc']`):
  thêm bước quét bổ sung `discover_router_logs()` — lọc Transfer event theo **topic1/topic2** (địa
  chỉ `from`/`to` được index sẵn trong sự kiện Transfer) trùng với địa chỉ router PancakeSwap, **bất
  kể token nào phát ra sự kiện đó**. Nhờ vậy bắt được MỌI token (kể cả token mới tạo, chưa ai biết
  đến) miễn là nó được giao dịch qua router đã biết.
- Token bắt được theo cách này **không cần có giá CoinGecko riêng** — hệ thống định giá USD bằng
  cách nhìn vào VẾ CÒN LẠI của giao dịch swap (2 chân: token A vào router, token B ra khỏi router,
  cùng 1 tx): nếu vế còn lại là USDT/BUSD/USDC/DAI hoặc WBNB/WETH (đã có giá tin cậy), lấy số lượng
  vế đó × giá của nó = giá trị USD thật của cả giao dịch. **Không đoán giá cho chính token
  meme/vốn hoá thấp đó** — nếu cả 2 vế đều là token lạ (không vế nào có giá tham chiếu), giao dịch
  bị bỏ qua hoàn toàn (không đoán bừa, giữ đúng nguyên tắc "không có giá xác thực thì không hiện").
- Token meme phát hiện được luôn dùng ngưỡng "token nhỏ" ($10k mặc định, chỉnh được) — không bao
  giờ được coi là "major".
- **Fix kèm theo:** đổi mapping của mọi token wrapped-native (WETH, WBTC, WMATIC, WBNB, cbBTC...)
  từ ID CoinGecko riêng của bản wrapped sang **ID của đồng gốc** (vd WETH dùng `ethereum` thay vì
  `weth`, WBNB dùng `binancecoin` thay vì `wbnb`) — vì bản thân đồng gốc luôn chắc chắn nằm trong
  top 250 CoinGecko, còn bản thân listing "wrapped" riêng đôi khi bị lệch hạng, gây lỗi "NOT FOUND"
  đã ghi nhận trước đó. Tiện thể sửa luôn lỗi tồn đọng này cho cả 7 chain, không chỉ BSC.
- **Cải tiến kèm theo:** phân loại DEX Buy/Sell giờ áp dụng cả khi vế đối ứng là WBNB/WETH (không
  chỉ riêng stablecoin như trước) — một token vốn hoá thấp đổi lấy WBNB giờ cũng hiện đúng "DEX Buy"/
  "DEX Sell" thay vì rơi vào "DEX Swap" chung chung (cải thiện luôn cho cả 3 chain gốc, không chỉ BSC).

**Giới hạn/rủi ro cần biết (chưa qua kiểm chứng thực tế, cần theo dõi vài ngày đầu):**
- **Tham số API `topic0_1_opr`/`topic0_2_opr` gửi cho Etherscan chưa được test trực tiếp** — đây là
  cách chuẩn để lọc theo topic1/topic2 riêng lẻ nhưng chưa xác nhận Etherscan V2 unified API chấp
  nhận đúng cú pháp này khi chỉ có topic1 HOẶC topic2 (không có topic1_2_opr đi kèm). **Cần bấm "Poll
  Multichain (Etherscan) Now" và kiểm tra bảng dữ liệu BSC xem có token lạ (ngoài danh sách 8 token
  đã biết) xuất hiện không** — nếu không thấy, có thể tham số API cần điều chỉnh lại.
- **Rủi ro token giả/lừa đảo:** BSC nổi tiếng có nhiều token scam dùng thủ thuật giả sự kiện
  Transfer (log sự kiện nhưng không thực sự chuyển token, hoặc token honeypot không bán lại được).
  Hệ thống chỉ xác nhận **có 1 giao dịch swap thật trên chain với giá trị USD tính từ vế
  USDT/WBNB thật** — không xác minh được bản thân token đó có phải lừa đảo/token rác hay không. Bài
  đăng X có thể vô tình nhắc đến 1 token scam như thể đó là "cá voi giao dịch lớn" — nên cân nhắc
  xem lại vài bài đầu có nhắc token BSC lạ trước khi để tự động hoàn toàn.
- **Tăng số lượng gọi API mỗi chu kỳ quét** — thêm tối đa 4 router × 2 hướng × tối đa 3 trang = tới
  24 lệnh gọi riêng cho BSC mỗi 5 phút (ngoài phần quét theo token đã có). Có giới hạn phân trang tối
  đa 3 trang/lượt để tránh treo request quá lâu, nhưng nếu BSC có đợt hoạt động cao điểm, dữ liệu quá
  3000 dòng/router/hướng trong 1 chu kỳ sẽ bị bỏ sót (chỉ ảnh hưởng lúc dồn dập, không ảnh hưởng theo
  dõi bình thường).

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
