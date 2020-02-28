define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/order/index' + location.search,
                    add_url: 'order/order/add',
                    edit_url: 'order/order/edit',
                    del_url: 'order/order/del',
                    multi_url: 'order/order/multi',
                    table: 'order',
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'order_sn', title: __('Order_sn')},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')}, formatter: Table.api.formatter.status},
                        {field: 'consignee', title: __('Consignee')},
                        {field: 'province_id', title: __('Province_id')},
                        {field: 'city_id', title: __('City_id')},
                        {field: 'area_id', title: __('Area_id')},
                        {field: 'address', title: __('Address')},
                        {field: 'mobile', title: __('Mobile')},
                        {field: 'goods_price', title: __('Goods_price'), operate:'BETWEEN'},
                        {field: 'shipping_price', title: __('Shipping_price'), operate:'BETWEEN'},
                        {field: 'order_amount', title: __('Order_amount'), operate:'BETWEEN'},
                        {field: 'total_amount', title: __('Total_amount'), operate:'BETWEEN'},
                        {field: 'shipment', title: __('Shipment')},
                        {field: 'user_money', title: __('User_money'), operate:'BETWEEN'},
                        {field: 'goods_num', title: __('Goods_num')},
                        {field: 'profit', title: __('Profit'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'shipping_time', title: __('Shipping_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'confirm_time', title: __('Confirm_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
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