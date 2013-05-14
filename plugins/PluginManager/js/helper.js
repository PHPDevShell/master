$(document).ready(function () {
    var $root = $("#repository");
    var $url  = $root.attr("data-url");

    countRepositoryOnce();

    $("#plugin-search").searchFilter("div#plugin-repo div.row");

    $root.on('mouseenter', ".hidden-button-bind", function (event) {
        $(".hide", this).show();
    });
    $root.on('mouseleave', ".hidden-button-bind", function (event) {
        $(".hide", this).hide();
    });

    // Repository update.
    $(".repo-refresh").on('click', function() {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage(100);
        var url = $(this).attr("data-update-url");
        $.get(url, function (data, textStatus, request) {
            if (data !== 'false') {
                var results  = $.parseJSON(data);
                var template = $("#new-plugin-template");
                $.each(results, function(i, item) {
                    $(".plugin-get-info", template).attr("data-plugin", i);
                    $(".plugin-get-install", template).attr("data-plugin", i);
                    $(".hidden-button-bind", template).attr("id", i);
                    $(".plugin-name", template).text(i);
                    $(".plugin-short-description", template).text(item.desc);
                    var newplugin = template.html();
                    $("#plugin-repo").prepend(newplugin);
                });
            }
            $("#plugin-tools").removeClass("open");
        });
        refreshMenus();

        return false;
    });

    // Menu refresh after fresh plugin install.
    $("#menu-refresh").on('click', function () {
        if (PHPDS.ajaxRequestBusy) return false;

        refreshMenus();

        return false;
    });

    // Show latest log.
    $root.on('click', "#latest-log", function() {
        $("#plugin-latest-log").modal();

        return false;
    });

    // Plugin get info.
    $root.on('click', ".plugin-get-info", function() {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage(100);
        var plugin        = $(this).data("plugin");
        $.get($url, {"info" : "get", "plugin" : plugin}, function (data, textStatus, request) {
            if (data !== false) {
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
        PHPDS.requestPage(100);
        var plugin = $(this).data("plugin");
        $.post($url, {"action": "delete", "plugin": plugin}, function (data, textStatus, request) {
            $(this).removeClass("confirm-delete-ready");
            refreshPluginStatus(plugin, $url);
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
        PHPDS.requestPage(100);
        var plugin = $(this).data("plugin");
        $.post($url, {"action": "uninstall", "plugin": plugin}, function (data, textStatus, request) {
            $(this).removeClass("confirm-uninstall-ready");
            pluginManagerLog(request);
            refreshPluginStatus(plugin, $url);
        });

        return false;
    });

    // Plugin download+install.
    $root.on('click', ".plugin-get-install", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage(100);
        var plugin = $(this).data("plugin");

        // Get dependencies
        $.get($url, {"action": "dependencies", "plugin": plugin}, function (data, textStatus, request) {
            var depends = jQuery.parseJSON(data);
            jQuery.each(depends, function (i, plugin_) {
                installPlugin($url, plugin_);
            });
        });

        return false;
    });

    // Plugin reinstall.
    $root.on('click', ".plugin-get-reinstall", function () {
        if (PHPDS.ajaxRequestBusy) return false;
        PHPDS.requestPage(100);
        var plugin = $(this).data("plugin");

        installPlugin($url, plugin);

        return false;
    });
});

// Change a plugins styling by status
function refreshPluginStatus(plugin, url)
{
    var $plugin = $("#" + plugin);
    $.get(url, {"action": "refresh", "plugin": plugin}, function (data, textStatus, request) {
        $plugin.replaceWith(data);
    });
    refreshMenus();
}

// Update menus to current state.
function refreshMenus()
{
    var url = $("#menu-refresh").attr("href");
    $.get(url, {"via-ajax": "light+mods"}, function (data, textStatus, request) {
        var mainnav = $("#main-nav");
        mainnav.fadeOut('slow');
        mainnav.html(data).hide();
        mainnav.fadeIn('slow');
        countRepositoryOnce();
    });
}

// Count repository up at once.
function countRepositoryOnce()
{
    countRepository("#r-c-available", $(".hidden-button-bind").size());
    countRepository("#r-c-installed", $(".plugin-ready").size());
}

// Count single repository value up.
function countRepository(id, countto)
{
    $({countNum: 0}, "#repository").animate({countNum: countto}, {
        duration: 1500,
        easing:'swing',
        step: function() {
            $(id).text(Math.floor(this.countNum));
        },
        complete: function() {}
    });
}

// Get plugin manager log.
function pluginManagerLog(request)
{
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
            if (latest_log.size()) {
                $("#latest-log").show('slow');
            }
        }
    }
    return true;
}

// Install a plugin.
function installPlugin($url, plugin)
{
    // Phase 0: Does plugin need downloading?
    $.get($url, {"action": "prepare", "plugin": plugin}, function (data, textStatus, request) {
        if (data != false) {
            var phase1 = $.parseJSON(data);
            $("#progress-bar").text(phase1.message);
            // Phase 1 : Plugins needs downloading.
            if (phase1.status == 'download') {
                $.get($url, {"action": phase1.status, "plugin": plugin}, function (data, textStatus, request) {
                    if (data != false) {
                        // Phase 2 : Plugin needs extraction.
                        var phase2 = $.parseJSON(data);
                        $("#progress-bar").text(phase2.message);
                        if (phase2.status == 'extract') {
                            $.post($url, {"action": phase2.status, "plugin": plugin, "zip": phase2.zip}, function (data, textStatus, request) {
                                if (data != false) {
                                    // Phase 3 : Installing.
                                    var phase3 = $.parseJSON(data);
                                    $("#progress-bar").text(phase3.message);
                                    if (phase3.status == 'install' || phase3.status == 'reinstall') {
                                        $.post($url, {"action": phase3.status, "plugin": plugin}, function (data, textStatus, request) {
                                            pluginManagerLog(request);
                                            refreshPluginStatus(plugin, $url);
                                        });
                                    }
                                } else {
                                    pluginManagerLog(request);
                                    refreshPluginStatus(plugin, $url);
                                }
                            });
                        }
                    }
                });
                // Phase 3 : Installing.
            } else if (phase1.status == 'install' || phase1.status == 'reinstall') {
                $.post($url, {"action": phase1.status, "plugin": plugin}, function (data, textStatus, request) {
                    pluginManagerLog(request);
                    refreshPluginStatus(plugin, $url);
                });
            }
        }
    });
}
