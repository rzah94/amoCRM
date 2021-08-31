<?php

class Request
{
    private $url;
    private $referer;
    private $headers;


    static public function resetIntegration($subdomain)
    {
        unlink(__DIR__ . '/files/code_' . $subdomain . '.txt');
        unlink(__DIR__ . '/files/access_token_' . $subdomain . '.txt');
        unlink(__DIR__ . '/files/refresh_token_' . $subdomain . '.txt');
    }

    public function __construct($access_token, $referer)
    {
        $this->url = 'https://' . $referer . '.amocrm.ru';

        $this->headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];

        $this->referer = $referer;
    }

    public function getReferer()
    {
        return $this->referer;
    }


    static public function writeFileContent($file, $content, $mode = 'w')
    {
        $fp = fopen($file, $mode);
        fwrite($fp, $content);
        fclose($fp);
        chmod($file, 0777);  //changed to add the zero
        return true;
    }

    static public function isNeedDeleteFile($file, $minutes): bool
    {
        if (file_exists($file)) {

            $comparTime = 60 * $minutes;

            $editTimeFile = date("Y-m-d H:i:s", filemtime($file));
            $editTimeFileUnix = strtotime($editTimeFile);
            $nowUnix = strtotime('now');

            if (($nowUnix - $editTimeFileUnix) >= $comparTime) {
                return true;
            } else {
                return false;
            }
        } else {
            // если файла не существует
            return true;
        }
    }

    static public function getAccessToken($subdomain, $redirect_uri, $client_id, $client_secret)
    {
        $code_file = __DIR__ . '/files/code_' . $subdomain . '.txt';
        $token_file = __DIR__ . '/files/access_token_' . $subdomain . '.txt';
        $refresh_file = __DIR__ . '/files/refresh_token_' . $subdomain . '.txt';

        // если нет файла access_token - получаем code
        if (!file_exists($token_file)) {

            if (@!$_GET['code']) {
                header('Location:' . "https://www.amocrm.ru/oauth?client_id={$client_id}&state=12345&mode=post_message");
            }

            if (@$_GET['code']) {
                // после переадресации создаем файл для code
                self::writeFileContent($code_file, $_GET['code']);
            }
        }

        // получаем token через code, если у нас нет refresh token
        if (!file_exists($refresh_file)) {
            $code = file_get_contents($code_file);
            $data = [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ];

            $link = 'https://' . $subdomain . '.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

            $response = self::getToken($link, $data);
            self::writeFileContent($token_file, $response['access_token']);
            self::writeFileContent($refresh_file, $response['refresh_token']);
        }

        // получаем token через refresh, если у нас есть refresh token
        if (file_exists($refresh_file)) {

            // если access_token истек - создаем новый access_token
            if (self::isNeedDeleteFile($token_file, 1400)) {
                $refresh_token = file_get_contents($refresh_file);

                $data = [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh_token,
                    'redirect_uri' => $redirect_uri,
                ];

                $link = 'https://' . $subdomain . '.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

                $response = self::getToken($link, $data);
                self::writeFileContent($token_file, $response['access_token']);
                self::writeFileContent($refresh_file, $response['refresh_token']);
            }

            // если refresh_token истек - удаляем его и  access_token
            if (self::isNeedDeleteFile($refresh_file, 43200 * 2.5)) {
                unlink($token_file);
                unlink($refresh_file);
            }
        }

        return file_get_contents($token_file);
    }


    static public function getToken($url, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);

        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::errors((int)$code);

        $response = json_decode($out, true);

        /* $access_token = $response['access_token']; //Access токен
        $refresh_token = $response['refresh_token']; //Refresh токен
        $token_type = $response['token_type']; //Тип токена
        $expires_in = $response['expires_in']; //Через сколько действие токена истекает*/

        return json_decode($out, true);
    }


    public function get($request)
    {
        $url = $this->url . $request;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::errors((int)$code);
        return $out;
    }



    public function getProstie_Zvonki($request)
    {
        $headers = array_merge($this->headers, [
            "Host: sletatonline.amocrm.ru",
            "Referer: https://sletatonline.amocrm.ru/settings/widgets/",
            'sec-ch-ua: "Google Chrome";v="89", "Chromium";v="89", ";Not A Brand";v="99',
            "sec-ch-ua-mobile: ?0",
            "Sec-Fetch-Dest: empty",
            "Sec-Fetch-Mode: cors",
            "Sec-Fetch-Site: same-origin",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36",
            "X-Requested-With: XMLHttpRequest"
        ]);

        $url = $this->url . $request;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::errors((int)$code);
        return $out;
    }



    public function post($request, $data)
    {
        $url = $this->url . $request;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::errors((int)$code);
        return $out;
    }

    public function patch($request, $data)
    {
        $url = $this->url . $request;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::errors((int)$code);
        return $out;
    }


    static private function errors($code)
    {
        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try {
            // Если код ответа не успешный - возвращаем сообщение об ошибке
            if ($code < 200 || $code > 204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        } catch (\Exception $e) {
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }
    }
}
