// Namespace
PluginManager = {};

// Change a plugins styling by status
PluginManager.refreshPluginStatus = function (plugin, url) {
    var $plugin = $("#" + plugin);
    $.get(url, {"action": "refresh", "plugin": plugin}, function (data, textStatus, request) {
        $plugin.replaceWith(data);
    });
};

// Checks single plugins for updates and creates buttons with update status.
PluginManager.checkUpdates = function ($url, data) {
    var plugins = jQuery.parseJSON(data), i = 0, deferc = Object.keys(plugins).length;
    $.each(plugins, function (pkey, plugin_) {
        $.get($url, {"check": "update", "plugin": pkey, "version": plugin_.version},
            function (data, textStatus, request) {
                i++;
                if (data != 'false') {
                    var results = $.parseJSON(data);
                    // tag update
                    $(".labeling-row", "#" + pkey).append('<span class="label label-important">'
                        + results.label + '</span>');
                    $(".action-buttons div.hide", "#" + pkey).
                        append('<button data-plugin="' + pkey + '" class="plugin-get-upgrade btn btn-primary" ' +
                            'title="' + results.label + '"><i class="icon-gift icon-white"></i></button>');
                    $(".plugin-information-row div.plugin-name", "#" + pkey)
                        .removeClass('text-success').addClass("text-error");
                }
                if (i == deferc) {
                    var updatesavail = $(".plugin-get-upgrade").length;
                    if (updatesavail > 0) {
                        $.get($url, {"check": "msg_updatesavail"});
                        $("#upgrade-all-plugins").show("slow");
                    } else {
                        $.get($url, {"check": "msg_alluptodate"});
                    }
                }
            });
    });
};

// Update menus to current state.
PluginManager.refreshMenus = function () {
    var url = $("#menu-refresh").attr("href");
    $.get(url, {"via-ajax": "light+mods"}, function (data, textStatus, request) {
        var mainnav = $("#main-nav");
        mainnav.fadeOut('slow');
        mainnav.html(data).hide();
        mainnav.fadeIn('slow');
    });
};

// Count repository up at once.
PluginManager.countRepositoryOnce = function () {
    PluginManager.countRepository("#r-c-available", $(".hidden-button-bind").size());
    PluginManager.countRepository("#r-c-installed", $(".plugin-ready").size());
};

// Count single repository value up.
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

// Do some janitor tasks.
PluginManager.janitor = function () {
    PHPDS.root.ajaxStop(function () {
        PluginManager.countRepositoryOnce();
        PluginManager.refreshMenus();
    });
};

// Get plugin manager log.
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

// Install a plugin.
PluginManager.managePlugin = function ($url, plugin, actiontype) {
    // Phase 0: Does plugin need downloading?
    $.get($url, {"action": "prepare", "plugin": plugin, "actiontype": actiontype},
        function (data, textStatus, request) {
            if (data != 'false') {
                var phase1 = $.parseJSON(data);
                $("#progress-bar").text(phase1.message);
                // Phase 1 : Plugins needs downloading.
                if (phase1.status == 'download') {
                    $.get($url, {"action": phase1.status, "plugin": plugin, "actiontype": actiontype},
                        function (data, textStatus, request) {
                        if (data != 'false') {
                            // Phase 2 : Plugin needs extraction.
                            var phase2 = $.parseJSON(data);
                            $("#progress-bar").text(phase2.message);
                            if (phase2.status == 'extract') {
                                $.post($url, {"action": phase2.status,
                                        "plugin": plugin, "zip": phase2.zip, "actiontype": actiontype},
                                    function (data, textStatus, request) {
                                        if (data != 'false') {
                                            // Phase 3 : Installing.
                                            var phase3 = $.parseJSON(data);
                                            $("#progress-bar").text(phase3.message);
                                            if (phase3.status == 'install' ||
                                                phase3.status == 'reinstall' ||
                                                phase3.status == 'upgrade') {
                                                $.post($url, {"action": phase3.status, "plugin": plugin},
                                                    function (data, textStatus, request) {
                                                        PluginManager.pluginManagerLog(request);
                                                        PluginManager.refreshPluginStatus(plugin, $url);
                                                    });
                                            }
                                        } else {
                                            PluginManager.pluginManagerLog(request);
                                            PluginManager.refreshPluginStatus(plugin, $url);
                                        }
                                    });
                            }
                        }
                    });
                    // Phase 3 : Installing.
                } else if (phase1.status == 'install' || phase1.status == 'reinstall' || phase1.status == 'upgrade') {
                    $.post($url, {"action": phase1.status, "plugin": plugin}, function (data, textStatus, request) {
                        PluginManager.pluginManagerLog(request);
                        PluginManager.refreshPluginStatus(plugin, $url);
                    });
                }
            }
        });
};

