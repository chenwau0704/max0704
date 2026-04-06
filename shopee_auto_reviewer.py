#!/usr/bin/env python3
"""Shopee seller assistant: auto-fill buyer reviews for completed orders.

This script intentionally keeps a manual-login step and optional dry-run mode
for safer operation.
"""

from __future__ import annotations

import argparse
import random
import time
from datetime import datetime
from pathlib import Path

from playwright.sync_api import Error, TimeoutError, sync_playwright

PENDING_REVIEW_URL = "https://seller.shopee.tw/portal/sale/order?type=to_rate"
ARTIFACTS_DIR = Path("artifacts")


def random_wait(min_seconds: float = 0.6, max_seconds: float = 1.8) -> None:
    """Sleep for a random interval to reduce bot-like action frequency."""
    time.sleep(random.uniform(min_seconds, max_seconds))


def save_failure_screenshot(page, tag: str) -> None:
    ARTIFACTS_DIR.mkdir(exist_ok=True)
    ts = datetime.utcnow().strftime("%Y%m%d-%H%M%S")
    target = ARTIFACTS_DIR / f"{tag}-{ts}.png"
    page.screenshot(path=str(target), full_page=True)
    print(f"[!] 已儲存錯誤截圖：{target}")


def wait_for_manual_login(page, seconds: int) -> None:
    print(f"[*] 請在 {seconds} 秒內完成手動登入…")
    for left in range(seconds, 0, -15):
        print(f"    剩餘約 {left} 秒")
        time.sleep(min(15, left))
    print("[*] 手動登入等待結束，繼續執行。")


def find_pending_order_rows(page):
    """Return visible order card/row locators in pending-review page.

    Selectors may need update if Shopee changes the page layout.
    """
    selectors = [
        '[data-sqe="order_item"]',
        '.order-card',
        '.shopee-table__row',
    ]

    for selector in selectors:
        rows = page.locator(selector)
        try:
            count = rows.count()
        except Error:
            continue
        if count > 0:
            print(f"[*] 使用選擇器 {selector} 找到 {count} 筆候選訂單。")
            return rows

    print("[!] 找不到待評價訂單列表，請檢查頁面是否正確。")
    return page.locator("__not_found__")


def set_star_rating(row, rating: int) -> bool:
    """Try click star based on rating."""
    star_candidates = [
        f'[data-sqe="rating_star_{rating}"]',
        f'.rating-stars :nth-child({rating})',
        f'.shopee-rating-stars__star:nth-child({rating})',
    ]

    for selector in star_candidates:
        star = row.locator(selector).first
        if star.count() == 0:
            continue
        star.click(timeout=1500)
        return True

    return False


def fill_comment(row, comment: str) -> bool:
    textbox_candidates = [
        'textarea[data-sqe="review_input"]',
        'textarea[placeholder*="評價"]',
        'textarea',
    ]

    for selector in textbox_candidates:
        box = row.locator(selector).first
        if box.count() == 0:
            continue
        box.click(timeout=1200)
        box.fill(comment)
        return True

    return False


def submit_review(row, dry_run: bool) -> bool:
    submit_candidates = [
        'button[data-sqe="submit_review_btn"]',
        'button:has-text("送出")',
        'button:has-text("提交")',
    ]

    for selector in submit_candidates:
        btn = row.locator(selector).first
        if btn.count() == 0:
            continue
        if dry_run:
            print("    [dry-run] 已略過最終送出。")
            return True
        btn.click(timeout=1500)
        return True

    return False


def process_reviews(page, rating: int, comment: str, max_orders: int, dry_run: bool) -> tuple[int, int]:
    rows = find_pending_order_rows(page)
    total_rows = rows.count()
    if total_rows == 0:
        return 0, 0

    target = min(total_rows, max_orders)
    success = 0

    for i in range(target):
        row = rows.nth(i)
        print(f"[*] 處理第 {i + 1}/{target} 筆…")

        try:
            random_wait()

            if not set_star_rating(row, rating):
                print("    [x] 找不到星等元件，略過。")
                continue

            if not fill_comment(row, comment):
                print("    [x] 找不到評語輸入框，略過。")
                continue

            random_wait(0.4, 1.2)

            if not submit_review(row, dry_run):
                print("    [x] 找不到送出按鈕，略過。")
                continue

            print("    [v] 完成。")
            success += 1

        except (TimeoutError, Error) as exc:
            print(f"    [x] 發生操作錯誤：{exc}")
            save_failure_screenshot(page, f"row-{i + 1}")

    return target, success


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Shopee 賣家待評價半自動助理")
    parser.add_argument("--manual-login", action="store_true", help="啟動後等待手動登入")
    parser.add_argument("--login-wait", type=int, default=90, help="手動登入等待秒數")
    parser.add_argument("--rating", type=int, default=5, choices=range(1, 6), help="評價星數")
    parser.add_argument(
        "--comment",
        type=str,
        default="感謝您的支持，期待再次為您服務！",
        help="評語內容",
    )
    parser.add_argument("--max-orders", type=int, default=20, help="最多處理訂單數")
    parser.add_argument("--dry-run", action="store_true", help="不送出，只演練流程")
    parser.add_argument("--headless", action="store_true", help="無頭模式執行")
    return parser.parse_args()


def main() -> int:
    args = parse_args()

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=args.headless)
        context = browser.new_context(locale="zh-TW")
        page = context.new_page()

        print(f"[*] 開啟頁面：{PENDING_REVIEW_URL}")
        page.goto(PENDING_REVIEW_URL, wait_until="domcontentloaded")

        if args.manual_login:
            wait_for_manual_login(page, args.login_wait)

        try:
            page.wait_for_load_state("networkidle", timeout=8000)
        except TimeoutError:
            print("[!] 網頁長時間載入中，先繼續流程。")

        target, success = process_reviews(
            page=page,
            rating=args.rating,
            comment=args.comment,
            max_orders=args.max_orders,
            dry_run=args.dry_run,
        )

        print("\n=== 執行結果 ===")
        print(f"候選訂單：{target}")
        print(f"成功處理：{success}")
        print(f"失敗/略過：{target - success}")

        browser.close()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
