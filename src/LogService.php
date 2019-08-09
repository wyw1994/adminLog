<?php

namespace xww\adminLog;

use Detection\MobileDetect;
use xww\ip\Ip;
use xww\models\Log;
use Yii;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

/**
 * Class LogService
 * @package services\common
 * @author jianyan74 <751393839@qq.com>
 */
class LogService
{
    public static $actionName = [];
    public static $controllerName = [];

    /**
     * 状态码
     *
     * @var int
     */
    private $statusCode;

    /**
     * 状态内容
     *
     * @var string
     */
    private $statusText;

    /**
     * 报错详细数据
     *
     * @var array
     */
    private $errData = [];

    /**
     * 唯一标识
     *
     * @var string
     */
    private $req_id;

    /**
     * 不记录的状态码
     *
     * @var array
     */
    public $exceptCode = [];

    /**
     * 日志记录
     *
     *
     * @param $response
     * @param bool $showReqId
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \Exception
     */
    public function record($response, $showReqId = false)
    {
        // 判断是否记录日志
        if (Yii::$app->params['user.log'] && in_array($this->getLevel($response->statusCode), Yii::$app->params['user.log.level'])) {
            $req_id = uniqid();

            // 检查是否报错
            if ($response->statusCode >= 300 && $exception = Yii::$app->getErrorHandler()->exception) {
                $this->errData = [
                    'type' => get_class($exception),
                    'file' => method_exists($exception, 'getFile') ? $exception->getFile() : '',
                    'errorMessage' => $exception->getMessage(),
                    'line' => $exception->getLine(),
                    'stack-trace' => explode("\n", $exception->getTraceAsString()),
                ];

                $showReqId && $response->data['req_id'] = $req_id;
            }

            $this->statusCode = $response->statusCode;
            $this->statusText = $response->statusText;
            $this->req_id = $req_id;
            //var_dump(!in_array($this->statusCode, \yii\helpers\ArrayHelper::merge($this->exceptCode, Yii::$app->params['user.log.except.code'])));exit;
            // 排除状态码
            if (!in_array($this->statusCode, ArrayHelper::merge($this->exceptCode, Yii::$app->params['user.log.except.code']))) {
                $this->insertLog();
            }
        }

        return $this->errData;
    }

    /**
     * 写入日志
     */
    public function insertLog()
    {
        try {
            $log = new Log();
            $log->attributes = $this->getData();
            $log->save();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 初始化默认日志数据
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function initData()
    {
        $user_id = Yii::$app->user->id;
        if (Yii::$app->id == 'app-backend' && !Yii::$app->user->isGuest) {
            /** @var AccessToken $identity */
            $identity = Yii::$app->user->identity;
            $user_id = $identity->id ?: 0;
        }

        $url = explode('?', Yii::$app->request->getUrl());

        $data = [];
        $data['user_id'] = $user_id ?: 0;
        $data['app_id'] = Yii::$app->id;
        $data['url'] = $url[0];
        $data['get_data'] = Json::encode(Yii::$app->request->get());
        $data['header_data'] = Json::encode(ArrayHelper::toArray(Yii::$app->request->headers));

        $module = $controller = $action = '';
        $module = isset(Yii::$app->controller->module->id) ? Yii::$app->controller->module->id : Yii::$app->params['module'];
        $controller = isset(Yii::$app->controller->id) ? $controller = Yii::$app->controller->id : Yii::$app->params['controller'];
        $action = isset(Yii::$app->controller->action->id) ? $action = Yii::$app->controller->action->id : Yii::$app->params['action'];

        $route = $module . '/' . $controller . '/' . $action;

        if (!in_array($controller . '/' . $action, Yii::$app->params['user.log.noPostData'])) {
            $data['post_data'] = Json::encode(Yii::$app->request->post());
        }

        $data['device'] = self::detectVersion();
        $data['method'] = Yii::$app->request->method;
        $data['module'] = $module;
        $data['controller'] = $controller;
        $data['action'] = $action;
        $data['ip'] = ip2long(Yii::$app->request->userIP);

        return $data;
    }

    /**
     * @param int $code
     */
    public function setStatusCode(int $code)
    {
        $this->statusCode = $code;
    }

    /**
     * @param string $text
     */
    public function setStatusText(string $text)
    {
        $this->statusText = $text;
    }

    /**
     * @param $error_data
     */
    public function setErrData($error_data)
    {
        $this->errData = $error_data;
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    private function getData()
    {
        $data = $this->initData();

        $data['req_id'] = $this->req_id;
        $data['error_code'] = $this->statusCode;
        $data['error_msg'] = $this->statusText;
        $data['error_data'] = is_array($this->errData) ? Json::encode($this->errData) : $this->errData;

        return $data;
    }

    /**
     * 获取报错级别
     *
     * @param $statusCode
     * @return bool|string
     */
    private function getLevel($statusCode)
    {
        if ($statusCode < 300) {
            return 'info';
        }

        if ($statusCode >= 300 && $statusCode < 400) {
            return 'warning';
        }

        if ($statusCode >= 400) {
            return 'error';
        }

        return false;
    }

    public static function detectVersion()
    {
        $detect = new MobileDetect();
        if ($detect->isMobile()) {
            $devices = $detect->getOperatingSystems();
            $device = '';

            foreach ($devices as $key => $valaue) {
                if ($detect->is($key)) {
                    $device = $key . $detect->version($key);
                    break;
                }
            }

            return $device;
        }

        return $detect->getUserAgent();
    }
    public static function analysisIp($ip, $long = true)
    {
        if (empty($ip)) {
            return false;
        }

        if (ip2long('127.0.0.1') == $ip) {
            return '本地';
        }

        if ($long === true) {
            $ip = long2ip($ip);
            if (((int)$ip) > 1000) {
                return '无法解析';
            }
        }

        $ipData = Ip::find($ip);

        $str = '';
        isset($ipData[0]) && $str .= $ipData[0];
        isset($ipData[1]) && $str .= ' · ' . $ipData[1];
        isset($ipData[2]) && $str .= ' · ' . $ipData[2];

        return $str;
    }
}