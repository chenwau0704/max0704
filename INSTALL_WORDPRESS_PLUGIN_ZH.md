# WordPress 新手安裝教學（One Click Simple Shop）

這份教學用最簡單方式，帶你把 `one-click-wordpress-shop.php` 安裝成可啟用的外掛。

## 1) 下載外掛檔案

你需要這個檔案：

- `one-click-wordpress-shop.php`

## 2) 在電腦建立外掛資料夾

1. 在桌面建立資料夾：`one-click-simple-shop`
2. 把 `one-click-wordpress-shop.php` 放進這個資料夾。

最後結構會像這樣：

```text
one-click-simple-shop/
└── one-click-wordpress-shop.php
```

## 3) 壓縮成 ZIP

1. 對 `one-click-simple-shop` 資料夾按右鍵。
2. 選「壓縮」或「Compress/Zip」。
3. 會得到 `one-click-simple-shop.zip`。

> 注意：不要只壓縮 php 單一檔案，請壓縮「整個資料夾」。

## 4) 進入 WordPress 後台安裝

1. 登入你的 WordPress 後台（通常是 `你的網址/wp-admin`）。
2. 左側選單點 **外掛** → **安裝外掛**。
3. 點上方 **上傳外掛**。
4. 選擇剛剛的 `one-click-simple-shop.zip`。
5. 按 **立即安裝**，完成後按 **啟用外掛**。

## 5) 啟用後會發生什麼

外掛會自動建立 3 個頁面：

- Shop
- Cart
- Checkout

並且在後台出現商品管理：

- **Products**（`ocss_product`）
- **Orders**（`ocss_order`）

## 6) 新增第一個商品

1. 後台左側找到 **Products**。
2. 點 **Add New Product**。
3. 輸入商品名稱、說明。
4. 右側/側邊欄填寫 **Product Price**。
5. 發佈。

## 7) 確認前台可用

1. 開啟 `Shop` 頁面，看是否出現商品。
2. 加入購物車後到 `Cart`。
3. 在 `Checkout` 填資料送出。
4. 後台 `Orders` 應該可看到新訂單。

## 8) 常見問題

### Q1: 上傳 ZIP 失敗？
- 請確認 ZIP 內是「資料夾 + php 檔」，不是直接一個 php。
- 主機可能限制上傳大小，可改用 FTP 上傳。

### Q2: 啟用外掛後看不到商品？
- 先到後台新增至少 1 個 Product 並發佈。

### Q3: 收不到訂單 Email？
- 先確認 WordPress 的管理員信箱是否正確。
- 很多主機需要 SMTP 外掛（例如 WP Mail SMTP）才能穩定寄信。

## 9) FTP 手動安裝（備用）

如果後台上傳失敗，可以用 FTP：

1. 連線到主機。
2. 把資料夾 `one-click-simple-shop` 上傳到：
   - `wp-content/plugins/`
3. 回 WordPress 後台 → **外掛**。
4. 找到 **One Click Simple Shop** → 按 **啟用**。

---

如果你願意，我可以下一步直接用「你的網站畫面流程」教你：
- 從哪個按鈕開始點
- 每一步按什麼
- 第一次上架商品的完整順序

## 10) 商用上線前必做（v2.0）

1. 後台 **Products → Shop Settings** 設定：
   - Currency（幣別）
   - Shipping Fee（運費）
   - Tax Rate（稅率）
   - Bank Transfer Info（匯款資訊）
2. 每個商品都要設定：
   - Price（售價）
   - Stock Qty（庫存）
3. 測試完整流程：
   - 建立測試訂單
   - 確認後台 `Orders` 有資料
   - 確認管理員與客戶都收到 Email
4. 正式上線建議：
   - 安裝 SMTP 外掛（提高寄信成功率）
   - 開啟 SSL（https）
   - 每日備份資料庫
