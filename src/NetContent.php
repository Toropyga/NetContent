<?php
/**
 * Получение данных из интернета
 *
 * @author Yuri Frantsevich (FYN)
 * Date: 29/08/2011
 * Time: 14:02
 * @version 3.0.5
 * @copyright 2011-2021
 */

namespace FYN;

class NetContent {

//      +---------------------------------------------+
//      |         Переменные настройки класса         |
//      +---------------------------------------------+

    /**
     * Настройки при работе через прокси сервер
     *      address -> IP адрес или имя прокси сервера
     *      port -> порт прокси сервера
     *      user -> имя пользователя прокси сервера
     *      password -> пароль пользователя прокси сервера
     * @var array
     */
    private $proxy = array(
        'address' => '',
        'port' => '3128',
        'user' => '',
        'password' => ''
    );

    /**
     * Протокол передачи данных используемый по умолчанию
     * http, https, ftp...
     * @var string
     */
    private $protocol = 'http';

    /**
     * Использовать или нет подключение через прокси сервер
     * @var bool
     */
    private $use_proxy = false;

    /**
     * Настраиваемые параметры заголовков
     * @var array
     */
    private $headers = array();

    /**
     * Тип используемых модулей
     *      CURL - подключаемся через библиотеку cURL
     *      SOCKET - подключаемся через socket
     *      FGC - подключаемся через функцию file_get_contents (по-умолчанию)
     *      FILE - подключаемся через функцию fopen()
     * @var string
     */
    private $type = 'CURL';

    /**
     * Время ожидания ответа от сервера в секундах
     * @var integer
     */
    private $nc_timeout = 60;

    /**
     * Имя пользователя используемое при подключении к серверу
     * @var string
     */
    private $nc_user = '';

    /**
     * Пароль пользователя используемый при подключении к серверу
     * @var string
     */
    private $nc_password = '';

    /**
     * Использовать логин и пароль при подключении к серверу
     * @var bool
     */
    private $nc_user2url = true;

    /**
     * Объект подключения при использовании cURL
     * @var object
     */
    private $net = '';

    /**
     * Метод запроса - GET или POST при использовании cURL
     * @var string
     */
    private $method = 'GET';

    /**
     * Использовать небезопасное соединение в модуле cURL
     * @var bool
     */
    private $not_use_security = false;

    /**
     * Получать или нет для обработки заголовки при работе с CURL
     * @var bool
     */
    private $nc_header = false;

    /**
     * Массив настроек для соединения с использованием cURL
     * @var array
     */
    private $cURL_opt = array();

    /**
     * Порт, используемый для подключения по указанному адресу
     * @var int
     */
    private $url_port = '';

    /**
     * Протокол, используемый для подключения по указанному адресу
     * @var string
     */
    private $url_protocol = 'http';

    /**
     * Возникшие ошибки
     * @var array
     */
    private $nc_log = array();

    /**
     * Список полученных файлов сохранённых во временной дирректории
     * @var array
     */
    private $delete_files = array();

    /**
     * Путь к сохранённому файлу
     * @var string
     */
    private $saved_file = '';

    /**
     * Имя файла в который сохраняется лог
     * @var string
     */
    private $log_file = 'net.log';

