# Pejman Aslani Asterisk Toolkit

یک فریم‌ورک جامع، مدرن و حرفه‌ای برای PHP که تعامل با استریسک را از طریق رابط‌های AGI (FastAGI) و AMI ساده و لذت‌بخش می‌کند. این فریم‌ورک با معماری رویدادمحور (Event-driven) و غیرهمزمان (Asynchronous) ساخته شده تا بالاترین سطح از کارایی و انعطاف‌پذیری را ارائه دهد.

### ✨ قابلیت‌های کلیدی

  * **ماژول FastAGI قدرتمند:** برای ساخت IVR های پیچیده و تعاملی با عملکرد بالا.
  * **کلاینت AMI مدرن:** مبتنی بر ReactPHP برای مانیتورینگ زنده و کنترل کامل استریسک.
  * **معماری رویدادمحور:** به راحتی به رویدادهای زنده استریسک (تماس جدید، قطع تماس، وضعیت داخلی‌ها و...) گوش دهید.
  * **مبتنی بر Promise:** ارسال دستورات به AMI و دریافت پاسخ آن‌ها به صورت غیرهمزمان و بدون بلاک کردن برنامه.
  * **کتابخانه توابع کمکی (Helpers):** شامل ابزارهای سطح بالا مانند `Menu` و `Authentication` برای AGI و توابع ساده برای دستورات رایج AMI.
  * **پشتیبانی از PSR-3 Logger:** قابلیت تزریق هر نوع لاگر استاندارد (مانند Monolog) برای لاگ‌نویسی حرفه‌ای.
  * **مدیریت تنظیمات آسان:** با استفاده از فایل `.env` برای جداسازی کامل تنظیمات از کد.
  * **اتصال مجدد خودکار:** کلاینت AMI به صورت خودکار در صورت قطع شدن ارتباط، تلاش برای اتصال مجدد می‌کند.

-----

## 🏁 شروع سریع

### نیازمندی‌ها

  * PHP 8.2 یا بالاتر
  * Composer
  * دسترسی به یک سرور Asterisk
  * فعال بودن رابط‌های Manager (AMI) و AGI در استریسک

### ۱. نصب

این فریم‌ورک را از طریق Composer به پروژه خود اضافه کنید:

```bash
composer require pejman-aslani/asterisk-toolkit
```

### ۲. تنظیمات

یک فایل `.env` در ریشه پروژه خود بسازید و تنظیمات مربوط به AGI و AMI را در آن وارد کنید. می‌توانید از فایل `.env.example` به عنوان الگو استفاده کنید.

**.env**

```dotenv
# --- FastAGI Server Configuration ---
AGI_LISTEN_ADDRESS="tcp://127.0.0.1:4573"

# --- AMI Client Configuration ---
AMI_HOST="127.0.0.1"
AMI_PORT=5038
AMI_USER="your_ami_user"
AMI_SECRET="your_ami_secret"

# --- General Configuration ---
LOG_LEVEL="INFO"
```

-----

## 📞 ماژول AGI (برای ساخت IVR)

ماژول AGI برای مدیریت منطق تماس‌های ورودی و ساخت منوهای صوتی تعاملی (IVR) طراحی شده است. این ماژول از طریق یک سرور دائمی FastAGI کار می‌کند که بالاترین عملکرد را تضمین می‌کند.

### راه‌اندازی سرور FastAGI

یک فایل اجرایی (مثلاً `bin/fastagi-server.php`) بسازید. این سرور به تماس‌های ورودی از استریسک گوش می‌دهد و آن‌ها را به اپلیکیشن IVR شما تحویل می‌دهد.

**`bin/fastagi-server.php`**

```php
#!/usr/bin/php -q
<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use PejmanAslani\Asterisk\Agi\AGI;
use PejmanAslani\Asterisk\Agi\Application\MainMenuIVR; // اپلیکیشن IVR شما
use PejmanAslani\Asterisk\Agi\Connection\FastAgiConnection;
use PejmanAslani\Asterisk\Agi\Exception\ConnectionException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Load Config & Logger ---
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$listenAddress = $_ENV['AGI_LISTEN_ADDRESS'] ?? 'tcp://127.0.0.1:4573';
$log = new Logger('FastAGIServer');
$log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// --- Server Startup & Loop ---
$log->info("Starting FastAGI server on {$listenAddress}");
$serverSocket = stream_socket_server($listenAddress, $errno, $errstr);
if (!$serverSocket) { $log->critical("Could not start server: $errstr"); exit(1); }

$ivrHandler = new MainMenuIVR(); // ساخت یک نمونه از اپلیکیشن IVR شما

while (true) {
    $clientSocket = @stream_socket_accept($serverSocket, -1);
    if ($clientSocket) {
        try {
            $agi = new AGI(new FastAgiConnection($clientSocket), $log);
            $ivrHandler->handle($agi, $log); // تحویل تماس به اپلیکیشن
        } catch (ConnectionException $e) {
            $log->info("Connection closed by peer.", ['reason' => $e->getMessage()]);
        } catch (Throwable $e) {
            $log->error("Uncaught exception.", ['error' => $e->getMessage()]);
        }
    }
}
```

