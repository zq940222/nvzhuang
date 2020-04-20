<?php

namespace app\admin\controller\message;

use app\common\controller\Backend;

/**
 * 消息管理
 *
 * @icon fa fa-circle-o
 */
class Message extends Backend
{
    
    /**
     * Message模型对象
     * @var \app\admin\model\Message
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Message;
        $this->view->assign("messageCategoryList", $this->model->getMessageCategoryList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isReadList", $this->model->getIsReadList());
    }

}
