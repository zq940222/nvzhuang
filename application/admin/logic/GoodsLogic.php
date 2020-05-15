<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/19
 * Time: 18:54
 */

namespace app\admin\logic;


use think\Model;

class GoodsLogic extends Model
{
    /**
     * 获取 规格的 笛卡尔积
     * @param $goods_id 商品 id
     * @param $spec_arr 笛卡尔积
     * @return string 返回表格字符串
     */
    public function getSpecInput($goods_id, $spec_arr)
    {
        // 排序
        foreach ($spec_arr as $k => $v)
        {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);
        foreach ($spec_arr_sort as $key =>$val)
        {
            $spec_arr2[$key] = $spec_arr[$key];
        }


        $clo_name = array_keys($spec_arr2);
        $spec_arr2 = combineDika($spec_arr2); //  获取 规格的 笛卡尔积

        $spec = model('Spec')->column('id,name'); // 规格表
        $specItem = model('SpecItem')->column('id,item,spec_id');//规格项
        $keySpecGoodsPrice = model('SpecGoodsPrice')->where('goods_id', '=',$goods_id)->column('key,key_name,tag_price,price,store_count,sku,spec_image');//规格项

        $str = "<table width='100px' class='table table-bordered' id='spec_input_tab'>";
        $str .="<tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v)
        {
            $str .=" <td><b>{$spec[$v]}</b></td>";
        }
        $str .="<td><b>吊牌价</b></td>
                <td><b>原价</b></td>
                <td><b>库存</b></td>
                <td><b>sku</b></td>
                <td><b>图片</b></td>
               <td><b>操作</b></td>
             </tr>";
        // 显示第二行开始
        foreach ($spec_arr2 as $k => $v)
        {
            $str .="<tr>";
            $item_key_name = array();
            foreach($v as $k2 => $v2)
            {
                $str .="<td>{$specItem[$v2]['item']}</td>";
                $item_key_name[$v2] = $spec[$specItem[$v2]['spec_id']].':'.$specItem[$v2]['item'];
            }
            ksort($item_key_name);
            $item_key = implode(',', array_keys($item_key_name));
            if(!array_key_exists($item_key,$keySpecGoodsPrice)) {
                $keySpecGoodsPrice[$item_key]["tag_price"] = 0;
                $keySpecGoodsPrice[$item_key]["price"] = 0;
                $keySpecGoodsPrice[$item_key]["store_count"] = 0;
                $keySpecGoodsPrice[$item_key]["sku"] = 0;
                $keySpecGoodsPrice[$item_key]["spec_image"] = '';
            }
            $str .="<td><input type='text' name='row[item][$item_key][tag_price]' value='{$keySpecGoodsPrice[$item_key]["tag_price"]}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
            $str .="<td><input type='text' name='row[item][$item_key][price]' value='{$keySpecGoodsPrice[$item_key]["price"]}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
            $str .="<td><input type='text' name='row[item][$item_key][store_count]' value='{$keySpecGoodsPrice[$item_key]["store_count"]}' onkeyup='this.value=this.value.replace(/[^\d]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d]/g,\"\")' /></td>";
            $str .="<td><input type='text' name='row[item][$item_key][sku]' value='{$keySpecGoodsPrice[$item_key]["sku"]}' onkeyup='this.value=this.value.replace(/[^\d]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d]/g,\"\")' /></td>";
            $str .="<td><input id='c-image-{$k}' type='hidden' name='row[item][$item_key][spec_image]' value='{$keySpecGoodsPrice[$item_key]["spec_image"]}' />";
            $str .="<span><button type='button' id='plupload-image-{$k}' class='btn btn-danger plupload list-block' data-input-id='c-image-{$k}' data-mimetype='image/gif,image/jpeg,image/png,image/jpg,image/bmp' data-multiple='false' data-preview-id='p-image-{$k}'><i class='fa fa-upload'></i> 上传</button></span>";
            $str .="<ul class='row list-inline plupload-preview' id='p-image-{$k}' ></ul></td>";
            $str .="<td><button type='button' class='btn btn-primary delete_item'>无效</button></td>";
            $str .="</tr>";
        }
        $str .= "</table>";
        return $str;
    }
}