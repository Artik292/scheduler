# Домен esgribu.lv через Cloudflare

Для текущего сайта используется Cloudflare Worker с Custom Domains. Посетители открывают `https://esgribu.lv`, а Worker передаёт запросы существующему Cloud Run по HTTPS. Формы, cookies и адреса страниц сохраняются. `www.esgribu.lv` перенаправляется на `esgribu.lv` с сохранением пути и параметров.

Исходный код Worker: [cloudflare/worker.mjs](../cloudflare/worker.mjs). Он передаёт POST-запросы и cookies, сохраняет ответы приложения и возвращает внутренние перенаправления на публичный домен. Кеширование запросов к приложению отключено.

На момент проверки домен уже делегирован Cloudflare, а DNS-записи сайта для `esgribu.lv` и `www.esgribu.lv` отсутствуют.

## 1. Создать Worker

1. Откройте [Cloudflare Dashboard](https://dash.cloudflare.com/) и выберите аккаунт с доменом `esgribu.lv`.
2. Перейдите в **Compute → Workers & Pages → Create application** и создайте Worker из примера **Hello World**.
3. Укажите имя, например `esgribu-proxy`, и выполните **Deploy**.
4. Откройте **Edit code**, замените код целиком содержимым [cloudflare/worker.mjs](../cloudflare/worker.mjs) и снова нажмите **Deploy**.
5. Если доступна настройка **Compatibility date**, используйте `2026-10-27` или более новую дату.

Сборка PHP и секреты приложения в Cloudflare не нужны: приложение продолжает работать в Cloud Run.

## 2. Подключить домен

Откройте созданный Worker и выберите вкладку **Domains** в верхнем горизонтальном меню, между **Issues** и **Access**. На этой вкладке выберите добавление **Custom Domain**.

В прежней версии панели этот раздел находился в **Settings → Domains & Routes**.

Добавьте по очереди два имени:

```text
esgribu.lv
www.esgribu.lv
```

Указывайте только имена доменов, без `https://`, пути и `/*`. При добавлении Custom Domain Cloudflare создаёт DNS-запись и сертификат HTTPS. Дождитесь активации обоих доменов и сертификатов.

Если Cloudflare сообщает о конфликте с существующей CNAME-записью для одного из этих имён, сначала проверьте и удалите именно конфликтующую запись сайта, затем повторите добавление Custom Domain.

Описание настройки в [документации Cloudflare Custom Domains](https://developers.cloudflare.com/workers/configuration/routing/custom-domains/).

## 3. Проверить HTTPS

В настройках зоны `esgribu.lv` откройте **SSL/TLS → Overview** и выберите **Full (strict)**. Исходящий запрос Worker направляется на адрес Cloud Run с действующим сертификатом Google.

Проверьте три адреса:

```text
https://esgribu.lv/healthz.php
https://esgribu.lv/
https://www.esgribu.lv/
```

Первый должен вернуть `ok`, второй — открыть приложение, третий — перенаправить на основной домен. Проверьте вход администратора и преподавателя, отображение групп и преподавателей. После перехода на новый домен потребуется войти заново: cookies прежнего домена `run.app` действуют для прежнего адреса.

## 4. Учесть лимит Workers

На тарифе Workers Free действует общий лимит аккаунта **100 000 запросов в сутки**. В этом варианте через Worker проходят запросы страниц, JavaScript, CSS и изображений. При превышении лимита Cloudflare возвращает ошибку 1027. Перед массовым открытием записи оцените число запросов в аналитике Worker и при необходимости выберите подходящий тариф.

Актуальные ограничения: [Cloudflare Workers limits](https://developers.cloudflare.com/workers/platform/limits/).
