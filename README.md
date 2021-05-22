# express-ru-tracker

Скрипт для отслеживания посылок Express.Ru.

Отправляет обновления в Telegram.

## Usage

1. Установить зависимости через composer.

2. Запускать через cron как-то так:
    ```
    php track.php \
        --tracking-number D123456 \
        --tracking-date 2021-05-20 \
        --user-login $USER_LOGIN \
        --user-signature-key $USER_SIGNATURE_KEY \
        --user-authorization-key $USER_AUTHORIZATION_KEY \
        --telegram-chat-id $CHAT_ID \
        --telegram-token $TOKEN
    ```
   `$USER_LOGIN`, `$USER_SIGNATURE_KEY` и `$USER_AUTHORIZATION_KEY` нужно получить
   в кабинете на express.ru.
   
## License

MIT