<?php

namespace main\app\ctrl;

use main\app\classes\AgileLogic;
use main\app\classes\OriginLogic;
use main\app\model\issue\IssueFileAttachmentModel;
use main\app\model\issue\IssueModel;
use main\app\model\OriginModel;
use main\app\model\project\ProjectModel;
use main\app\model\project\ProjectVersionModel;

class Backlog extends BaseUserCtrl
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * index
     */
    public function index()
    {
        $data = [];
        $data['title'] = 'Backlog';
        $data['nav_links_active'] = 'backlog';
        $data['sub_nav_active'] = 'all';
        $this->render('gitlab/agile/backlog.php', $data);
    }

    /**
     *  fetch backlog
     */
    public function fetch()
    {
        $projectId = null;
        if (isset($_GET['_target'][2])) {
            $projectId = (int)$_GET['_target'][2];
        }
        if (isset($_GET['id'])) {
            $projectId = (int)$_GET['id'];
        }
        if (empty($projectId)) {
            $this->ajaxFailed('failed,params_error');
        }
        $issueLogic = new AgileLogic();
        $data['backlogs'] = $issueLogic->getBacklogs();
        $data['sprints'] = $issueLogic->getSprints();

        $projectVersionModel = new ProjectVersionModel();
        $data['versions'] = $projectVersionModel->getByProject($projectId);


        $this->ajaxSuccess('success', $data);
    }

    public function fetchProjects($id = null)
    {
        if (isset($_GET['_target'][2])) {
            $id = (int)$_GET['_target'][2];
        }
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
        }

        $model = new OriginModel();
        $origin = $model->getById($id);
        if (empty($origin)) {
            $this->ajaxFailed('failed,server_error');
        }

        $model = new ProjectModel();
        $projects = $model->getsByOrigin($id);

        $data = [];
        $data['projects'] = $projects;

        $this->ajaxSuccess('success', $data);
    }

    public function fetchAll()
    {
        $data = [];
        $originLogic = new OriginLogic();
        $origins = $originLogic->getOrigins();

        $projectModel = new ProjectModel();
        $projects = $projectModel->getAll();

        $originProjects = [];
        foreach ($projects as $p) {
            $originProjects[$p['origin_id']][] = $p;
        }
        foreach ($origins as &$origin) {
            $id = $origin['id'];
            $origin['projects'] = [];
            $origin['is_more'] = false;
            if (isset($originProjects[$id])) {
                $origin['projects'] = $originProjects[$id];
                if (count($origin['projects']) > 10) {
                    $origin['is_more'] = true;
                    $origin['projects'] = array_slice($origin['projects'], 0, 10);
                }
            }
        }
        unset($projects, $originProjects);
        $data['origins'] = $origins;
        $this->ajaxSuccess('success', $data);
    }


    public function create()
    {
        $data = [];
        $data['title'] = '创建组织';
        $data['nav_links_active'] = 'origin';
        $data['sub_nav_active'] = 'all';

        $data['id'] = '';
        $data['action'] = 'add';
        $this->render('gitlab/origin/form.php', $data);
    }

    public function edit($id = null)
    {
        $data = [];
        $data['title'] = '编辑组织';
        $data['nav_links_active'] = 'origin';
        $data['sub_nav_active'] = 'all';

        if (isset($_GET['_target'][2])) {
            $id = (int)$_GET['_target'][2];
        }
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
        }
        $data['id'] = $id;
        $data['action'] = 'edit';
        $this->render('gitlab/origin/form.php', $data);
    }

    public function get($id = null)
    {
        if (isset($_GET['_target'][2])) {
            $id = (int)$_GET['_target'][2];
        }
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
        }
        $model = new OriginModel();
        $origin = $model->getById($id);
        if (empty($origin)) {
            $this->ajaxFailed('failed,server_error');
        }

        if (strpos($origin['avatar'], 'http://') === false) {
            $origin['avatar'] = ROOT_URL . $origin['avatar'];
        }

        $data = [];
        $data['origin'] = $origin;

        $this->ajaxSuccess('success', $data);

    }

    /**
     * 添加origin
     */
    public function add($params = [])
    {
        // @todo 判断权限:全局权限和项目角色
        $uid = $this->getCurrentUid();

        if (!isset($params['path']) || empty(trimStr($params['path']))) {
            $this->ajaxFailed('param_error:path_is_null');
        }
        if (!isset($params['name']) || empty(trimStr($params['name']))) {
            $this->ajaxFailed('param_error:name_is_null');
        }
        $path = $params['path'];
        $model = new OriginModel();
        $origin = $model->getByPath($path);
        if (isset($origin['id'])) {
            $this->ajaxFailed('path_exists');
        }
        $name = $params['name'];
        $origin = $model->getByName($name);
        if (isset($origin['id'])) {
            $this->ajaxFailed('name_exists');
        }

        $info = [];
        $info['path'] = $params['path'];
        $info['name'] = $params['name'];
        $info['description'] = $params['description'];
        if (isset($params['fine_uploader_json']) && !empty($params['fine_uploader_json'])) {
            $avatar = json_decode($params['fine_uploader_json'], true);
            if (isset($avatar[0]['uuid'])) {
                $uuid = $avatar[0]['uuid'];
                $fileModel = new IssueFileAttachmentModel();
                $file = $fileModel->getByUuid($uuid);
                if (isset($file['file_name'])) {
                    $info['avatar'] = $file['file_name'];
                }
            }
        }

        $info['scope'] = $params['scope'];
        $info['created'] = time();
        $info['create_uid'] = $uid;

        list($ret, $insertId) = $model->insert($info);
        if (!$ret) {
            $this->ajaxFailed('failed,error:' . $insertId);
        }

        $this->ajaxSuccess('success');
    }

    public function update($params)
    {
        // @todo 判断权限:全局权限和项目角色
        $id = null;
        if (isset($_GET['_target'][2])) {
            $id = (int)$_GET['_target'][2];
        }
        if (isset($_REQUEST['id'])) {
            $id = (int)$_REQUEST['id'];
        }
        if (!$id) {
            $this->ajaxFailed('id_is_null');
        }

        $model = new OriginModel();
        $origin = $model->getById($id);

        $info = [];
        if (isset($params['name'])) {
            $info['name'] = $params['name'];
        }

        if (isset($params['description'])) {
            $info['description'] = $params['description'];
        }

        if (isset($params['fine_uploader_json']) && !empty($params['fine_uploader_json'])) {
            $avatar = json_decode($params['fine_uploader_json'], true);
            if (isset($avatar[0]['uuid'])) {
                $uuid = $avatar[0]['uuid'];
                $fileModel = new IssueFileAttachmentModel();
                $file = $fileModel->getByUuid($uuid);
                if (isset($file['file_name'])) {
                    $info['avatar'] = $file['file_name'];
                }
            }
        }

        $noModified = true;
        foreach ($info as $k => $v) {
            if ($v != $origin[$k]) {
                $noModified = false;
            }
        }
        if ($noModified) {
            $this->ajaxSuccess('success');
        }

        if (!empty($info)) {
            $info['updated'] = time();
        }

        list($ret, $affectedRows) = $model->updateById($id, $info);
        if (!$ret) {
            $this->ajaxFailed('update_failed,error:' . $id);
        }

        $this->ajaxSuccess('success');
    }

}
