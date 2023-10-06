<?php
/*
 * Copyright (c) 2023. Ankio. All Rights Reserved.
 */

namespace library\login\engine;

use cleanphp\App;
use cleanphp\base\EventManager;
use cleanphp\base\Json;
use cleanphp\base\Request;
use cleanphp\base\Response;
use cleanphp\base\Session;
use cleanphp\base\Variables;
use cleanphp\cache\Cache;
use cleanphp\engine\EngineManager;
use cleanphp\file\Log;
use library\login\AnkioApi;
use library\login\CallbackObject;
use library\verity\VerityException;


class SSO extends BaseEngine
{

    private ?Cache $cache;

    public function __construct()
    {
        $this->cache = Cache::init(0, Variables::getCachePath("tokens", DS));
    }

    function route($action): void
    {
        $result = EngineManager::getEngine()->render(401, '未登录');

        switch (strtolower($action)) {
            case 'logout':
            {
                $this->cache->del(arg('token', 'empty'));
                $result = EngineManager::getEngine()->render(200, '成功退出');
                break;
            }
            case 'callback':
            {
                try {
                    $object = new CallbackObject(arg(), AnkioApi::getInstance()->config->key);
                    $result = $this->callback($object);
                    if ($result === true) {
                        Response::location($object->redirect);
                    }
                } catch (VerityException $e) {
                    $result = EngineManager::getEngine()->render(403, $e->getMessage());
                }
                //进行回调
                break;
            }
        }
        (new Response())->render($result)->send();
    }

    private function callback(CallbackObject $object)
    {
        $result = $this->request('api/login/replace', ['refresh_token' => $object->code]);
        App::$debug && Log::record('SSO', Json::encode($result));
        if (isset($result['code']) && $result['code'] === 200) {
            Session::getInstance()->set('__token', $result['data']['token']);
            Session::getInstance()->set('__timeout', $result['data']['timeout']);
            $result['data']['username'] = $result['data']['nickname'];
            Session::getInstance()->set("__user", $result['data']);
            $this->cache->set($result['data']['token'], true);
            EventManager::trigger("__login_success__", $result['data']);
            return true;
        } else {
            return $result['msg'];
        }
    }

    private function request($url, $data = [])
    {
        return AnkioApi::getInstance()->request($url, $data);
    }

    function isLogin(): bool
    {
        $token = Session::getInstance()->get('__token');
        $timeout = Session::getInstance()->get("__timeout");
        // 检查必要的数据是否存在，如果不存在就返回 false
        if (empty($token) || empty($timeout) || !$this->cache->get($token)) {
            if ($token) {
                $this->cache->del($token);
            }
            return false;
        }

        // 检查是否需要续期
        if ($timeout < time() + 3600 * 6) {
            $data = $this->request('api/login/islogin', ['token' => $token]);
            if ($data['code'] === 200) {
                // 更新 __timeout 数据
                Session::getInstance()->set("__timeout", $data['data']);
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    function logout(): void
    {
        $token = Session::getInstance()->get("__token");
        if (!empty($token)) {
            $this->cache->del($token);
            $this->request('api/login/logout', ['token' => $token]);
        }
        Session::getInstance()->destroy();
    }

    /**
     * 获取登录地址
     * @return string
     */
    function getLoginUrl(): string
    {
        return AnkioApi::getInstance()->config->url . '#!login?' . http_build_query([
                'id' => AnkioApi::getInstance()->config->id,
                'redirect' => $_SERVER['HTTP_REFERER'] ?? Request::getAddress()
            ]);
    }


    function getUser(): array
    {
        return Session::getInstance()->get("__user");
    }
}