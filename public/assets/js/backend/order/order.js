define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/order/index' + location.search,
                    add_url: 'order/order/add',
                    edit_url: 'order/order/remark',
                    del_url: '',
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
                pageSize: 20,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'users.nickname', title: __('用户名'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'order_sn', title: __('Order_sn'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        // {field: 'order_sn_unique', title: __('唯一订单号')},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.status},
                        {field: 'is_refund', title: __('是否退款'),searchList: {"1":__('是'),"0":__('否')}, formatter: Table.api.formatter.status},
                        // {field: 'consignee', title: __('Consignee'),operate:false},
                        // {field: 'province_id', title: __('Province_id'),operate:false},
                        // {field: 'city_id', title: __('City_id'),operate:false},
                        // {field: 'area_id', title: __('Area_id'),operate:false},
                        // {field: 'address', title: __('Address'),operate:false},
                        {field: 'order_goods.0.goods_name', title: __('商品名称'), operate:false},
                        {field: 'order_goods.0.spec_image', title: __('商品主图'), operate:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'order_goods.0.spec_key_name', title: __('商品规格'), operate:false},
                        {field: 'user_remark', title: __('用户备注'), operate:false},
                        {field: 'admin_remark', title: __('后台备注'), operate:false},
                        // {field: 'mobile', title: __('Mobile')},
                        {field: 'users.wx', title: __('微信号'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        // {field: 'goods_price', title: __('Goods_price'), operate:'BETWEEN'},
                        // {field: 'shipping_price', title: __('Shipping_price'), operate:'BETWEEN'},
                        {field: 'order_amount', title: __('付款金额'), operate:'BETWEEN'},
                        // {field: 'total_amount', title: __('Total_amount'), operate:'BETWEEN'},
                        // {field: 'shipment', title: __('Shipment')},
                        // {field: 'user_money', title: __('User_money'),operate:false},
                        // {field: 'goods_num', title: __('Goods_num')},
                        // {field: 'profit', title: __('Profit'),operate:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'shipping_time', title: __('Shipping_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'confirm_time', title: __('Confirm_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    title: __('订单详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'order/order/detail',
                                    callback: function (data) {
                                    }
                                },
                                {
                                    name: 'shipping',
                                    title: __('配送'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-ambulance',
                                    url: 'order/order/shipping',
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        if (row.status == 1)
                                        {
                                            return true;
                                        }else {
                                            return false;
                                        }
                                    },
                                    callback: function (data) {
                                    }
                                }
                            ],
                            formatter: Table.api.formatter.operate}
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
        detail: function () {
            Controller.api.bindevent();
        },
        shipping: function () {
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