define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {

        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    }
    var unit = '件';
    $(function () {
        $(document).on("click", '#submit', function (e) {
            verifyForm();
        })
    })
    //运费配置单个对象
    function ConfigItem(config_id, area_ids, first_unit, first_money, continue_unit, continue_money, is_default) {
        this.config_id = config_id;
        this.area_ids = area_ids;
        this.first_unit = first_unit;
        this.first_money = first_money;
        this.continue_unit = continue_unit;
        this.continue_money = continue_money;
        this.is_default = is_default;
    }
    function verifyForm(){
        $('span.err').hide();
        $('span.err').text('');
        var config_list = new Array();
        var template_id = $("input[name='template_id']").val();
        var template_name = $("input[name='template_name']").val();
        var is_enable_default = $("select[name='is_enable_default']").val();
        var config_item = $("#config_list").find('tr');
        config_item.each(function(i,o){
            var area_ids_input = $(this).find("input[name^='area_ids']");
            var first_unit_val = $(this).find("input[name^='first_unit']").val();
            var config_id_val = $(this).find("input[name^='config_id']").val();
            var first_money_val = $(this).find("input[name^='first_money']").val();
            var continue_unit_val = $(this).find("input[name^='continue_unit']").val();
            var continue_money_val = $(this).find("input[name^='continue_money']").val();
            var is_default_val = $(this).find("input[name^='is_default']").val();
            if (area_ids_input.val().length > 0 || $('.default_config').length > 0) {
                var configItem = new ConfigItem(config_id_val, area_ids_input.val(), first_unit_val, first_money_val, continue_unit_val, continue_money_val, is_default_val);
                config_list.push(configItem);
            }
        })
        $.ajax({
            type: "POST",
            url: "Freight/template/save",
            data: {template_id:template_id,template_name:template_name,config_list:config_list,is_enable_default:is_enable_default},
            async:false,
            dataType: "json",
            error: function () {
                layer.alert("服务器繁忙, 请联系管理员!");
            },
            success: function (data) {
                if (data.code == 1) {
                    layer.msg(data.msg,{icon: 1,time: 2000},function(){

                    });
                } else {
                    layer.msg(data.msg, {icon: 2,time: 3000});
                }
            }
        });
    }
    $(function () {
        $(document).on("click", '.select_area', function (e) {
            $('.select_area').removeClass('select_area_focus');
            $(this).addClass('select_area_focus');
            var url = "area";
            layer.open({
                name:'area',
                type: 2,
                title: '选择地区',
                shadeClose: true,
                shade: 0.2,
                area: ['500px', '400px'],
                content: url
            });
        })
    })
    $(function () {
        $(document).on("click", '.new_config', function (e) {
            var html =  '<tr><td class="left"> <div class="w80">' +
                '<input name="is_default[]" value="0" type="hidden"></div></td> <td align="left"> <div class="w150"> ' +
                '<input class="select_area" readonly name="" value="" type="text"> <input name="area_ids[]" class="area_ids" value="" type="hidden"> ' +
                '<input name="config_id[]" value="" type="hidden"> </div> </td> <td align="left"> <div class="w150"> ' +
                '<input name="first_unit[]" value="" onpaste="this.value=this.value.replace(/[^\\d.]/g,\'\')" onkeyup="this.value=this.value.replace(/[^\\d.]/g,\'\')" type="text"> ' +
                '<span class="first_unit_span">'+unit+'</span> </div> </td> <td align="left"> <div class="w150"> ' +
                '<input name="first_money[]" value="" type="text"><span>元</span> </div> </td> <td align="left"> <div class="w150">' +
                '<input name="continue_unit[]" value="" onpaste="this.value=this.value.replace(/[^\\d.]/g,\'\')" onkeyup="this.value=this.value.replace(/[^\\d.]/g,\'\')" type="text"> ' +
                '<span class="continue_unit_span">'+unit+'</span> </div> </td> <td align="left"> <div class="w150">' +
                '<input name="continue_money[]" value="" type="text"><span>元</span> </div> </td> <td align="left" class="handle"> <div class="w150"> ' +
                '<a class="btn red" onclick="$(this).parent().parent().parent().remove();"><i class="fa fa-trash-o"></i>删除</a> </div> </td> </tr>';
            $('#config_list').append(html);
        })
    })

    $(function () {
        $(document).on("change", '#c-is_enable_default', function (e) {
            initDefault();
        })
    })
    function initDefault(){
        var default_config_length = $('.default_config').length;
        var is_enable_default = $("#c-is_enable_default").val();
        if (is_enable_default == 1 && default_config_length == 0) {
            var html =  '<tr class="default_config"><td class="left"> <div class="w80">' +
                '默认配置<input name="is_default[]" value="1" type="hidden"></div></td> <td align="left"> <div class="w150"> ' +
                '<input readonly name="" value="中国" type="text"> <input name="area_ids[]" class="area_ids" value="" type="hidden"> ' +
                '<input name="config_id[]" value="" type="hidden"> </div> </td> <td align="left"> <div class="w150"> ' +
                '<input name="first_unit[]" value="" onpaste="this.value=this.value.replace(/[^\\d.]/g,\'\')" onkeyup="this.value=this.value.replace(/[^\\d.]/g,\'\')" type="text"> ' +
                '<span class="first_unit_span">'+unit+'</span> </div> </td> <td align="left"> <div class="w150"> ' +
                '<input name="first_money[]" value="" type="text"><span>元</span> </div> </td> <td align="left"> <div class="w150">' +
                '<input name="continue_unit[]" value="" onpaste="this.value=this.value.replace(/[^\\d.]/g,\'\')" onkeyup="this.value=this.value.replace(/[^\\d.]/g,\'\')" type="text"> ' +
                '<span class="continue_unit_span">'+unit+'</span> </div> </td> <td align="left"> <div class="w150">' +
                '<input name="continue_money[]" value="" type="text"><span>元</span> </div> </td> <td align="left" class="handle"> <div class="w150"> ' +
                '</div> </td> </tr>';
            $('#config_list').prepend(html);
        }else if(is_enable_default == 0){
            $('.default_config').remove();
        }
    }
    $(document).ready(function(){
        type = $("input[name='type']:checked").val();
        // initType();
        initDefault();
    });
    $(function () {
        $(document).on("click", ".type", function (e) {
            if(typeof(type) != 'undefined' && type != $(this).val()){
                type = $(this).val();
                clear_freight_config();
            }else{
                type = $("input[name='type']:checked").val();
                // initType();
            }
        })
    })
    function initType(){
        var config_table = $('#config_table');
        if(parseInt(type) >= 0){
            config_table.show();
        }
        var first_unit = $('.first_unit');
        var continue_unit = $('.continue_unit');
        var first_unit_span = $('.first_unit_span');
        var continue_unit_span = $('.continue_unit_span');
        switch(parseInt(type))
        {
            case 0:
                unit = "件";
                first_unit.html('首件');
                continue_unit.html('续件');
                break;
            case 1:
                unit = "克";
                first_unit.html('首重');
                continue_unit.html('续重');
                break;
            case 2:
                unit = "立方米";
                first_unit.html('首体积');
                continue_unit.html('续体积');
                break;
        }
        first_unit_span.html(unit);
        continue_unit_span.html(unit);
    }

    /**
     * 清空运费模板信息
     */
    function clear_freight_config() {
        var template_id = $("input[name='template_id']").val();
        layer.confirm('切换计价方式后，当前模板的运费信息将被清空，确定继续吗？', {
            btn: ['确定', '取消']
        }, function () {
            if (template_id > 0) {
                $('#config_list').empty();
                // initType();
                layer.closeAll();
            }else{
                layer.closeAll();
                type = $("input[name='type']:checked").val();
                // initType();
            }
        }, function (index) {
            $("input[name='type']").attr("checked",false);
            $("input[name='type'][value="+type+"]").attr("checked",true);
            type = $("input[name='type']:checked").val();
            // initType();
            layer.close(index);
        });
    }
    function confirm(){
        var input = $("input[type='checkbox']:checked");
        if (input.length == 0) {
            layer.alert('请添加区域', {icon: 2});
            return false;
        }
        var area_list = new Array();
        input.each(function(i,o){
            var area_id = $(this).attr("value");
            var area_name = $(this).data("name");
            var cartItemCheck = new Area(area_id,area_name);
            area_list.push(cartItemCheck);
        })
        window.parent.call_back(area_list);
    }
    //地区对象
    function Area(id, name) {
        this.id = id;
        this.name = name;
    }
    //  添加配送区域
    function addArea(){
        //
        var province = $("#province").val(); // 省份
        var city = $("#city").val();        // 城市
        var district = $("#district").val(); // 县镇
        var text = '';  // 中文文本
        var tpl = ''; // 输入框 html
        var is_set = 0; // 是否已经设置了

        // 设置 县镇
        if(district > 0){
            text = $("#district").find('option:selected').text();
            tpl = '<li><label><input class="checkbox" type="checkbox" checked name="area_list[]" data-name="'+text+'" value="'+district+'">'+text+'</label></li>';
            is_set = district; // 街道设置了不再设置市
        }
        // 如果县镇没设置 就获取城市
        if(is_set == 0 && city > 0){
            text = $("#city").find('option:selected').text();
            tpl = '<li><label><input class="checkbox" type="checkbox" checked name="area_list[]" data-name="'+text+'"  value="'+city+'">'+text+'</label></li>';
            is_set = city;  // 市区设置了不再设省份

        }
        // 如果城市没设置  就获取省份
        if(is_set == 0 && province > 0){
            text = $("#province").find('option:selected').text();
            tpl = '<li><label><input class="checkbox" type="checkbox" checked name="area_list[]" data-name="'+text+'"  value="'+province+'">'+text+'</label></li>';
            is_set = province;

        }

        var obj = $("input[class='checkbox']"); // 已经设置好的复选框拿出来
        var exist = 0;  // 表示下拉框选择的 是否已经存在于复选框中
        $(obj).each(function(){
            if($(this).val() == is_set){  //当前下拉框的如果已经存在于 复选框 中
                layer.alert('已经存在该区域', {icon: 2});  // alert("已经存在该区域");
                exist = 1; // 标识已经存在
            }
        })
        if(!exist)
            $('#area_list').append(tpl); // 不存在就追加进 去
    }

    //删除运费确定事件
    $(function () {
        $(document).on("click", '.delete_template', function (e) {
            var template_id = $(this).data('template-id');
            layer.confirm('确认删除？', {
                    btn: ['确定', '取消'] //按钮
                }, function () {
                    $.ajax({
                        type: 'post',
                        url: 'Freight/template/del',
                        data: {ids: template_id},
                        dataType: 'json',
                        success: function (data) {
                            layer.closeAll();
                            if (data.code == 1) {
                                layer.msg(data.msg, {icon: 1, time: 2000}, function () {
                                    window.location.reload();
                                });
                            } else {
                                layer.msg(data.msg, {icon: 2, time: 2000});
                            }
                        }
                    })
                }, function (index) {
                    layer.close(index);
                }
            );
        })
    })
    return Controller;
})