    /**
     * Заголовок User Agent отсылаемый PHP
     * @var string
     */
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:66.0) Gecko/20100101 Firefox/66.0';
    //private $user_agent = 'MSIE 4\.0b2;'

    /**
     * Включить | выключить режим отладки
     * всё записываем в файл
     * @var bool
     */
    private $debug = true;

    /**
     * NetContent constructor.
     */
    public function __construct() {
        if (!defined("SEPARATOR")) {
            $separator = getenv("COMSPEC")? '\\' : '/';
            define("SEPARATOR", $separator);
        }
        if (defined("NET_DEBUG")) $this->debug = NET_DEBUG;
        ini_set('user_agent', $this->user_agent);
        $this->headers['user_agent'] = $this->user_agent;
        if (defined("NET_USE_PROXY")) $this->setProxyUse(NET_USE_PROXY);
        if (defined("NET_PROTOCOL"))$this->protocol = NET_PROTOCOL;
        if (defined("NET_PROXY_ADDRESS") && defined("NET_PROXY_PORT") && defined("NET_PROXY_USER") && defined("NET_PROXY_PASSWD")) $this->setProxy(NET_PROXY_ADDRESS, NET_PROXY_PORT, NET_PROXY_USER, NET_PROXY_PASSWD);
        if (defined("NET_METHOD")) $this->setMethod(NET_METHOD);
        if (defined("NET_NOT_SECURITY")) $this->not_use_security = NET_NOT_SECURITY;
        if (defined("NET_TIMEOUT")) $this->setNCTimeOut(NET_TIMEOUT);
        if (defined("NET_LOG_NAME")) $this->log_file = NET_LOG_NAME;
        if (defined("NET_TYPE")) $this->setType(NET_TYPE);
        return true;
    }

    /**
     * Запись в лог
     * Деструктор класса.
     */

    public function __destruct() {
        if (count($this->delete_files)) $this->clearTmp();
    }


    /**
     * Получение контента из интернета или другого компьютера
     *
     * @param string $url - адрес рессурса
     * @param int $mode - параметры обработки файла:
     *          1 - вывести в стандартный поток ввода/вывода
     *          2 - вернуть как строку
     *          3 - парсинг HTML кода и вывод на экран
     *          4 - сохранить
     *          5 - вернуть как есть
     * @param mixed $data - передаваемые параметры
     * @param string $save_path - директория для сохранения
     * @param string $save_name - имя при сохранении
     * @return mixed
     */
    function getContent ($url, $mode = 1, $data = '', $save_path = '', $save_name = '') {
        if ($this->debug) $this->nc_log[] = 'Function getContent (URL: ' . $url . ', MODE: ' . $mode . ')';
        // парсим запрошенный адрес
        $url_data = parse_url($url);
        $protocol = (isset($url_data['scheme']))?$url_data['scheme']:'http';
        $this->url_port = (isset($url_data['port']))?$url_data['port']:'';
        $user = (isset($url_data['user']))?$url_data['user']:'';
        $password = (isset($url_data['pass']))?$url_data['pass']:'';
        $url = (isset($url_data['host']))?$url_data['host']:'';
        $url .= (isset($url_data['path']))?$url_data['path']:'';
        if (isset($url_data['query']) && $url_data['query']) $url .= '?'.$url_data['query'];
        if (!$url) {
            if ($this->debug) $this->nc_log[] = 'Function getContent() ERROR. HOST not found';
            return false;
        }
        else $parse_url = $url;
        // определяемпорт по умолчанию, если не передан для подключения через сокет
        if (!$this->url_port) {
            switch ($protocol) {
                case 'https':
                case 'ssl':
                    $this->url_port = 443;
                    break;
                case 'ftp':
                    $this->url_port = 21;
                    break;
                default:
                    $this->url_port = 80;
            }
        }
        if ($this->debug) $this->nc_log[] = 'Function getContent (Port: ' . $this->url_port . ')';

        // устанавливаем параметры для авторизации (логин, пароль)
        if (!$user && $this->nc_user) $user = $this->nc_user;
        if (!$password && $this->nc_password) $password = $this->nc_password;
        if (!$protocol) $protocol = $this->protocol;
        if ($this->nc_user2url && ($user || $password)) $url = $user . '@' . $password . ':' . $url;
        $url = $protocol . '://' . $url;
        $this->url_protocol = $protocol;
        if ($this->debug) $this->nc_log[] = 'Function getContent (Protocol: ' . $this->url_protocol . ')';
        if ($this->debug) $this->nc_log[] = 'Function getContent (Connection TYPE: ' . $this->type . ')';
        // подключаемся к запрошенному ресурсу
        if ($this->type == 'CURL') $RESULT = $this->getCURL($url, $data);
        elseif ($this->type == 'FILE') $RESULT = $this->getFILE($url, $data);
        elseif ($this->type == 'SOCKET') $RESULT = $this->getSOCKET($url, $data);
        else $RESULT = $this->getFGC($url, $data);
        // если вернулась ошибка, то прерываем выполнение
        if (!$RESULT) return false;

        $mime = 'text/html';
        $file = array();
        $name = '';
        if ($mode == 1 || $mode == 2 || $mode == 3 || $mode == 4) {
            // сохраняем полученные данные в файл для дальнейшей обработки
            $tmp_file = $this->getTmpPath($url);
            $tmp_split = strtr($tmp_file, array(''.SEPARATOR.''=>'-#-'));
            $path_array = preg_split('-#-', $tmp_split);
            $name = array_pop($path_array);
            if ($this->debug) $this->nc_log[] = 'Function getContent (Save temp file path: ' . $tmp_file . ')';
            $tmp_fp = fopen($tmp_file, "w");
            fwrite($tmp_fp, $RESULT);
            fclose($tmp_fp);
            $this->delete_files[] = $tmp_file;
            $mime = function_exists("mime_content_type")?mime_content_type($tmp_file):$this->get_mime_content_type($tmp_file);
            if ($this->debug) $this->nc_log[] = 'Function getContent (File mime: ' . $mime . ')';
            $file = file($tmp_file);
        }
        // обрабатываем результат
        switch ($mode) {
            case 1:
                if ($this->debug) $this->nc_log[] = 'Function getContent (Run MODE: 1 => ' . $mode . ')';
                header("Content-Type: $mime");
                if ($this->use_proxy && ($this->type == 'SOCKET' || $this->type == 'FILE')) {
                    $print = 0;
                    foreach ($file as $num=>$line) {
                        if (preg_match("/Proxy-Connection: close/", $line)) {
                            $print = 1;
                        }
                        if ($print < 1) header(stripslashes($line));
                        elseif ($print < 3) {
                            header(stripslashes($line));
                            $print++;
                        }
                        else echo stripslashes($line);
                    }
                }
                else {
                    echo stripslashes(implode("\n", $file));
                }
                break;
            case 2:
                if ($this->debug) $this->nc_log[] = 'Function getContent (Run MODE: 2 => ' . $mode . ')';
                return implode("\n", $file);
            case 3:
                if ($this->debug) $this->nc_log[] = 'Function getContent (Run MODE: 3 => ' . $mode . ')';
                // определяем путь к запрашиваемому файлу для дальнейшего парсинга html страницы
                $tmp_url = preg_replace("/\?.+$/", "", $parse_url);
                $tmp_url_array = preg_split("/\//", $tmp_url);
                if (count($tmp_url_array) < 2) $path = preg_replace("/\/$/", "", $tmp_url);
                else {
                    $tmp = $tmp_url_array;
                    $last_name = array_pop($tmp);
                    if (!preg_match("/\.(php|htm|html|asp|pl|exe)/", $last_name)) $path = join("/", $tmp_url_array);
                    else $path = join("/", $tmp);
                }
                if ($this->nc_user2url && ($user || $password)) $path = $user . '@' . $password . ':' . $path;
                $server = $this->url_protocol . '://' . $path;

                header("Content-Type: $mime");
                $file = implode("\n", $file);
                if ($mime == 'text/html') {
                    if ($this->debug) $this->nc_log[] = 'Function getContent (GoTo HTML Parser)';
                    $file = $this->parseHTML($file, $server);
                }
                echo stripslashes($file);
                break;
            case 4:
                if ($this->debug) $this->nc_log[] = 'Function getContent (Run MODE: 4 => ' . $mode . ')';
                if ($save_path) $save_dir = $save_path;
                else $save_dir = sys_get_temp_dir();
                if (!is_dir($save_dir)) {
                    mkdir($save_dir, 0777);
                    chmod($save_dir, 0777);
                }
                if ($save_name) $name = $save_name;
                $new_file = $save_dir.SEPARATOR.$name;
                $new_f = fopen($new_file, "w");
                if ($this->use_proxy) {
                    $print = 0;
                    foreach ($file as $num=>$line) {
                        if (preg_match("/Proxy-Connection: close/", $line)) {
                            $print = 1;
                        }

                        if ($print > 0 && $print < 3) {
                            $print++;
                        }
                        elseif ($print > 2) fwrite($new_f, $line);
                    }
                }
                else {
                    fwrite($new_f, implode("\n", $file));
                }
                fclose($new_f);
                $this->saved_file = $new_file;
                break;
            case 5:
                if ($this->debug) $this->nc_log[] = 'Function getContent (Run MODE: 5 => ' . $mode . ')';
                return $RESULT;
        }
        if ($this->debug) $this->nc_log[] = 'Function getContent () END request:' . $url;
        return true;

    }

    /**
     * Включение/выключение режима отладки
     * При включенном режиме все шаги протоколируются и записываются в лог-файл
     * @param bool $debug
     * @return bool
     */
    public function setDebug($debug = false) {
        $this->nc_log[] = 'Function setDebug (' . $debug . ')';
        if (is_integer($debug) && $debug === 1 || $debug === 0) $debug = ($debug)?true:false;
        elseif (!is_bool($debug)) $debug = false;
        $this->debug = $debug;
        return true;
    }

    /**
     * Работать или нет через прокси сервер
     *
     * @param bool $use_proxy
     * @return bool
     */
    public function setProxyUse ($use_proxy = false) {
        if ($this->debug) {
            $txt = ($use_proxy)?'true':'false';
            $this->nc_log[] = 'Function setProxyUse (' . $txt . ')';
        }
        if (is_integer($use_proxy) && $use_proxy === 1 || $use_proxy === 0) $use_proxy = ($use_proxy)?true:false;
        elseif (!is_bool($use_proxy)) $use_proxy = false;
        $this->use_proxy = $use_proxy;
        return true;
    }

    /**
     * Установка настроек прокси сервера
     *
     * @param string $address
     * @param int $port
     * @param string $user
     * @param string $password
     * @return bool
     */
    public function setProxy ($address = '', $port = 3128, $user = '', $password = '') {
        if ($this->debug) $this->nc_log[] = 'Function setProxy (\'' . $address . '\', \''.$port.'\', \''.$user.'\', \''.$password.'\')';
        if (! is_numeric($port)) {
            if ($this->debug) $this->nc_log[] = 'WRONG PORT NUMBER: '.$port;
            $port = 3128;
        }
        $this->proxy = array(
            'address' => $address,
            'port' => $port,
            'user' => $user,
            'password' => $password
        );
        return true;
    }

    /**
     * Установка времени ожидания ответа от сервера
     *
     * @param int $timeout
     * @return bool
     */
    public function setNCTimeOut ($timeout = 60) {
        if ($this->debug) $this->nc_log[] = 'Function setNCTimeOut (' . $timeout . ')';
        if (! is_numeric($timeout)) {
            if ($this->debug) $this->nc_log[] = 'Function setNCTimeOut () WRONG TIMEOUT: '.$timeout;
            $timeout = 60;
        }
        else {
            $timeout = sprintf("%d", $timeout);
        }
        if ($timeout < 0) {
            if ($this->debug) $this->nc_log[] = 'Function setNCTimeOut () WRONG TIMEOUT: '.$timeout;
            $timeout = -1*$timeout;
        }
        $this->nc_timeout = $timeout;
        $this->headers['timeout'] = $timeout;
        return true;
    }

    /**
     * Установка метода передачи данных при подключении к запрашиваемому URL (GET или POST)
     * @param string $method
     * @return boolean
     */
    public function setMethod ($method = 'GET') {
        if ($this->debug) $this->nc_log[] = 'Function setMethod (' . $method . ')';
        $method = strtoupper($method);
        // ToDo другие методы (PUT, DELETE и т.д.)
        if ($method != 'GET' && $method != 'POST') $method = 'GET';
        $this->method = $method;
        $this->headers['method'] = $method;
        return true;
    }

    /**
     * Установка имени пользователя и пароля используемых при подключении к удалённому серверу
     * @param string $user
     * @param string $password
     * @return boolean
     */
    public function setUser ($user = '', $password = '') {
        if ($this->debug) $this->nc_log[] = 'Function setUser (\'' . $user . '\', \''.$password.'\')';
        $this->nc_user = $user;
        $this->nc_password = $password;
        return true;
    }

    /**
     * Установка заголовка авторизации на удалённом сервере
     * @param string $type -  тип авторизации, по умолчанию - BASIC
     * @param string $key - ключ авторизации, по умолчанию Base64 код
     * @param bool $use - добавлять или не добавлять логин и пароль в адресную строку
     * @return bool
     */
    public function setNCAuth ($type = 'BASIC', $key = '', $use = false) {
        if ($this->debug) $this->nc_log[] = 'Function setNCAuth (\'' . $type . '\', \''.$key.'\', '.$use.')';
        $type = strtoupper($type);
        if ($type == 'BASIC' && !$key) $key = base64_encode($this->nc_user.":".$this->nc_password);
        $this->headers['header'] .= "Authorization: ".$type." ".$key.PHP_EOL;
        $this->nc_user2url = $use;
        return true;
    }

    /**
     * Устанавливаем тип модулей и функций, используемых для подключения
     *      CURL - подключаемся через библиотеку cURL
     *      SOCKET - подключаемся через socket
     *      FGC - подключаемся через функцию file_get_contents (по-умолчанию)
     *      FILE - подключаемся через функцию fopen()
     * @param string $type
     * @return bool
     */
    public function setType ($type = 'FGC') {
        if ($this->debug) $this->nc_log[] = 'Function setType (\'' . $type . '\')';
        $type = strtoupper($type);
        $types = array('CURL', 'SOCKET', 'FGC', 'FILE');
        if (in_array($type, $types)) {
            if ($this->debug) $this->nc_log[] = "Function setType(): set TYPE to \"".$type."\"";
            $this->type = $type;
            return true;
        }
        else {
            if ($this->debug) $this->nc_log[] = "Function setType(): Wrong TYPE. Set TYPE to \"FGC\"";
            $this->type = 'FGC';
        }
        return false;
    }

    /**
     * Устанавливаем параметр получать или нет для обработки заголовки при работе с CURL
     * @param bool $header - принимает значения true или false
     * @return bool
     */
    public function setHeaderCURL ($header = false) {
        if ($this->debug) $this->nc_log[] = 'Function setHeaderCURL (' . $header . ')';
        if (is_integer($header) && $header === 1 || $header === 0) $header = ($header)?true:false;
        elseif (!is_bool($header)) $header = false;
        $this->nc_header = $header;
        return true;
    }

    /**
     * Установка значений дополнительных заголовков, используемых при подключении
     * @param string $header - имя заголовка
     * @param string $value - значение
     */
    public function setHeaders ($header, $value = '') {
        if ($this->debug) $this->nc_log[] = 'Function setHeaders (\'' . $header . '\', \''.$value.'\')';
        // массив используемых заголовков
        $headers = array(
            'Accept' => 'header',
            'Accept-CH' => 'header',
            'Accept-Charset' => 'header',
            'Accept-Features' => 'header',
            'Accept-Encoding' => 'header',
            'Accept-Language' => 'header',
            'Accept-Ranges' => 'header',
            'Access-Control-Allow-Credentials' => 'header',
            'Access-Control-Allow-Origin' => 'header',
            'Access-Control-Allow-Methods' => 'header',
            'Access-Control-Allow-Headers' => 'header',
            'Access-Control-Max-Age' => 'header',
            'Access-Control-Expose-Headers' => 'header',
            'Access-Control-Request-Method' => 'header',
            'Access-Control-Request-Headers' => 'header',
            'Age' => 'header',
            'Allow' => 'header',
            'Alternates' => 'header',
            'Authorization' => 'header',
            'Cache-Control' => 'header',
            'Connection' => 'header',
            'Content-Encoding' => 'header',
            'Content-Language' => 'header',
            'Content-Length' => 'header',
            'Content-Location' => 'header',
            'Content-MD5' => 'header',
            'Content-Range' => 'header',
            'Content-Security-Policy' => 'header',
            'Content-Type' => 'header',
            'Cookie' => 'header',
            'DNT' => 'header',
            'Date' => 'header',
            'ETag' => 'header',
            'Expect' => 'header',
            'Expires' => 'header',
            'From' => 'header',
            'Host' => '',
            'If-Match' => 'header',
            'If-Modified-Since' => 'header',
            'If-None-Match' => 'header',
            'If-Range' => 'header',
            'If-Unmodified-Since' => 'header',
            'Last-Event-ID' => 'header',
            'Last-Modified' => 'header',
            'Link' => 'header',
            'Location' => 'header',
            'Max-Forwards' => 'max_redirects',
            'Negotiate' => 'header',
            'Origin' => 'header',
            'Pragma' => 'header',
            'Proxy-Authenticate' => 'proxy',
            'Proxy-Authorization' => 'proxy',
            'Range' => 'header',
            'Referer' => 'header',
            'Retry-After' => 'header',
            'Sec-Websocket-Extensions' => 'header',
            'Sec-Websocket-Key' => 'header',
            'Sec-Websocket-Origin' => 'header',
            'Sec-Websocket-Protocol' => 'header',
            'Sec-Websocket-Version' => 'header',
            'Server' => 'header',
            'Set-Cookie' => 'header',
            'Set-Cookie2' => 'header',
            'Strict-Transport-Security' => 'header',
            'TCN' => 'header',
            'TE' => 'header',
            'Trailer' => 'header',
            'Transfer-Encoding' => 'header',
            'Upgrade' => 'header',
            'User-Agent' => 'user_agent',
            'Variant-Vary' => 'header',
            'Vary' => 'header',
            'Via' => 'header',
            'Warning' => 'header',
            'WWW-Authenticate' => 'header',
            'X-Content-Duration' => 'header',
            'X-Content-Security-Policy' => 'header',
            'X-DNSPrefetch-Control' => 'header',
            'X-Frame-Options' => 'header',
            'X-Requested-With' => 'header'
        );
        if (in_array($header, array_keys($headers))) {
            $key = $headers[$header];
            if (!isset($this->headers[$key])) $this->headers[$key] = '';
            $this->headers[$key] .= $header.": ".$value.PHP_EOL;
        }
    }

    /**
     * Установка конфигурационных параметров прокси для cURL
     * @return bool
     */
    private function setProxy_cURL () {
        if ($this->debug) $this->nc_log[] = 'Function setProxy_cURL ()';
        if ($this->debug) $this->nc_log[] = 'Function setProxy_cURL parameters: '.print_r($this->proxy, true);
        $this->setOPTcURL(CURLOPT_PROXY, $this->proxy['address']);
        $this->setOPTcURL(CURLOPT_PROXYUSERPWD, $this->proxy['user'].":".$this->proxy['password']);
        $this->setOPTcURL(CURLOPT_PROXYPORT, $this->proxy['port']);
        if ($this->protocol == 'https') $this->setOPTcURL(CURLOPT_HTTPPROXYTUNNEL, 1);
        //$this->setOPTcURL(CURLOPT_SSLVERSION, 6);
        //CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
        if ($this->protocol == 'https') $this->setOPTcURL(CURLOPT_SSL_VERIFYPEER, false);
        if ($this->protocol == 'https') $this->setOPTcURL(CURLOPT_SSLVERSION, 3);
        return true;
    }

    /**
     * Формирование массива конфигурационных параметров для библиотеки cURL
     * @param $option -  параметр
     * @param $value - значение
     * @return bool
     */
    public function setOPTcURL ($option, $value) {
        if ($this->debug) {
            if (is_array($value)) $dt = http_build_query($value);
            else $dt = $value;
            $this->nc_log[] = 'Function setOPTcURL ('.$option.', '.$dt.')';
        }
        // массив всех возможных параметров для cURL
        $cURL_options = array();
        //Для следующих значений параметра option, параметр value должен быть типа bool:
        @$cURL_options[CURLOPT_AUTOREFERER]             = 'bool';
        @$cURL_options[CURLOPT_BINARYTRANSFER]          = 'bool';
        @$cURL_options[CURLOPT_COOKIESESSION]           = 'bool';
        @$cURL_options[CURLOPT_CERTINFO]                = 'bool';
        @$cURL_options[CURLOPT_CONNECT_ONLY]            = 'bool';
        @$cURL_options[CURLOPT_CRLF]                    = 'bool';
        @$cURL_options[CURLOPT_DNS_USE_GLOBAL_CACHE]    = 'bool';
        @$cURL_options[CURLOPT_FAILONERROR]             = 'bool';
        @$cURL_options[CURLOPT_SSL_FALSESTART]          = 'bool';
        @$cURL_options[CURLOPT_FILETIME]                = 'bool';
        @$cURL_options[CURLOPT_FOLLOWLOCATION]          = 'bool';
        @$cURL_options[CURLOPT_FORBID_REUSE]            = 'bool';
        @$cURL_options[CURLOPT_FRESH_CONNECT]           = 'bool';
        @$cURL_options[CURLOPT_FTP_USE_EPRT]            = 'bool';
        @$cURL_options[CURLOPT_FTP_USE_EPSV]            = 'bool';
        @$cURL_options[CURLOPT_FTP_CREATE_MISSING_DIRS] = 'bool';
        @$cURL_options[CURLOPT_FTPAPPEND]               = 'bool';
        @$cURL_options[CURLOPT_TCP_NODELAY]             = 'bool';
        @$cURL_options[CURLOPT_FTPASCII]                = 'bool';
        @$cURL_options[CURLOPT_FTPLISTONLY]             = 'bool';
        @$cURL_options[CURLOPT_HEADER]                  = 'bool';
        @$cURL_options[CURLINFO_HEADER_OUT]             = 'bool';
        @$cURL_options[CURLOPT_HTTPGET]                 = 'bool';
        @$cURL_options[CURLOPT_HTTPPROXYTUNNEL]         = 'bool';
        @$cURL_options[CURLOPT_MUTE]                    = 'bool';
        @$cURL_options[CURLOPT_NETRC]                   = 'bool';
        @$cURL_options[CURLOPT_NOBODY]                  = 'bool';
        @$cURL_options[CURLOPT_NOPROGRESS]              = 'bool';
        @$cURL_options[CURLOPT_NOSIGNAL]                = 'bool';
        @$cURL_options[CURLOPT_PATH_AS_IS]              = 'bool';
        @$cURL_options[CURLOPT_PIPEWAIT]                = 'bool';
        @$cURL_options[CURLOPT_POST]                    = 'bool';
        @$cURL_options[CURLOPT_PUT]                     = 'bool';
        @$cURL_options[CURLOPT_RETURNTRANSFER]          = 'bool';
        @$cURL_options[CURLOPT_SAFE_UPLOAD]             = 'bool';
        @$cURL_options[CURLOPT_SASL_IR]                 = 'bool';
        @$cURL_options[CURLOPT_SSL_ENABLE_ALPN]         = 'bool';
        @$cURL_options[CURLOPT_SSL_ENABLE_NPN]          = 'bool';
        @$cURL_options[CURLOPT_SSL_VERIFYPEER]          = 'bool';
        @$cURL_options[CURLOPT_SSL_VERIFYSTATUS]        = 'bool';
        @$cURL_options[CURLOPT_TCP_FASTOPEN]            = 'bool';
        @$cURL_options[CURLOPT_TFTP_NO_OPTIONS]         = 'bool';
        @$cURL_options[CURLOPT_TRANSFERTEXT]            = 'bool';
        @$cURL_options[CURLOPT_UNRESTRICTED_AUTH]       = 'bool';
        @$cURL_options[CURLOPT_UPLOAD]                  = 'bool';
        @$cURL_options[CURLOPT_VERBOSE]                 = 'bool';
        @$cURL_options[CURLOPT_SSL_VERIFYHOST]          = 'bool';
        //Для следующих значений параметра option, параметр value должен быть типа integer:
        @$cURL_options[CURLOPT_BUFFERSIZE]              = 'integer';
        @$cURL_options[CURLOPT_CLOSEPOLICY]             = 'integer';
        @$cURL_options[CURLOPT_CONNECTTIMEOUT]          = 'integer';
        @$cURL_options[CURLOPT_CONNECTTIMEOUT_MS]       = 'integer';
        @$cURL_options[CURLOPT_DNS_CACHE_TIMEOUT]       = 'integer';
        @$cURL_options[CURLOPT_EXPECT_100_TIMEOUT_MS]   = 'integer';
        @$cURL_options[CURLOPT_FTPSSLAUTH]              = 'integer';
        @$cURL_options[CURLOPT_HEADEROPT]               = 'integer';
        @$cURL_options[CURLOPT_HTTP_VERSION]            = 'integer';
        @$cURL_options[CURLOPT_HTTPAUTH]                = 'integer';
        @$cURL_options[CURLOPT_INFILESIZE]              = 'integer';
        @$cURL_options[CURLOPT_LOW_SPEED_LIMIT]         = 'integer';
        @$cURL_options[CURLOPT_LOW_SPEED_TIME]          = 'integer';
        @$cURL_options[CURLOPT_MAXCONNECTS]             = 'integer';
        @$cURL_options[CURLOPT_MAXREDIRS]               = 'integer';
        @$cURL_options[CURLOPT_PORT]                    = 'integer';
        @$cURL_options[CURLOPT_POSTREDIR]               = 'integer';
        @$cURL_options[CURLOPT_PROTOCOLS]               = 'integer';
        @$cURL_options[CURLOPT_PROXYAUTH]               = 'integer';
        @$cURL_options[CURLOPT_PROXYPORT]               = 'integer';
        @$cURL_options[CURLOPT_PROXYTYPE]               = 'integer';
        @$cURL_options[CURLOPT_REDIR_PROTOCOLS]         = 'integer';
        @$cURL_options[CURLOPT_RESUME_FROM]             = 'integer';
        @$cURL_options[CURLOPT_SSL_OPTIONS]             = 'integer';
        //@$cURL_options[CURLOPT_SSL_VERIFYHOST]          = 'integer';
        @$cURL_options[CURLOPT_SSLVERSION]              = 'integer';
        @$cURL_options[CURLOPT_STREAM_WEIGHT]           = 'integer';
        @$cURL_options[CURLOPT_TIMECONDITION]           = 'integer';
        @$cURL_options[CURLOPT_TIMEOUT]                 = 'integer';
        @$cURL_options[CURLOPT_TIMEOUT_MS]              = 'integer';
        @$cURL_options[CURLOPT_TIMEVALUE]               = 'integer';
        @$cURL_options[CURLOPT_MAX_RECV_SPEED_LARGE]    = 'integer';
        @$cURL_options[CURLOPT_MAX_SEND_SPEED_LARGE]    = 'integer';
        @$cURL_options[CURLOPT_SSH_AUTH_TYPES]          = 'integer';
        @$cURL_options[CURLOPT_IPRESOLVE]               = 'integer';
        @$cURL_options[CURLOPT_FTP_FILEMETHOD]          = 'integer';
        //Для следующих значений параметра option, параметр value должен быть типа string:
        @$cURL_options[CURLOPT_CAINFO]                  = 'string';
        @$cURL_options[CURLOPT_CAPATH]                  = 'string';
        @$cURL_options[CURLOPT_COOKIE]                  = 'string';
        @$cURL_options[CURLOPT_COOKIEFILE]              = 'string';
        @$cURL_options[CURLOPT_COOKIEJAR]               = 'string';
        @$cURL_options[CURLOPT_CUSTOMREQUEST]           = 'string';
        @$cURL_options[CURLOPT_DEFAULT_PROTOCOL]        = 'string';
        @$cURL_options[CURLOPT_DNS_INTERFACE]           = 'string';
        @$cURL_options[CURLOPT_DNS_LOCAL_IP4]           = 'string';
        @$cURL_options[CURLOPT_DNS_LOCAL_IP6]           = 'string';
        @$cURL_options[CURLOPT_EGDSOCKET]               = 'string';
        @$cURL_options[CURLOPT_ENCODING]                = 'string';
        @$cURL_options[CURLOPT_FTPPORT]                 = 'string';
        @$cURL_options[CURLOPT_INTERFACE]               = 'string';
        @$cURL_options[CURLOPT_KEYPASSWD]               = 'string';
        @$cURL_options[CURLOPT_KRB4LEVEL]               = 'string';
        @$cURL_options[CURLOPT_LOGIN_OPTIONS]           = 'string';
        @$cURL_options[CURLOPT_PINNEDPUBLICKEY]         = 'string';
        @$cURL_options[CURLOPT_POSTFIELDS]              = 'mixed';
        @$cURL_options[CURLOPT_PRIVATE]                 = 'string';
        @$cURL_options[CURLOPT_PROXY]                   = 'string';
        @$cURL_options[CURLOPT_PROXY_SERVICE_NAME]      = 'string';
        @$cURL_options[CURLOPT_PROXYUSERPWD]            = 'string';
        @$cURL_options[CURLOPT_RANDOM_FILE]             = 'string';
        @$cURL_options[CURLOPT_RANGE]                   = 'string';
        @$cURL_options[CURLOPT_REFERER]                 = 'string';
        @$cURL_options[CURLOPT_SERVICE_NAME]            = 'string';
        @$cURL_options[CURLOPT_SSH_HOST_PUBLIC_KEY_MD5] = 'string';
        @$cURL_options[CURLOPT_SSH_PUBLIC_KEYFILE]      = 'string';
        @$cURL_options[CURLOPT_SSH_PRIVATE_KEYFILE]     = 'string';
        @$cURL_options[CURLOPT_SSL_CIPHER_LIST]         = 'string';
        @$cURL_options[CURLOPT_SSLCERT]                 = 'string';
        @$cURL_options[CURLOPT_SSLCERTPASSWD]           = 'string';
        @$cURL_options[CURLOPT_SSLCERTTYPE]             = 'string';
        @$cURL_options[CURLOPT_SSLENGINE]               = 'string';
        @$cURL_options[CURLOPT_SSLENGINE_DEFAULT]       = 'string';
        @$cURL_options[CURLOPT_SSLKEY]                  = 'string';
        @$cURL_options[CURLOPT_SSLKEYPASSWD]            = 'string';
        @$cURL_options[CURLOPT_SSLKEYTYPE]              = 'string';
        @$cURL_options[CURLOPT_UNIX_SOCKET_PATH]        = 'string';
        @$cURL_options[CURLOPT_URL]                     = 'string';
        @$cURL_options[CURLOPT_USERAGENT]               = 'string';
        @$cURL_options[CURLOPT_USERNAME]                = 'string';
        @$cURL_options[CURLOPT_USERPWD]                 = 'string';
        @$cURL_options[CURLOPT_XOAUTH2_BEARER]          = 'string';
        //Для следующих значений параметра option, параметр value должен быть массивом:
        @$cURL_options[CURLOPT_CONNECT_TO]              = 'array';
        @$cURL_options[CURLOPT_HTTP200ALIASES]          = 'array';
        @$cURL_options[CURLOPT_HTTPHEADER]              = 'array';
        @$cURL_options[CURLOPT_POSTQUOTE]               = 'array';
        @$cURL_options[CURLOPT_PROXYHEADER]             = 'array';
        @$cURL_options[CURLOPT_QUOTE]                   = 'array';
        @$cURL_options[CURLOPT_RESOLVE]                 = 'array';
        //Для следующих значений параметра option, параметр value должен быть потоковым
        //дескриптором (возвращаемым, например, функцией fopen()):
        @$cURL_options[CURLOPT_FILE]                    = 'resource';
        @$cURL_options[CURLOPT_INFILE]                  = 'resource';
        @$cURL_options[CURLOPT_STDERR]                  = 'resource';
        @$cURL_options[CURLOPT_WRITEHEADER]             = 'resource';
        //Для следующих значений параметра option, параметр value должен быть правильным
        //именем функции или замыканием:
        @$cURL_options[CURLOPT_HEADERFUNCTION]          = 'function';
        @$cURL_options[CURLOPT_PASSWDFUNCTION]          = 'function';
        @$cURL_options[CURLOPT_PROGRESSFUNCTION]        = 'function';
        @$cURL_options[CURLOPT_READFUNCTION]            = 'function';
        @$cURL_options[CURLOPT_WRITEFUNCTION]           = 'function';
        //Другие значения:
        @$cURL_options[CURLOPT_SHARE]                   = 'resource';

        if (in_array($option, array_keys($cURL_options))) {
            $type = $cURL_options[$option];
            $passed = false;
            switch ($type) {
                case 'bool':
                    if (is_bool($value) || $value === 0 || $value === 1) $passed = true;
                    break;
                case 'integer':
                    if (is_integer($value)) $passed = true;
                    break;
                case 'string':
                    if (is_string($value)) $passed = true;
                    break;
                case 'array':
                    if (is_array($value)) $passed = true;
                    break;
                case 'resource':
                    if (is_resource($value)) $passed = true;
                    break;
                case 'function': //ToDo подумать над проверкой
                case 'mixed':
                    $passed = true;
            }
            if ($passed) {
                $this->cURL_opt[$option] = $value;
                return true;
            }
            else {
                if ($this->debug) $this->nc_log[] = "Function setOPTcURL (): for option \"".$option."\" value has not passed type ($type). Type is ".gettype($value);
            }
        }
        else {
            if ($this->debug) $this->nc_log[] = "Function setOPTcURL (): no option \"".$option."\" in options list";
        }
        return false;
    }

    /**
     * Подключение через функцию file_get_contents()
     * @param $url - запрашиваемый URL
     * @param string $data - передаваемые параметры
     * @return false|string
     */
    private function getFGC ($url, $data = '') {
        $opt = array();
        $opt['http'] = $this->headers;
        if (is_array($data)) $data = http_build_query($data);
        if ($this->debug) $this->nc_log[] = 'Function getFGC (\''.$url.'\', \''.$data.'\')';
        if ($data) {
            $opt['http']['content'] = $data;
            $opt['http']['header'] = 'Connection: close'.PHP_EOL.'Content-Length: '.strlen($data).PHP_EOL;
        }
        $headers = stream_context_create($opt);
        $content = @file_get_contents($url, false, $headers);
        if (!$content) {
            if ($this->debug) $this->nc_log[] = 'Function  getFGC () CAN`T CONNECT TO ADDRESS :' . $url;
            return false;
        }
        return $content;
    }

    /**
     * Подключение через функцию file
     * @param $url
     * @param mixed $data - передаваемые параметры
     * @return bool|string
     */
    private function getFILE ($url, $data = '') {
        if (is_array($data)) $data = http_build_query($data);
        if ($this->debug) $this->nc_log[] = 'Function getFILE (\''.$url.'\', \''.$data.'\')';
        if ($data) $url .= "?".$data;
        $direct_fp = fopen($url, 'rb');
        if (!$direct_fp) {
            if ($this->debug) $this->nc_log[] = 'Function  getFILE () CAN`T CONNECT TO ADDRESS :' . $url;
            return false;
        }
        stream_set_timeout($direct_fp, $this->nc_timeout);
        $content = '';
        while (!feof($direct_fp)) $content .= fread($direct_fp, 4096);
        fclose($direct_fp);
        return $content;
    }

    /**
     * Подключение через сокет
     * @param $url - запрашиваемый URL
     * @param mixed $data - передаваемые параметры
     * @return string
     */
    private function getSOCKET ($url, $data = '') {
        if ($this->debug) {
            if (is_array($data)) $dt = http_build_query($data);
            else $dt = $data;
            $this->nc_log[] = 'Function getSOCKET (\''.$url.'\', \''.$dt.'\')';
        }
        $purl = parse_url($url);
        if ($this->method == 'GET' && $data) {
            if (is_array($data)) $url .= "?".http_build_query($data);
            elseif (is_string($data)) $url .= "?".$data;
            unset($data);
        }
        $path = '/';
        if (preg_match("/^([^:]+:\/\/[^\/]+)(\/)?(.+)?$/", $url, $match)) {
            $url = $match[1];
            $path = $match[2].$match[3];
        }
        if ($this->use_proxy) {
            if ($this->debug)  $this->nc_log[] = 'Function getSOCKET () connect to Proxy: ' . $this->proxy['address'] . ':' . $this->proxy['port'];
            $fp = fsockopen($this->proxy['address'], $this->proxy['port']);
            if (!$fp) {
                if ($this->debug) $this->nc_log[] = 'Function getSOCKET () CAN`T CONNECT TO PROXY :' . $this->proxy['address'] . ':' . $this->proxy['port'];
                return false;
            }
            //stream_set_timeout($proxy_fp, $this->nc_timeout);
            $host = $purl['host'];
            if ($this->debug) $this->nc_log[] = ">>> {$this->method} $path HTTP/1.1";
            fputs($fp, "{$this->method} $path HTTP/1.1" . PHP_EOL);
            if ($this->debug) $this->nc_log[] = ">>> Host: $host";
            fputs($fp, "Host: $host" . PHP_EOL);
            if ($this->debug) $this->nc_log[] = '>>> Proxy-Connection: keep-alive';
            fputs($fp, "Proxy-Connection: keep-alive" . PHP_EOL);
            if ($this->debug) $this->nc_log[] = ">>> Proxy-Authorization: Basic " . base64_encode("{$this->proxy['user']}:{$this->proxy['password']}");
            fputs($fp, "Proxy-Authorization: Basic " . base64_encode("{$this->proxy['user']}:{$this->proxy['password']}") . PHP_EOL);

            if ($this->debug) $this->nc_log[] = ">>> Connection: Close";
            fputs($fp, "Connection: Close" . PHP_EOL);
        }
        else {
            $openssl =  extension_loaded('openssl')?1:0;
            if ($this->url_protocol == 'https' && $openssl) $url = preg_replace("/^https/", "ssl", $url);
            if ($this->not_use_security || !$openssl)  {
                $url = preg_replace("/^(https|ssl)/", "http", $url);
                $this->url_port = 80;
            }
            $fp = fsockopen($url, $this->url_port);
            $purl = parse_url($url);
            $host = $purl['host'];
            if ($this->debug) $this->nc_log[] = ">>> {$this->method} $path HTTP/1.1";
            fputs($fp, "{$this->method} $path HTTP/1.1" . PHP_EOL);
            if ($this->debug) $this->nc_log[] = ">>> Host: $host";
            fputs($fp, "Host: $host" . PHP_EOL);
        }
        if (count($this->headers) && isset($this->headers['header'])) {
            $headers = preg_split("/".PHP_EOL."/", $this->headers['header']);
            foreach ($headers as $key => $value) {
                if (preg_match("/^Proxy-Authorization/", $value)) continue;
                if ($this->debug) $this->nc_log[] = '>>> ' . $value;
                fputs($fp, $value . PHP_EOL);
            }
        }

        if (isset($data)) {
            if (is_array($data)) $data = http_build_query($data);
        }
        else $data = '';
        if ($data) {
            if ($this->debug) $this->nc_log[] = '>>> Content-Length: ' . strlen($data);
            fputs($fp, 'Content-Length: ' . strlen($data) . PHP_EOL);
            if ($this->debug) $this->nc_log[] = '>>> ' . $data;
            fputs($fp, PHP_EOL . $data . PHP_EOL);
        }
        fputs($fp, PHP_EOL);
        $content = '';
        while($line = @fread($fp, 4096)) {
            //while (!feof($fp)) {
            //    $line = @fread($fp, 4096);
            if ($line && $this->debug) $this->nc_log[] = '<<< ' . $line;
            $content .= $line;
        }
        fclose($fp);
        if ($this->debug) $this->nc_log[] = "Function  getSOCKET () end connect to Socket";
        return $content;
    }

    /**
     * Подключение с использованием cURL
     * @param $url - запрашиваемый URL
     * @param mixed $data - передаваемые параметры
     * @return bool|string
     */
    private function getCURL ($url, $data = '') {
        if ($this->debug) {
            if (is_array($data)) $dt = http_build_query($data);
            else $dt = $data;
            $this->nc_log[] = 'Function getCURL (\''.$url.'\', \''.$dt.'\')';
        }
        if (!function_exists("curl_init")) {
            if ($this->debug) $this->nc_log[] = 'Function getCURL () ERROR: function curl_init not exists';
            return false;
        }
        $this->net = curl_init();
        if ($this->use_proxy) { // Подключаемся через прокси
            if ($this->debug) $this->nc_log[] = 'Function getCURL () go to Proxy: '.$this->proxy['address'];
            $this->setProxy_cURL();
        }
        if ($this->nc_user || $this->nc_password) {
            $userpwd = $this->nc_user.':'.$this->nc_password;
            if ($this->debug) $this->nc_log[] = 'Function getCURL () set UserPass: '.$userpwd;
            $this->setOPTcURL(CURLOPT_USERPWD, $userpwd);
        }
        $this->setOPTcURL(CURLOPT_CUSTOMREQUEST, $this->method);
        if ($this->method == 'POST') {
            if ($this->debug) $this->nc_log[] = 'Function getCURL () set Post method';
            $this->setOPTcURL(CURLOPT_POST, true);
            $this->setOPTcURL(CURLOPT_CUSTOMREQUEST, "POST");
            if ($data) {
                if ($this->debug) $this->nc_log[] = 'Function getCURL () SET DATA for POST method: '.http_build_query($data, '', '&');
                $this->setOPTcURL(CURLOPT_POSTFIELDS, $data);
            }
        }
        elseif ($data) {
            $url_array = parse_url($url);
            if (is_array($data)) $data = http_build_query($data, '', '&');
            $scheme   = isset($url_array['scheme']) ? $url_array['scheme'] . '://' : '';
            $host     = isset($url_array['host']) ? $url_array['host'] : '';
            $port     = isset($url_array['port']) ? ':' . $url_array['port'] : '';
            $user     = isset($url_array['user']) ? $url_array['user'] : '';
            $pass     = isset($url_array['pass']) ? ':' . $url_array['pass']  : '';
            $pass     = ($user || $pass) ? "$pass@" : '';
            $path     = isset($url_array['path']) ? $url_array['path'] : '';
            $query    = isset($url_array['query']) ? '?' . $url_array['query'] : '';
            $fragment = isset($url_array['fragment']) ? '#' . $url_array['fragment'] : '';
            $url = $scheme.$user.$pass.$host.$port.$path.$query.$data.$fragment;
            if ($this->debug) $this->nc_log[] = 'Function getCURL () SET DATA for GET method: '.$url;
        }
        if ($this->debug) $this->nc_log[] = 'Function getCURL (Query URL: '. $url .')';
        $this->setOPTcURL(CURLOPT_URL, $url);
        if ($this->not_use_security) {
            if ($this->debug) $this->nc_log[] = 'Function getCURL () SET SSL for not security';
            $this->setOPTcURL(CURLOPT_SSL_VERIFYHOST, false);
            $this->setOPTcURL(CURLOPT_SSL_VERIFYPEER, false);
        }
        /**
         * Альтернатива с сертификатом
         *
         * Tell cURL where our certificate bundle is located.
         * Download pem certificate https://curl.haxx.se/docs/caextract.html
         *
         * Use:
         * $certificate = "C:\wamp\cacert.pem";
         * curl_setopt($ch, CURLOPT_CAINFO, $certificate);
         * curl_setopt($ch, CURLOPT_CAPATH, $certificate);
         */
        if ($this->debug) $this->nc_log[] = 'Function getCURL () SET User-Agent: '.$this->user_agent;
        $this->setOPTcURL(CURLOPT_USERAGENT, $this->user_agent);

        if (count($this->headers) && isset($this->headers['header'])) {
            $headers = preg_split("/".PHP_EOL."/", $this->headers['header']);
            //if ($this->debug) $this->nc_log[] = 'Function getCURL () SET Headers: '.$this->headers['header'];
            if ($this->debug) $this->nc_log[] = 'Function getCURL () SET Headers: '.join (' - ', $headers);
            $this->setOPTcURL(CURLOPT_HTTPHEADER, $headers);
        }

        /*
        // curl_setopt($this->net, CURLOPT_USERAGENT, "Opera/9.80 (Windows NT 5.1; U; ru) Presto/2.9.168 Version/11.51");
        // curl_setopt($this->net, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); // If expected to call with specific PROXY type
        // curl_setopt($this->net, CURLOPT_FOLLOWLOCATION, 1);  // If url has redirects then go to the final redirected URL.
        */
        if ($this->debug) $this->nc_log[] = 'Function getCURL () SET cURL Header parameter: '.$this->nc_header;
        $this->setOPTcURL(CURLOPT_HEADER, $this->nc_header);
        if ($this->debug) $this->nc_log[] = 'Function getCURL () SET cURL Transfer parameter: '.true;
        $this->setOPTcURL(CURLOPT_RETURNTRANSFER,true);

        curl_setopt_array($this->net, $this->cURL_opt);
        $content = curl_exec($this->net);
        if ($this->debug) $this->nc_log[] = 'Function getCURL () END use cURL function';
        if (curl_error($this->net)) {
            if ($this->debug) $this->nc_log[] = "Function getCURL () cURL error: ".curl_error($this->net);
            if ($this->debug) $this->nc_log[] = print_r(curl_getinfo($this->net), true);
            return false;
        }
        curl_close($this->net);
        return $content;
    }

    /**
     * Возвращает путь к последнему сохранённому файлу
     * @return string
     */
    public function getLastSavedPath () {
        if ($this->debug) $this->nc_log[] = 'Function getLastSavedPath () => '.$this->saved_file.')';
        return $this->saved_file;
    }

    /**
     * Возвращает путь к временному файлу
     * @param $tmp_url
     * @return string
     */
    private function getTmpPath ($tmp_url) {
        if ($this->debug) $this->nc_log[] = 'Function getTmpPath ('.$tmp_url.')';
        $tmp_url = preg_replace("/\?.+$/", "", $tmp_url);
        $tmp_url_array = preg_split("/\//", $tmp_url);
        $tmp_name = array_pop($tmp_url_array);
        if (!$tmp_name) $tmp_name = date('YmdHis').'_save_tmp_index.html';
        if (preg_match("/(.+)\.([^\.\d]+)$/", trim($tmp_name), $match)) {
            $tmp_name = str_replace('.', '_', $match[1]);
            $tmp_name = $tmp_name . '.' . $match[2];
        } else {
            $tmp_name = str_replace('.', '_', $tmp_name);
            $tmp_name = $tmp_name . '.html';
        }
        $tmp_dir = sys_get_temp_dir();
        if (!file_exists($tmp_dir) || !is_dir($tmp_dir)) {
            $tmp_dir = (isset($_ENV['TMP']) && $_ENV['TMP']) ? $_ENV['TMP'] : '';
            if (!$tmp_dir) $tmp_dir = (isset($_ENV['TMPDIR']) && $_ENV['TMPDIR']) ? $_ENV['TMPDIR'] : '';
        }
        if ($tmp_dir) $tmp_file = $tmp_dir.SEPARATOR.$tmp_name;
        else $tmp_file = $tmp_name;
        return $tmp_file;
    }

    /**
     * Определение MIME TYPE файла
     * используется при неработающей стандартной функции mime_content_type
     * @param $filename - путь к файлу
     * @return mixed|string
     */
    public function get_mime_content_type ($filename) {
        if ($this->debug) $this->nc_log[] = 'Function get_mime_content_type ('.$filename.')';
        $mime_types = array(
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation'
        );
        $file_explode = explode('.',$filename);
        $ext = strtolower(array_pop($file_explode));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $file_info = finfo_open(FILEINFO_MIME);
            $mime_type = finfo_file($file_info, $filename);
            finfo_close($file_info);
            return $mime_type;
        }
        else {
            return 'application/octet-stream';
        }
    }

    /**
     * Очистка временной дирректории от полученных файлов
     * @return bool
     */
    private function clearTmp () {
        if ($this->debug) $this->nc_log[] = 'Function clearTmp ()';
        foreach ($this->delete_files as $num=>$file) {
            if (file_exists($file)) unlink($file);
        }
        $this->delete_files = array();
        return true;
    }

    /**
     * Парсинг полученной html страницы (?)
     *
     * @param string $file_content - содержимое файла
     * @param string $server_path - путь к запрошенной странице
     * @param string $local_link - путь к локальному серверу (скрипту), обрабатывающему файлы
     * @return string
     */
    private function parseHTML ($file_content, $server_path = '', $local_link = '') {
        if ($this->debug) $this->nc_log[] = 'Function parseHTML ()';
        $protocol = $this->url_protocol;
        $server = preg_replace("/(http:\/\/|ftp:\/\/|https:\/\/)/",  "", $server_path);
        $server = preg_split("/\//", $server);
        $domain = array_shift($server);
        $point = rand(100, 999);
        $point = '#K_STYLE_'.$point.'#';
        $file_content = strtr($file_content, array('.' => $point));
        //обработка стилевых ссылок
        if (preg_match_all("/(href\s?=\s?\\\?(\"|\')?)((\\\?\/?\w+)+)(#K_STYLE_\d{3}#css)/", $file_content, $styles)) {
            $cn = sizeof($styles)-1;
            foreach($styles[0] as $key=>$stl) {
                if (preg_match("/^\//", trim($styles[3][$key]))) $server_add = $protocol.$domain;
                else $server_add = $server_path.'/';
                $file_content = strtr($file_content, array($stl => $styles[1][$key].$local_link.$server_add.$styles[3][$key].$styles[$cn][$key]));
            }
        }
        //обработка фоновых картинок
        if (preg_match_all("/(background(\s)?=)((\s?\\\?\"?)(\w*\/)?)/", $file_content, $images)) {
            foreach($images[0] as $key=>$img) {
                if (preg_match("/^\//", trim($images[5][$key]))) $server_add = $protocol.$domain;
                else $server_add = $server_path.'/';
                $file_content = strtr($file_content, array($img => $images[1][$key].$images[4][$key].$local_link.$server_add.$images[5][$key]));
            }
            $file_content = strtr($file_content, array('.' => $point));
            $file_content = addslashes(stripslashes($file_content));
        }
        //обработка изображений
        if (preg_match_all("/(<img)([^.]+)(src(\s)?=)\s?([^<]+>)/U", $file_content, $images)) {
            foreach($images[0] as $key=>$img) {
                $begin = preg_replace("/^(\"|\')?([^.]+)/", "\\1", stripslashes($images[5][$key]));
                $end = preg_replace("/^(\"|\')?([^.]+)/", "\\2", stripslashes($images[5][$key]));
                $begin = addslashes($begin);
                $end = addslashes($end);
                if (preg_match("/^\//", trim($end))) $server_add = $protocol.$domain;
                else $server_add = $server_path.'/';
                $file_content = strtr($file_content, array($img => $images[1][$key].$images[2][$key].$images[3][$key].$begin.$local_link.$server_add.$end));
            }
            $file_content = strtr($file_content, array('.' => $point));
            $file_content = addslashes(stripslashes($file_content));
        }
        //обработка скриптовых ссылок
        if (preg_match_all("/(<script)([^.]+)(src(\s)?=)\s?([^<]+>)/U", $file_content, $images)) {
            foreach($images[0] as $key=>$img) {
                $begin = preg_replace("/^(\"|\')?([^.]+)/", "\\1", stripslashes($images[5][$key]));
                $end = preg_replace("/^(\"|\')?([^.]+)/", "\\2", stripslashes($images[5][$key]));
                $begin = addslashes($begin);
                $end = addslashes($end);
                if (preg_match("/^\//", trim($end))) $rel_path = $protocol.$domain;
                else $rel_path = $server_path.'/';
                $file_content = strtr($file_content, array($img => $images[1][$key].$images[2][$key].$images[3][$key].$begin.$rel_path."/".$end));
            }
            $file_content = strtr($file_content, array('.' => $point));
            $file_content = addslashes(stripslashes($file_content));
        }
        //обработка гиперссылок
        if (preg_match_all("/<a(\s?\w*)*(href\s?=\s?\\\?(\"|\')?)(\\\?\/?.+)(\\3)(.*)>/U", $file_content, $links)) {
            foreach ($links[0] as $key=>$link) {
                $links[4][$key] = strtr($links[4][$key], array('&'=>'here_amp_here'));
                if (preg_match("/(http:\/\/|https:\/\/|ftp:\/\/)/", $links[4][$key])) {
                    $link_i = $local_link.$links[4][$key].'&mode=3';
                }
                elseif (preg_match("/^\//", trim($links[4][$key]))) {
                    $link_i = $local_link.$protocol.$domain.$links[4][$key].'&mode=3';
                }
                else $link_i = $local_link.$domain.'/'.$links[4][$key].'&mode=3';
                $file_content = strtr($file_content, array($link => '<a'.$links[1][$key].$links[2][$key].$link_i.$links[5][$key].$links[6][$key].'>'));
            }
            $file_content = strtr($file_content, array('.' => $point));
            $file_content = addslashes(stripslashes($file_content));
            $file_content = strtr($file_content, array('here_amp_here'=>'&'));
        }
        $file_content = strtr($file_content, array($point => '.'));
        return $file_content;
    }

    /**
     * Возвращаем логи
     * @param bool $all - все записи (true) или только последнюю (false)
     * @return array|mixed
     */
    public function getLogs ($all = false) {
        if ($all) $return['log'] = $this->nc_log;
        else {
            $c = count($this->nc_log) - 1;
            $return['log'] = $this->nc_log[$c];
        }
        $return['file'] = $this->log_file;
        return $return;
    }
}