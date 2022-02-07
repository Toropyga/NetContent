# NetContent
Получение данных из интернета

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v3.1.0-blue.svg)
![PHP](https://img.shields.io/badge/php-v5.5_--_v8-blueviolet.svg)

# Содержание

- [Общие понятия](#общие-понятия)
- [Возможности класса NetContent](#Возможности-класса-NetContent)
- [Установка](#Установка)
- [Описание работы](#описание-работы)
    - [Основные функции](#Основные-функции)
    - [Дополнительные функции](#Дополнительные-функции)

# Общие понятия

Класс NetContent предназначен для взаимодействия и получения контента с удалённых ресурсов.
Для работы необходимо наличие PHP версии 5 и выше.

# Возможности класса NetContent

Данный клас может использоваться для получения HTML страниц, изображений и файлов из сети интернет.

Класс может подключаться к удалённым ресурсам с использованием функций библиотеки cURL,
функций прямого взаимодействия (socket), стандартной функции file_get_contents() и функции file().

Поддерживает авторизацию на Proxy-серверах.

Позволяет настраивать и отправлять произвольные параметры заголовков

Позволяет определить кодировку текста, даже если не отработала функция mb_detect_encoding

Может осуществлять конвертирование текста в заданную кодировку

Поддерживает определение MIME TYPE файла при неработающей стандартной функции mime_content_type

Может осуществлять протоколирование всех действий.


# Установка

Рекомендуемый способ установки библиотеки NetContent с использованием [Composer](http://getcomposer.org/):

```bash
composer require toropyga/netcontent
```
или просто скачайте и сохраните библиотеку в нужную директорию.

# Описание работы

## Основные функции
Подключение файла класса
```php
require_once("NetContent.php");
```
или с использованием composer
```php
require_once("vendor/autoload.php");
```

Инициализация класса
```php
$net = new FYN\NetContent();
```
> **Внимание!!!**
>
> В классе есть значения используемые по умолчанию. Изменение всех параметров
по умолчанию можно произвести в блоке переменных "Переменные настройки класса". Или
через специальные функции класса которые описаны ниже.

Запрос контента с внешнего ресурса осуществляется через функцию getContent()
```php
$net->getContent('https://www.site.com');
```
Функция getContent принимает несколько параметров:
```php
@param string $url - адрес запрашиваемого рессурса
@param int $mode - параметры обработки полученного контента:
       1 - вывести в стандартный поток ввода/вывода
       2 - обработать и вернуть как строку
       3 - парсинг HTML кода и вывод на экран
       4 - сохранить в файл
       5 - вернуть как есть, без обработки
@param mixed $data - параметры передаваемые в запросе к удалённому ресурсу
@param string $save_path - путь к директории для сохранения полученного файла относительно текущей директории или полный путь (если директория не существует, класс попытается её создать)
@param string $save_name - имя полученного файла при сохранении
```
Пример:
```php
$net->getContent('https://www.site.com', 4, 'files', 'index.html');
```

Для включения/выключения функций отладки (логирования всех действий в файл лога) используется функция setDebug
```php
$net->setDebug(true|false);
```
Имя файла лога задаётся в переменной $log_file или константе NET_LOG_NAME

Предварительная настройка взаимодействия осуществляется через следующие функции:
```php
$net->setType($type)                                - Устанавливаем тип подключения (CURL - библиотека cURL, SOCKET - через socket, FGC - функция file_get_contents, FILE - функция fopen)
$net->setProxyUse (true|false)                      - Работать или нет через прокси сервер
$net->setProxy ($address, $port, $user, $password)  - Настройка параметров взаимодействия с Proxy-сервером
$net->setNCTimeOut($time_in_seconds)                - Установка времени ожидания ответа от сервера
$net->setMethod('GET|POST')                         - Установка метода передачи данных при подключении к запрашиваемому URL (GET или POST)
$net->setUser($user, $password)                     - Установка имени пользователя и пароля используемых при подключении к удалённому серверу
$net->setNCAuth ($type, $key, $use)                 - Установка заголовка авторизации на удалённом сервере (type -  тип авторизации, $key - ключ авторизации, $use - добавлять или не добавлять логин и пароль в адресную строку)
$net->setHeaderCURL(true|false)                     - Устанавливаем параметр получать или нет для обработки заголовки при работе с CURL
$net->setHeaders($header, $value)                   - Установка значений дополнительных заголовков, используемых при подключении
$net->setOPTcURL($option, $value)                   - Установка конфигурационных параметров для библиотеки cURL
```
Более подробное описание приведено ниже по каждой функции

Класс поддерживает настройку через заранее установленные константы:
```php
NET_DEBUG               - включение/выключение отладки
NET_TYPE                - тип используемого подключения
NET_USE_PROXY           - работать или нет через Proxy-сервер
NET_PROXY_ADDRESS       - адрес Proxy-сервера
NET_PROXY_PORT          - порт Proxy-сервера
NET_PROXY_USER          - пользователь Proxy-сервера
NET_PROXY_PASSWD        - пароль пользователя Proxy-сервера
NET_TIMEOUT             - время ожидания ответа от сервера
NET_METHOD              - метод передачи данных при подключении к запрашиваемому URL (GET или POST)
NET_PROTOCOL            - протокол взаимодействия по умолчанию (http, https и т.п.)
NET_NOT_SECURITY        - использовать небезопасное соединение в модуле cURL
NET_LOG_NAME            - имя файла логов
```

## Дополнительные функции 

Возвращает путь к последнему сохранённому файлу
```php
$path = $net->getLastSavedPath();
```
Определение MIME TYPE файла (используется при неработающей стандартной функции mime_content_type)
```php
@param $filename - путь к файлу
$mime = $net->get_mime_content_type($filename);
```