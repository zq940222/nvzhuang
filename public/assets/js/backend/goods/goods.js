define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'goods/goods/index' + location.search,
                    add_url: 'goods/goods/add',
                    edit_url: 'goods/goods/edit',
                    del_url: 'goods/goods/del',
                    multi_url: 'goods/goods/multi',
                    table: 'goods',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'brand_id', title: __('Brand_id')},
                        {field: 'style_id', title: __('Style_id')},
                        {field: 'activity_id', title: __('Activity_id')},
                        {field: 'goods_sn', title: __('Goods_sn')},
                        {field: 'name', title: __('Name')},
                        {field: 'cover_image', title: __('Cover_image'), events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'goods_images', title: __('Goods_images'), events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'price1', title: __('Price1'), operate:'BETWEEN'},
                        {field: 'price2', title: __('Price2'), operate:'BETWEEN'},
                        {field: 'price3', title: __('Price3'), operate:'BETWEEN'},
                        {field: 'price4', title: __('Price4'), operate:'BETWEEN'},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'click_count', title: __('Click_count')},
                        {field: 'store_count', title: __('Store_count')},
                        {field: 'keywords', title: __('Keywords')},
                        {field: 'is_on_sale', title: __('Is_on_sale'), searchList: {"0":__('Is_on_sale 0'),"1":__('Is_on_sale 1')}, formatter: Table.api.formatter.normal},
                        {field: 'is_free_shipping', title: __('Is_free_shipping'), searchList: {"0":__('Is_free_shipping 0'),"1":__('Is_free_shipping 1')}, formatter: Table.api.formatter.normal},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'is_new', title: __('Is_new'), searchList: {"0":__('Is_new 0'),"1":__('Is_new 1')}, formatter: Table.api.formatter.normal},
                        {field: 'is_hot', title: __('Is_hot'), searchList: {"0":__('Is_hot 0'),"1":__('Is_hot 1')}, formatter: Table.api.formatter.normal},
                        {field: 'sales_sum', title: __('Sales_sum')},
                        {field: 'spu', title: __('Spu')},
                        {field: 'sku', title: __('Sku')},
                        {field: 'template_id', title: __('Template_id')},
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