### ساخت یک اپلیکیشن IVR

منطق IVR خود را در یک کلاس جداگانه پیاده‌سازی کنید.

**`src/Agi/Application/MainMenuIVR.php`**

```php
<?php
namespace PejmanAslani\Asterisk\Agi\Application;

use PejmanAslani\Asterisk\Agi\AGI;
use PejmanAslani\Asterisk\Agi\Helpers\Menu;
use Psr\Log\LoggerInterface;

class MainMenuIVR implements IVRApplicationInterface
{
    public function handle(AGI $agi, LoggerInterface $log): void
    {
        $agi->answer();
        $menu = new Menu($agi);
        $menu->prompt('ivr-menu')
             ->option('1', fn(AGI $agi) => $agi->streamFile('ivr-sales'))
             ->option('2', fn(AGI $agi) => $agi->streamFile('ivr-support'))
             ->onInvalid(fn(AGI $agi) => $agi->streamFile('ivr-invalid'))
             ->execute();
        $agi->hangup();
    }
}
```

-----

## 🖥️ ماژول AMI (برای مانیتورینگ و کنترل)

ماژول AMI به شما اجازه می‌دهد به صورت زنده به تمام رویدادهای استریسک گوش دهید و دستورات مدیریتی مانند ایجاد تماس جدید را صادر کنید.

### راه‌اندازی کلاینت AMI

یک فایل اجرایی (مثلاً `bin/ami-server.php`) بسازید. این اسکریپت به صورت دائمی به AMI متصل می‌ماند.

**`bin/ami-server.php`**

```php
#!/usr/bin/php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use PejmanAslani\Asterisk\Ami\AmiClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use React\EventLoop\Loop;

// --- Load Config, Logger, Loop ---
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$amiConfig = [
    'host' => $_ENV['AMI_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['AMI_PORT'] ?? 5038),
    'username' => $_ENV['AMI_USER'] ?? '',
    'secret' => $_ENV['AMI_SECRET'] ?? '',
];
$log = new Logger('AmiClient');
$log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
$loop = Loop::get();

// --- Application ---
$amiClient = new AmiClient($loop, $amiConfig, $log);

// 1. گوش دادن به رویدادها
$amiClient->on('newchannel', function(array $event) use ($log) {
    $log->info("New call detected from: {$event['CallerIDNum']}");
});
$amiClient->on('hangup', fn(array $event) => $log->info("Call on channel {$event['Channel']} ended."));

// 2. ارسال دستور پس از اتصال
$amiClient->on('connect', function(AmiClient $client) use ($log) {
    $log->info("AMI Client Connected. Originating a call in 5 seconds...");
    Loop::addTimer(5, function() use ($client, $log) {
        $client->originate('SIP/101', 'from-internal', 's', 1, ['CallerID' => 'My PHP App'])
            ->then(
                fn($res) => $log->info("Originate action successful!"),
                fn($err) => $log->error("Originate action failed!", $err)
            );
    });
});

// --- Start ---
$amiClient->connect();
$loop->run();
```

### توابع کمکی پرکاربرد AMI

کلاینت AMI شامل توابع ساده‌ای برای دستورات پیچیده است. تمام این توابع یک **Promise** برمی‌گردانند.

  * `$client->originate(channel, context, exten, priority, options)`: ایجاد یک تماس جدید.
  * `$client->hangup(channel)`: قطع کردن یک کانال فعال.
  * `$client->coreShowChannels()`: دریافت لیست تمام کانال‌های فعال.
  * `$client->pjsipShowEndpoints()`: دریافت لیست تمام داخلی‌های PJSIP.
  * `$client->queueStatus(queueName)`: دریافت وضعیت یک صف.
  * `$client->queueAdd(queue, interface, ...)`: اضافه کردن یک اپراتور به صف.
  * `$client->queueRemove(queue, interface)`: حذف اپراتور از صف.
  * `$client->queuePause(queue, interface, paused)`: قرار دادن اپراتور در حالت استراحت.

-----

## 📜 لایسنس

این فریم‌ورک تحت لایسنس MIT منتشر شده است. برای اطلاعات بیشتر فایل [LICENSE](https://www.google.com/search?q=LICENSE) را مطالعه کنید.