/**
 * Namespace
 * @type {{}}
 */
PluginManager = {};

/**
 * Do some final janitor tasks.
 */
PluginManager.janitor = function () {
    PHPDS.root.one('ajaxStop', function () {
        PluginManager.countRepositoryOnce();
        PluginManager.refreshMenus();
        var updatesavail = $(".plugin-get-upgrade").length;
        if (updatesavail > 0) {
            $("#upgrade-all-plugins, #count-updates").show("slow");
            PluginManager.countRepository("#r-c-updates", updatesavail + 0.5);
        } else {
            $("#upgrade-all-plugins, #count-updates").hide();
        }
        var dep_broken = $(".plugin-get-fix").length;
        if (dep_broken > 0) {
            $("#fix-all-plugins, #count-broken").show("slow");
            PluginManager.countRepository("#r-c-broken", dep_broken + 0.5);
        } else {
            $("#fix-all-plugins, #count-broken").hide();
        }
        $("#progress-bar-message").text('');
        PluginManager.progress_bar.css({'width': "100%"});
        PluginManager.progress_parts = 0;
        PluginManager.progress_part  = 0;
    });
    $("#plugin-tools").removeClass("open");
};

/**
 * Change a plugins styling by status
 *
 * @param plugin
 */
PluginManager.refreshPluginStatus = function (plugin) {
    var $plugin = $("#" + plugin);
    $.get(PluginManager.url, {"action": "refresh", "plugin": plugin}, function (data, textStatus, request) {
        $plugin.replaceWith(data);
    });
};

/**
 * Checks single plugins for updates and creates buttons with update status.
 *
 * @param url
 */
PluginManager.checkUpdates = function (url) {
    $.get(url, function (data, textStatus, request) {
        if (data != 'false') {
            var plugins = jQuery.parseJSON(data), count = 0, deferc = Object.keys(plugins).length;
            PluginManager.progressPercentage();
            PluginManager.progress_parts = Math.floor(100 / deferc);
            $.each(plugins, function (pkey, plugin_) {
                $.get(PluginManager.url, {"check": "update-process", "plugin": pkey, "version": plugin_.version},
                    function (data, textStatus, request) {
                        count++;
                        if (data != 'false') {
                            // tag update
                            var labeling = $(".labeling-row", "#" + pkey);
                            if (!$("span", labeling).hasClass("label-upgrade-plugin")) {
                                    labeling.append('<span class="label label-important label-upgrade-plugin">'
                                    + i18n_upgrade_text + '</span>');
                                $(".action-buttons div.hide", "#" + pkey).
                                    append('<button data-plugin-upgrade-available="' + pkey +
                                        '" class="plugin-get-upgrade btn btn-primary" ' +
                                        'title="' + i18n_upgrade_text + '">' +
                                        '<i class="icon-gift icon-white"></i></button>');
                                $(".plugin-information-row div.plugin-name", "#" + pkey)
                                    .removeClass('text-success').addClass("text-error");
                            }
                        }
                        if (count == deferc) {
                            var updatesavail = $(".plugin-get-upgrade").length;
                            if (updatesavail > 0) {
                                $.get(PluginManager.url, {"check": "msg-updatesavail"});
                            } else {
                                $.get(PluginManager.url, {"check": "msg-alluptodate"});
                            }
                        }
                        PluginManager.progressPercentage();
                    });
            });
        }
    });
};

/**
 * Checks dependencies for installed plugins.
 *
 * @param url
 */
PluginManager.checkDependencies = function (url) {
    $.get(url, function (data, textStatus, request) {
        if (data != 'false') {
            var plugins = jQuery.parseJSON(data), count = 0, deferc = Object.keys(plugins).length;
            $.each(plugins, function (pkey, classes) {
                count ++;
                var broken_  = false;
                var labeling = $(".labeling-row", "#" + pkey);
                $.each(classes, function (ckey, classes_) {
                    if (classes_.ready == false) {
                        if (!$("span", labeling).hasClass("label-dep-broken-plugin")) {
                            labeling.append('<span class="label label-important label-dep-broken-plugin">'
                                + i18n_fix_text + '</span>');
                            $(".label-success", labeling).hide();
                            $(".action-buttons div.hide", "#" + pkey).
                                append('<button data-plugin-fix-available="' + pkey +
                                    '" class="plugin-get-fix btn btn-danger" ' +
                                    'title="' + i18n_fix_text + '">' +
                                    '<i class="icon-heart icon-white"></i></button>');
                            $(".plugin-information-row div.plugin-name", "#" + pkey)
                                .removeClass('text-success').addClass("text-error");
                        }
                        broken_ = true;
                    }
                });
                if (count == deferc) {
                    var dep_broken = $(".plugin-get-fix").length;
                    if (dep_broken > 0) {
                        $.get(PluginManager.url, {"check": "msg-dep-broken"});
                    } else {
                        $.get(PluginManager.url, {"check": "msg-dep-ok"});
                    }
                }
            });
        }
    });
};

