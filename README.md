# 蝦皮評價助理（Shopee Auto Reviewer）

> 這是一個給賣家使用的半自動工具：
> - 先由你手動登入蝦皮（降低帳號風險）
> - 工具自動找出「待評價」訂單
> - 自動填入預設評價內容與星等
> - 最後可選擇 `--dry-run` 先演練，不真正送出

## 功能

- 支援批次處理待評價訂單
- 可設定星等與評語模板
- 內建隨機等待時間，避免過快操作
- 失敗時自動截圖到 `artifacts/` 方便除錯

## 免責聲明與風險提示

- 請先確認你的使用方式符合蝦皮平台規範與當地法規。
- 過度自動化可能導致風控、驗證碼或帳號限制。
- 建議先使用 `--dry-run` 測試流程。

## 安裝

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -m playwright install chromium
```

## 使用方式

### 1) 啟動瀏覽器並登入

```bash
python shopee_auto_reviewer.py --manual-login
```

你會有 90 秒可手動登入賣家中心。

### 2) 先演練（不送出）

```bash
python shopee_auto_reviewer.py \
  --rating 5 \
  --comment "感謝您的支持，期待再次為您服務！" \
  --max-orders 20 \
  --dry-run
```

### 3) 正式執行

```bash
python shopee_auto_reviewer.py \
  --rating 5 \
  --comment "感謝您的支持，期待再次為您服務！" \
  --max-orders 20
```

## 常用參數

- `--manual-login`：開啟後暫停等待你手動登入
- `--login-wait`：手動登入等待秒數（預設 90）
- `--rating`：評分星數（1-5，預設 5）
- `--comment`：評語文字
- `--max-orders`：最多處理幾筆待評價訂單
- `--dry-run`：只演練點擊，不按最終送出
- `--headless`：無頭模式執行

## 重要提醒

蝦皮後台頁面結構可能調整，若元素找不到：
1. 檢查網址是否仍為賣家中心「待評價」頁。  
2. 執行時觀察畫面，必要時更新程式內選擇器。  
3. 查看 `artifacts/` 的錯誤截圖協助修正。
