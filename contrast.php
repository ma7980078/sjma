<?php
define('IN_DISCUZ', true);
ini_set( 'display_errors', 'On' );
error_reporting(E_ERROR);


//接收前台文件
$file = $_FILES['contrast'];
//重设置文件名
$filename = time() .'_'.$file['name'];
$z_name = substr($file['name'], strripos($file['name'], '.'));
$path = './contrast/' . $filename;//设置移动路径
move_uploaded_file($file['tmp_name'], $path);
//表用函数方法 返回数组
$exfn = _readExcel($path,$z_name); // 读取内容
$result_data = upload_file($exfn, $path); // 上传数据

function _readExcel($path,$z_name){
    //引用PHPexcel 类
    include_once('./Classes/PHPExcel.php');
    include_once('./Classes/PHPExcel/IOFactory.php');//静态类
    $type = $z_name=='.xls' ? 'Excel5' : 'Excel2007';//设置为Excel5代表支持2003或以下版本，Excel2007代表2007版
    $xlsReader = PHPExcel_IOFactory::createReader($type);
    $xlsReader->setReadDataOnly(true);
    $xlsReader->setLoadSheetsOnly(true);
    $Sheets = $xlsReader->load($path);
//        var_dump($Sheets);die;
    //开始读取上传到服务器中的Excel文件，返回一个二维数组

    $dataArray = $Sheets->getSheet(0)->toArray();
    return $dataArray;
}

//将数据导入数据库
function upload_file($data, $path){
    $one_title = array();
    array_push($one_title, $data[0]);

    //循环第一层title，把没用的去掉
    foreach($one_title[0] as $key=>$val){
        if($val!==NULL && $val!=='合计'){
            $one_title_new[] = $one_title[0][$key];
        }
    }

    //删除第一项
    unset($data[0]);
    $arr=array();
    array_push($arr, $data[1]);
    unset($data[1]);

    $result=[];
    for($i=19;$i<22;$i++){
        $result[$i.'_garage_num']         =   0;//车库数量
        $result[$i.'_garage_money']       =   0;//车库金额
        $result[$i.'_house_num']          =   0;//住宅数量
        $result[$i.'_house_money']        =   0;//住宅金额
        $result[$i.'_house_area']         =   0;//住宅面积
        $result[$i.'_support_num']        =   0;//配套数量
        $result[$i.'_support_money']      =   0;//配套金额
        $result[$i.'_support_area']       =   0;//配套面积
    }


    $new_data=[];
    $type=[];

    foreach($data as $key=>$val){
            $new_data[] =array_merge(array_slice($val,3,1),array_slice($val,11));
    }


//    $arr = array_slice($arr[0],11);

    foreach($new_data as $key=>$val){
        foreach($val as $k=>$v){
            if($v===NULL){
                unset($new_data[$key][$k]);
            }
        }

        $new_data[$key] = array_values($new_data[$key]);
    }

    foreach($new_data as $k=>$v){
        if(mb_strstr($v[0],"地下车库") || mb_strstr($v[0],"车位")){
            $garage_num=1;
            $garage_money=4;
            for($i=19;$i<22;$i++){
                $result[$i.'_garage_num']    +=   $v[$garage_num];
                $result[$i.'_garage_money']  +=   $v[$garage_money];
                $garage_num+=6;
                $garage_money+=6;
//                var_dump($result);die;
            }
        }
        if(mb_strstr($v[0],"住宅") || mb_strstr($v[0],"别墅")){
            $house_num=1;
            $house_area=2;
            $house_money=4;
            for($i=19;$i<22;$i++){
                $result[$i.'_house_num']    +=   $v[$house_num];
                $result[$i.'_house_money']  +=   $v[$house_money];
                $result[$i.'_house_area']   +=   $v[$house_area];
                $house_num+=6;
                $house_area+=6;
                $house_money+=6;
            }
        }
        if(mb_strstr($v[0],"储藏室")){
            $support_num=1;
            $support_area=2;
            $support_money=4;
            for($i=19;$i<22;$i++){
                $result[$i.'_support_num']    +=   $v[$support_num];
                $result[$i.'_support_money']  +=   $v[$support_money];
                $result[$i.'_support_area']   +=   $v[$support_area];
                $support_num+=6;
                $support_area+=6;
                $support_money+=6;
            }
        }

    }
    return $result;

}

for($i=19;$i<22;$i++) {

?>


        <table border="0" cellspacing="60px">
            <tr>
                <th colspan="4">20<?php echo $i;?>年签约确认收入</th>
            </tr>
            <tr>
                <th>类型</th>
                <th>套数</th>
                <th>面积</th>
                <th>金额</th>
            </tr>
            <tr>
                <td>住宅</td>
                <td><?php echo $result_data[$i.'_house_num']; ?></td>
                <td><?php echo $result_data[$i.'_house_area']; ?></td>
                <td><?php echo $result_data[$i.'_house_money']/10000; ?>(万)</td>
            </tr>
            <tr>
                <td>车库</td>
                <td><?php echo $result_data[$i.'_garage_num']; ?></td>
                <td></td>
                <td><?php echo $result_data[$i.'_garage_money']/10000; ?>(万)</td>
            </tr>
            <tr>
                <td>配套</td>
                <td><?php echo $result_data[$i.'_support_num']; ?></td>
                <td><?php echo $result_data[$i.'_support_area']; ?></td>
                <td><?php echo $result_data[$i.'_support_money']/10000; ?>(万)</td>
            </tr>
        </table>
<?php
    }
?>
