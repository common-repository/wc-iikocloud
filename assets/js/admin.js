(function ($) {

    "use strict";

    // Common functions.
    window.wpwc = {

        WCIikoNonce: $('#wc_iikocloud_nonce').val(),

        // Convert selected options to JSON object.
        selected_as_JSON: function (options_selected) {
            let result = [];

            options_selected.each(function () {
                result.push({
                    id: $(this).val(),
                    name: $(this).text()
                });
            })

            return result;
        },

        // Add a record to the terminal.
        add_terminal_record: function (text, type, title, terminal) {

            if (undefined === text) return;

            let messageType = 'error' === type ? 'terminal_error' : 'notice' === type ? 'terminal_notice' : 'terminal_data',
                wpwc_terminal = $('#wpwcIikoTerminal');

            if (undefined === terminal && 0 !== wpwc_terminal.length) {
                terminal = wpwc_terminal;
            } else {
                return;
            }

            terminal.prepend($('<p></p>')
                .attr('class', messageType)
                .text(text)
            );

            if (undefined !== title) {
                terminal.prepend($('<p></p>')
                    .attr('class', 'terminal_title')
                    .text(title)
                );
            }
        },

        // Add logs to the terminal.
        add_terminal_logs: function (notices, errors) {

            if (undefined === notices || undefined === errors) return;

            if (null !== notices) {
                $.each(notices, function (index, value) {
                    wpwc.add_terminal_record(value, 'notice');
                });
            }

            if (null !== errors) {
                $.each(errors, function (index, value) {
                    wpwc.add_terminal_record(value, 'error');
                });
            }
        },

        // Show preloader and disable get buttons.
        request_start: function () {

            $('#wpwcPreloader').removeClass('hidden');
            $('.wpwc_form_submit').prop('disabled', true);
        },

        // Hide preloader, enable get buttons and disable get nomenclature button if the nomenclature wasn't get.
        request_stop: function (isGetIikoNomenclatureDisable = false) {

            $('#wpwcPreloader').addClass('hidden');
            $('.wpwc_form_submit').prop('disabled', false);

            if (true === isGetIikoNomenclatureDisable) {
                $('#getIikoNomenclature').prop('disabled', true);
            }
        },

        // Check selected keys.
        is_empty: function (key, message, isGetIikoNomenclatureDisable) {
            if (null === key || undefined === key || 0 === key.length) {
                wpwc.add_terminal_record(message, 'error');
                wpwc.request_stop(isGetIikoNomenclatureDisable);

                return false;
            }
        },

        // Auto adjust select size.
        auto_adjust_select_size: function (select_id) {
            let select = document.getElementById(select_id);

            select.size = select.length;

            if (select.length === 1) {
                select.selectedIndex = 0;
            }
        }
    };

    $(document).ready(function () {

        if ($('#wpwcIikoPage').length) {

            // Remove premium banner.
            $('#wpwcPremiumClose').on('click', function () {
                $('#wpwcPremium').fadeOut('fast');
            });

            // Clear terminal.
            $('#wpwcIikoClearTerminal').on('click', function (e) {

                e.preventDefault();

                $('#wpwcIikoTerminal').empty();
            });

            // Remove access token.
            $('#wpwcIikoRemoveAccessToken').on('click', function (e) {

                e.preventDefault();

                $.ajax({
                    type: 'post',
                    url: ajaxurl,
                    data: {
                        action: 'wc_iikocloud__remove_access_token_ajax',
                        wc_iikocloud_nonce: wpwc.WCIikoNonce,
                    },

                    success: function (response) {

                        if (response === '0') {
                            wpwc.add_terminal_record('AJAX error: 0.', 'error');

                        } else {

                            if (response.success) {
                                wpwc.add_terminal_record(response.data.message, 'notice');
                            } else {
                                wpwc.add_terminal_record(response.data.message, 'error');
                            }
                        }
                    },

                    error: function (xhr, textStatus, errorThrown) {
                        wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                    },
                });
            });

            // Clear iiko nomenclature info.
            function clear_iiko_nomenclature_info() {
                $('#wpwcIikoNomenclatureInfoWrap .wpwc_nomenclature_value').each(function () {
                    $(this).text('-');
                });
            }

            // Clear organization info.
            function clear_organization_info() {

                $('#wpwcIikoTerminalsWrap').addClass('hidden');
                $('#wpwcIikoMenusWrap').addClass('hidden');
                $('#wpwcIikoNomenclatureInfoWrap').addClass('hidden');
                $('#wpwcIikoNomenclatureGroupsWrap').addClass('hidden');
                $('#wpwcIikoNomenclatureImportedWrap').addClass('hidden');
                $('#wpwcIikoTerminals').empty();
                $('#wpwcIikoGroups').empty();

                clear_iiko_nomenclature_info();
            }

            // Build categories tree.
            function fill_in_groups_tree(groups, prefix = '', deep = 0) {

                $.each(groups, function (index, value) {

                    if (null === value.parentGroup) {
                        prefix = '';
                    }

                    $('#wpwcIikoGroups').append($('<option></option>')
                        .attr('value', value.id)
                        .attr('class', true === value.isDeleted ? 'deleted' : '')
                        .text(value.name)
                        .text(prefix + value.name)
                    );

                    if (null !== value.childGroups) {
                        prefix = recursive_prefix(deep);

                        fill_in_groups_tree(value.childGroups, prefix, deep + 1);

                        prefix = recursive_prefix(deep - 1);
                    }
                });

                wpwc.auto_adjust_select_size('wpwcIikoGroups');
            }

            // Build prefix indent for subcategories.
            function recursive_prefix(deep = 0, symbol = '\u2014') {

                let prefix = '';

                for (let i = 0; i <= deep; i++) {
                    prefix += symbol;
                }

                return prefix;
            }

            // iiko form handler.
            $('.wpwc_form_submit').on('click', function (e) {

                e.preventDefault();

                let isGetIikoNomenclatureDisable = true,
                    organizations = $('#wpwcIikoOrganizations'),
                    organizationId = organizations.val(),
                    organizationName = $('#wpwcIikoOrganizations option:selected').text(),
                    terminals_wrap = $('#wpwcIikoTerminalsWrap'),
                    terminals = $('#wpwcIikoTerminals'),
                    terminal_ids = $('#wpwcIikoTerminals option:selected'),
                    menus_wrap = $('#wpwcIikoMenusWrap'),
                    menus = $('#wpwcIikoMenus'),
                    menu_id = menus.val(),
                    price_categories = $('#wpwcIikoPrice_categories'),
                    price_category_id = price_categories.val(),
                    groups = $('#wpwcIikoGroups'),
                    chosen_groups = groups.val(),
                    nomenclature_info_wrap = $('#wpwcIikoNomenclatureInfoWrap'),
                    nomenclature_groups_wrap = $('#wpwcIikoNomenclatureGroupsWrap'),
                    cities_wrap = $('#wpwcIikoCitiesWrap'),
                    cities = $('#wpwcIikoCities'),
                    city_ids = cities.val();

                wpwc.request_start();

                switch ($(this).attr('name')) {

                    // Get iiko organizations.
                    case 'get_iiko_organizations':

                        clear_organization_info();

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__get_organizations_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.organizations) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {

                                        organizations.empty();

                                        $.each(response.data.organizations, function (index, value) {
                                            organizations.append($('<option></option>')
                                                .attr('value', value.id)
                                                .text(value.name)
                                            );

                                            wpwc.add_terminal_record(value.name + ' - ' + value.id, 'data');
                                        });

                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.organization_title);

                                        $('#wpwcIikoOrganizationsWrap').removeClass('hidden');

                                        // Unblock get nomenclature button.
                                        isGetIikoNomenclatureDisable = false;
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Save organization for import.
                    case 'save_iiko_organization_import':

                        if (false === wpwc.is_empty(organizationId, wc_iikocloud.chose_organization, true)) return;

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__save_organization_import_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                organizationId: organizationId,
                                organizationName: organizationName,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.result) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {
                                        wpwc.add_terminal_record(response.data.result, 'notice');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Get iiko terminals.
                    case 'get_iiko_terminals':

                        if (false === wpwc.is_empty(organizationId, wc_iikocloud.chose_organization, true)) return;

                        terminals.empty();
                        terminals_wrap.addClass('hidden');

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__get_terminals_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                organizationId: organizationId,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.terminalGroups) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {

                                        terminals.empty();

                                        $.each(response.data.terminalGroups, function (index, value) {

                                            if (value.organizationId === organizationId) {

                                                $.each(value.items, function (index, value) {
                                                    terminals.append($('<option></option>')
                                                        .attr('value', value.id)
                                                        .text(value.name)
                                                    );

                                                    wpwc.add_terminal_record(value.name + ' - ' + value.id, 'data');
                                                });
                                            }
                                        });

                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.terminals_title);

                                        wpwc.auto_adjust_select_size('wpwcIikoTerminals');

                                        terminals_wrap.removeClass('hidden');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop();
                            }
                        });

                        break;

                    // Save organization and terminals for export.
                    case 'save_iiko_organization_terminals_export':

                        if (false === wpwc.is_empty(organizationId, wc_iikocloud.chose_organization, true)) return;
                        if (false === wpwc.is_empty(terminal_ids, wc_iikocloud.chose_terminals, true)) return;

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__save_organization_terminals_export_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                organizationId: organizationId,
                                organizationName: organizationName,
                                chosenTerminals: wpwc.selected_as_JSON(terminal_ids),
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.result) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {
                                        wpwc.add_terminal_record(response.data.result, 'notice');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Get iiko nomenclature.
                    case 'get_iiko_nomenclature':

                        if (false === wpwc.is_empty(organizationId, wc_iikocloud.chose_organization, true)) return;

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__get_nomenclature_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                organizationId: organizationId,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.groupsTree) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {

                                        groups.empty();

                                        // Output groups info to the terminal.
                                        $.each(response.data.groups_list, function (index, value) {
                                            wpwc.add_terminal_record(value + ' - ' + index, 'data');
                                        });
                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.groups_title);

                                        // Output products info to the terminal.
                                        $.each(response.data.simple_dishes, function (index, value) {
                                            wpwc.add_terminal_record(value + ' - ' + index, 'data');
                                        });
                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.dishes_title);

                                        $.each(response.data.simple_goods, function (index, value) {
                                            wpwc.add_terminal_record(value + ' - ' + index, 'data');
                                        });
                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.goods_title);

                                        $.each(response.data.simple_services, function (index, value) {
                                            wpwc.add_terminal_record(value + ' - ' + index, 'data');
                                        });
                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.services_title);

                                        $.each(response.data.simple_modifiers, function (index, value) {
                                            wpwc.add_terminal_record(value + ' - ' + index, 'data');
                                        });
                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.modifiers_title);

                                        // Output sizes info to the terminal.
                                        $.each(response.data.simple_sizes, function (index, value) {
                                            wpwc.add_terminal_record(value + ' - ' + index, 'data');
                                        });
                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.sizes_title);

                                        clear_iiko_nomenclature_info();

                                        // Output nomenclature general info.
                                        $('#wpwcIikoNomenclatureGroups').text(Object.keys(response.data.groups_list).length);
                                        $('#wpwcIikoNomenclatureProductCategories').text(response.data.productCategories.length);
                                        $('#wpwcIikoNomenclatureDishes').text(Object.keys(response.data.simple_dishes).length);
                                        $('#wpwcIikoNomenclatureGoods').text(Object.keys(response.data.simple_goods).length);
                                        $('#wpwcIikoNomenclatureServices').text(Object.keys(response.data.simple_services).length);
                                        $('#wpwcIikoNomenclatureModifiers').text(Object.keys(response.data.simple_modifiers).length);
                                        $('#wpwcIikoNomenclatureSizes').text(response.data.sizes.length);
                                        $('#wpwcIikoNomenclatureRevision').text(response.data.revision);

                                        fill_in_groups_tree(response.data.groupsTree);

                                        nomenclature_info_wrap.removeClass('hidden');
                                        nomenclature_groups_wrap.removeClass('hidden');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Get iiko external menus.
                    case 'get_iiko_menus':

                        menus.empty();
                        price_categories.empty();
                        menus_wrap.addClass('hidden');

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__get_menus_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.externalMenus) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {

                                        menus.empty();
                                        price_categories.empty();

                                        $.each(response.data.externalMenus, function (index, value) {
                                            menus.append($('<option></option>')
                                                .attr('value', value.id)
                                                .text(value.name)
                                            );

                                            wpwc.add_terminal_record(value.name + ' - ' + value.id, 'data');
                                        });

                                        if (undefined !== response.data.priceCategories && response.data.priceCategories.length > 0) {
                                            $.each(response.data.priceCategories, function (index, value) {
                                                price_categories.append($('<option></option>')
                                                    .attr('value', value.id)
                                                    .text(value.name)
                                                );

                                                wpwc.add_terminal_record(value.name + ' - ' + value.id, 'data');
                                            });
                                        } else {
                                            price_categories.append($('<option></option>').attr('value', '').text('---'));
                                        }

                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.menu_title);

                                        menus_wrap.removeClass('hidden');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Get menu nomenclature.
                    case 'get_iiko_menu_nomenclature':

                        if (false === wpwc.is_empty(menu_id, wc_iikocloud.chose_menu, true)) return;
                        if (false === wpwc.is_empty(organizationId, wc_iikocloud.chose_organization, true)) return;

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__get_menu_nomenclature_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                menuId: menu_id,
                                organizationId: organizationId,
                                priceCategoryId: price_category_id,
                                menuName: $('#wpwcIikoMenus option:selected').text(),
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    let itemsCount = 0;

                                    if (undefined === response.data.categories) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {

                                        groups.empty();

                                        // Output categories info to the nomenclature block.
                                        $.each(response.data.categories, function (index, value) {
                                            groups.append($('<option></option>')
                                                .attr('value', index)
                                                .text(value)
                                            );
                                        });

                                        // Output items info to the terminal.
                                        $.each(response.data.items, function (index, value) {
                                            $.each(value, function (cat_index, cat_value) {
                                                wpwc.add_terminal_record(cat_value + ' - ' + cat_index, 'data');
                                                itemsCount++;
                                            });
                                            wpwc.add_terminal_record('', 'data', response.data.categories[index] + ' - ' + index);
                                        });
                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.groups_title + ' / ' + wc_iikocloud.dishes_title);

                                        clear_iiko_nomenclature_info();

                                        // Output nomenclature general info.
                                        $('#wpwcIikoNomenclatureGroups').text(Object.keys(response.data.categories).length);
                                        $('#wpwcIikoNomenclatureDishes').text(itemsCount);

                                        wpwc.auto_adjust_select_size('wpwcIikoGroups');

                                        nomenclature_info_wrap.removeClass('hidden');
                                        nomenclature_groups_wrap.removeClass('hidden');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Import groups and products to WooCommerce.
                    case 'import_iiko_groups_products':

                        if (false === wpwc.is_empty(chosen_groups, wc_iikocloud.chose_groups, false)) return;

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__import_nomenclature_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                chosenGroups: chosen_groups,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    let notices = response.data.notices,
                                        errors = response.data.errors,
                                        importedGroups = undefined !== response.data.importedGroups ? response.data.importedGroups : 0,
                                        importedProducts = undefined !== response.data.importedProducts ? response.data.importedProducts : 0;

                                    // Output errors and notices.
                                    if (undefined !== notices || undefined !== errors) {
                                        wpwc.add_terminal_logs(notices, errors);
                                    }

                                    // Output imported nomenclature info.
                                    $('#wpwcIikoNomenclatureImportedGroups').text(importedGroups);
                                    $('#wpwcIikoNomenclatureImportedProducts').text(importedProducts);

                                    $('#wpwcIikoNomenclatureImportedWrap').removeClass('hidden');
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Save groups for auto import to WooCommerce.
                    case 'save_iiko_groups':

                        if (false === wpwc.is_empty(chosen_groups, wc_iikocloud.chose_groups, false)) return;

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__save_groups_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                chosenGroups: wpwc.selected_as_JSON($('#wpwcIikoGroups option:selected')),
                                menuId: menu_id,
                                priceCategoryId: price_category_id,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.result) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {
                                        wpwc.add_terminal_record(response.data.result, 'notice');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop(isGetIikoNomenclatureDisable);
                            }
                        });

                        break;

                    // Get iiko cities.
                    case 'get_iiko_cities':

                        if (false === wpwc.is_empty(organizationId, wc_iikocloud.chose_organization, false)) return;

                        cities.empty();
                        cities_wrap.addClass('hidden');

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__get_cities_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                organizationId: organizationId,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined === response.data.cities) {
                                        if (undefined !== response.data.notices || undefined !== response.data.errors) {
                                            wpwc.add_terminal_logs(response.data.notices, response.data.errors);
                                        }

                                    } else {

                                        cities.empty();

                                        $.each(response.data.cities, function (index, value) {

                                            cities.append($('<option></option>')
                                                .attr('value', index)
                                                .text(value)
                                            );

                                            wpwc.add_terminal_record(value + ' - ' + index, 'data');
                                        });

                                        wpwc.add_terminal_record('', 'data', wc_iikocloud.cities_title);

                                        wpwc.auto_adjust_select_size('wpwcIikoCities');

                                        cities_wrap.removeClass('hidden');
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop();
                            }
                        });

                        break;

                    // Get iiko streets.
                    case 'get_iiko_streets':

                        if (false === wpwc.is_empty(organizationId, wc_iikocloud.chose_organization, false)) return;
                        if (false === wpwc.is_empty(city_ids, wc_iikocloud.chose_cities, false)) return;

                        $.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'wc_iikocloud__get_streets_ajax',
                                wc_iikocloud_nonce: wpwc.WCIikoNonce,
                                organizationId: organizationId,
                                cityIds: city_ids,
                            },

                            success: function (response) {

                                if (response === '0') {
                                    wpwc.add_terminal_record('AJAX error: 0.', 'error');

                                } else {

                                    if (undefined !== response.streets) {

                                        // Output streets info to the terminal.
                                        $.each(response.streets, function (city_id, city_streets) {

                                            $.each(city_streets, function (street_id, street_name) {
                                                wpwc.add_terminal_record(street_name + ' - ' + street_id, 'data');
                                            });

                                            wpwc.add_terminal_record('', 'data', city_id);
                                        });
                                    }

                                    if (undefined !== response.notices || undefined !== response.errors) {
                                        wpwc.add_terminal_logs(response.notices, response.errors);
                                    }
                                }
                            },

                            error: function (xhr, textStatus, errorThrown) {
                                wpwc.add_terminal_record(errorThrown ? errorThrown : xhr.status, 'error');
                            },

                            complete: function () {
                                wpwc.request_stop();
                            }
                        });

                        break;
                }
            });
        }
    });

})(jQuery);
