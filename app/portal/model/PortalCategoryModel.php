<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\portal\model;

use app\admin\model\RouteModel;
use think\Model;
use tree\Tree;

class PortalCategoryModel extends Model
{

    protected $type = [
        'more' => 'array',
    ];

    /**
     * 生成分类 select树形结构
     * @param int $selectId 需要选中的分类 id
     * @param int $currentCid 需要隐藏的分类 id
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function adminCategoryTree($selectId = 0, $currentCid = 0,  $parentIds = '')
    {
        $where = ['delete_time' => 0];
        if (!empty($currentCid)) {
            $where['id'] = ['neq', $currentCid];
        }
//        $categories = $this->order("list_order ASC")->where($where)->select()->toArray();
        if (empty ( $parentIds )){
            $categories = $this->order("list_order ASC")->where($where)->where('status = 1 and ishidden = 1')->select()->toArray();
        } else {
            $categories = db(' portal_category ')->query("
                SELECT * FROM `cmf_portal_category` WHERE `delete_time` = 0 AND id in ( $parentIds )
                union 
                SELECT * FROM `cmf_portal_category` WHERE `delete_time` = 0 AND parent_id in ( $parentIds )
                ORDER BY `list_order` ASC
            ");
        }

        $tree       = new Tree();
        $tree->icon = ['&nbsp;&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'];
        $tree->nbsp = '&nbsp;&nbsp;';

        $newCategories = [];
        foreach ($categories as $item) {
            $item['selected'] = $selectId == $item['id'] ? "selected" : "";

            array_push($newCategories, $item);
        }

        $tree->init($newCategories);
        $str     = '<option value=\"{$id}\" {$selected}>{$spacer}{$name}</option>';
        $treeStr = $tree->getTree(0, $str);

        return $treeStr;
    }

    /**
     * 分类树形结构
     * @param int $currentIds
     * @param string $tpl
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function adminCategoryTableTree($currentIds = 0, $tpl = '',  $parentIds = '')
    {
        $where = ['delete_time' => 0];
//        $where = ['id' => ['neq',391]];
        if (empty ( $parentIds )){
            $categories = $this->order("list_order ASC")->where($where)->select()->toArray();
        } else {
            $categories = db(' portal_category ')->query("
                SELECT * FROM `cmf_portal_category` WHERE `delete_time` = 0 AND id in ( $parentIds )
                union 
                SELECT * FROM `cmf_portal_category` WHERE `delete_time` = 0 AND parent_id in ( $parentIds )
                ORDER BY `list_order` ASC
            ");
        }
        //$categories = $this->order("list_order ASC")->where($where)->select()->toArray();
        foreach ($categories as $key => $val){
            if($val['parent_id'] == 0){
                $ids = $this->order("list_order ASC")->where(['parent_id'=>$val['id']])->column('id');
                array_unshift($ids, $val['id']);
                $ids_str = implode(',',$ids);
              	$wherep['status'] = 1;
              	$wherep['post_status'] = 1;
                $categories[$key]['zixunz'] = db('portal_post')->where(['parent_id'=>['in',$ids_str]])->where($wherep)->count();
             	$wheret['arcrank'] = 1;
              	$wheret['status'] = 1;
                $categories[$key]['xiangmuz'] = db('portal_xm')->where(['typeid'=>['in',$ids_str]])->where($wheret)->count();
              
              //$categories[$key]['zixunz'] = db('portal_post')->where(['parent_id'=>['in',$ids_str] ,'post_status'=>1 , 'status'=>1])->count();
             // $categories[$key]['xiangmuz'] = db('portal_xm')->where(['typeid'=>['in',$ids_str],'arcrank' => 1 , 'status' => 1 ])->count();

              
              
            }
        }
        $cates = db('portal_category')->where('parent_id = 0')->select()->toArray();

        $tree       = new Tree();
        $tree->icon = ['&nbsp;&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'];
        $tree->nbsp = '&nbsp;&nbsp;';

        if (!is_array($currentIds)) {
            $currentIds = [$currentIds];
        }
        $http = $this->is_https() ? 'https://' : 'http://';
        $newCategories = [];
        foreach ($categories as $item) {
            if($item['parent_id'] != 0){
                $item['zixunz'] = db('portal_post')->where('parent_id = '.$item['id'])->count();
                $item['xiangmuz'] = db('portal_xm')->where('typeid = '.$item['id'])->count();
            }

            $item['parent_id_node'] = ($item['parent_id']) ? ' class="child-of-node-' . $item['parent_id'] . '"' : '';
            $item['style']          = empty($item['parent_id']) ? '' : 'display:none;';
            $item['status_text']    = empty($item['status'])?'隐藏':'显示';
            $item['checked']        = in_array($item['id'], $currentIds) ? "checked" : "";
			$url_str =  substr($item['path'],0,1) == '/' ? '' : '/';
            $item['url']            = $http.$_SERVER['SERVER_NAME']. $url_str.$item['path'].'/';
            //$item['url']            = $http.$_SERVER['SERVER_NAME'].'/'.$item['path'];
            $item['str_action']     = '<a href="' . url("AdminCategory/add", ["parent" => $item['id']]) . '">添加子分类</a>  <a href="' . url("AdminCategory/edit", ["id" => $item['id']]) . '">' . lang('EDIT') . '</a>  <a class="js-ajax-delete" href="' . url("AdminCategory/delete", ["id" => $item['id']]) . '">' . lang('DELETE') . '</a> ';
            if ($item['status']) {
                $item['str_action'] .= '<a class="js-ajax-dialog-btn" data-msg="您确定隐藏此分类吗" href="' . url('AdminCategory/toggle', ['ids' => $item['id'], 'hide' => 1]) . '">隐藏</a>';
            } else {
                $item['str_action'] .= '<a class="js-ajax-dialog-btn" data-msg="您确定显示此分类吗" href="' . url('AdminCategory/toggle', ['ids' => $item['id'], 'display' => 1]) . '">显示</a>';
            }
            array_push($newCategories, $item);
        }


        $tree->init($newCategories);
        if (empty($tpl)) {
            $tpl = " <tr id='node-\$id' \$parent_id_node style='\$style' data-parent_id='\$parent_id' data-id='\$id'>
                        <td style='padding-left:20px;'><input type='checkbox' class='js-check' data-yid='js-check-y' data-xid='js-check-x' name='ids[]' value='\$id' data-parent_id='\$parent_id' data-id='\$id'></td>
                        <td>
                        <span id='list_order'>\$list_order</span>
                        </td>
                        <td id='id'>\$id</td>
                        <td data-partid = '\$parent_id' >\$spacer <a href='\$url' target='_blank'>\$name</a><span>&nbsp;&nbsp;&nbsp;&nbsp;(文章数：<span style='color:red;' > \$zixunz </span>)&nbsp;&nbsp;&nbsp;&nbsp;(项目数：<span style='color :red;'> \$xiangmuz </span>)</span></td>
                        <td>\$status_text</td>
                        <td>\$str_action</td>
                    </tr>";
        }
        $treeStr = $tree->getTree(0, $tpl);

        return $treeStr;
    }
    private function is_https() {
        if ( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
            return true;
        } elseif ( !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        return false;
    }

    /**
     * 添加文章分类
     * @param $data
     * @return bool
     */
    public function addCategory($data)
    {
        $result = true;
        self::startTrans();
        try {
            if (!empty($data['more']['thumbnail'])) {
                $data['more']['thumbnail'] = cmf_asset_relative_url($data['more']['thumbnail']);
            }
            $this->allowField(true)->save($data);
            $id = $this->id;
            if (empty($data['parent_id'])) {

                $this->where(['id' => $id])->update(['path' => '0-' . $id]);
            } else {
                $parentPath = $this->where('id', intval($data['parent_id']))->value('path');
                $this->where(['id' => $id])->update(['path' => "$parentPath-$id"]);

            }
            self::commit();
        } catch (\Exception $e) {
            self::rollback();
            $result = false;
        }

        if ($result != false) {
            //设置别名
            $routeModel = new RouteModel();
            if (!empty($data['alias']) && !empty($id)) {
                $routeModel->setRoute($data['alias'], 'portal/List/index', ['id' => $id], 2, 5000);
                $routeModel->setRoute($data['alias'] . '/:id', 'portal/Article/index', ['cid' => $id], 2, 4999);
            }
            $routeModel->getRoutes(true);
        }

        return $result;
    }

