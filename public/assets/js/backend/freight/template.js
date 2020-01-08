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
                        {field: 'type', title: __('Type')},
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
    return Controller;
});