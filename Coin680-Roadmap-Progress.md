# Coin680 Roadmap Progress Tracker

Đối chiếu thực tế các bài đã xuất bản trên coin680.com (kiểm tra qua WP-CLI, không dựa trí nhớ)
với thứ tự cố định trong `Coin680-Bitcoin-Academy-Roadmap.md` / `Coin680-Exchange-Hub-Roadmap.md`.
Cập nhật file này mỗi khi xuất bản bài mới.

**Kiểm tra lần cuối:** 2026-07-27, qua SSH/WP-CLI (`wp post list`), post ID 71.

## ➡️ BÀI CẦN VIẾT TIẾP THEO: **BTA-002 — "How Does Bitcoin Work?"**

## Bảng trạng thái BTA-001 → BTA-040 (Nhóm 1: Fundamentals)

| ID | Tiêu đề (Roadmap) | Trạng thái | Ghi chú |
|---|---|---|---|
| BTA-001 | What Is Bitcoin? A Complete Beginner's Guide | ✅ Đã đăng | Post ID 71, slug `what-is-bitcoin`, category Bitcoin Academy + Fundamentals, đăng qua SSH/WP-CLI (xem Phần 11 Master Content Prompt). Schema Article+FAQPage đầy đủ, không bị cắt. CTA "Continue Learning" trỏ về `/category/bitcoin-academy/` (chưa trỏ thẳng BTA-002 vì bài đó chưa tồn tại — sẽ cân nhắc cập nhật lại sau khi BTA-002 live). |
| **BTA-002** | **How Does Bitcoin Work?** | ❌ **Chưa viết** | **← Viết tiếp theo** |
| BTA-003 → BTA-040 | (xem `Coin680-Bitcoin-Academy-Roadmap.md`) | ❌ Chưa viết | |

## Quy tắc cho các bài viết tiếp theo

- Viết tuần tự BTA-001 → BTA-002 → ... không nhảy cóc.
- Internal link chỉ trỏ tới ID đã có dấu ✅ ở trên tại thời điểm viết.
- Đăng qua SSH/WP-CLI (Phần 11 Master Content Prompt), không dùng REST API cho nội dung bài dài.
- Category gán đúng theo bảng ID đã tạo (Bitcoin Academy = 7, Fundamentals = 8, ...).
- Sau khi đăng bài mới, cập nhật lại bảng này (đổi ❌ → ✅) và cân nhắc cập nhật CTA "Continue
  Learning" của bài trước đó để trỏ thẳng sang bài mới thay vì trỏ về category archive.