/**
 * Update menus to current state.
 * $get['update'] = menus
 */
PluginManager.refreshMenus = function () {
    var url = $("#menu-refresh").attr("href");
    $.get(url, {"via-ajax": "light+mods"}, function (data, textStatus, request) {
        var mainnav = $("#main-nav");
        mainnav.html(data);
    });
};

/**
 * Count repository up at once.
 */
PluginManager.countRepositoryOnce = function () {
    PluginManager.countRepository("#r-c-available", $(".hidden-button-bind").size());
    PluginManager.countRepository("#r-c-installed", $(".plugin-ready").size());
};

/**
 * Count single repository value up.
 *
 * @param id
 * @param countto
 */
PluginManager.countRepository = function (id, countto) {
    $({countNum: 0}, "#repository").animate({countNum: countto}, {
        duration: 1500,
        easing: 'swing',
        step: function () {
            $(id).text(Math.floor(this.countNum));
        },
        complete: function () {
        }
    });
};

PluginManager.repoRefresh = function(url) {
    $.get(url, function (data, textStatus, request) {
        if (data != 'false') {
            var results = $.parseJSON(data);
            var template = $("#new-plugin-template");
            $.each(results, function (i, item) {
                $(".plugin-get-info", template).attr("data-plugin", i);
                $(".plugin-get-install", template).attr("data-plugin", i);
                $(".hidden-button-bind", template).attr("id", i);
                $(".plugin-name", template).text(i);
                $(".plugin-short-description", template).text(item.desc);
                var newplugin = template.html();
                $("#plugin-repo").prepend(newplugin);
            });
            PluginManager.janitor();
        }
        $("#plugin-tools").removeClass("open");
    });
};

/**
 * Get plugin manager log.
 *
 * @param request
 * @returns {boolean}
 */
PluginManager.pluginManagerLog = function (request) {
    if (typeof request == 'undefined') return false;
    var json = request.getResponseHeader('ajaxPluginManagerLog');
    if (json) {
        var logs = jQuery.parseJSON(json);
        var logs_extracted = [];
        jQuery.each(logs, function (i, log) {
            logs_extracted[i] = "<pre>" + log + "</pre>";
        });
        if (logs_extracted) {
            var latest_log = $("#plugin-log-data");
            latest_log.append(logs_extracted);
            if (latest_log.length) {
                $("#latest-log").show('slow');
            }
        }
    }
    return true;
};

/**
 * Installs a specific plugin while including all dependencies.
 *
 * @param plugin
 */
PluginManager.installWithDependency = function (plugin) {
    $.get(PluginManager.url, {"action": "dependencies", "plugin": plugin}, function (data, textStatus, request) {
        var depends = jQuery.parseJSON(data);
        PluginManager.progressPercentage();
        PluginManager.progress_parts = Math.floor(100 / Object.keys(depends).length) / 2;
        $.each(depends, function (key, plugin_) {
            PluginManager.managePlugin(plugin_, 'install');
        });
    });
};

/**
 * Install/Re-install/Upgrade a plugin.
 *
 * @param plugin
 * @param actiontype
 */
