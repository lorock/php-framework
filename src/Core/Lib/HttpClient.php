<?php
namespace Core\Lib;

/**
 * http客户端工具
 *
 * 使用示例：
 * ```php
 * // get请求
 * $request = new HttpClient('http://httpbin.org/get', HttpClient::HTTP_GET);
 * $request->setParams(['foo' => 'bar']);
 * $result = $request->getJsonBody();
 *
 * // post请求
 * $request = new HttpClient('http://httpbin.org/post', HttpClient::HTTP_POST);
 * $postData = ['username' => 'test'];
 * $request->setParams($postData);
 * $result = $request->getJsonBody();
 *
 * // 上传文件
 * $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
 * $content = 'hello world';
 * file_put_contents($tmpFile, $content);
 * $request = new HttpClient($this->testUrl, HttpClient::HTTP_POST);
 * $request->setFile('file', $tmpFile);
 * $body = $request->getBody();
 *
 * // 获取返回cookies
 * $request = new HttpClient('http://httpbin.org/cookies/set?k2=v2&k1=v1');
 * $cookies = $request->getCookies();
 *
 * // cookie jar
 * $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
 * $request = new HttpClient('http://httpbin.org/cookies/set?k2=v2&k1=v1');
 * $request->setCookieJar($tmpFile);
 * $request->getResponse();
 * $request->reset();
 * $request->setUrl('http://httpbin.org/cookies');
 * $cookies = $request->getJsonBody()['cookies'];
 *
 * // http base auth
 * $request = new HttpClient('http://httpbin.org/basic-auth/user/passwd');
 * $request->setBasicAuth('user', 'passwd');
 * $request->getResponseCode();
 *
 * // debug
 * $request = new HttpClient('http://httpbin.org/basic-auth/user/passwd');
 * $request->setDebug();
 * $request->setBasicAuth('user', 'passwd');
 * $request->getResponse();
 * echo $request->getDebugInfo();
 * ```
 *
 * @author sijie.li
 * @package Core\Lib
 */
class HttpClient
{
    /**
     * http方法
     */
    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';
    const HTTP_PUT = 'PUT';
    const HTTP_PATCH = 'PATCH';
    const HTTP_DELETE = 'DELETE';

    /**
     * 是否调试模式
     * @var bool
     */
    private $debug = false;

    /**
     * 调试信息
     * @var resource
     */
    private $debugInfo = null;

    /**
     * 请求URL
     * @var string
     */
    private $url;

    /**
     * 请求http方法
     * @var string
     */
    private $method;

    /**
     * 设置信息
     * @var array
     */
    private $settings = [
        'connect_timeout' => 10, // 连接超时时间
        'timeout' => 30, // curl执行超时时间
        'user_agent' => 'HttpClient', // 请求的UserAgent
    ];

    /**
     * 请求头信息
     * @var array
     */
    private $headers = [];

    /**
     * 请求cookies信息
     * @var array
     */
    private $cookies = [];

    /**
     * 保存cookie信息的文件名
     * @var string
     */
    private $cookieJar = '';

    /**
     * 请求体
     * @var null|string
     */
    private $body = null;

    /**
     * 请求参数
     * @var array
     */
    private $params = [];

    /**
     * 上传的文件
     * @var array
     */
    private $postFiles = [];

    /**
     * http基础验证帐号密码信息
     * @var array
     */
    private $baseAuth = [];

    /**
     * 原始输出内容
     * @var string
     */
    private $response = null;

    /**
     * 输出http头信息
     * @var array
     */
    private $responseHeaders = [];

    /**
     * 输出的cookie信息
     * @var array
     */
    private $responseCookies = [];

    /**
     * 输出内容
     * @var string
     */
    private $responseBody = '';

    /**
     * 响应状态码
     * @var int
     */
    private $responseCode = 0;

    public function __construct($url, $method = self::HTTP_GET)
    {
        $this->setUrl($url, $method);
    }

    /**
     * 设置调试开关
     * @param bool|true $bool
     */
    public function setDebug($bool = true)
    {
        $this->debug = (bool)$bool;
    }

    /**
     * 获取调试信息
     * @return string
     */
    public function getDebugInfo()
    {
        return $this->debugInfo;
    }

    /**
     * 设置请求url和方法
     * @param string $url
     * @param string $method
     */
    public function setUrl($url, $method = self::HTTP_GET)
    {
        if (!in_array($method, [self::HTTP_GET, self::HTTP_PUT, self::HTTP_POST, self::HTTP_DELETE, self::HTTP_PATCH])) {
            throw new \InvalidArgumentException("method '{$method}' not allowed.");
        }
        $this->url = $url;
        $this->method = $method;
    }

