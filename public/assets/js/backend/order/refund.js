define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/refund/index' + location.search,
                    add_url: '',
                    edit_url: '',
                    del_url: '',
                    multi_url: '',
                    agree_url: 'order/refund/agree',
                    table: 'refund_order',
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
                        {field: 'users.nickname', title: __('用户名')},
                        {field: 'order_sn', title: __('Order_sn')},
                        {field: 'order_id', title: __('Order_id')},
                        {field: 'order_goods_id', title: __('Order_goods_id')},
                        {field: 'goods.name', title: __('商品名称')},
                        {field: 'goods_num', title: __('Goods_num')},
                        {field: 'order_price', title: __('Order_price'), operate:'BETWEEN'},
                        {field: 'images', title: __('Images'), events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'courier_company', title: __('Courier_company')},
                        {field: 'courier_no', title: __('Courier_no')},
                        {field: 'status', title: __('Status'), searchList: {"-1":__('Status -1'),"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    title: __('详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'order/refund/detail',
                                    callback: function (data) {
                                    }
                                },
                        ],
                            formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            // 获取选中项
            $(document).on("click", ".btn-agree", function () {
                var ids = Table.api.selectedids(table);
                var url = 'order/refund/agree'
                var options = {url: url, data: {ids: ids}};
                Layer.confirm(__('您确认退款选中订单吗?'),
                    {icon: 3, title: __('Warning'), shadeClose: true},
                    function (index) {
                        Fast.api.ajax(options, function (data, ret) {
                            Toastr.success(data.msg);
                            table.bootstrapTable('refresh');
                        }, function (data, ret) {

                        });
                        Layer.close(index);
                    }
                );

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
        del:function (){

        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});