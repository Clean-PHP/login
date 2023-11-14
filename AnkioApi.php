<?php
/*******************************************************************************
 * Copyright (c) 2022. Ankio. All Rights Reserved.
 ******************************************************************************/

/**
 * Package: app\utils
 * Class AnkioApi
 * Created By ankio.
 * Date : 2023/4/28
 * Time : 14:35
 * Description :
 */

namespace library\login;

use cleanphp\base\Config;
use cleanphp\base\Json;
use cleanphp\base\Request;
use cleanphp\file\Log;
use library\http\HttpClient;
use library\http\HttpException;

class AnkioApi
{
    private static ?AnkioApi $instance = null;

    public SsoConfig $config;

    public function __construct()
    {
        $this->config = new SsoConfig(Config::getConfig("notice"));
    }

    static function getInstance(): ?AnkioApi
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function request($url, $data = [])
    {
        try {
            $headers = [
                'Client-Ip' => Request::getClientIP(),
                'User-Agent' => Request::getHeaderValue('User-Agent') ?? 'NO UA'
            ];
            $data['t'] = time();
            $data['appid'] = $this->config->id;
            $response = HttpClient::init($this->config->url)->setHeaders($headers)->post(SignUtils::sign($data, $this->config->key), 'form')->send($url);
            return Json::decode($response->getBody(), true);
        } catch (HttpException $e) {
            Log::record("API", $e->getMessage(), Log::TYPE_ERROR);
            return ['code' => 500, 'msg' => '服务器错误'];
        }
    }

    /**
     * 调用API发邮件
     * @param string $mailto
     * @param string $subject
     * @param string $content
     * @param string $fromname
     * @return mixed|true
     */
    static function sendMail(string $mailto, string $subject, string $content, string $fromname): mixed
    {
        $data = self::getInstance()->request("api/mail/send", [
            "mailto" => $mailto,
            "subject" => $subject,
            "fromname" => $fromname,
            "content" => base64_encode($content)
        ]);
        if ($data["code"] == 200) return true;
        return $data["msg"];
    }

    static function ai($content)
    {
        $data = self::getInstance()->request("api/ai/msg", [
            "content" => base64_encode($content)
        ]);
        return [$data["data"], $data["code"]];
    }

}