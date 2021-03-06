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
namespace app\portal\controller;
use cmf\controller\HomeBaseController;
use think\Db;
use think\Request;
use app\portal\model\AreaModel;
use think\cache\driver\Redis;
use app\portal\model\CategoryModel;
use app\portal\model\ProductModel;
use app\portal\model\NewsModel;
use plugins\comment\model\PluginCommentModel;
class CommonController extends HomeBaseController
{
    public function _initialize()
    {
		$this->daohang();
		$this->dibu();
        if (\think\Request::instance()->isMobile()) {
            $this->assign('seo_url', 'http://www.91chuangye.com' . $this->request->url());
        }else{
            $this->assign('seo_url', 'http://m.91chuangye.com' . $this->request->url());
        }
    }
    public function index()
    {
        //获取参数
        if (!Request::instance()->isGet()){
            $this->error1();
        }
        //实例化AreaModel
        $AreaModel = new AreaModel();
        //项目目录
        $path = $this->request->param('classname');
		//项目地址/筛选金额
        $param_addr = $this->request->param('id');
        //项目地址/筛选金额
        $param_price = $this->request->param('num');
        //项目目录必须存在，不存在404
        if(!isset($path) || empty($path)){
            return $this->error1();
        }

		//判断专题
        
        if(isset($param_addr)){
            $zt = explode('_',$param_addr);
            if(in_array('zt', $zt)){
                if($path == 'yangsheng'){
                    $path2 = str_replace('_zt','',$param_addr);
                    $paths = $path.'/'.$path2;
                    $path = Db::name('portal_category')->where(['path'=>$paths])->value('path');
                    if($path){
                        return $this->zhuanti($path);
                    }
                }
                
            }
        }
        
        
		preg_match('/^(\w+)_zt$/',$path,$match);
		if(!empty($match)){
			$id = Db::name('portal_category')->where(['path'=>$match[1]])->value('id');
			if(isset($id)){
				if(empty($param_addr) && empty($param_price)){
					return $this->zhuanti($match[1]);
				}
			}
			return $this->error1();
		}
        //指定参数/项目金额/地址
        $area = $price = '';
        $page = 1;
        //允许访问栏目
        $passtypeid = explode(',','2,312,8,10,5,4,7,313,9,1,3,339,6,396,420,734,742,63,350');
        if($path != 'xiangmu' && $path != 'haoxiangmu' && $path != 'haibao'){
            $category = Db::name('portal_category')->where(['path'=>$path])->field('id,parent_id')->find();
            //判断当前目录是否存在
            $path = in_array($category['id'],$passtypeid) ? $path : in_array($category['parent_id'],$passtypeid) ? $path : false;

        }
        //不存在返回404
        if(!$path){
           return $this->error1();
        }
        //养生栏目与其他栏目规则不符，单独判断
        if($path == 'yangsheng'){
            $soncategory = Db::name('portal_category')->where(['parent_id'=>$category['id'],'path'=>'yangsheng/'.$param_addr])
                ->field('id,name,path')
                ->find();
            if($soncategory){
                $pathinfo = str_replace($soncategory['path'],'',$this->request->path());
                $first = strpos($pathinfo,'/');
                $pathinfo = $first == 0 ? substr($pathinfo,1) : $pathinfo;
                $param = explode('/',$pathinfo);
                $param_addr = $this->request->param('num');
                $param_price = (isset($param[1]) && !empty($param[1])) ? $param[1] : '';
                $path = $soncategory['path'];

            }
        }
        //判断是否是ID，跳转详情页
        if((isset($param_addr) && !empty($param_addr)) && (!isset($param_price) || empty($param_price))){
            if(is_numeric($param_addr)){
                $data = db('portal_xm')->alias('a')->join('portal_category b','a.typeid = b.id')->where(['a.aid'=>$param_addr,'class'=>$path])->find();
                if($data){
                    return $this->article_xm($param_addr);
                }
            }
        }
        //前两个参数都存在时
        if((isset($param_addr)&&!empty($param_addr)) && (isset($param_price)&&!empty($param_price))){
            //防止参数相同漏洞
            if($param_addr == $param_price){
                return $this->error1();
            }
			if(is_numeric($param_addr)){
                $data = db('portal_xm')->alias('a')->join('portal_category b','a.typeid = b.id')->where(['a.aid'=>$param_addr,'class'=>$path])->find();
                if($data){
					//判断是否是海报
					if($param_price == 'haibao' || $param_price == 'wenda'){
						return $this->article_xm($param_addr,$param_price);
					}
                    return $this->error1();
                }
            }
            preg_match('/^list_(\d+)(.html)?$/',$param_price,$match);
            //如第三参数不是page
            if(empty($match)){
                //第二参数不是地区404
                $passarea = $AreaModel::areaName(['py' => $param_addr]);
                if (!$passarea) {
                    return $this->error1();
                }
            }
        }
        $passprice = ['0-1','1-5','5-10','10-20','20-50','50-100','100-200'];
        //判断参数类型$param_addr
        if((isset($param_addr)&&!empty($param_addr))){
            preg_match('/^list_(\d+)(.html)?$/',$param_addr,$match);
            if(empty($match)){
                if(in_array($param_addr,$passprice)){
                    $price = $param_addr;
                }else{
                    $passarea = $AreaModel::areaName(['py' => $param_addr]);
                    if ($passarea) {
                        $area =  $param_addr;
                    }else{
                        return $this->error1();
                    }
                }
            }else{
                $page = $match[1];
            }
        }
        //判断参数类型$param_price
        if((isset($param_price)&&!empty($param_price))){
            preg_match('/^list_(\d+)(.html)?$/',$param_price,$match);
            if(empty($match)){
                if(in_array($param_price,$passprice)){
                    $price = $param_price;
                }else{
                    $passarea = $AreaModel::areaName(['py' => $param_price]);
                    if ($passarea) {
                        $area =  $param_addr;
                    }else{
                        return $this->error1();
                    }
                }
            }else{
                //如果分页存在证明两个参数都是list_\d.html 则404
                if($page != 1){
                    return $this->error1();
                }
                $page = $match[1];
            }
        }
        //分页
        if($page == 1){
            preg_match('/^(.*)\/list_(\d+)(.html)?$/',$this->request->url(),$match);
            if(!empty($match)){
                $page = $match[2];
            }
        }
        //list_0 404
        if($page == 0){
            return $this->error1();
        }
        $param = [
            'classname'=>$path,
            'address'=>$area,
            'num'=>$price,
            'page'=>$page,
        ];
        return $this->xm($param);
     }
    //项目列表
    public function xm($post)
    {
		$redis = new Redis();
        $page = $post['page'];
        $array_reverse = $youlian = $selcttag1 ="";
        $areaModel = new AreaModel();
        $CategoryModel = new CategoryModel();
        $ProductModel = new ProductModel();
        $NewsModel = new NewsModel();
        //地区
        $showsheng = '';
        if(!empty($post['address'])){
            $areaHave = $areaModel->areaName(['py'=>$post['address']]);
            $areaValue = $areaHave['evalue'];
            if($areaValue % 500 == 0){
                $fareavalue = intval($areaValue);
                $maxvalue = $fareavalue+500;
                $sareavalue = DB::name('sys_enum')->field('evalue')->where('evalue > '.$fareavalue.' and evalue < '
                    .$maxvalue)->group('evalue')->select();
                $areaAll = [];
                foreach ($sareavalue as $value){
                    if(!in_array(floor($value['evalue']),$areaAll)){
                        $areaAll[] = floor($value['evalue']);
                    }
                }
                $areaAll[] = $areaValue;
                $where = ['por.nativeplace'=>['in',implode(',',$areaAll)]];
                //相关地区项目条件
                $aboutwhere = ['por.nativeplace'=>['in',implode(',',$areaAll)]];
            }else{
                $evalue = $areaValue - $areaValue % 500;
                $showsheng = DB::name('sys_enum')->where(['evalue'=>$evalue,'egroup'=>'nativeplace'])->value('py');
                $where['por.nativeplace'] = $areaHave['evalue'];
                //相关地区项目条件
                $aboutwhere['por.nativeplace'] = $areaHave['evalue'];
            }
        }

        //金额参数
        if(!empty($post['num']))
        {
            $where['por.invested'] = $priceshow =  ($post['num'] == '100-200') ? '100-200万' : $post['num'].'万';
        }
        //分类
        if(empty($post['classname']) || $post['classname']=='xiangmu'){
			if($redis->get('xiangmuCat')){
				$cate = json_decode($redis->get('xiangmuCat'),true);
			}else{
				$cate = $CategoryModel::getCategory();
				$redis->set('xiangmuCat',json_encode($cate));
			}			
        }else{
            $categoryData = $CategoryModel::categoryData(['path'=>$post['classname']]);
            if(empty($categoryData['parent_id'])){
				if($redis->get($post['classname'].'Cat')){
					$cate = json_decode($redis->get($post['classname'].'Cat'),true);
				}else{
					$cate = $CategoryModel::getSonCate($post['classname']);
					$redis->set($post['classname'].'Cat',json_encode($cate));
				}
            }else{
                $categoryData = $CategoryModel::categoryData(['id'=>$categoryData['parent_id']]);
				if($redis->get($categoryData['path'].'Cat')){
					$cate = json_decode($redis->get($categoryData['path'].'Cat'),true);
				}else{
					$cate = $CategoryModel::getSonCate($categoryData['path']);
					$redis->set($categoryData['path'].'Cat',json_encode($cate));
				}
            }
        }

        //当栏目不是xiangmu时
        if($post['classname']!='xiangmu'){
            $category = $CategoryModel::getone(['path'=>$post['classname']],'id,path,name,parent_id');
            if(!$category){
                return $this->error1();
            }
            $id = $catePhbId = $category['id'];
            $cateParentid = $category['parent_id'];
            $catePhbName = $category['name'];
            //面包屑
			if($redis->get('position_'.$id)){
				$array_reverse = json_decode($redis->get('position_'.$id),true);
			}else{
				$array_reverse = $this->position($id);
				$redis->set('position_'.$id,json_encode($array_reverse));
			}
			
            //友情链接
			if($redis->get('youlian_'.$id)){
				$youlian = json_decode($redis->get('youlian_'.$id),true);
			}else{
				$youlian = db("flink")->where("typeid = ".$id." and ischeck = 1")->field('webname,url')->order("dtime desc")->limit(30)->select();
				$redis->set('youlian_'.$id,json_encode($youlian));
			}
            if($category['parent_id'] == 0)
				{
				//子栏目ID
				$sonIds =  $CategoryModel::getOneColumn(['parent_id'=>$id],'id');
				$where['por.typeid'] = ['in',$sonIds];
			}else{
				$where['por.typeid'] = $id;
				$selcttag1=$post['classname'];
			}
            //推荐项目品牌
			if($redis->get('tuijian_'.$id)){
				$tuijian = json_decode($redis->get('tuijian_'.$id),true);
			}else{
				$tuijian = Db::name('portal_xm')
					->alias('por')
					->where($where)
					->where('status = 1 and arcrank = 1')
					->field('aid,title,class')
					->order('aid desc')->limit('50')->select();
				$redis->set('tuijian_'.$id,json_encode($tuijian));
			}
			
        }else{
            //排除养生保健、创业好项目、休闲娱乐、加盟排行榜、采集栏目
            $where['cat.parent_id'] = ['notin',['350','63','391','432']];
            $where['por.typeid'] = ['notin',['350','63','391','432']];
            $tuijian = Db::name('portal_xm')
                ->alias('por')
                ->where(['por.typeid'=>['notin',['350','63','391','432']]])
                ->where('status = 1 and arcrank = 1')
                ->field('aid,title,class')
                ->order('aid desc')->limit('50')->select();
			//友情链接
			// $youlian = db("flink")->where("ischeck = 1")->field('webname,url')->order("dtime desc")->limit(30)->select();
                $youlian = '';
        }
        //图片为空不调用
        $where['por.litpic'] = ['neq',''];
        $where['por.litpic'] = ['neq','/uploads/jd/qjnone.gif'];
        //项目列表数据
        $data = Db::name('portal_xm')
            ->alias('por')
            ->field('por.aid,por.class,por.litpic,por.thumbnail,por.title,por.sum,por.invested,por.companyname,por.address,por.nativeplace,cat.name as categoryname,cat.path,por.description,por.jieshao')
            ->join('portal_category cat','por.typeid = cat.id')
            ->where($where)->where(['por.status' => 1,'por.arcrank' => 1])->order('update_time desc')->paginate(20,
                false,['query' =>request()->param(),
                'page'=>$page]);

        //当分页数量大于总页数404
        if($page != 1 && $page > $data->lastPage()){
            return $this->error1();
        }
        $infos = $data->all();
        $dataa = $data->all();

        foreach ($dataa as $key => $value) {
                // $pattern = "#<img[^>]+>#";
                $html = $this->cutArticle($value['jieshao'],240);
                $dataa[$key]['jieshao'] = $html;
            if(isset($value['nativeplace']) && ($value['nativeplace']!='')){
                $nativeplace = db('sys_enum')->where("evalue = ".$value['nativeplace']." and py != ''")->field("ename,py")->find();
                $dataa[$key]['address'] = !empty($nativeplace['ename']) ? $nativeplace['ename'] : '';
                $dataa[$key]['py'] = !empty($nativeplace['py']) ? $nativeplace['py'] : '';
            }else{
                $dataa[$key]['address'] = '';
                $dataa[$key]['py'] = '';
            }
        }

        $aboutdata = [];
        //当检索条件不满足时，显示该分类下的十个项目
        if(empty($dataa) || count($dataa) < 6){
            //xiangmu
            if($post['classname'] == 'xiangmu'){
                $aboutwhere['cat.parent_id'] = ['notin',['350','63','391','432']];
                $aboutwhere['por.typeid'] = ['notin',['350','63','391','432']];

            }else{
                $aboutwhere['por.typeid'] = $id;
                if($category['parent_id'] == 0){
                    $aboutwhere['por.typeid'] = ['in',$sonIds];
                }
                unset($aboutwhere['por.nativeplace']);
            }
            //查询相关数据
            $aboutdata = Db::name('portal_xm')
                ->alias('por')
                ->field('por.aid,por.class,por.litpic,por.thumbnail,por.title,por.sum,por.invested,por.companyname,por.address,por.nativeplace,cat.name as categoryname,cat.path,por.description,por.jieshao')
                ->join('portal_category cat','por.typeid = cat.id')
                ->where('por.arcrank = 1 and por.status = 1')
                ->where($aboutwhere)->order('update_time desc')->limit(100)->select()->toArray();
            $count = (count($aboutdata) > 10) ? 10 : count($aboutdata);
            if($count > 1){
                $rand = array_rand($aboutdata,$count);
            }

            foreach ($aboutdata as $key => $value) {
                    // $pattern = "#<img[^>]+>#";
                    $html = $this->cutArticle($value['jieshao'],220);
                    $aboutdata[$key]['jieshao'] = $html;
                $aboutdata[$key]['class'] = substr($value['class'],0,1) == '/' ? substr($value['class'],1) : $value['class'];
                if(isset($value['nativeplace']) && ($value['nativeplace']!='')){
                    $nativeplace = db('sys_enum')->where("evalue = ".$value['nativeplace']." and py != ''")->field("ename,py")->find();
                    $aboutdata[$key]['address'] = !empty($nativeplace['ename']) ? $nativeplace['ename'] : '';
                    $aboutdata[$key]['py'] = !empty($nativeplace['py']) ? $nativeplace['py'] : '';
                }else{
                    $aboutdata[$key]['address'] = '';
                    $aboutdata[$key]['py'] = '';
                }
                if($count > 1) {
                    if (!in_array($key, $rand)) {
                        unset($aboutdata[$key]);
                    }
                }
            }
        }
         //TDK
        $nativeplace = db('sys_enum')->where(['py'=>$post['address']])->value("ename");
        $nativeplace = str_replace('省','',$nativeplace);
        $nativeplace = str_replace('市','',$nativeplace);

        $seo = db("portal_category")->where("path="."'$post[classname]' and status = 1 and ishidden = 1")->find();
        $selcttag3 = $post['num'];
        $seo_name = '';
        if(isset($post['classname']) && ($post['classname']=='xiangmu')){
            if($selcttag3 || $nativeplace){
                if(!empty($selcttag3)){
                    $selcttag3 = $selcttag3.'万';
                }
                $seo_title = $nativeplace.$selcttag3."招商加盟项目大全_2019 ".$nativeplace.$selcttag3."热门招商加盟项目推荐-91创业网 ";
                $seo_keywords = $nativeplace.$selcttag3.'加盟项目'.", ".$nativeplace.$selcttag3."招商加盟项目";
                $seo_description = "91创业网-汇集各种".$nativeplace.$selcttag3."加盟,".$nativeplace.$selcttag3."连锁加盟,".$nativeplace.$selcttag3."十大品牌排行榜等".$nativeplace.$selcttag3."加盟费信息,帮助广大创业者找到适合自己的加盟项目,选择好的".$nativeplace.$selcttag3."加盟项目,让创业者轻松创业！";
            }else {
                $seo_title = "招商加盟项目大全_2019热门招商加盟项目推荐-91创业网";
                $seo_keywords = "加盟项目,招商加盟项目";
                $seo_description = "91创业网-汇集各种品牌加盟项目大全,招商连锁加盟,品牌加盟十大排行榜等2019招商加盟费信息,帮助广大创业者找到适合自己的加盟项目,选择好的品牌加盟项目,让创业者轻松创业";
            }

        }else{
            //一级分类的判断
            //判断金额存在
            $seo_name = str_replace('加盟', '', $seo['name']);
            if((!empty($selcttag3))&&(empty($nativeplace)) || ($seo['parent_id'] != 0) && (!empty($selcttag3)) && (empty($nativeplace))){
                $selcttag3 = $selcttag3 . '万';
                if($seo['id'] == 734 || $seo['parent_id'] == 734){
                    $seo_title = $selcttag3 . $seo_name.'品牌连锁店项目加盟_'.$selcttag3.$seo_name.'品牌加盟条件-91创业网';
                    $seo_keywords = $selcttag3 . $seo_name.'加盟,'.$selcttag3.$seo_name.'连锁店加盟';
                }else if($seo['id'] == 10){
                    $seo_title = $selcttag3 . $seo_name.'项目加盟_'.$selcttag3.$seo_name.'项目加盟条件-91创业网';
                    $seo_keywords = $selcttag3 . $seo_name.'加盟';
                }else if($seo['id'] == 312){
                    $seo_title = $selcttag3 . $seo_name.'酒水加盟项目_'.$selcttag3.$seo_name.'酒水加盟店排行榜-91创业网';
                    $seo_keywords = $selcttag3 . $seo_name.'酒水加盟,'.$selcttag3 . $seo_name.'酒水加盟店,'.$selcttag3 . $seo_name.'酒水加盟排行榜,'.$selcttag3 . $seo_name.'水加盟十大品牌';
                }else if($seo['id'] == 396){
                    $seo_title = $selcttag3 . $seo_name.'项目加盟_'.$selcttag3.$seo_name.'项目加盟条件-91创业网';
                    $seo_keywords = $selcttag3 . $seo_name.'加盟,'.$selcttag3 . $seo_name.'代理';
                }else if($seo['id'] == 420){
                    $seo_title = $selcttag3.'互联网创业项目加盟_'.$selcttag3.'网络创业项目代理条件-91创业网';
                    $seo_keywords = $selcttag3 .'互联网创业项目加盟,'.$selcttag3 .'互联网创业项目代理';
                }else{
                    $seo_title = $selcttag3 . $seo_name.'品牌连锁店项目加盟_'.$selcttag3.$seo_name.'品牌加盟条件-91创业网';
                    $seo_keywords = $selcttag3 . $seo_name.'加盟,'.$selcttag3 . $seo_name.'连锁店加盟';
                }
                $seo_description = "91创业网-汇集各种" . $selcttag3 . $seo_name . '加盟' . "," . $selcttag3 . $seo_name . '加盟' . "连锁品牌," . $selcttag3 . $seo_name . "加盟十大品牌排行榜等" . $selcttag3 . $seo_name . "加盟费信息,帮助广大创业者找到适合自己的加盟项目,选择好的" . $selcttag3 . $seo_name . "加盟项目,让创业者轻松创业！";

            //判断地区存在
            }else if((!empty($nativeplace)) && (empty($selcttag3))|| ($seo['parent_id'] != 0) && (!empty($nativeplace)) && (empty($selcttag3))) {
                if($seo['id'] == 734){
                    $seo_title = $nativeplace.$seo_name.'品牌连锁店加盟_'.$nativeplace.$seo_name.'加盟费用_多少钱-91创业网';
                    $seo_keywords = $nativeplace.$seo_name.'加盟,'.$nativeplace.$seo_name.'品牌连锁店加盟,'.$nativeplace.$seo_name.'连锁店加盟,'.$nativeplace.$seo_name.'品牌加盟';
                }else if($seo['id'] == 10){
                    $seo_title = $nativeplace.$seo_name.'加盟_'.$nativeplace.$seo_name.'加盟费用多少钱-91创业网';
                    $seo_keywords = $nativeplace.$seo_name.'加盟,'.$nativeplace.$seo_name.'加盟费用,'.$nativeplace.$seo_name.'加盟多少钱';
                }else if($seo['id'] == 312){
                    $seo_title = $nativeplace.$seo_name.'加盟项目_'.$nativeplace.$seo_name.'加盟店排行榜-91创业网';
                    $seo_keywords = $nativeplace.$seo_name.'加盟,'.$nativeplace.$seo_name.'加盟店,'.$nativeplace.$seo_name.'加盟排行榜,'.$nativeplace.$seo_name.'加盟十大品牌';
                }else if($seo['id'] == 396){
                    $seo_title = $nativeplace.$seo_name.'项目代理加盟_'.$nativeplace.$seo_name.'项目加盟费用多少钱-91创业网';
                    $seo_keywords = $nativeplace.$seo_name.'加盟,'.$nativeplace.$seo_name.'代理,'.$nativeplace.$seo_name.'代理费用';
                }else if($seo['id'] == 420){
                    $seo_title = $nativeplace.'互联网创业项目加盟_'.$nativeplace.'零成本网络创业项目招商代理-91创业网';
                    $seo_keywords = $nativeplace.'互联网项目加盟,'.$nativeplace.'网络创业项目加盟,'.$nativeplace.'互联网创业项目代理,'.$nativeplace.'网络创业项目代理';
                }else{
                    $seo_title = $nativeplace.$seo_name.'品牌连锁店加盟_'.$nativeplace.$seo_name.'加盟费用_多少钱-91创业网';
                    $seo_keywords = $nativeplace.$seo_name.'加盟,'.$nativeplace.$seo_name.'品牌连锁店加盟,'.$nativeplace.$seo_name.'连锁店加盟,'.$nativeplace.$seo_name.'品牌加盟';
                }

                $seo_description = "91创业网-汇集各种" . $nativeplace . $seo_name . '加盟' . "," . $nativeplace . $seo_name . '加盟' . "连锁品牌," . $nativeplace . $seo_name . "加盟十大品牌排行榜等" . $nativeplace . $seo_name . "加盟费信息,帮助广大创业者找到适合自己的加盟项目,选择好的" . $nativeplace . $seo_name . "加盟项目,让创业者轻松创业！";

            //判断金额和地区都存在
            }else if((!empty($selcttag3)) && (!empty($nativeplace)) || ($seo['parent_id'] != 0) && (!empty($selcttag3)) && (!empty($nativeplace))){
                $selcttag3 = $selcttag3 . '万';
                if($seo['id'] == 734) {
                    $seo_title = $nativeplace . $selcttag3 . $seo_name . '品牌连锁店项目加盟_' . $nativeplace . $selcttag3 . $seo_name . '品牌加盟条件-91创业网';
                    $seo_keywords = $nativeplace . $selcttag3 . $seo_name . '加盟,' . $nativeplace . $selcttag3 . $seo_name . '连锁店加盟';
                }else if($seo['id'] == 10){
                    $seo_title = $nativeplace . $selcttag3 . $seo_name . '项目加盟_' . $nativeplace . $selcttag3 . $seo_name . '项目加盟条件-91创业网';
                    $seo_keywords = $nativeplace . $selcttag3 . $seo_name . '加盟,' . $nativeplace . $selcttag3 . $seo_name . '项目加盟';
                }else if($seo['id'] == 312){
                    $seo_title = $nativeplace . $selcttag3 . $seo_name . '加盟项目_' . $nativeplace . $selcttag3 . $seo_name . '加盟店排行榜-91创业网';
                    $seo_keywords = $nativeplace . $selcttag3 . $seo_name . '加盟,' . $nativeplace . $selcttag3 . $seo_name . '加盟店,'. $nativeplace . $selcttag3 . $seo_name.'加盟排行榜,'.$nativeplace . $selcttag3 . $seo_name.'加盟十大品牌';
                }else if($seo['id'] == 396){
                    $seo_title = $nativeplace . $selcttag3 . $seo_name . '加盟项目_' . $nativeplace . $selcttag3 . $seo_name . '项目加盟条件-91创业网';
                    $seo_keywords = $nativeplace . $selcttag3 . $seo_name . '加盟,' . $nativeplace . $selcttag3 . $seo_name . '代理';
                }else if($seo['id'] == 420){
                    $seo_title = $nativeplace . $selcttag3 . $seo_name . '互联网创业项目加盟_' . $nativeplace . $selcttag3 . $seo_name . '网络创业项目代理条件-91创业网';
                    $seo_keywords = $nativeplace . $selcttag3 . $seo_name . '互联网创业项目加盟,' . $nativeplace . $selcttag3 . $seo_name . '联网创业项目代理';
                }else{
                    $seo_title = $nativeplace . $selcttag3 . $seo_name . '品牌连锁店项目加盟_' . $nativeplace . $selcttag3 . $seo_name . '品牌加盟条件-91创业网';
                    $seo_keywords = $nativeplace . $selcttag3 . $seo_name . '加盟,' . $nativeplace . $selcttag3 . $seo_name . '连锁店加盟';
                }
                $seo_description = "91创业网-汇集各种" . $nativeplace . $selcttag3 . $seo_name . '加盟' . "," . $nativeplace . $selcttag3 . $seo_name . '加盟' . "连锁品牌," . $nativeplace . $selcttag3 . $seo_name . "加盟十大品牌排行榜等" . $nativeplace . $selcttag3 . $seo_name . "加盟费信息,帮助广大创业者找到适合自己的加盟项目,选择好的" . $nativeplace . $selcttag3 . $seo_name . "加盟项目,让创业者轻松创业！";
            }else{
                $seo_title = $seo['seo_title'];
                $seo_keywords = $seo['seo_keywords'];
                $seo_description = "91创业".$seo_name."加盟网是中国最专业的".$seo_name."加盟网，有最新中国".$seo_name."加盟店排行榜，".$seo_name."加盟项目".$data->total()."个。网站提供".$seo_name."加盟、".$seo_name."加盟店、".$seo_name."品牌、".$seo_name."店等内容，深受创业加盟者喜爱。";
            }
        }

        if(isset($post['classname'])){
            $catename = db('portal_category')->where("path="."'$post[classname]'")->field('name')->find();
            $catename = str_replace('加盟','',$catename['name']);
        }else{
            $catename = '热门';
        }
        if((isset($post['classname'])) && ($post['classname'] != 'xiangmu')){
            $cate_Name = db('portal_category')->where("path="."'$post[classname]'")->value('name');
        }else{
            $cate_Name = '项目库';
        }
		
        //导航行业以及热门行业
		if($redis->get('remenhy')){
			$type = json_decode($redis->get('remenhy'),true);
		}else{
			$type = db("portal_category")->where("parent_id = 0 and status = 1 and ishidden = 1 and id != 350")->field('id,name,path')->order('list_order asc')->limit(18)->select();
			$redis->set('remenhy',json_encode($type));
		}
		//发源地
		if($redis->get('area')){
			$area = json_decode($redis->get('area'),true);
		}else{
			$area = $areaModel->allarea('(evalue MOD 500)=0');
			$redis->set('area',json_encode($area));
		}
        $this->assign('selcttag3',$selcttag3);
        $this->assign('nativeplace',$nativeplace);
        $this->assign('seo_name',$seo_name);
        $this->assign('cate_Name',$cate_Name);
        $this->assign('catename',$catename);
        $this->assign('showsheng',$showsheng);
        $this->assign('lastpage',$data->lastPage());
        $this->assign('seo_title',str_replace('_第1页','',$seo_title));
        $this->assign('seo_keywords',$seo_keywords);
        $this->assign('seo_description',$seo_description);
        $this->assign('category',$post['classname']);
        $this->assign('selcttag1',$selcttag1);
        $this->assign('param',$post);
        $this->assign('youlian',$youlian);
        $this->assign('data',$data);
        $this->assign('total',$data->total());
        $this->assign('dataa',$dataa);
        $this->assign('aboutdata',$aboutdata);
        $this->assign('infos',$infos);
        $this->assign('sys',$area);
        $this->assign('cate',$cate);
        $this->assign('type',$type);
        $this->assign('array_reverse',$array_reverse);
		//最新资讯
		if($post['classname'] != 'xiangmu'){
			if($cateParentid == 0){
				$topcateid = db('portal_category')->where(['name'=>$catePhbName.'资讯','parent_id'=>399])->value('id');
			}else{
				$topname = db('portal_category')->where(['id'=>$cateParentid])->value('name');
				$topcateid = db('portal_category')->where(['name'=>$topname.'资讯','parent_id'=>399])->value('id');
			}
			$topcateid = empty($topcateid) ? 401 : $topcateid;
			$lick3 = $NewsModel->conditionArray(['parent_id'=>$topcateid],'id,post_title,class,published_time',10,'published_time','desc');
			foreach ($lick3 as $key => $value) {
				$lick3[$key]['class'] = strpos($value['class'],'/') ? 'news' :$value['class'];
			}
		}else{
			$lick3 = $NewsModel->conditionArray([],'id,post_title,class,published_time',10,'published_time','desc');
			foreach ($lick3 as $key => $value) {
				$lick3[$key]['class'] = strpos($value['class'],'/') ? 'news' :$value['class'];
			}
		}
		
		if($post['classname'] == 'xiangmu'){
			//品牌排行榜
			$lick4 = Db::name('portal_category')->where(['parent_id' => 391])->field('id,name,path')
				->order('list_order asc')->limit(10)->select()->toarray();
			$topname = '热门';
		}else{
			//品牌排行榜
			if($cateParentid == 0){
				$topcateid = db('portal_category')->where(['name'=>$catePhbName,'parent_id'=>391])->value('id');
				$topname = str_replace('加盟','',$catePhbName);
				$redis->set('news_topname'.$catePhbId,json_encode($topname));
			}else{
				$topname = db('portal_category')->where(['id'=>$cateParentid])->value('name');
				$topcateid = db('portal_category')->where(['name'=>$topname,'parent_id'=>391])->value('id');
				$topname = str_replace('加盟','',$topname);
				$redis->set('news_topname'.$catePhbId,json_encode($topname));
			}
			//这么调用的原因是因为排行榜栏目名称有重复的
			$nowdata = db('portal_category')->where(['name'=>$catePhbName,'parent_id'=>$topcateid])->field('id,name,path')->find();
			$pwhere['parent_id'] = $topcateid;
			if($nowdata){
				$pwhere['id'] = ['neq',$nowdata['id']];
			}
			if($nowdata){
				$lick4 = Db::name('portal_category')->where($pwhere)->field('id,name,path')
				->order('list_order asc')->limit(9)->select()->toarray();
				$lick4 = array_reverse(array_merge($lick4,['9'=>$nowdata]));
			}else{
				$lick4 = Db::name('portal_category')->where($pwhere)->field('id,name,path')
				->order('list_order asc')->limit(10)->select()->toarray();
			}
			$redis->set('news_phb'.$catePhbId,json_encode($lick4));
		}
		//创业聚焦
		if($redis->get('cyJujiao_')){
			$lick5 = json_decode($redis->get('cyJujiao_'),true);
		}else{
			$lick5 = $NewsModel->conditionlist(['parent_id'=>'11'],'',10,'click','desc')->toArray();
			foreach ($lick5 as $key => $value) {
				$lick5[$key]['class'] = strpos($value['class'],'/') ? 'news' :$value['class'];
			   
			}
			$redis->set('cyJujiao_',json_encode($lick5));
		}
		$this->assign('topname',$topname);
		$this->assign('lick3',$lick3);
		$this->assign('lick4',$lick4);
		$this->assign('lick5',$lick5);
        if (\think\Request::instance()->isMobile()) {
            $cate = $CategoryModel::categoryData(['path'=>$post['classname']]);
            if($cate['id']==63||$cate['parent_id']==63){
				if($redis->get('mobileCate_63')){
					$catess = json_decode($redis->get('mobileCate_63'),true);
					$cated = json_decode($redis->get('mobileCated_63'),true);
				}else{
					//获取创业好项目
					$catess = db("portal_category")->where(['id'=>63])->where('status = 1 and ishidden = 1')->field('id,parent_id,name,path')->order('list_order asc')->select();
					$cated = db('portal_category')->where(['parent_id' => 63,'ishidden' => 1,'status' => 1])->field('id,path,name,mobile_thumbnail')->select();
					$redis->set('mobileCate_63',json_encode($catess));
					$redis->set('mobileCated_63',json_encode($cated));
				}
            }else{
                //获取所有一级分类
				if($redis->get('mobileCate')){
					$catess = json_decode($redis->get('mobileCate'),true);
					$cated = json_decode($redis->get('mobileCated'),true);
				}else{
					//获取创业好项目
					$arr = '2,1,4,5,7,10,3,6,8,9,312,313,396,420,339,734,742';
					$catess = db("portal_category")->where('id', 'in', $arr)->where('status = 1 and ishidden = 1')->field('id,parent_id,name,path')->order('list_order asc')->select();
					$cated = db('portal_category')->where(['parent_id' => 2,'ishidden' => 1,'status' => 1])->field('id,path,name,mobile_thumbnail')->select();
					$redis->set('mobileCate',json_encode($catess));
					$redis->set('mobileCated',json_encode($cated));
				}
            }
			/*
            //创业资讯
			if($redis->get('mobileCyzx')){
				$zixun = json_decode($redis->get('mobileCyzx'),true);
			}else{
				$where25['parent_id'] = ['in','399,401,402,403,404,405,406,407,408,409,433'];
				$zixun = db('portal_post')->where($where25)->where('post_status = 1 and status = 1')->field('id,post_title,post_excerpt,thumbnail,published_time,class')->order('published_time desc')->limit(10)->select();
				$redis->set('mobileCyzx',json_encode($zixun));
			}
            
            //创业知识
			if($redis->get('mobileCyzs')){
				$zhishi = json_decode($redis->get('mobileCyzs'),true);
			}else{
				$where26['parent_id'] = ['in','20,22,27,31'];
				$zhishi = db('portal_post')->where($where26)->where('post_status = 1 and status = 1')->field('id,post_title,post_excerpt,thumbnail,published_time,class')->order('published_time desc')->limit(10)->select();
				$redis->set('mobileCyzs',json_encode($zhishi));
			}
            
            //创业故事
			if($redis->get('mobileCygs')){
				$gushi = json_decode($redis->get('mobileCygs'),true);
			}else{
				$where27['parent_id'] = ['in','11'];
				$gushi = db('portal_post')->where($where27)->where('post_status = 1 and status = 1')->field('id,post_title,post_excerpt,thumbnail,published_time,class')->order('published_time desc')->limit(10)->select();
				$redis->set('mobileCygs',json_encode($gushi));
			}
			*/
            $cateid = $cateparentid = 0;

            if($post['classname'] != 'xiangmu')
            {
                $cateid = isset($category['id']) ? $category['id'] : 0;
                $cateparentid = isset($category['parent_id']) ? $category['parent_id'] : 0;
            }
            $show = [
                'classname' => isset($cate['name']) ? str_replace('加盟','',$cate['name']) : '',
                'price' => isset($priceshow) ? $priceshow : '',
                'address' => isset($areaHave['ename']) ? $areaHave['ename'] : '',
            ];
            $this->assign('show',$show);
            $this->assign('param',$post);
            $this->assign('cateid',$cateid);
            $this->assign('cateparentid',$cateparentid);
            
            $this->assign('catess',$catess);
            $this->assign('cated',$cated);
			/*
            $this->assign('zhishi',$zhishi);
            $this->assign('gushi',$gushi);
			$this->assign('zixun',$zixun);
			*/
			//养生下栏目不生成静态页
			$exPath = ['yangsheng/am','yangsheng/bjp','yangsheng/bj','yangsheng/hs','yangsheng/js','yangsheng/spa','yangsheng/chhf','yangsheng/yj','yangsheng/zl','yangsheng/zy'];
            if(empty($post['address']) && empty($post['num']) && $page == 1 && !in_array($post['classname'],$exPath)){
                mackHtml($this->fetch(':mobile/list'),$post['classname'],2);
            }
            return $this->fetch(':mobile/list');
        }else{
            if(($post['classname'] == 'xiangmu')){
                //热门品牌加盟
                $lick7 = $ProductModel->conditionlist(['aid'=>['in','1169,86522,90909,119539,119502']],'aid,class,litpic,title,thumbnail',5,'click','desc');
            }else{
				if($redis->get('hotPp_'.$post['classname'])){
					$lick7 = json_decode($redis->get('hotPp_'.$post['classname']),true);
				}else{
					$categorys = db('portal_category')->where(['status'=>1,'ishidden'=>1,'path'=>$post['classname']])->field('id,parent_id')->find();
					if($categorys['parent_id'] == 0){
						$cated = db('portal_category')->where(['parent_id' => $categorys['id'],'ishidden' => 1,'status' => 1])->column('id');
						array_unshift($cated, $categorys['id']);
						$a['typeid'] = ['in',$cated];
						$lick7 = db('portal_xm')->where("status = 1 and arcrank = 1")->where($a)->field('aid,class,litpic,title,thumbnail')->limit(5)->order('click desc')->select();
					}else{
						$lick7 = db('portal_xm')->where("status = 1 and arcrank = 1 and typeid = ".$categorys['id'])
							->field('aid,class,litpic,title,thumbnail')->limit(5)->order('click desc')->select();
					}
					$redis->set('hotPp_'.$post['classname'],json_encode($lick7));
				}
                
            }
            //好项目加盟
            $linkwhere = [];
			$lick1 = $ProductModel->conditionlist($linkwhere,'aid,title,class,invested,litpic,click,sum','5,4','click','desc');
            //十大餐饮排行榜
            $lick2 = $ProductModel->conditionArray($linkwhere,'aid,typeid,title,class,invested',10,'weight','desc');
            foreach ($lick2 as $kes=>$v){
                $name = db('portal_category')->where('id = '.$v['typeid'])->field('name')->find();
                $path2 = db('portal_category')->where("name like '$name[name]' and id > 391")->value('path');
                $lick2[$kes]['path2'] = !empty($path2) ? $path2:'';

                $lick2[$kes]['catename'] = str_replace('加盟','',$name['name']);
            }
            if($post['classname']!='xiangmu'){
                $linkwhere['typeid'] = $id;
                if($category['parent_id'] == 0){
                    $linkwhere['typeid'] = ['in',$sonIds];
                }
				//好项目
				if($redis->get('goodPro_'.$post['classname'])){
					$lick1 = json_decode($redis->get('goodPro_'.$post['classname']),true);
				}else{
					$lick1 = $ProductModel->conditionlist($linkwhere,'aid,title,class,invested,litpic,click,sum','5,4','click','desc');
					$redis->set('goodPro_'.$post['classname'],json_encode($lick1));
				}
				
				//十大餐饮排行榜
				if($redis->get('tenPhb_'.$post['classname'])){
					$lick2 = json_decode($redis->get('tenPhb_'.$post['classname']),true);
				}else{
					$lick2 = $ProductModel->conditionArray($linkwhere,'aid,typeid,title,class,invested',10,'weight','desc');
					foreach ($lick2 as $kes=>$v){
						$name = db('portal_category')->where('id = '.$v['typeid'])->field('name')->find();
						$path2 = db('portal_category')->where("name like '$name[name]' and id > 391")->value('path');
						$lick2[$kes]['path2'] = !empty($path2) ? $path2:'';

						$lick2[$kes]['catename'] = str_replace('加盟','',$name['name']);
					}
					$redis->set('tenPhb_'.$post['classname'],json_encode($lick2));
				}
            }
            
            
            //热门专题
			/*
			if($redis->get('rmZhuanti_')){
				$lick4 = json_decode($redis->get('rmZhuanti_'),true);
			}else{
				$lick4 = $NewsModel->conditionArray([],'id,post_title,class,published_time',10,'click','desc');
				foreach ($lick4 as $key => $value) {
					$lick4[$key]['class'] = strpos($value['class'],'/') ? 'news' :$value['class'];
				}
				$redis->set('rmZhuanti_',json_encode($lick4));
			}
			*/
            //品牌专区
			$this->foot_hytj();
			//创业聚焦
			// if($redis->get('daPai_')){
			// 	$dapai = json_decode($redis->get('daPai_'),true);
			// }else{

                if($post['classname']=='xiangmu'){
                    $where18['litpic'] = ['neq',' '];
                }else{
                    if($category['parent_id'] == 0)
                    {
                        //子栏目ID
                        $sonIds =  $CategoryModel::getOneColumn(['parent_id'=>$id],'id');
                        $where18['typeid'] = ['in',$sonIds];
                        $where18['litpic'] = ['neq',' '];
                    }else{
                        $where18['typeid'] = $id;
                        $where18['litpic'] = ['neq',' '];
                    }
                }
				$dapai = $ProductModel->conditionArray($where18,'aid,typeid,title,class,invested,litpic','30,3','click','desc');
				// $redis->set('daPai_',json_encode($dapai));
			// }

            $this->assign('lick1',$lick1);
            $this->assign('lick2',$lick2);
            $this->assign('lick7',$lick7);
            $this->assign('dapai',$dapai);
            $this->assign('tuijian',$tuijian);
			$exPath = ['yangsheng/am','yangsheng/bjp','yangsheng/bj','yangsheng/hs','yangsheng/js','yangsheng/spa','yangsheng/chhf','yangsheng/yj','yangsheng/zl','yangsheng/zy'];
            if(empty($post['address']) && empty($post['num']) && $page == 1 && !in_array($post['classname'],$exPath)){
                mackHtml($this->fetch(':list'),$post['classname']);
            }
            return $this->fetch(':list');
          }
    }