$(document).ready(function () {

    var $root = $("#repository");
    var $url = $root.attr("data-url");

    PluginManager.countRepositoryOnce();

    $("#plugin-search").searchFilter("div#plugin-repo div.row");

    $root.on('mouseenter', ".hidden-button-bind", function (event) {
        $(".hide", this).show();
    });
    $root.on('mouseleave', ".hidden-button-bind", function (event) {
        $(".hide", this).hide();
    });

    // Repository update.
    $(".repo-refresh").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var url = $(this).attr("data-update-url");
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
                $("#plugin-tools").removeClass("open");
                PluginManager.countRepositoryOnce();
            }
        });
        return false;
    });

    // Plugin update check.
    $("#check-updates").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var url = $(this).attr("href");
        $.get(url, function (data, textStatus, request) {
            PluginManager.checkUpdates($url, data);
        });
        $("#plugin-tools").removeClass("open");
        return false;
    });

    // Menu refresh after fresh plugin install.
    $("#menu-refresh").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PluginManager.refreshMenus();
        $("#plugin-tools").removeClass("open");
        return false;
    });

    // Show latest log.
    $root.on('click', "#latest-log", function () {
        $("#plugin-latest-log").modal();
        return false;
    });

    // Plugin get info.
    $root.on('click', ".plugin-get-info", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin");
        $.get($url, {"info": "get", "plugin": plugin}, function (data, textStatus, request) {
            if (data != 'false') {
                $(data).modal();
            }
        });
        return false;
    });

    // Plugin delete warn.
    $root.on('click', ".plugin-get-delete", function () {
        $(this).removeClass("btn-warning").addClass("btn-danger confirm-delete-ready");
        return false;
    });

    // Delete a plugin.
    $root.on('click', ".confirm-delete-ready", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin");
        $.post($url, {"action": "delete", "plugin": plugin}, function (data, textStatus, request) {
            $(this).removeClass("confirm-delete-ready");
            PluginManager.refreshPluginStatus(plugin, $url);
            PluginManager.countRepositoryOnce();
        });
        return false;
    });

    // Plugin uninstall warn.
    $root.on('click', ".plugin-get-uninstall", function () {
        $(this).removeClass("btn-warning").addClass("btn-danger confirm-uninstall-ready");
        return false;
    });

    // Uninstall a plugin.
    $root.on('click', ".confirm-uninstall-ready", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin");
        $.post($url, {"action": "uninstall", "plugin": plugin}, function (data, textStatus, request) {
            $(this).removeClass("confirm-uninstall-ready");
            PluginManager.pluginManagerLog(request);
            PluginManager.refreshPluginStatus(plugin, $url);
            PluginManager.countRepositoryOnce();
            PluginManager.refreshMenus();
        });
        return false;
    });

    // Update a plugin.
    $root.on('click', ".plugin-get-upgrade", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin");
        PluginManager.managePlugin($url, plugin, 'upgrade');
        return false;
    });

    // Plugin download+install.
    $root.on('click', ".plugin-get-install", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin");

        // Get dependencies
        $.get($url, {"action": "dependencies", "plugin": plugin}, function (data, textStatus, request) {
            var depends = jQuery.parseJSON(data);
            jQuery.each(depends, function (i, plugin_) {
                PluginManager.managePlugin($url, plugin_, 'install');
            });
            PluginManager.countRepositoryOnce();
            PluginManager.refreshMenus();
        });
        return false;
    });

    // Plugin reinstall.
    $root.on('click', ".plugin-get-reinstall", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage();
        var plugin = $(this).data("plugin");
        PluginManager.managePlugin($url, plugin, 'reinstall');
        PluginManager.refreshMenus();
        return false;
    });
});

