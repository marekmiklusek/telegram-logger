![blog-featured-how-to-delete-telegram](https://github.com/user-attachments/assets/78d2e1ec-7b2d-4e59-9b07-45a08aec6a56)

# 📢 Laravel Telegram Logger

🚀 **Laravel Telegram Logger** is a package that sends **Laravel log messages** and **exceptions** to **Telegram** for real-time monitoring.

## 📌 Features

✅ **Real-time logging** to Telegram  
✅ **Supports all log levels** (`debug`, `info`, `warning`, `error`, etc.)  
✅ **Automatic exception handling** (captures error file, line, and message)  
✅ **Configurable log level filtering**  
✅ **Silent notifications support** (to avoid sound/vibration in Telegram)  
✅ **Minimal setup, easy to integrate**  
✅ **Can be enabled/disabled via configuration**

---

## 📋 Requirements

- PHP 8.4+
- Laravel 13.0+

## 🛠 Installation

Require the package via Composer:

```bash
composer require marekmiklusek/telegram-logger
```

## 🔧 Configuration

Publish the package configuration:

```bash
php artisan vendor:publish --tag=telegram-logger-config
```

This will create a config file at `config/telegram-logger.php`.

### .env Configuration

Add your Telegram Bot API token and Chat ID to your `.env` file:

```env
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

All other options are configurable via `.env` as well:

```env
TELEGRAM_LOG_LEVEL=error
TELEGRAM_LOG_SILENT=false
TELEGRAM_LOG_ENABLED=true
TELEGRAM_LOG_THROW_ON_FAILURE=false
```

### Config File (`config/telegram-logger.php`)

```php
return [
    'bot_token' => (string) env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id' => (string) env('TELEGRAM_CHAT_ID', ''),
    'level' => (string) env('TELEGRAM_LOG_LEVEL', 'error'),
    'silent_notification' => (bool) env('TELEGRAM_LOG_SILENT', false),
    'is_enabled' => (bool) env('TELEGRAM_LOG_ENABLED', true),
    'throw_on_failure' => (bool) env('TELEGRAM_LOG_THROW_ON_FAILURE', false),
];
```

## 🏗 Usage

### Basic Logging

Use Laravel's `Log` facade as usual, and errors will be sent to Telegram automatically:

```php
use Illuminate\Support\Facades\Log;

Log::debug('Debug message');
Log::info('Info message');
Log::warning('Warning message');
Log::error('Error message');
```

### Logging with Context

You can pass additional context to logs:

```php
Log::error('User not found', ['user_id' => 42, 'action' => 'login']);
```

### Logging Exceptions

Exceptions are automatically detected and logged:

```php
try {
    throw new \Exception('Database connection failed!');
} catch (\Exception $exception) {
    Log::error('Unhandled exception occurred', ['exception' => $exception]);
}
```

## ⚙ How It Works

The package listens to Laravel's logging events and sends structured messages to Telegram.

### Example Telegram Log Output

```
🛠️ Application: MyLaravelApp
🌍 Environment: production

❌ Level: ERROR
📝 Message: User not found

📌 File:
/var/www/html/app/Http/Controllers/UserController.php
🎯 Line: 45

📂 Context:
{
    "user_id": 42,
    "action": "login"
}

⏳ Time: 2025-02-19 10:15:30
```

### Example Telegram Log Output (Exception)

```
🛠️ Application: MyLaravelApp
🌍 Environment: production

❌ Level: ERROR
📝 Message: Unhandled exception occurred

🔥 Exception: PDOException
💥 Message: Database connection failed!

📌 File:
/var/www/html/app/Services/DatabaseService.php
🎯 Line: 30

⏳ Time: 2025-02-19 10:18:45
```

## 🎯 Advanced Configuration

### 1️⃣ Logger Enablement

You can enable or disable the logger in `config/telegram-logger.php`:
```php
return [
    'is_enabled' => false,
];
```

✅ If `true`, logs will be sent to Telegram as configured  
✅ If `false`, the logger will be completely disabled (no logs sent)

### 2️⃣ Log Level Filtering

You can define the minimum log level in `config/telegram-logger.php`:
```php
return [
    'level' => 'warning',
];
```

- `debug`: Logs everything
- `info`: Logs `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`
- `error`: Logs `error`, `critical`, `alert`, `emergency`
- `critical`: Logs only `critical`, `alert`, `emergency`

### 3️⃣ Silent Notifications

Enable silent notifications (no sound/vibration) in `config/telegram-logger.php`
```php
return [
    'silent_notification' => true,
];
```

✅ If `true`, messages will be sent silently.  
✅ If `false`, Telegram will send notifications normally.

### 4️⃣ Failure Reporting

By default a failed delivery is swallowed — a logger must never break the application:
```php
return [
    'throw_on_failure' => false,
];
```

Set `TELEGRAM_LOG_THROW_ON_FAILURE=true` locally to surface the actual Telegram API error
(invalid token, chat not found, rate limit) instead of silence.

If Telegram rejects the message formatting (HTTP 400), the package automatically retries once
as plain text, so the log is delivered even when formatting fails.

## 💡 Troubleshooting

❓ **Logs not appearing in Telegram?**
1. Set `TELEGRAM_LOG_THROW_ON_FAILURE=true` — the real API error will be thrown.
2. Check that your `.env` values are correctly set:
```bash
php artisan config:clear
php artisan config:cache
```
3. Ensure your bot has permission to send messages to your chat.
4. Verify `TELEGRAM_LOG_LEVEL` is not more severe than the level you are logging.

❓ **Getting "Chat not found" error?**
- Make sure you have sent a message to your bot first.
- Use [this tool](https://telegram.me/userinfobot) to get your Chat ID.

## 🧪 Testing

```bash
composer test
```

This runs the full quality chain: Pint, Rector, PHPStan (level max),
100% type coverage and the test suite with 100% code coverage.

Individual steps:

```bash
composer test:lint           # Pint code style check
composer test:refactor       # Rector dry run
composer test:types          # PHPStan level max
composer test:type-coverage  # 100% type coverage
composer test:unit           # Pest with 100% code coverage
```

To apply automatic fixes:

```bash
composer lint       # Pint
composer refactor   # Rector
```

## 📜 License

This package is open-source and licensed under the [MIT License](LICENSE).