    //项目详情
    public function article_xm($id,$type='')
    {
		$redis = new Redis();
		$ProductModel = new ProductModel();
		//导航行业以及热门行业
		$data = db('portal_xm')->where("aid = $id")->find();
		$category = db('portal_category')->where('id = '.$data['typeid'])->field('id,name,path,parent_id')->find();

		$data['category'] = $category['name'];
		if($data['nativeplace'])
		{
		  $nativeplace = db('sys_enum')->where("evalue = ".$data['nativeplace']." and py != ''")->value("ename");
		  $data['address'] = $nativeplace;
		}else{
		  $data['address'] = $data['address'];
		}
		$typeid = $data['typeid'];
		
		//详情页图片
		$imgs_arrs  = cmf_get_content_images($data['jieshao'].$data['tiaojian'].$data['liucheng']);
		$this->assign('imgs_arrs',$imgs_arrs);
		//自动增加
		Db::name('portal_xm')->where('aid', $id)->setInc('click');
		//项目关联图片表
        $imgs = db("uploads")->where("arcid = ".$id)->select()->toArray();
		$this->assign("name",$category);
		$this->assign("imgs",$imgs);
		$this->assign('data',$data);

		//项目海报
        $haibao = db('uploads')->where('arcid = '.$id)->field('url')->order('aid','asc')->select();
		$this->assign('haibao',$haibao);
        if (\think\Request::instance()->isMobile()) {      
			//项目相关新闻
			if($redis->get('aboutNews_'.$data['aid'])){
				$did = json_decode($redis->get('aboutNews_'.$data['aid']),true);
			}else{
				$did = db("portal_post")->where('did = '.$data['aid'].' and status = 1 and post_status = 1')->field('id,post_title,class,published_time')->limit(6)->select()->toArray();
				if(empty($did)){
				  $wherew['status'] = 1;
				  $wherew['post_status'] = 1;
				  $did = db("portal_post")->where($wherew)->field('id,post_title,class,published_time')->limit(6)
					 ->select()->toArray();
				}
				foreach ($did as $key => $value) {
					$did[$key]['class'] = strpos($value['class'],'/') ? 'news' :$value['class'];
				}
				$redis->set('aboutNews_'.$data['aid'],json_encode($did));
			}
            //项目推荐
			if($redis->get('mobileProTj_'.$data['typeid'])){
				$txiangmu = json_decode($redis->get('mobileProTj_'.$data['typeid']),true);
			}else{
				$txiangmu = db('portal_xm')->where('typeid = '.$data['typeid'].' and status = 1 and arcrank = 1')->field('aid,title,sum,companyname,invested,class,litpic')->limit(10)->select();
				$redis->set('mobileProTj_'.$data['typeid'],json_encode($txiangmu));
			}
            $this->assign('did',$did);
            $this->assign('txiangmu',$txiangmu);
			if($type == 'haibao'){
				return $this->fetch(':mobile/article_poster');
			}elseif($type == 'wenda'){
				
				return $this->fetch(':mobile/article_wenda');
			}
            return $this->fetch(':mobile/article_xm');
        }else{
			//面包屑
			if($redis->get('position_'.$typeid)){
				$array_reverse = json_decode($redis->get('position_'.$typeid),true);
			}else{
				$array_reverse = $this->position($typeid);
				$redis->set('position_'.$typeid,json_encode($array_reverse));
			}
			
			//相关项目
			if($redis->get('aboutPro_'.$typeid)){
				$lick1 = json_decode($redis->get('aboutPro_'.$typeid),true);
			}else{
				$lick1 = db('portal_xm')->where("typeid = $typeid and status = 1 and arcrank = 1")->field('aid,title,litpic,invested,sum,class')->order('click asc')->limit(0,4)->select();
				$redis->set('aboutPro_'.$typeid,json_encode($lick1));
			}
			$inve = str_replace('万','',$data['invested']);
			//品牌项目
			if($redis->get('pinpai_'.$inve.'_'.$typeid)){
				$pinpai = json_decode($redis->get('pinpai_'.$inve.'_'.$typeid),true);
			}else{
				$pinpai = db('portal_xm')->where("invested = "."'$data[invested]' and typeid = $typeid and status = 1 and arcrank = 1")->field('aid,title,litpic,invested,sum,class')->order('click desc')->limit(4)->select();
				$redis->set('pinpai_'.$inve.'_'.$typeid,json_encode($pinpai));
			}
			
			//相关分类
			if($redis->get('aboutCat_'.$typeid)){
				$abouttype = json_decode($redis->get('aboutCat_'.$typeid),true);
			}else{
				$about = db('portal_category')->where('id = '.$typeid)->field('parent_id')->find();
				$abouttype = db('portal_category')->where('parent_id = '.$about['parent_id'])->field('name,path')->limit(14)->select();
				$redis->set('aboutCat_'.$typeid,json_encode($abouttype));
			}
			
			//热门品牌
			if($redis->get('hotCat1_'.$typeid)){
				$hotpinpai1 = json_decode($redis->get('hotCat1_'.$typeid),true);
				$hotpinpai2 = json_decode($redis->get('hotCat2_'.$typeid),true);
			}else{
				$fenlei = db('portal_category')->where('id = '.$typeid)->value('parent_id');
				$cates =  db("portal_category")->where("parent_id = $fenlei and status = 1 and ishidden = 1")->field('id')->select()->toArray();
				$ids = array_column($cates,'id');
				$where['typeid'] = array('in',$ids);
				$hotpinpai = db('portal_xm')->where('status = 1 and arcrank = 1')->where($where)->field('aid,title,typeid,class,invested,litpic')->order('click desc')->limit(19)->select();
				$hotpinpai = $hotpinpai->all();
				foreach ($hotpinpai as $k=>$v){
					$catetype = db('portal_category')->where('id = '.$v['typeid'])->field('name,path')->find();
					$hotpinpai[$k]['cate'] = $catetype['name'];
					$hotpinpai[$k]['class2'] = $catetype['path'];
				}
				$hotpinpai1 = array_slice($hotpinpai,0, 3);
				$hotpinpai2 = array_slice($hotpinpai,3, 16);
				$redis->set('hotCat1_'.$typeid,json_encode($hotpinpai1));
				$redis->set('hotCat2_'.$typeid,json_encode($hotpinpai2));
			}
			//品牌排行
			if($redis->get('Ph_'.$data['class'])){
				$pinpaipaihang = json_decode($redis->get('Ph_'.$data['class']),true);
			}else{
				$pinpaipaihang = db('portal_xm')->where("class = "."'$data[class]' and status = 1 and arcrank = 1")->field('aid,title,invested,litpic,sum,class')->order('click desc')->limit(10)->select();
				$redis->set('Ph_'.$data['class'],json_encode($pinpaipaihang));
			}
			//品牌推荐
			// $w = "FIND_IN_SET('a',flag)";
			if($redis->get('pinpaiTj_'.$typeid)){
				$lick5 = json_decode($redis->get('pinpaiTj_'.$typeid),true);
			}else{
				$lick5 = db('portal_xm')->where('status = 1 and arcrank = 1 and typeid = '.$typeid)->field('aid,title,class,litpic,click,invested')->limit(0,5)->select();
				$redis->set('pinpaiTj_'.$typeid,json_encode($lick5));
			}
			//项目相关新闻
			if($redis->get('aboutNews_'.$data['aid'])){
				$lick7 = json_decode($redis->get('aboutNews_'.$data['aid']),true);
			}else{
				$lick7 = db("portal_post")->where('did = '.$data['aid'].' and status = 1 and post_status = 1')->field('id,post_title,class,published_time')->limit(6)->select()->toArray();
				if(empty($lick7) || count($lick7)<5){
				  $wherew['post_title'] = ['like','%'.$data['title'].'%'];
				  $wherew['status'] = 1;
				  $wherew['post_status'] = 1;
					$lick7 = db("portal_post")->where($wherew)->field('id,post_title,class,published_time')->limit(6)
					 ->select()->toArray();
				}
				if(empty($lick7)){
					$lick7 = db("portal_post")->where('status = 1 and post_status = 1 and parent_id = 401')->field('id,post_title,class,published_time')->limit(6)->select()->toArray();
				}
				foreach ($lick7 as $key => $value) {
					$lick7[$key]['class'] = strpos($value['class'],'/') ? 'news' :$value['class'];
				}
				$redis->set('aboutNews_'.$data['aid'],json_encode($lick7));
			}
			
			
			//品牌排行榜
			if($redis->get('news_phb'.$category['id'])){
				$lick8 = json_decode($redis->get('news_phb'.$category['id']),true);
				$topname = json_decode($redis->get('news_topname'.$category['id']),true);
			}else{
				if($category['parent_id'] == 0){
					$topcateid = db('portal_category')->where(['name'=>$category['name'],'parent_id'=>391])->value('id');
					$topname = str_replace('加盟','',$category['name']);
					$redis->set('news_topname'.$category['id'],json_encode($topname));
				}else{
					$topname = db('portal_category')->where(['id'=>$category['parent_id']])->value('name');
					$topcateid = db('portal_category')->where(['name'=>$topname,'parent_id'=>391])->value('id');
					$topname = str_replace('加盟','',$topname);
					$redis->set('news_topname'.$category['id'],json_encode($topname));
				}
				//这么调用的原因是因为排行榜栏目名称有重复的
				$nowdata = db('portal_category')->where(['name'=>$category['name'],'parent_id'=>$topcateid])->field('id,name,path')->find();
				$pwhere['parent_id'] = $topcateid;
				$pwhere['id'] = ['neq',$nowdata['id']];
				$lick8 = Db::name('portal_category')->where($pwhere)->field('id,name,path')
					->order('list_order asc')->limit(9)->select()->toarray();
				$lick8 = array_reverse(array_merge($lick8,['9'=>$nowdata]));
				$redis->set('news_phb'.$category['id'],json_encode($lick8));
			}
			
			$this->assign('topname',$topname);
			//更多内容
			if($redis->get('moreCat_'.$typeid)){
				$neirong = json_decode($redis->get('moreCat_'.$typeid),true);
			}else{
				$neirong = db('portal_xm')->where('typeid = '.$typeid.' and status = 1 and arcrank = 1')->field('aid,class,title')->orderRaw('rand()')->limit(50)->select();
				$redis->set('moreCat_'.$typeid,json_encode($neirong));
			}
			
			//最新资讯
			if($redis->get('newsZx')){
				$newsInfo = json_decode($redis->get('newsZx'),true);
			}else{
				$newsInfo = db('portal_post')->where('status = 1 and post_status = 1')->field('id,class,post_title,published_time')->order('id desc')->limit(10)->select()->toArray();
				foreach ($newsInfo as $key => $value) {
				   $a = explode('/', $value['class']);
					if(in_array('news', $a)){
						$newsInfo[$key]['class'] = 'news';
					}else{
						$newsInfo[$key]['class'] = $value['class'];
					}
				}
				$redis->set('newsZx',json_encode($newsInfo));
			}
			
			//问答
			if($redis->get('newsWenda')){
				$newsWenda = json_decode($redis->get('newsWenda'),true);
			}else{
				$newsWenda = db('portal_post')->where('parent_id = 392 and status = 1 and post_status = 1')->field('id,class,post_title,published_time')->order('id desc')->limit(10)->select();
				$redis->set('newsWenda',json_encode($newsWenda));
			}
			
			//品牌专区
			$this->foot_hytj();

			$this->assign('lick1',$lick1);
			$this->assign('pinpai',$pinpai);
			$this->assign('abouttype',$abouttype);
			$this->assign('hotpinpai1',$hotpinpai1);
			$this->assign('hotpinpai2',$hotpinpai2);
			$this->assign('pinpaipaihang',$pinpaipaihang);
			$this->assign('neirong',$neirong);
			$this->assign('newsInfo',$newsInfo);
			$this->assign('newsWenda',$newsWenda);
			$this->assign('lick5',$lick5);
			$this->assign("lick7",$lick7);
			$this->assign("lick8",$lick8);
			$this->assign('array_reverse',$array_reverse);
			$ewmurl = 'http://m.91chuangye.com'.$this->request->url();
			$this->assign('ewmurl',$ewmurl);
			if($type == 'haibao'){
				return $this->fetch(':article_poster');
			}elseif($type == 'wenda'){
				return $this->fetch(':article_wenda');
			}
			$model = new PluginCommentModel();
			$where = ['object_id' => $data['aid'],'status'=>['neq',0], 'delete_time' => 0];
			$comment = $model->with("touser,parent")
				->order('create_time desc')->where($where)->limit(6)->select();
			$this->assign("comment",$comment);
			return $this->fetch(':article_xm');
        }
    }
    
