/* global jQuery, tfm_category_select_data */
(function ($, data) {
    $(function () {
        var $row_template = wp.template('tfm-category-row'),
            SelectView = Backbone.View.extend({
                rowTemplate: $row_template,
                input: null,
                readout: null,
                defaultLabel: false,
                initialize: function () {
                    this.input = this.$el.siblings('.tfm-category-input');
                    this.readout = this.$el.siblings('.tfm-selected-category');

                    if (1 === this.$el.data('is-bulk')) {
                        this.defaultLabel = data.strings.no_change;
                    } else if (1 === this.$el.data('is-variation')) {
                        this.defaultLabel = data.strings.same_as_parent;
                    } else {
                        this.defaultLabel = data.strings.none;
                    }

                    this.$el.click({view: this}, this.openModal);
                    this.$el.siblings('.tfm-reset-category').click({view: this}, this.resetCategory);
                },
                render: function () {
                    this.selectCategory(this.input.val());
                },
                bindEvents: function () {
                    $(document.body).on('click', '.tfm-select-done', {view: this}, this.updateSelection);
                    $(document.body).on('wc_backbone_modal_response', {view: this}, this.completeSelection);
                },
                unbindEvents: function () {
                    $(document.body).off('click', '.tfm-select-done', this.updateSelection);
                    $(document.body).off('wc_backbone_modal_response', this.completeSelection);
                },
                openModal: function (event) {
                    var view = event.data.view;

                    event.preventDefault();

                    $(this).SSTBackboneModal({
                        'template': 'tfm-category-select-modal'
                    });

                    view.bindEvents();
                    view.initModal();
                },
                initModal: function () {
                    var view = this,
                        $list = $('.tfm-category-list');

                    $list.empty();

                    _.each(data.category_list, function (rowData) {
                        $list.append(view.rowTemplate(rowData));
                    });

                    $('.tfm-category-search').hideseek();
                },
                updateSelection: function (event) {
                    var $target = $(event.target),
                        $tr = $target.closest('tr');

                    $('input[name="category"]').val($tr.data('id'));
                    $('#btn-ok').trigger('click');
                },
                completeSelection: function (event, target, posted) {
                    if ('tfm-category-select-modal' === target) {
                        event.data.view.selectCategory(posted['category']);
                        event.data.view.unbindEvents();
                    }
                },
                selectCategory: function (selected_category) {
                    var categories = _.indexBy(data.category_list, 'product_tax_code');

                    if ('' === selected_category) {
                        this.readout.text(this.defaultLabel);
                    } else {
                        var category = categories[selected_category];

                        this.readout.text(category['name'] + ' (' + category['product_tax_code'] + ')');
                    }

                    this.input
                        .val(selected_category)
                        .trigger('change');
                },
                resetCategory: function (event) {
                    event.data.view.selectCategory('');
                }
            });

        function initialize() {
            $('.tfm-select-category:not(.initialized)').each(function () {
                var selectView = new SelectView({
                    el: $(this)
                });

                selectView.render();

                $(this).addClass('initialized');
            });
        }

        initialize();

        // Runs initialize() when new variations are added via AJAX
        $(document)
            .on('ajaxSuccess', function (event, xhr, settings) {
                var actions = [
                    'wcv_json_add_variation',
                    'wcv_json_link_all_variations',
                    'woocommerce_add_variation',
                    'woocommerce_link_all_variations'
                ];

                $.each(actions, function (key, action) {
                    if (-1 !== settings.data.indexOf(action)) {
                        initialize();
                    }
                });
            })
            .on('woocommerce_variations_loaded', initialize);
    });
})(jQuery, tfm_category_select_data);