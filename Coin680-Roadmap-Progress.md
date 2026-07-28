# Coin680 Roadmap Progress Tracker

Đối chiếu thực tế các bài đã xuất bản trên coin680.com (kiểm tra qua WP-CLI, không dựa trí nhớ)
với thứ tự cố định trong `Coin680-Bitcoin-Academy-Roadmap.md` / `Coin680-Exchange-Hub-Roadmap.md`.
Cập nhật file này mỗi khi xuất bản bài mới.

**Kiểm tra lần cuối:** 2026-07-28, qua SSH/WP-CLI (`wp post list`), post ID 71-113.

## ➡️ BÀI CẦN VIẾT TIẾP THEO: **BTA-012** (xem `Coin680-Bitcoin-Academy-Roadmap.md` Nhóm 2)

## Bảng trạng thái BTA-001 → BTA-040 (Nhóm 1: Fundamentals)

| ID | Tiêu đề (Roadmap) | Trạng thái | Ghi chú |
|---|---|---|---|
| BTA-001 | What Is Bitcoin? A Complete Beginner's Guide | ✅ Đã đăng | Post ID 71, slug `what-is-bitcoin`, publish. 2.606 từ. |
| BTA-002 | How Does Bitcoin Work? | ✅ Hẹn giờ | Post ID 95, slug `how-does-bitcoin-work`, 2026-07-28 07:30 UTC (14:30 VN) |
| BTA-003 | Who Created Bitcoin? The Story of Satoshi Nakamoto | ✅ Hẹn giờ | Post ID 97, slug `who-created-bitcoin`, 08:30 UTC (15:30 VN) |
| BTA-004 | Is Bitcoin Legal? A Country-by-Country Overview | ✅ Hẹn giờ | Post ID 99, slug `is-bitcoin-legal`, 09:30 UTC (16:30 VN) |
| BTA-005 | Is Bitcoin Safe to Use and Invest In? | ✅ Hẹn giờ | Post ID 101, slug `is-bitcoin-safe`, 10:30 UTC (17:30 VN) |
| BTA-006 | Bitcoin vs Traditional Currency: What's the Difference? | ✅ Hẹn giờ | Post ID 103, slug `bitcoin-vs-traditional-currency`, 11:30 UTC (18:30 VN) |
| BTA-007 | Bitcoin vs Gold: Which Is the Better Store of Value? | ✅ Hẹn giờ | Post ID 105, slug `bitcoin-vs-gold`, 12:30 UTC (19:30 VN) |
| BTA-008 | What Is Cryptocurrency? Understanding the Basics | ✅ Hẹn giờ | Post ID 107, slug `what-is-cryptocurrency`, 13:30 UTC (20:30 VN) |
| BTA-009 | What Makes Bitcoin Valuable? | ✅ Hẹn giờ | Post ID 109, slug `what-makes-bitcoin-valuable`, 14:30 UTC (21:30 VN) |
| BTA-010 | How Many Bitcoins Are There? Understanding Bitcoin's Supply | ✅ Hẹn giờ | Post ID 111, slug `how-many-bitcoins-are-there`, 15:30 UTC (22:30 VN) |
| BTA-011 | What Is Bitcoin's 21 Million Coin Limit? | ✅ Hẹn giờ | Post ID 113, slug `bitcoin-21-million-coin-limit`, 16:30 UTC (23:30 VN) |
| **BTA-012** | (xem Roadmap Nhóm 2: How It Works, BTA-041–080 chưa chi tiết — cần soạn chi tiết BTA-012–040 trước khi viết tiếp) | ❌ **Chưa viết** | **← Việc tiếp theo: chi tiết hoá tiêu đề BTA-012→040 trong Roadmap trước khi viết** |

Tất cả BTA-002→011 dùng CTA "Continue Learning" trỏ về `/category/bitcoin-academy/` (chưa trỏ
thẳng bài kế tiếp vì cả loạt được đăng cùng lúc trong 1 batch, không có thứ tự "đã tồn tại trước"
rõ ràng giữa chúng — có thể cập nhật lại sau nếu muốn liên kết tuần tự chặt hơn).

## Crypto Market News — đã đăng 2026-07-27 và 2026-07-28

15 bài tin đã đăng (5 bài 2026-07-27 + 1 bài publish ngay + 9 bài hẹn giờ ngày 2026-07-28).
Xem chi tiết qua `wp post list --category_name=crypto-market-news` trên site — không dùng
roadmap ID cố định, chỉ cần đối chiếu ngày đăng để tránh trùng sự kiện khi viết bài mới.

## Quy tắc cho các bài viết tiếp theo

- Viết tuần tự BTA-001 → BTA-002 → ... không nhảy cóc.
- Internal link chỉ trỏ tới ID đã có dấu ✅ ở trên tại thời điểm viết.
- Đăng qua SSH/WP-CLI (Phần 11 Master Content Prompt), không dùng REST API cho nội dung bài dài.
- Category gán đúng theo bảng ID đã tạo (Bitcoin Academy = 7, Fundamentals = 8, ...).
- Sau khi đăng bài mới, cập nhật lại bảng này (đổi ❌ → ✅) và cân nhắc cập nhật CTA "Continue
  Learning" của bài trước đó để trỏ thẳng sang bài mới thay vì trỏ về category archive.