	//专题
	public function zhuanti($path)
    {
        $ProductModel = new ProductModel();
        $NewsModel = new NewsModel;

        //允许访问栏目
        $passtypeid = explode(',','2,312,8,10,5,4,7,313,9,1,3,339,6,396,420,734,742,63,350');
        $category = Db::name('portal_category')->where(['path'=>$path])->field('id,parent_id,name')->find();
		//判断当前目录是否存在
		$path = in_array($category['id'],$passtypeid) ? $path : in_array($category['parent_id'],$passtypeid) ? $path : false;
        //不存在返回404
        if(!$path){
            return $this->error1();
        }
        $this->assign('catename',str_replace('加盟','',$category['name']));
        $this->assign('path',$path);

        $sonIds = $this->CategoryModel->getOneColumn(['parent_id'=>$category['id']],'id');
        array_push($sonIds,$category['id']);

        //创业知识
        $zhishiIds = $this->CategoryModel->getOneColumn(['parent_id' => 20], 'id');
        $zhishi = $NewsModel->conditionlist(['parent_id' => ['in', $zhishiIds]], 'id,class,post_title,post_excerpt,create_time,thumbnail', 5, 'published_time', 'desc');

        //创业之道
        $zhidao = $NewsModel->conditionlist(['parent_id' => ['in','32']], 'id,class,post_title,post_excerpt,create_time,thumbnail', 5, 'published_time', 'desc');

        //创业故事
        $gushi = $NewsModel->conditionlist(['parent_id' => ['in','11']], 'id,class,post_title,post_excerpt,create_time,thumbnail', 5, 'published_time', 'desc');
        $this->assign('zhishi', $zhishi);
        $this->assign('zhidao', $zhidao);
        $this->assign('gushi', $gushi);
        $this->assign('path',$path);

        //排行榜
        $Top = $ProductModel->conditionlist(['typeid'=>['in',$sonIds]],'aid,class,litpic,click,sum,invested,title,typeid,description,logo',10,'click','desc');
        $this->assign('Top',$Top);
        
        $ZtTdk = $this->CategoryModel->getOne(['id'=>$category['id']],'zt_title,zt_keywords,zt_description');
        $this->assign('ZtTdk',$ZtTdk);

        //TDK
        $name = str_replace('加盟','',$category['name']);
        if($category['id'] == 2){
            $Seo['Seo_Title'] = '餐饮连锁项目招商加盟条件费用多少钱_餐饮加盟十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = '餐饮加盟,餐饮加盟费用,餐饮加盟多少钱,餐饮排行榜';
            $Seo['Seo_Description'] = '91创业网餐饮频道为您汇集全国各地特色餐饮美食加盟项目,详细为您介绍各品牌餐饮项目加盟条件以及加盟费用，并为您总结了餐饮行业投资加盟项目排行榜，筛选出餐饮加盟投资十大品牌。';
        }else if($category['id'] == 734){
            $Seo['Seo_Title'] = '商务快捷连锁酒店招商加盟条件费用多少钱_酒店招商加盟项目十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = '酒店加盟,酒店加盟费用,酒店加盟多少钱,酒店排行榜';
            $Seo['Seo_Description'] = '91创业网酒店加盟频道为您汇集全国各地酒店加盟项目,详细为您介绍各品牌酒店项目加盟条件以及加盟费用,并为您总结了酒店行业投资加盟项目排行榜,筛选出酒店加盟投资十大品牌。';
        }else if($category['id'] == 8){
            $Seo['Seo_Title'] = '母婴用品行业招商加盟条件费用多少钱_母婴店加盟十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = '母婴加盟,母婴加盟费用,母婴加盟多少钱,母婴排行榜';
            $Seo['Seo_Description'] = '91创业网母婴加盟频道为您汇集全国各地母婴加盟项目,详细为您介绍各品牌母婴项目加盟条件以及加盟费用,并为您总结了母婴行业投资加盟项目排行榜,筛选出母婴加盟投资十大品牌。';
        }else if($category['id'] == 10){
            $Seo['Seo_Title'] = '教育行业培训机构招商加盟条件费用多少钱_教育辅导班加盟十大品牌排行榜top10-91创业网';
            $Seo['Seo_Keywords'] = '教育加盟,教育加盟费用,教育加盟多少钱,教育排行榜';
            $Seo['Seo_Description'] = '91创业网教育加盟频道为您汇集全国各地教育加盟项目,详细为您介绍各品牌教育项目加盟条件以及加盟费用,并为您总结了教育行业投资加盟项目排行榜,筛选出教育加盟投资十大品牌。';
        }else if($category['id'] == 312){
            $Seo['Seo_Title'] = '酒水行业项目招商加盟代理条件费用多少钱_酒水加盟代理十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = '酒水加盟,酒水加盟费用,酒水加盟多少钱,酒水排行榜';
            $Seo['Seo_Description'] = '91创业网酒水加盟频道为您汇集全国各地酒水加盟项目,详细为您介绍各品牌酒水项目加盟条件以及加盟费用,并为您总结了酒水行业投资加盟项目排行榜,筛选出酒水加盟投资十大品牌。';
        }else if($category['id'] == 5 || $category['id'] == 4 || $category['id'] == 7 || $category['id'] == 9 || $category['id'] == 339 || $category['id'] == 1 || $category['id'] == 313 || $category['id'] == 3){
            $Seo['Seo_Title'] = $name.'连锁项目招商加盟条件费用多少钱_'.$name.'加盟十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = $name.'加盟，'.$name.'加盟费用，'.$name.'加盟多少钱，'.$name.'排行榜';
            $Seo['Seo_Description'] = '91创业网'.$name.'加盟频道为您汇集全国各地'.$name.'加盟项目,详细为您介绍各品牌'.$name.'项目加盟条件以及加盟费用,并为您总结了'.$name.'行业投资加盟项目排行榜,筛选出'.$name.'加盟投资十大品牌。';
        }else if($category['id'] == 6){
            $Seo['Seo_Title'] = '汽车服务连锁项目招商加盟条件费用多少钱_汽车加盟十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = '汽车加盟,汽车加盟费用,汽车加盟多少钱,汽车排行榜';
            $Seo['Seo_Description'] = '91创业网汽车加盟频道为您汇集全国各地汽车加盟项目,详细为您介绍各品牌汽车项目加盟条件以及加盟费用,并为您总结了汽车行业投资加盟项目排行榜,筛选出汽车加盟投资十大品牌。';
        }else if($category['id'] == 396){
            $Seo['Seo_Title'] = '金融平台代理招商加盟条件费用多少钱_金融加盟十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = '金融加盟,金融加盟费用,金融加盟多少钱,金融排行榜';
            $Seo['Seo_Description'] = '91创业网金融加盟频道为您汇集全国各地金融加盟项目,详细为您介绍各品牌金融项目加盟条件以及加盟费用,并为您总结了金融行业投资加盟项目排行榜,筛选出金融加盟投资十大品牌。';
        }else if($category['id'] == 420){
            $Seo['Seo_Title'] = '互联网创业项目代理加盟条件费用多少钱_零成本网络创业项目排行榜top10-91创业网';
            $Seo['Seo_Keywords'] = '互联网加盟,互联网加盟费用,互联网加盟多少钱,互联网排行榜';
            $Seo['Seo_Description'] = '91创业网互联网加盟频道为您汇集全国各地互联网加盟项目,详细为您介绍各品牌互联网项目加盟条件以及加盟费用,并为您总结了互联网行业投资加盟项目排行榜,筛选出互联网加盟投资十大品牌。';
        }else{
            $Seo['Seo_Title'] = $name.'连锁项目招商加盟条件费用多少钱_'.$name.'加盟十大品牌排行榜TOP10-91创业网';
            $Seo['Seo_Keywords'] = $name.'加盟，'.$name.'加盟费用，'.$name.'加盟多少钱，'.$name.'排行榜';
            $Seo['Seo_Description'] = '91创业网'.$name.'加盟频道为您汇集全国各地'.$name.'加盟项目,详细为您介绍各品牌'.$name.'项目加盟条件以及加盟费用,并为您总结了'.$name.'行业投资加盟项目排行榜,筛选出'.$name.'加盟投资十大品牌。';
        }
        
        $this->assign('seo',$Seo);
        $this->assign('category_id',$category['id']);

        if(($category['id'] == 3) || ($category['parent_id'] == 3)){
                $logo = 3;
            }else if(($category['id'] == 2) || ($category['parent_id'] == 2)){
                $logo = 2;
            }else{
                $logo = '';
            }
            $this->assign('logo',$logo);

        if (\think\Request::instance()->isMobile()) {

            $areaModel = new AreaModel();
            //行业分类
            $hangye = $this->CategoryModel->getSonCate($path);

            //项目调用
            $Xm = $ProductModel->brand(['status'=>1,'arcrank'=>1,'typeid'=>['in',$sonIds]],'aid,typeid,title,class,logo,litpic,invested,companyname,sum',30,'click','desc');
            foreach ($Xm as $k=>$v){
                $Xm[$k]['cate_name'] = db('portal_category')->where(['id'=>$v['typeid']])->value('name');
                $Xm[$k]['cate_path'] = db('portal_category')->where(['id'=>$v['typeid']])->value('path');
            }
            $count = 30 - count($Xm);
            if(count($Xm) < 30){
                $Xm2 = $ProductModel->brand(['status'=>1,'arcrank'=>1],'aid,typeid,title,class,logo,litpic,invested,companyname,sum',$count,'aid','desc');
                foreach ($Xm2 as $k=>$v){
                    $Xm2[$k]['cate_name'] = db('portal_category')->where(['id'=>$v['typeid']])->value('name');
                    $Xm2[$k]['cate_path'] = db('portal_category')->where(['id'=>$v['typeid']])->value('path');
                }
                $Xm = array_merge($Xm,$Xm2);
            }
			
            //创业资讯
            $where25['parent_id'] = ['in','399,401,402,403,404,405,406,407,408,409,433'];
            $zixun = db('portal_post')->where($where25)->where('post_status = 1 and status = 1')->field('id,post_title,post_excerpt,thumbnail,published_time,class,create_time')->order('published_time desc')->limit(5)->select();


            //热门项目
            $arr = '2,1,4,5,7,10,3,6,8,9,312,313,396,420';
            $catess = db("portal_category")->where('id', 'in', $arr)->where('status = 1 and ishidden = 1')->field('id,name,path')->order('list_order asc')->select();
            $cates = $catess->all();
            foreach($cates as $keys=>$v)
            {
                $cated = db('portal_category')->where(['parent_id' => $v['id'],'ishidden' => 1,'status' => 1])->column('id');
                array_unshift($cated, $v['id']);
                $cates[$keys]['ids'] = implode(',', $cated);
            }
            foreach ($cates as $key => $val) {
                $wheres['typeid'] = array('in', $val['ids']);
                $where3['status'] = 1;
                $where3['arcrank'] = 1;
                $val['data'] = db("portal_xm")->where($wheres)->where($where3)->field('aid,title,invested,litpic,class')->order('pubdate asc')->limit(24)->select();
                $datas[] = $val;
            }

            $this->assign('zixun',$zixun);
            $this->assign('catess',$catess);
            $this->assign('datas',$datas);
            $this->assign('Xm',$Xm);
            $this->assign('type',$hangye);
            $this->assign('sys',$areaModel->allarea('(evalue MOD 500)=0'));
			mackHtml($this->fetch(':mobile/special'),$path.'_zt',2);
            return $this->fetch(':mobile/special');
        }else{
            //行业分类
            // $hangye = $this->CategoryModel->getSonCate($path);
            //$hangye = $ProductModel->brand(['status'=>1,'arcrank'=>1,'typeid'=>['in',$sonIds]],'aid,title,class',50,'click','desc');
            if($category['parent_id'] == 0){
                $buxian = 1;
                $buxianPath = '';
                $hangye = $this->CategoryModel->allList(['parent_id'=>$category['id']],'id,name,path,parent_id');
            }else{
                $buxianPath = $this->CategoryModel->getOne(['id'=>$category['parent_id']],'id,name,path,parent_id');
                $buxian = 2;
                $hangye = $this->CategoryModel->allList(['parent_id'=>$category['parent_id']],'id,name,path,parent_id');
            }



            $Xm = $ProductModel->brand(['status'=>1,'arcrank'=>1,'typeid'=>['in',$sonIds]],'aid,title,class,logo,litpic,invested',55,'click','desc');
            //判断数量少的时候调取相关数据
            $count = count($Xm);
            // if(count($Xm) < 51){
            //     $Xm2 = $ProductModel->brand(['status'=>1,'arcrank'=>1,'parent_id'=>2],'aid,title,class,logo,litpic,invested',$count,'aid','desc');
            //     $Xm = array_merge($Xm,$Xm2);
            // }

            //栏目下四个项目
            $Cate_Xm = $ProductModel->brand(['status'=>1,'arcrank'=>1,'typeid'=>['in',$sonIds]],'aid,logo,class,title',4,'aid','desc');

            //品牌上榜字
            // $brandz = $ProductModel->brand(['status'=>1,'arcrank'=>1,'flag' => ['like', '%h%'],'typeid'=>['in',$sonIds]],'aid,title,class',4,'aid','desc');

            //品牌上榜图
            // $brandt = $ProductModel->brand(['status'=>1,'arcrank'=>1,'flag' => ['like', '%e%'],'typeid'=>['in',$sonIds],'logo'=>['neq',' ']],'aid,title,class,logo',4,'aid','desc');

            //精品推荐字
            // $boutiquez = $ProductModel->brand(['status'=>1,'arcrank'=>1,'flag' => ['like', '%a%'],'typeid'=>['in',$sonIds]],'aid,title,class',4,'aid','desc');

            //精品推荐图
            // $boutiquet = $ProductModel->brand(['status'=>1,'arcrank'=>1,'flag' => ['like', '%f%'],'typeid'=>['in',$sonIds],'logo'=>['neq',' ']],'aid,title,class,logo',4,'aid','desc');

            //排行榜
//            $topXm = $ProductModel->conditionArray(['typeid'=>['in',$sonIds]],'aid,title,invested,class',10,'click','desc');

            //模块2大图（左一）
            // $banner1 = $ProductModel->brand(['status'=>1,'arcrank'=>1,'flag' => ['like', '%b%'],'typeid'=>['in',$sonIds],'litpic'=>['neq',' ']],'aid,title,class,litpic',1,'aid','desc');

            //模块2大图（右四）
            // $banner2 = $ProductModel->brand(['status'=>1,'arcrank'=>1,'flag' => ['like', '%j%'],'typeid'=>['in',$sonIds],'litpic'=>['neq',' ']],'aid,title,class,litpic,invested',4,'aid','desc');

            //模块3（10个项目）
            $Modular = $ProductModel->conditionArray(['typeid'=>['in',$sonIds],'flag'=>['like','%d%'],'litpic'=>['neq',' ']],'aid,title,invested,class,litpic',10,'aid','desc');
            //最新项目推荐
            $NewsXm = $ProductModel->conditionArray(['typeid'=>['in',$sonIds],'litpic'=>['neq',' ']],'aid,title,invested,class,litpic',8,'update_time','desc');
            //热门推荐
            // $HotXm = $ProductModel->conditionArray(['typeid'=>['in',$sonIds],'flag'=>['like','%z%']],'aid,title,invested,class,litpic',12,'click','desc');
            $HotXm = $ProductModel->conditionArray(['flag'=>['like','%z%']],'aid,title,invested,class,litpic',12,'click','desc');
            //热点资讯推荐


             $ids = $ProductModel->GetxmId(['status'=>1,'arcrank'=>1,'typeid'=>$category['id'],'litpic'=>['neq',' ']],'click','desc','aid');
             $HotPost = $NewsModel->conditionarray(['did'=>['in',$ids]],'id,post_title,post_excerpt,thumbnail,class',6,'click','desc');
             if(empty($HotPost)){
                // $ids = $NewsModel->NewsYi(['parent_id'=>['in','399']]);
                // array_push($ids,'11','20','32','37','399');
                $HotPost = $NewsModel->conditionarray(['parent_id'=>['in','401']],'id,post_title,post_excerpt,thumbnail,class',6,'click','desc');
            }


            // $ids = $NewsModel->NewsYi(['name'=>$category['name'].'资讯','parent_id'=>399]);
            // $Cate_Patn = $this->CategoryModel->getOne(['name'=>$category['name'].'资讯','parent_id'=>399],'path');
            // $HotPost = $NewsModel->conditionarray(['parent_id'=>['in',$ids]],'id,post_title,post_excerpt,thumbnail,class',6,'click','desc');
            // if(empty($HotPost)){
            //     $ids = $NewsModel->NewsYi(['parent_id'=>['in','11,20,32,37,399']]);
            //     array_push($ids,'11','20','32','37','399');
            //     $HotPost = $NewsModel->conditionarray(['parent_id'=>['in',$ids]],'id,post_title,post_excerpt,thumbnail,class',6,'click','desc');
            //     $Cate_Patn['path'] = 'news';
            // }

            foreach ($HotPost as $key => $value) {
                $a = explode('/', $value['class']);
                if (in_array('news', $a)) {
                    $HotPost[$key]['class'] = 'news';
                } else {
                    $HotPost[$key]['class'] = $value['class'];
                }
            }
            //最新餐饮资讯
            $ida = $NewsModel->NewsYi(['parent_id'=>['in','399']]);
            array_push($ida,'399');
            $Newscanyin = $NewsModel->navNews(['parent_id'=>['in',$ida]],'id,post_title,post_excerpt,class,thumbnail,create_time','published_time','desc',5);
            foreach ($Newscanyin as $key => $value) {
                $a = explode('/', $value['class']);
                if (in_array('news', $a)) {
                    $Newscanyin[$key]['class'] = 'news';
                } else {
                    $Newscanyin[$key]['class'] = $value['class'];
                }
            }
            //创业指南
            $zhishiIdsa = $this->CategoryModel->getOneColumn(['parent_id' => 37], 'id');
            $zhinan = $NewsModel->conditionlist(['parent_id' => ['in', $zhishiIdsa]], 'id,class,post_title,post_excerpt,create_time,thumbnail', 5, 'published_time', 'desc');
            //热门加盟行业
   //          $HotHy = $this->CategoryModel->allListArray(['parent_id'=>$category['id']],'id,name,path',32,'list_order','asc');
			// if(!$HotHy){
			// 	$HotHy = $this->CategoryModel->allListArray(['parent_id'=>$category['parent_id']],'id,name,path',32,'list_order','asc');
			// }


            //热门加盟项目
            // $HotJmxm = $ProductModel->categroyData($category['id'],32);

            //热门加盟地区
            $areaModel = new AreaModel();
            $HotAddress = $areaModel->allarea('(evalue MOD 500)=0');


            //品牌名称
            $brandName = $ProductModel->conditionArray(['typeid'=>['in',$sonIds]],'aid,title,class',300,'aid','desc');
            $youlian = '';
            // $youlian = db("flink")->where("typeid = ".$category['id']." and ischeck = 1")->field('webname,url')->order("dtime desc")->limit(30)->select();
            //底部项目
            $this->foot_hytj();

            $this->assign('Xm',$Xm);
            $this->assign('count',$count);
            $this->assign('Cate_Xm',$Cate_Xm);
            
//            $this->assign('brandz',$brandz);
//            $this->assign('brandt',$brandt);
            $this->assign('hangye',$hangye);
//            $this->assign('boutiquez',$boutiquez);
//            $this->assign('boutiquet',$boutiquet);
//            $this->assign('topXm',$topXm);
//            $this->assign('banner1',$banner1);
//            $this->assign('banner2',$banner2);
            $this->assign('Modular',$Modular);
            $this->assign('NewsXm',$NewsXm);
            $this->assign('HotXm',$HotXm);
            $this->assign('HotPost',$HotPost);
            $this->assign('Newscanyin',$Newscanyin);
            // $this->assign('Cate_Patn',$Cate_Patn);
            $this->assign('zhinan', $zhinan);
            // $this->assign('HotHy', $HotHy);
            // $this->assign('HotJmxm', $HotJmxm);
            $this->assign('HotAddress',$HotAddress);
            $this->assign('brandName', $brandName);
            $this->assign('youlian', $youlian);
            $this->assign('buxian',$buxian);
            $this->assign('buxianPath',$buxianPath);
			$this->zuoce();
			mackHtml($this->fetch(':special'),$path.'_zt');
            return $this->fetch(':special');
        }
    }
    function cutArticle($data,$cut=120)
    {
        $str="…";
        $data=trim(strip_tags($data));//去除html标记
        $pattern = "/&amp;[a-zA-Z]+;/";//去除特殊符号
        $data=preg_replace($pattern,"",$data);
        if(!is_numeric($cut)){
            return $data;
        }
        if($cut != 0){
            $data=mb_strimwidth($data,0,$cut,$str);
            return $data;
        }

    }
}