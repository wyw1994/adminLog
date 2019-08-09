<?php

namespace xww\adminLog;


/**
 * Class Application
 *
 * @package services
 * @property LogService $log 商户
 *
 * @author jianyan74 <751393839@qq.com>
 */
class Application extends Service
{
    /**
     * @var array
     */
    public $childService = [
        /** ------ 系统 ------ **/
        'log' => [
            'class' => LogService::class,
            'exceptCode' => [403] // 除了数组内的状态码不记录，其他按照配置记录
        ],
    ];
}