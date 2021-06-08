<?php
    define('IN_DISCUZ', true);
    ini_set( 'display_errors', 'On' );
    error_reporting(E_ERROR);


    //接收前台文件
    $file = $_FILES['excel'];
    //重设置文件名
    $filename = time() .'_'.$file['name'];
    $z_name = substr($file['name'], strripos($file['name'], '.'));
    $path = './excel/' . $filename;//设置移动路径
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
        $arr = array();
        array_push($arr, $data[0]);

        //删除第一项
        unset($data[0]);


        $result['garage_num']         =   0;//车库数量
        $result['garage_money']       =   0;//车库金额
        $result['house_num']          =   0;//住宅数量
        $result['house_money']        =   0;//住宅金额
        $result['house_area']         =   0;//住宅面积
        $result['support_num']        =   0;//配套数量
        $result['support_money']      =   0;//配套金额
        $result['support_area']       =   0;//配套面积

        $all_data=[];
        //数据重构，把表头设置成数组的键
        foreach($data as $key=>$val){
            foreach($val as $k=>$v){
                $all_data[$key][$arr[0][$k]]=$v;
            }
        }

        foreach($all_data as $k=>$v){
//            var_dump($v);die;

            if(mb_substr($v['产品类型'],0,2)=='车库'){
                $result['garage_num']+=$v['套数'];
                $result['garage_money']+=$v['合同额']/10000;
            }
            if(mb_substr($v['产品类型'],0,2)=='住宅'){
                $result['house_num']+=$v['套数'];
                $result['house_money']+=$v['合同额']/10000;
                $result['house_area']+=$v['面积'];
            }
            if(mb_substr($v['产品类型'],0,2)=='配套' ){
                $result['support_num']+=$v['套数'];
                $result['support_money']+=$v['合同额']/10000;
                $result['support_area']+=$v['面积'];
            }

        }
        return $result;


    }



?>
<table border="0" cellspacing="60px">
    <tr>
        <th>类型</th>
        <th>套数</th>
        <th>面积</th>
        <th>金额</th>
    </tr>
    <tr>
        <td>住宅</td>
        <td><?php echo $result_data['house_num'];?></td>
        <td><?php echo $result_data['house_area'];?></td>
        <td><?php echo $result_data['house_money'];?>(万)</td>
    </tr>
    <tr>
        <td>车库</td>
        <td><?php echo $result_data['garage_num'];?></td>
        <td></td>
        <td><?php echo $result_data['garage_money'];?>(万)</td>
    </tr>
    <tr>
        <td>配套</td>
        <td><?php echo $result_data['support_num'];?></td>
        <td><?php echo $result_data['support_area'];?></td>
        <td><?php echo $result_data['support_money'];?>(万)</td>
    </tr>
</table>
