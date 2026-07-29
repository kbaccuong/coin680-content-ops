# Coin680 Crypto Market News — Playbook

Luồng nội dung mới, không tồn tại bên FXM680 — vì FXM680 định vị "Education, not news", còn Coin680
đã thống nhất mô hình gần giống CoinDesk: Bitcoin Academy (evergreen) + tin tức hàng ngày.

Khác biệt cốt lõi so với Bitcoin Academy / Exchange Hub:
- **Không theo roadmap ID cố định** — chủ đề phụ thuộc sự kiện thị trường thực tế mỗi ngày.
- **Không hẹn giờ xa** — tin phải lên gần thời điểm viết để giữ tính thời sự (không phải kiểu
  "future" nhiều ngày sau như Academy).
- **Bắt buộc có nguồn thật** — không được bịa số liệu giá, % biến động, tuyên bố của tổ chức/nhân
  vật. Đây là quy tắc quan trọng nhất của luồng News, khác hẳn Academy (kiến thức nền tảng ổn định).

---

## 1. Quy trình vận hành (khi chạy tự động qua cron)

**Quyết định 2026-07-27:** không nêu tên CoinDesk (hay bất kỳ báo nào khác) trong bài, và không
dịch/diễn giải sát 1 bài báo duy nhất. Để vẫn an toàn về bản quyền/đạo văn dù không ghi nguồn, quy
trình bắt buộc **đối chiếu nhiều nguồn cho cùng 1 sự kiện** trước khi viết, thay vì bám theo cách
đưa tin của riêng một báo:

1. **Tìm kiếm** (web search) các diễn biến crypto/Bitcoin nổi bật trong ~24h gần nhất, quét đều
   **cả CoinDesk và Cointelegraph** làm 2 nguồn tín hiệu chính để phát hiện tin (không dùng CoinDesk
   một mình như bản đầu) — cộng thêm biến động giá lớn, tin quy định/pháp lý, động thái tổ chức lớn
   (ETF, công ty niêm yết mua Bitcoin...), sự kiện on-chain đáng chú ý, tin từ 1 trong 6 sàn đối tác
   (Binance, Bybit, OKX, BingX, Gate, MEXC) nếu có liên quan. Đây vẫn chỉ là nguồn tín hiệu để phát
   hiện tin, không phải nguồn duy nhất quyết định cách viết.
2. **Đối chiếu ít nhất 2 nguồn độc lập** đưa tin về cùng sự kiện trước khi viết (không viết dựa
   trên duy nhất 1 bài của 1 báo, kể cả khi cả CoinDesk và Cointelegraph cùng đưa tin — vẫn nên có
   thêm 1 nguồn thứ 3 nếu có để chắc chắn). Nếu chỉ có 1 nguồn đưa tin và nguồn đó không đủ uy tín,
   hạ mức ưu tiên hoặc bỏ qua.
3. **Chọn 1-3 tin đáng viết nhất trong ngày, không trùng lặp giữa các ngày** — trước khi viết, kiểm
   tra nhanh các bài News đã đăng gần đây (qua `wp post list` hoặc đọc tiêu đề) để chắc chắn không
   viết lại đúng sự kiện đã đưa tin trước đó, ưu tiên tin có tác động thị trường rõ, tránh tin giật
   gân/chưa kiểm chứng.
4. **Viết hoàn toàn độc lập theo sự kiện/dữ kiện** (bản thân dữ kiện tin tức không có bản quyền),
   không bám theo cấu trúc câu hay cách trình bày của bất kỳ báo cụ thể nào, **không nêu tên
   CoinDesk hay báo nào khác trong bài**. Theo khung Phần 5.2 của `Coin680-Master-Content-Prompt.md`
   (600-1200 từ, inverted pyramid).
