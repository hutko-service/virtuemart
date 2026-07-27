# hutko payment plugin for VirtueMart

Accept payments through the hutko hosted checkout page.

[Українська версія](#платіжний-плагін-hutko-для-virtuemart)

## Compatibility

- Joomla 5.4.x
- VirtueMart 4.6.x
- PHP 8.1 or later

Joomla 6 / VirtueMart 5 has not yet been validated.

## Installation

1. In Joomla Administrator, open **System → Install → Extensions**.
2. Upload the plugin ZIP file.
3. Open **System → Plugins**.
4. Find **VM Payment - hutko**, open it, set **Status** to **Enabled**, then save.

Installing and enabling the plugin makes the `hutko` payment element available. You must then create a VirtueMart payment method for it.

## Create the payment method

1. Open **Components → VirtueMart → Payment Methods**.
2. Select **New**.
3. On **Payment Method Information**, set:

   - **Payment Name**: `hutko` (or any customer-facing name you prefer)
   - **Published**: `Yes`
   - **Payment Method**: `hutko`
   - **Shopper Group**: `Available for all` (unless you need a restriction)
   - **Currency**: choose your shop currency, for example `Ukrainian hryvnia`

4. Click **Save**.
5. Open the **Configuration** tab and enter:

   - **Merchant ID**: your hutko merchant ID
   - **Payment password**: your payment password from hutko technical settings
   - **Payment currency**: the same currency as your VirtueMart payment method
   - **Successful payment status**, **Pending payment status**, and **Failed or reversed payment status**: choose the appropriate VirtueMart order statuses

6. Click **Save & Close**.

## Checkout logo

In the same **Configuration** tab, use **Logos** to select an optional checkout logo:

- `hutko_symbol_red.png` — recommended default
- `hutko_symbol_black.png` — for light designs
- `hutko_symbol_white.png` — for dark designs
- no logo

The compact symbol is constrained to 40 × 40 px so it remains visible without disrupting the checkout layout.

## Test before production

1. Make a small payment using hutko sandbox credentials.
2. Test a successful payment and a declined payment.
3. Confirm that the VirtueMart order status changes correctly after payment.

For the order status to update automatically, your shop must be publicly reachable over HTTPS. hutko cannot deliver its server callback to `localhost`; use a public test URL or a tunnel while testing locally.

## Payment security

The plugin validates the hutko callback signature, merchant ID, amount, and currency before it updates an order. It also ignores duplicate successful callbacks.

---

# Платіжний плагін hutko для VirtueMart

Приймання платежів через платіжну сторінку hutko.

## Сумісність

- Joomla 5.4.x
- VirtueMart 4.6.x
- PHP 8.1 або новіша версія

Joomla 6 / VirtueMart 5 ще не перевірялися.

## Встановлення

1. В адміністративній панелі Joomla відкрийте **System → Install → Extensions**.
2. Завантажте ZIP-файл плагіна.
3. Відкрийте **System → Plugins**.
4. Знайдіть **VM Payment - hutko**, відкрийте його, установіть **Status** на **Enabled** та збережіть зміни.

Після встановлення й увімкнення плагіна стає доступним платіжний елемент `hutko`. Далі потрібно створити для нього платіжний метод у VirtueMart.

## Створення платіжного методу

1. Відкрийте **Components → VirtueMart → Payment Methods**.
2. Натисніть **New**.
3. На вкладці **Payment Method Information** установіть:

   - **Payment Name**: `hutko` або іншу назву, яку бачитимуть покупці
   - **Published**: `Yes`
   - **Payment Method**: `hutko`
   - **Shopper Group**: `Available for all`, якщо не потрібні обмеження
   - **Currency**: валюту вашого магазину, наприклад `Ukrainian hryvnia`

4. Натисніть **Save**.
5. Відкрийте вкладку **Configuration** та заповніть:

   - **Merchant ID**: ID мерчанта hutko
   - **Payment password**: платіжний пароль із технічних налаштувань hutko
   - **Payment currency**: ту саму валюту, що в платіжному методі VirtueMart
   - **Successful payment status**, **Pending payment status** і **Failed or reversed payment status**: потрібні статуси замовлення VirtueMart

6. Натисніть **Save & Close**.

## Логотип на сторінці оформлення

На вкладці **Configuration** у полі **Logos** можна вибрати необов'язковий логотип:

- `hutko_symbol_red.png` — рекомендований варіант
- `hutko_symbol_black.png` — для світлого дизайну
- `hutko_symbol_white.png` — для темного дизайну
- без логотипа

Компактний символ обмежений розміром 40 × 40 px, тому він помітний і не порушує макет оформлення замовлення.

## Перевірка перед запуском

1. Зробіть невеликий платіж із тестовими реквізитами hutko.
2. Перевірте успішну та відхилену оплату.
3. Переконайтеся, що після оплати статус замовлення VirtueMart оновлюється правильно.

Для автоматичного оновлення статусу замовлення сайт має бути публічно доступним через HTTPS. hutko не може надіслати серверний callback на `localhost`; під час локального тестування використовуйте публічну тестову адресу або тунель.

## Безпека платежів

Плагін перевіряє signature callback-виклику hutko, ID мерчанта, суму та валюту до оновлення замовлення. Повторні успішні callback-виклики ігноруються.
