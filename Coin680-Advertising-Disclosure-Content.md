# Advertising Disclosure — nội dung sẵn sàng dán vào WordPress Page

Tạo 1 Page mới trong WordPress (Pages → Add New), slug đặt đúng `advertising-disclosure` để khớp
với link tự động chèn ở footer + banner tự động trong theme (`functions.php`), tiêu đề "Advertising
Disclosure", dán nội dung bên dưới vào phần content (dùng Custom HTML block hoặc Classic Editor).

---

```html
<p>Coin680 is an independent Bitcoin and cryptocurrency education website. This page explains how
we fund our work and how that may relate to the content you read.</p>

<h2>How We Make Money</h2>
<p>Some pages on Coin680 — primarily articles in our Exchange Comparison and Exchange Reviews
sections (Binance, Bybit, OKX, BingX, Gate, MEXC) — contain affiliate links. If you click one of
these links and sign up for an account, Coin680 may earn a commission from the exchange. This
comes at no additional cost to you.</p>

<h2>Editorial Independence</h2>
<p>Affiliate relationships do not influence the factual accuracy of our content. Our Bitcoin
Academy articles are purely educational and contain no affiliate links or sponsored placements.
Exchange reviews are written to reflect publicly available information about fees, features, and
account types; we do not accept payment in exchange for a favorable review or a guaranteed
ranking.</p>

<h2>How to Identify Sponsored Content</h2>
<p>Any article that contains affiliate links displays a short notice near the top of the page
linking back to this disclosure. Exchange Comparison and Exchange Reviews articles should always
be read with the understanding that Coin680 may benefit financially if you open an account through
a link in that article.</p>

<h2>No Financial Advice</h2>
<p>Nothing on Coin680, including content in affiliate-linked articles, constitutes financial,
investment, or trading advice. Cryptocurrency markets are highly volatile and involve significant
risk of loss. Always do your own research and consult a licensed financial advisor before making
any investment decisions. See also our <a href="/risk-disclaimer/">Risk Disclaimer</a>.</p>

<h2>Questions</h2>
<p>If you have questions about this disclosure or about any specific article, contact us via our
<a href="/contact/">Contact page</a>.</p>

<p><em>Last updated: [ngày bạn xuất bản trang này].</em></p>
```

---

## Ghi chú

- Theme (`coin680-theme.zip`) đã tự động chèn 1 banner disclosure ngắn lên đầu mọi bài thuộc
  category Exchange Comparison / Binance / Bybit / OKX / BingX / Gate / MEXC, link thẳng về trang
  này — không cần tự thêm banner thủ công vào từng bài.
- Trang `/risk-disclaimer/` và `/contact/` được nhắc tới ở đây và trong footer theme — cần tạo 2
  trang này (nội dung đơn giản hơn, tôi có thể soạn khi bạn cần, hiện chưa soạn vì bạn chỉ yêu cầu
  Advertising Disclosure trước).