PluginManager.managePlugin = function (plugin, actiontype) {
    // Phase 0: Does plugin need downloading?
    $.when($.get(PluginManager.url, {"action": "prepare", "plugin": plugin, "actiontype": actiontype})
    ).then(function(data, textStatus, request) {
        if (data != 'false') {
            var phase1 = $.parseJSON(data);
            $("#progress-bar-message").text(phase1.message);
            // Phase 1 : Plugins needs downloading.
            if (phase1.status == 'download') {
                $.when($.get(PluginManager.url, {"action": phase1.status, "plugin": plugin, "actiontype": actiontype})
                ).then(function (data, textStatus, request) {
                        if (data != 'false') {
                            PluginManager.progressPercentage();
                            // Phase 2 : Plugin needs extraction.
                            var phase2 = $.parseJSON(data);
                            $("#progress-bar-message").text(phase2.message);
                            if (phase2.status == 'extract') {
                                $.when($.post(PluginManager.url, {"action": phase2.status,
                                        "plugin": plugin, "zip": phase2.zip, "actiontype": actiontype})
                                ).then(function (data, textStatus, request) {
                                        if (data != 'false') {
                                            // Phase 3 : Installing.
                                            var phase3 = $.parseJSON(data);
                                            $("#progress-bar-message").text(phase3.message);
                                            if (phase3.status == 'install' ||
                                                phase3.status == 'reinstall' ||
                                                phase3.status == 'upgrade') {
                                                $.when($.post(PluginManager.url, {"action": phase3.status, "plugin": plugin})
                                                ).then(function (data, textStatus, request) {
                                                        PluginManager.pluginManagerLog(request);
                                                        PluginManager.refreshPluginStatus(plugin);
                                                        PluginManager.progressPercentage();
                                                    });
                                            }
                                        } else {
                                            PluginManager.pluginManagerLog(request);
                                            PluginManager.refreshPluginStatus(plugin);
                                        }
                                    });
                            }
                        }
                    });
                // Phase 3 : Installing.
            } else if (phase1.status == 'install' || phase1.status == 'reinstall' || phase1.status == 'upgrade') {
                $.post(PluginManager.url, {"action": phase1.status, "plugin": plugin}, function (data, textStatus, request) {
                    PluginManager.pluginManagerLog(request);
                    PluginManager.refreshPluginStatus(plugin);
                    PluginManager.progressPercentage();
                });
            }
        }
    });
};

/**
 * Simply handles basic progress bar for certain actions.
 */
PluginManager.progress_parts = 0;
PluginManager.progress_part  = 0;
PluginManager.progressPercentage = function () {
    PluginManager.progress_part ++;
    var percentage = PluginManager.progress_part * PluginManager.progress_parts;
    if (percentage > 100) percentage = 100;
    PluginManager.progress_bar.css({'width': percentage + "%"});
};

/**
 * Plugin manager interactive UX assets.
 */
