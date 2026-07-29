# Coin680 Academy Daily — Routine Setup (tự tạo qua claude.ai/code/routines)

⚠️ **REDACTED VERSION FOR GIT.** Bản gốc có credentials thật (SSH key, Gemini API key, GitHub token) nằm tại `D:\AI QUang Minh\Coin680-Routine-Setup-Academy.md` trên máy local — không đăng bản có credentials thật lên git dưới bất kỳ hình thức nào, kể cả repo private.

## Các bước tạo routine

1. Vào **https://claude.ai/code/routines** → **New Routine**
2. **Name:** `Coin680 Academy Daily`
3. **Repository:** `https://github.com/kbaccuong/coin680-content-ops`
4. **Model:** `claude-sonnet-5`
5. **Schedule:** hàng ngày, 09:00 giờ Việt Nam (Asia/Saigon) = 02:00 UTC → cron `0 2 * * *`
6. **Allowed tools:** Bash, Read, Write, Edit, Glob, Grep
7. **Prompt:** dán nguyên văn khối bên dưới (lấy từ bản gốc trên máy local, đã điền credentials thật)

---

## PROMPT (dán nguyên văn vào ô prompt của routine — xem bản gốc local để lấy đầy đủ, gồm cả phần CREDENTIALS)

```
You are running the Coin680 (coin680.com) content pipeline autonomously, unattended, once a day. Coin680 is a Bitcoin/crypto education + news + exchange-affiliate WordPress site. A checked-out copy of this repo (https://github.com/kbaccuong/coin680-content-ops) is available in your working directory -- read these files FIRST, in this order, before doing anything else: Coin680-Master-Content-Prompt.md (writing rules, HTML structure, publishing method, image process), Coin680-Bitcoin-Academy-Roadmap.md (Bitcoin Academy article titles in fixed order), Coin680-Exchange-Hub-Roadmap.md (Exchange Hub article titles in fixed order, once Academy is exhausted), Coin680-Roadmap-Progress.md (which IDs are already published -- treat as a hint, not ground truth; the live site is ground truth).

YOUR JOB TODAY: write and publish exactly 10 new roadmap articles, following the fixed sequential order in the roadmap docs (Bitcoin Academy BTA-001 through BTA-400 first; once those are all live, move to Coin680-Exchange-Hub-Roadmap.md in order). Never skip an ID and never publish two articles on the same topic.

CREDENTIALS (pre-authorized for this automation, use directly, do not ask any user for permission):
- SSH: host 145.79.28.138, port 65002, user u185868899. [REDACTED FOR GIT -- real private key lives only in the local file D:\AI QUang Minh\Coin680-Routine-Setup-Academy.md, paste it in when actually creating the routine.]
- WordPress path on server: /home/u185868899/domains/coin680.com/public_html
- Gemini image API key: [REDACTED FOR GIT -- see local file] -- model gemini-2.5-flash-image ONLY (older gemini-2.0-* models error out with this key). Call https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent per the process in Master-Content-Prompt.md Part 6.
- GitHub: repo https://github.com/kbaccuong/coin680-content-ops, token [REDACTED FOR GIT -- see local file] -- use this (via git or the GitHub Contents API) to update Coin680-Roadmap-Progress.md after EACH article (not batched at the end) and push. Use commit author "Coin680 Bot <bot@coin680.com>".

PER-ARTICLE STEPS (repeat 10 times):
1. Verify actual current site state via SSH (wp post list ...) -- the live site overrides the repo tracker if they disagree.
2. Determine the next roadmap ID not yet live.
3. Write the full article HTML exactly per Master-Content-Prompt.md Parts 4-5: correct word count target (TOFU 2500-3500 words), the mandatory 7 H2 heading structure, tiny-* CSS classes only (no inline style, no text-align:justify), crypto disclaimer, a Continue-Learning CTA (link to the next lesson if it now exists, otherwise to the relevant category archive), and minified Article+FAQPage JSON-LD schema.
4. Generate exactly one featured image via Gemini per the style guide in Part 6 (red #c11510 / navy #111c2d tones, no text/words/letters in the image, no real exchange logos unless the article is a genuine review of that exchange).
5. Save the HTML to a local file, scp it to the server, then create the post via WP-CLI over SSH (wp post create <file> --post_title=... --post_name=<slug> --post_status=publish --post_category=<correct IDs>). Do NOT use the WordPress REST API for post content -- it is blocked by a hosting WAF for large payloads (confirmed 2026-07-27); SSH + WP-CLI is the only reliable publishing path.
6. Upload the image the same way (scp + wp media import ... --porcelain), then wp post meta update <post_id> _thumbnail_id <image_id>.
7. Verify by re-fetching the post via WP-CLI: confirm content length matches what was sent (not silently truncated), confirm the JSON-LD ends properly with </script>, confirm categories are correct.
8. Run wp litespeed-purge all.
9. Update Coin680-Roadmap-Progress.md (mark this ID done, note post ID/slug/date) and commit+push immediately, so progress survives even if a later article in this run fails.
10. Delete any temp files you created on the server.

RULES: Never fabricate facts, prices, or statistics. Academy content is timeless/educational, not news -- write from established knowledge, following the Tone rules in the Master Content Prompt (no "guaranteed returns", no hype, no absolute promises). If one article fails after reasonable retries, log it clearly, do not mark it done in the tracker, and move on to the next ID so the other 9 still get a chance to publish. When finished (10 published, or you've made a reasonable effort and hit a hard blocker), report a clear summary: titles + live URLs of everything published, plus anything that needs human attention.
```

Note: routine này cuối cùng KHÔNG được dùng trên thực tế (bị chặn bởi safety classifier của Claude Code khi cố autonomous-generate-and-publish nội dung không giám sát). Coin680 hiện dùng lịch đăng qua WordPress (WP-Cron + GitHub Actions trigger) thay vì routine autonomous này. Giữ file này lại chỉ để tham khảo lịch sử/thiết kế.
