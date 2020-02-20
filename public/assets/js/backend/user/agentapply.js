define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/agentapply/index' + location.search,
                    add_url: 'user/agentapply/add',
                    edit_url: 'user/agentapply/edit',
                    del_url: 'user/agentapply/del',
                    multi_url: 'user/agentapply/multi',
                    table: 'agent_apply',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'superior_id', title: __('Superior_id')},
                        {field: 'agency_id', title: __('Agency_id')},
                        {field: 'name', title: __('Name')},
                        {field: 'mobile', title: __('Mobile')},
                        {field: 'password', title: __('Password')},
                        {field: 'wx', title: __('Wx')},
                        {field: 'id_card', title: __('Id_card')},
                        {field: 'pay_type', title: __('Pay_type'), searchList: {"1":__('Pay_type 1'),"2":__('Pay_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'bank_account', title: __('Bank_account')},
                        {field: 'pay_time', title: __('Pay_time'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'pay_certificate_images', title: __('Pay_certificate_images'), events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"-1":__('Status -1')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
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