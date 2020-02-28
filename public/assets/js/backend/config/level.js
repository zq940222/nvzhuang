define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'config/level/index' + location.search,
                    add_url: 'config/level/add',
                    edit_url: 'config/level/edit',
                    del_url: 'config/level/del',
                    multi_url: 'config/level/multi',
                    table: 'level',
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
                        {field: 'name', title: __('Name')},
                        {field: 'nickname', title: __('Nickname')},
                        {field: 'total_money', title: __('Total_money'), operate:'BETWEEN'},
                        {field: 'goods_payment', title: __('Goods_payment'), operate:'BETWEEN'},
                        {field: 'margin', title: __('Margin'), operate:'BETWEEN'},
                        {field: 'bonus', title: __('Bonus'), operate:'BETWEEN'},
                        {field: 'discount', title: __('Discount'), operate:'BETWEEN'},
                        {field: 'experience', title: __('Experience'), operate:'BETWEEN'},
                        {field: 'rebate', title: __('Rebate'), operate:'BETWEEN'},
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