5. **Gắn schema `NewsArticle`**, `datePublished` = thời điểm đăng thật (không hẹn giờ xa).
6. **Đăng ngay hoặc hẹn giờ rất gần** (tối đa vài giờ sau, không phải nhiều ngày như Academy).
7. **Internal link:** luôn dẫn 1-2 link về bài Bitcoin Academy liên quan (vd tin về halving →
   link bài BTA giải thích halving) — giữ đúng nguyên tắc "mũi tên tàng hình" kéo traffic tin tức
   xuống nội dung evergreen.

---

## 2. Cadence đề xuất (cần bạn xác nhận)

- **Mặc định:** 1-2 bài tin/ngày, giờ đăng cố định 08:00 và 20:00 giờ Việt Nam — đủ để site có tín
  hiệu "cập nhật hàng ngày" mà không cần tần suất tin tức 24/7 như một toà soạn thật.
- Có thể tăng lên 3 bài/ngày nếu thị trường biến động mạnh (halving, sự kiện ETF, sự cố sàn lớn...).
- **Vận hành tự động:** cần thiết lập cron job (schedule) kích hoạt tôi chạy quy trình ở Mục 1 vào
  đúng khung giờ trên, sau khi site đã có API credentials. Trước khi có site thật, có thể chạy thủ
  công theo yêu cầu để làm quen quy trình.

---

## 3. Cấu trúc bài News — BẮT BUỘC ĐA DẠNG, KHÔNG LẶP KHUNG GIỮA CÁC BÀI

**Quy tắc quan trọng (bổ sung 2026-07-28, sau phản hồi thực tế):** 5 bài tin đầu tiên đều dùng
đúng 1 khung 4 mục "What Happened / Why It Matters / Market Context / What to Watch Next" — đọc
lên thấy rõ là rập khuôn, không tự nhiên như một tòa soạn thật viết mỗi ngày. **Từ nay cấm lặp lại
y hệt khung này ở 2 bài liên tiếp.** Một tòa soạn thật không viết bài nào cũng giống bài nào —
mỗi câu chuyện có hình dạng khác nhau tùy vào loại tin.

**Bắt buộc trước khi viết:** kiểm tra lại 3-5 bài News gần nhất đã đăng (qua `wp post list` hoặc
đọc trực tiếp), liệt kê xem chúng dùng dạng mở bài nào, có heading hay không, độ dài bao nhiêu —
rồi **chủ động chọn một dạng khác** cho bài đang viết.

**Các dạng bài có thể luân phiên (không giới hạn, có thể sáng tạo thêm dạng khác):**

- **Breaking/thẳng vào việc (straight news):** không cần heading H2 nào cả, chỉ 3-5 đoạn văn liền
  mạch theo tháp ngược, phù hợp cho tin giao dịch/sáp nhập/số liệu công bố. Độ dài ngắn, 400-600 từ.
- **Tường thuật có bối cảnh (narrative lead):** mở bài bằng bối cảnh/tình huống cụ thể thay vì nêu
  thẳng sự kiện ở câu đầu, dẫn dắt người đọc vào câu chuyện trước khi tiết lộ tin chính — phù hợp
  tin về công ty/con người/quyết định chiến lược.
- **Giải thích chuyên sâu (explainer):** dùng heading, có thể có 1 bảng hoặc danh sách, phù hợp tin
  về công nghệ/cơ chế thị trường (staking, perps, halving...). Dài hơn, 700-1000 từ.
- **Điều tra/pháp lý (regulatory/investigative):** đi theo trình tự thời gian sự việc, nhấn mạnh
  ai nói gì, hệ quả pháp lý — phù hợp tin quy định/kiện tụng/xử phạt.
- **Điểm nhanh nhiều tin (roundup ngắn):** khi có 2-3 tin nhỏ cùng chủ đề trong ngày, có thể gộp
  thành 1 bài dạng danh sách ngắn thay vì viết 3 bài riêng lẻ na ná nhau.

**Luôn thay đổi giữa các bài:**
- Câu mở đầu: không phải bài nào cũng bắt đầu bằng "X has done Y" — có thể bắt đầu bằng câu hỏi,
  một con số, một câu trích dẫn diễn giải, hoặc bối cảnh.
