<?php
/*
 * Copyright (c) 2023. Ankio.  由CleanPHP4强力驱动。
 */
/**
 * Package: library\login
 * Class SsoConfig
 * Created By ankio.
 * Date : 2023/7/19
 * Time : 12:15
 * Description :
 */

namespace library\login;

use library\verity\VerityObject;
use library\verity\VerityRule;

class SsoConfig extends VerityObject
{

    public string $url = "";
    public string $id = "";
    public string $key = "";
    /**
     * @inheritDoc
     */
    function getRules(): array
    {
       return [
           "url"=>new VerityRule("^http","SSO地址错误"),
           "id"=>new VerityRule("^\w{8}$","授权id应是8位字符串"),
           "key"=>new VerityRule("^\w{32}$","授权密钥应是32位字符串"),
       ];
    }
}