$(document).ready(function () {
    var $root                   = $("#repository");
    PluginManager.url           = $root.attr("data-url");
    PluginManager.progress_bar  = $("#progress-bar");

    PluginManager.countRepositoryOnce();

    $("#plugin-search").searchFilter("div#plugin-repo div.row");

    $root.on('mouseenter', ".hidden-button-bind", function (event) {
        $(".hide", this).show();
    });
    $root.on('mouseleave', ".hidden-button-bind", function (event) {
        $(".hide", this).hide();
    });

    /**
     * Repository update.
     * @see $get['update'] = repo
     */
    $(".repo-refresh").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var url = $(this).attr("data-update-url");
        PluginManager.repoRefresh(url);
        return false;
    });

    /**
     * Plugin update check.
     * @see $get[check] = updates
     */
    $("#check-updates").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_seeking_text);
        var url = $(this).attr("href");
        PluginManager.checkUpdates(url);
        PluginManager.janitor();
        return false;
    });

    /**
     * Plugin dependency check and report broken dependencies.
     * @see $get[check] = dependencies
     */
    $("#check-dependencies").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var url = $(this).attr("href");
        PluginManager.checkDependencies(url);
        PluginManager.janitor();
        return false;
    });

    /**
     * Menu refresh after fresh plugin install.
     * @see $get['update'] = menus
     */
    $("#menu-refresh").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_busy_text);
        PluginManager.refreshMenus();
        $("#plugin-tools").removeClass("open");
        return false;
    });

    /**
     * Menu refresh after fresh plugin install.
     * @see $get['refresh'] = plugins
     */
    $("#refresh-plugins").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_busy_text);
        var url = $("#refresh-plugins").attr("href");
        $.get(url, {"via-ajax": "light+mods"}, function (data, textStatus, request) {
            var repo = $("#repository");
            repo.html(data);
        });
        $("#plugin-tools").removeClass("open");
        return false;
    });

    /**
     * Show latest log.
     */
    $root.on('click', "#latest-log", function () {
        $("#plugin-latest-log").modal();
        return false;
    });

    /**
     * Plugin get info.
     */
    $root.on('click', ".plugin-get-info", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_busy_text);
        var plugin = $(this).data("plugin");
        $.get(PluginManager.url, {"info": "get", "plugin": plugin}, function (data, textStatus, request) {
            if (data != 'false') {
                $(data).modal();
            }
        });
        return false;
    });

    /**
     * Plugin delete warn.
     */
    $root.on('click', ".plugin-get-delete", function () {
        $(this).removeClass("btn-warning").addClass("btn-danger confirm-delete-ready");
        return false;
    });

    /**
     * Delete a plugin.
     */
    $root.on('click', ".confirm-delete-ready", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_busy_text);
        var plugin = $(this).data("plugin");
        $.post(PluginManager.url, {"action": "delete", "plugin": plugin}, function (data, textStatus, request) {
            $(this).removeClass("confirm-delete-ready");
            PluginManager.refreshPluginStatus(plugin);
            PluginManager.countRepositoryOnce();
            PluginManager.janitor();
        });
        return false;
    });

    /**
     * Plugin uninstall warn.
     */
    $root.on('click', ".plugin-get-uninstall", function () {
        $(this).removeClass("btn-warning").addClass("btn-danger confirm-uninstall-ready");
        return false;
    });

    /**
     * Uninstall a plugin.
     */
    $root.on('click', ".confirm-uninstall-ready", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_busy_text);
        var plugin = $(this).data("plugin");
        $.post(PluginManager.url, {"action": "uninstall", "plugin": plugin}, function (data, textStatus, request) {
            $(this).removeClass("confirm-uninstall-ready");
            PluginManager.pluginManagerLog(request);
            PluginManager.refreshPluginStatus(plugin);
            PluginManager.janitor();
        });
        return false;
    });

    /**
     * Upgrade a plugin.
     */
    $root.on('click', ".plugin-get-upgrade", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_busy_text);
        var plugin = $(this).data("plugin-upgrade-available");
        PluginManager.managePlugin(plugin, 'upgrade');
        PluginManager.janitor();
        return false;
    });

    /**
     * Upgrade all plugins.
     */
    $root.on('click', "#upgrade-all-plugins", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var upgrades = $(".plugin-get-upgrade", $root);
        PluginManager.progressPercentage();
        PluginManager.progress_parts = Math.floor(100 / upgrades.length) / 2;
        $.each(upgrades, function (i, data) {
            var plugin = $(data).data("plugin-upgrade-available");
            PluginManager.managePlugin(plugin, 'upgrade');
        });
        PluginManager.janitor();
        return false;
    });

    /**
     * Plugin fix attempt by reinstalling and collecting dependencies.
     */
    $root.on('click', ".plugin-get-fix", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin-fix-available");
        // Get dependencies
        PluginManager.installWithDependency(plugin);
        PluginManager.janitor();
        return false;
    });

    /**
     * Fix all plugin dependencies.
     */
    $root.on('click', "#fix-all-plugins", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var fix = $(".label-dep-broken-plugin", $root);
        PluginManager.progressPercentage();
        PluginManager.progress_parts = Math.floor(100 / fix.length) / 2;
        $.each(fix, function (i, data) {
            var plugin = $(data).data("plugin-fix-available");
            PluginManager.managePlugin(plugin, 'reinstall');
        });
        PluginManager.janitor();
        return false;
    });

    /**
     * Plugin download+install.
     */
    $root.on('click', ".plugin-get-install", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin");
        // Get dependencies
        PluginManager.installWithDependency(plugin);
        PluginManager.janitor();
        return false;
    });

    /**
     * Plugin reinstall/fix.
     */
    $root.on('click', ".plugin-get-reinstall", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        $("#progress-bar-message").text(i18n_busy_text);
        var plugin = $(this).data("plugin");
        PluginManager.managePlugin(plugin, 'reinstall');
        PluginManager.janitor();
        return false;
    });
});