- Heading (nếu dùng): đặt heading phản ánh đúng nội dung bài đó, không copy nguyên văn "What
  Happened/Why It Matters" từ bài trước — mỗi bài có thể có số lượng heading khác nhau (0, 2, 3, 4).
- Độ dài: dao động 400-1000 từ tùy độ phức tạp của tin, không cố định một con số.
- Câu kết và CTA: thay đổi cách dẫn về Bitcoin Academy mỗi lần, không dùng lại nguyên văn câu CTA.

Vẫn giữ nguyên các yêu cầu bắt buộc khác: không bịa số liệu, đối chiếu ≥2 nguồn, không nêu tên báo
nguồn, disclaimer cuối bài, schema `NewsArticle`, class `tiny-*` cho các thành phần dùng chung.
CTA nhẹ dẫn về Academy) để đồng bộ giao diện với Academy/Exchange Hub.

**CTA cuối bài News:** 1 CTA nhẹ dẫn về bài Bitcoin Academy liên quan (khối `tiny-cta-multi`,
không affiliate) — không gắn CTA sàn giao dịch trực tiếp vào bài tin tức thuần, trừ khi tin đó
chính là về 1 trong 6 sàn đối tác và có thể dẫn hợp lý sang Exchange Hub tương ứng.

---

## 4. Danh mục WordPress

Cần tạo category riêng "Crypto Market News" (ID điền sau khi tạo site) — tách biệt hoàn toàn khỏi
category "Bitcoin Academy" để không làm loãng topical authority của nội dung evergreen.

---

## 6. Đăng kèm lên X (Twitter) — quy tắc từ 2026-07-29

Mỗi bài News (và có thể cả Academy nếu được yêu cầu) khi viết + đăng/lên lịch trên coin680.com,
**đồng thời soạn 1 bài X đi kèm**, đăng qua plugin riêng **Coin680 X Scheduler** (wp-admin → X
Scheduler; DB `wp_coin680_x_queue`, cron tự chạy mỗi 5 phút, không giới hạn số bài, không qua
Ayrshare) — xem chi tiết plugin tại `coin680-x-scheduler-plugin/` (đã đẩy GitHub).

**Nguyên tắc:**
- Giờ đăng X = **đúng giờ bài viết xuất bản trên web** (không lệch).
- Nội dung ngắn gọn, tự nhiên, **giọng văn/kiểu mở đầu luôn khác nhau giữa các bài** (số liệu, câu
  hỏi, trích dẫn, tình huống...), giống nguyên tắc đa dạng cấu trúc ở Mục 3.
- Kèm ảnh đại diện của bài viết (media_url là link ảnh đã upload lên coin680.com).
- **3-6 hashtag chọn thủ công theo đúng chủ đề bài viết** (không dùng danh sách ngẫu nhiên chung
  chung — ví dụ bài về Ethereum dùng #Ethereum, bài về stablecoin dùng #Stablecoins, không trộn
  lẫn giữa các bài).
- Bình luận đầu tiên (first comment) luôn dẫn link bài viết gốc kèm 1 câu mời đọc thêm, câu mời
  cũng nên thay đổi cách diễn đạt giữa các bài.
- Tài khoản X: @coin680, credentials lưu tại wp option `coin680x_settings` (đã cấu hình sẵn).

## 7. Việc cần bạn xác nhận

1. Cadence 1-2 bài/ngày (08:00 + 20:00 giờ VN) có phù hợp không, hay muốn tần suất khác?
2. Có muốn tôi tự động hoá qua cron ngay khi site sẵn sàng, hay giai đoạn đầu vẫn viết thủ công
   theo yêu cầu để kiểm tra chất lượng trước khi bật tự động?
3. Có nguồn tin ưu tiên nào bạn muốn tôi tham khảo trước (ngoài tìm kiếm chung), hay để tôi tự
   chọn nguồn uy tín phổ biến trong ngành?
