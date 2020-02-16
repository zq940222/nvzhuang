define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'freight/template/index' + location.search,
                    add_url: 'freight/template/add',
                    edit_url: 'freight/template/edit',
                    del_url: 'freight/template/del',
                    multi_url: 'freight/template/multi',
                    table: 'freight_template',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'template_id',
                sortName: 'template_id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'template_id', title: __('Template_id')},
                        {field: 'template_name', title: __('Template_name')},
                        {field: 'is_enable_default', title: __('Is_enable_default'), searchList: {"0":__('Is_enable_default 0'),"1":__('Is_enable_default 1')}, formatter: Table.api.formatter.normal},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };

    var type;//计价方式
    var unit = '件';
    $(function () {
        $(document).on("click", '#submit', function (e) {
            $('#submit').attr('disabled',true);
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
        var type = $("input[name='type']:checked").val();
        var is_enable_default = $("input[name='is_enable_default']:checked").val();
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
            url: "{:Url('Freight/save')}",
            data: {template_id:template_id,template_name:template_name,type:type,config_list:config_list,is_enable_default:is_enable_default},
            async:false,
            dataType: "json",
            error: function () {
                layer.alert("服务器繁忙, 请联系管理员!");
            },
            success: function (data) {
                if (data.status == 1) {
                    layer.msg(data.msg,{icon: 1,time: 2000},function(){
                        location.href = "{:Url('Freight/index')}";
                    });
                } else {
                    $('#submit').attr('disabled',false);
                    $.each(data.result, function (index, item) {
                        $('span.err').show();
                        $('#err_'+index).text(item);
                    });
                    layer.msg(data.msg, {icon: 2,time: 3000});
                }
            }
        });
    }
    $(function () {
        $(document).on("click", '.select_area', function (e) {
            $('.select_area').removeClass('select_area_focus');
            $(this).addClass('select_area_focus');
            var url = "freight/template/area";
            layer.open({
                type: 2,
                title: '选择地区',
                shadeClose: true,
                shade: 0.2,
                area: ['420px', '400px'],
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
    function call_back(area_list) {
        var area_list_name = '';
        var area_list_id = '';
        $.each(area_list, function (index, item) {
            area_list_name += item.name + ',';
            area_list_id += item.id + ',';
        });
        var area_focus = $('.select_area_focus');
        if(area_list_id.length > 1){
            area_list_id = area_list_id.substr(0,area_list_id.length-1);
            area_list_name = area_list_name.substr(0,area_list_name.length-1);
        }
        area_focus.val(area_list_name);
        area_focus.parent().find('.area_ids').val(area_list_id);
        layer.closeAll('iframe');
    }

    return Controller;

});