    public function editCategory($data)
    {
        $result = true;

        $id          = intval($data['id']);
        $parentId    = intval($data['parent_id']);
        $oldCategory = $this->where('id', $id)->find();

        if (empty($parentId)) {
            $newPath = '0-' . $id;
        } else {
            $parentPath = $this->where('id', intval($data['parent_id']))->value('path');
            if ($parentPath === false) {
                $newPath = false;
            } else {
                $newPath = "$parentPath-$id";
            }
        }

        if (empty($oldCategory) || empty($newPath)) {
            $result = false;
        } else {

            $data['path'] = $newPath;
            if (!empty($data['more']['thumbnail'])) {
                $data['more']['thumbnail'] = cmf_asset_relative_url($data['more']['thumbnail']);
            }
            $this->isUpdate(true)->allowField(true)->save($data, ['id' => $id]);

            $children = $this->field('id,path')->where('path', 'like', $oldCategory['path'] . "-%")->select();
            if (!$children->isEmpty()) {
                foreach ($children as $child) {
                    $childPath = str_replace($oldCategory['path'] . '-', $newPath . '-', $child['path']);
                    $this->where('id', $child['id'])->update(['path' => $childPath], ['id' => $child['id']]);
                }
            }

            $routeModel = new RouteModel();
            if (!empty($data['alias'])) {
                $routeModel->setRoute($data['alias'], 'portal/List/index', ['id' => $data['id']], 2, 5000);
                $routeModel->setRoute($data['alias'] . '/:id', 'portal/Article/index', ['cid' => $data['id']], 2, 4999);
            } else {
                $routeModel->deleteRoute('portal/List/index', ['id' => $data['id']]);
                $routeModel->deleteRoute('portal/Article/index', ['cid' => $data['id']]);
            }

            $routeModel->getRoutes(true);
        }


        return $result;
    }


}