    /**
     * 设置超时时间
     * @param int $connectTimeout 连接超时时间/秒
     * @param int $timeout curl执行超时时间
     * @return $this
     */
    public function setTimeout($connectTimeout, $timeout)
    {
        $this->settings['connect_timeout'] = intval($connectTimeout);
        $this->settings['timeout'] = intval($timeout);
        return $this;
    }

    /**
     * 设置userAgent
     * @param string $userAgent
     * @return $this
     */
    public function setUserAgent($userAgent)
    {
        $this->settings['user_agent'] = $userAgent;
        return $this;
    }

    /**
     * 设置http基础验证的帐号密码
     * @param string $username
     * @param string $password
     */
    public function setBasicAuth($username, $password)
    {
        $this->baseAuth = [$username, $password];
    }

    /**
     * 设置请求头信息
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * 设置请求cookie
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setCookie($key, $value)
    {
        $this->cookies[$key] = $value;
        return $this;
    }

    /**
     * 设置保存cookie信息的文件路径
     * 设置后，如果服务器有输出cookie信息，将保存到该文件，后续的请求中将发送到请求服务器。
     * @param string $filePath
     */
    public function setCookieJar($filePath)
    {
        $this->cookieJar = $filePath;
    }

    /**
     * 设置请求体
     * @param string|resource|\SplFileObject $data
     * @return $this
     */
    public function setBody($data)
    {
        if (is_resource($data)) {
            rewind($data);
            $this->body = stream_get_contents($data);
            fclose($data);
        } elseif ($data instanceof \SplFileObject) {
            $data->rewind();
            $this->body = $data->fread($data->getSize());
        } else {
            $this->body = (string)$data;
        }
        return $this;
    }

    /**
     * 设置请求参数
     * @param array $data
     * @return $this
     */
    public function setParams(array $data)
    {
        $this->params = $data;
        return $this;
    }

    /**
     * 设置要上传的文件
     * @param string $formName 表单名称
     * @param string $filePath 文件路径
     * @return $this
     */
    public function setFile($formName, $filePath)
    {
        if ($this->method != self::HTTP_POST) {
            throw new \RuntimeException(__FUNCTION__ . " not allowed in '{$this->method}' method.");
        }
        if (!is_file($filePath)) {
            throw new \RuntimeException("file not exists: {$filePath}");
        }
        $this->postFiles[$formName] = $filePath;
        return $this;
    }

    /**
     * 获取返回的内容
     * @return string
     */
    public function getBody()
    {
        $this->doRequest();
        return $this->responseBody;
    }

    /**
     * 返回json格式内容
     * @return array
     */
    public function getJsonBody()
    {
        $this->doRequest();
        return json_decode($this->responseBody, true);
    }

    /**
     * 获取返回http头信息
     * @return array
     */
    public function getHeaders()
    {
        $this->doRequest();
        return $this->responseHeaders;
    }

    /**
     * 获取返回的cookie信息
     * @return array
     */
    public function getCookies()
    {
        $this->doRequest();
        return $this->responseCookies;
    }

    /**
     * 获取原始返回数据
     * @return string
     */
    public function getResponse()
    {
        $this->doRequest();
        return $this->response;
    }

    /**
     * 获取响应状态码
     * @return int
     */
    public function getResponseCode()
    {
        $this->doRequest();
        return $this->responseCode;
    }

    /**
     * 将结果保存到文件
     * @param $file
     * @return int
     */
    public function saveToFile($file)
    {
        $this->doRequest();
        return file_put_contents($file, $this->responseBody);
    }

