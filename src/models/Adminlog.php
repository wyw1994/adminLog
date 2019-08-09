<?php
/**
 * @link http://www.shuwon.com/
 * @copyright Copyright (c) 2016 成都蜀美网络技术有限公司
 * @license http://www.shuwon.com/
 */
namespace xww\models;

use Yii;
use yii\db\ActiveRecord;
/**
 * ban模型。
 * @author 制作人
 * @since 1.0
 */
class Adminlog extends ActiveRecord {

    public static function tableName() {
        return 'tbl_admin_log';
    }

    public function attributeLabels() {
        return [
    	    'log_id' => 'id',
		    'log_cate' => '分类',
		    'type' => '状态',
		    'ip' => 'ip',
		    'username' => '用户名',
		    'created_at' => '添加时间',
		    'updated_at' => '更新时间',
		    'enabled' => '状态',
		    'sort' => '排序',
		    'user_id' => '用户ID',
	        ];
    }

    public function rules() {
        return [
             [['log_cate','username','type'], 'trim'],
             [['log_cate'], 'required'],
             [['enabled'], 'number'],
             ['created_at', 'default','value'=>0], ['updated_at', 'default','value'=>0], ['enabled', 'default','value'=>1], ['sort', 'default','value'=>0], ['user_id', 'default','value'=>0], ['type', 'default','value'=>'login'],
        ];
    }

    
    public function beforeSave($insert) {
        if (parent::beforeSave($insert)) {
            if ($this->isNewRecord) {
                $this->created_at = time();
                $this->updated_at = time();
            }else{
                $this->updated_at = time();
            }
            //$this->user_id = !empty(Yii::$app->user->identity->id) ? Yii::$app->user->identity->id : 0;
            return true;
        }
        return false;
    }

}