    /**
     * 执行请求
     * @throws \RuntimeException
     */
    private function doRequest()
    {
        if ($this->response !== null) {
            return;
        }
        $url = $this->url;
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_HEADER => true,
            CURLOPT_SAFE_UPLOAD => true,
            CURLOPT_CONNECTTIMEOUT => $this->settings['connect_timeout'],
            CURLOPT_TIMEOUT => $this->settings['timeout'],
            CURLOPT_USERAGENT => $this->settings['user_agent'],
        ];
        // 调试模式
        if ($this->debug) {
            $options[CURLOPT_VERBOSE] = true;
            $options[CURLOPT_STDERR] = fopen('php://temp', 'w+');
        }
        // https不进行证书验证
        if (substr($url, 0, 6) == 'https:') {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = false;
        }
        // http base auth
        if (!empty($this->baseAuth)) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = implode(':', $this->baseAuth);
        }
        // 处理自定义http请求头
        if (!empty($this->headers)) {
            $headers = [];
            foreach ($this->headers as $key => $val) {
                $headers[] = "{$key}: {$val}";
            }
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        // 处理请求cookies
        if (!empty($this->cookies)) {
            $cookies = [];
            foreach ($this->cookies as $key => $val) {
                $cookies[] = urlencode($key) . '=' . urlencode($val);
            }
            $options[CURLOPT_COOKIE] = implode('; ', $cookies);
        }
        if (!empty($this->cookieJar)) {
            $options[CURLOPT_COOKIEJAR] = $this->cookieJar;
            $options[CURLOPT_COOKIEFILE] = $this->cookieJar;
        }
        // 处理不同的请求方式
        if ($this->method == self::HTTP_GET) {
            if (!empty($this->params)) {
                $queryString = http_build_query($this->params);
                if (strpos($url, '?') !== false) {
                    $url .= '&' . $queryString;
                } else {
                    $url .= '?' . $queryString;
                }
            }
        } else {
            switch ($this->method) {
                case self::HTTP_POST:
                    $options[CURLOPT_POST] = true;
                    break;
                case self::HTTP_PUT:
                    $options[CURLOPT_PUT] = true;
                    break;
                default:
                    $options[CURLOPT_CUSTOMREQUEST] = $this->method;
                    break;
            }
            if ($this->body) {
                $options[CURLOPT_POSTFIELDS] = $this->body;
            } else {
                $postData = $this->params;
                foreach ($this->postFiles as $key => $value) {
                    $postData[$key] = new \CURLFile($value);
                }
                $options[CURLOPT_POSTFIELDS] = $postData;
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $this->response = curl_exec($ch);
        if (isset($options[CURLOPT_STDERR])) {
            rewind($options[CURLOPT_STDERR]);
            $this->debugInfo = stream_get_contents($options[CURLOPT_STDERR]);

        }
        if ($errno = curl_errno($ch)) {
            throw new \RuntimeException("curl error: " . curl_error($ch) . "(" . $errno . ")");
        }
        $this->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = mb_substr($this->response, 0, $headerSize);
        $this->parseHeaders($rawHeaders);
        $this->responseBody = mb_substr($this->response, $headerSize);
        curl_close($ch);
    }

    /**
     * 解析http头信息
     * @param $rawHeaders
     * @return array
     */
    private function parseHeaders($rawHeaders)
    {
        $rawHeaders = str_replace("\r\n", "\n", $rawHeaders);
        $headerCollection = explode("\n\n", trim($rawHeaders));
        $rawHeaders = array_pop($headerCollection);
        $headerComponents = explode("\n", $rawHeaders);
        foreach ($headerComponents as $line) {
            if (strpos($line, ': ') === false) {
                $headers['http_code'] = $line;
            } else {
                list($key, $value) = explode(': ', $line);
                if ($key == 'Set-Cookie') {
                    $cookie = $this->parseCookie($value);
                    $this->responseCookies[$cookie['name']] = $cookie['value'];
                } else {
                    $this->responseHeaders[$key] = $value;
                }
            }
        }
    }

    /**
     * cookie解析
     * @param string $string
     * @return array cookie信息
     */
    private function parseCookie($string)
    {
        $cookie = [
            'name' => '',
            'value' => '',
            'expire' => 0,
            'domain' => null,
            'path' => null,
            'secure' => false,
            'httponly' => false,
        ];
        foreach (explode('; ', $string) as $idx => $part) {
            if (strpos($part, '=') === false) {
                switch (strtolower($part)) {
                    case 'httponly':
                        $cookie['httponly'] = true;
                        break;
                    case 'secure':
                        $cookie['secure'] = true;
                        break;
                }
            } else {
                list($key, $val) = explode('=', $part, 2);
                switch (strtolower($key)) {
                    case 'path':
                        $cookie['path'] = $val;
                        break;
                    case 'domain':
                        $cookie['domain'] = $val;
                        break;
                    case 'expires':
                        $cookie['expire'] = strtotime($val);
                        break;
                    default:
                        if ($idx == 0) {
                            $cookie['name'] = urldecode($key);
                            $cookie['value'] = urldecode(trim($val, '"'));
                        }
                }
            }
        }
        return $cookie;
    }

    /**
     * 重置请求
     * 清除掉输出的信息，以便再次发送请求
     */
    public function reset()
    {
        $this->response = null;
        $this->responseHeaders = [];
        $this->responseCookies = [];
        $this->responseBody = '';
        $this->debugInfo = '';
    }

    public function __clone()
    {
        $this->reset();
    }
}
