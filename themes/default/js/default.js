
/**
 * Call a PHP function
 *
 * The function must be handled by the current controller (method name is "ajax" + functionName)
 *
 * Parameters are preferably passed through POST for two reasons:
 * - GET data maybe polluted for other reasons (sessions handling, ...) where POST are always under control
 * - GET data appear in URL therefore are limited in size and charset
 * @see http://www.cs.tut.fi/~jkorpela/forms/methods.html
 *
 * Note: only application parameters are sent through GET/POST, handling data such as function name sent though headers
 *
 * Caution: prior to PHP 5 the parameters fed to the PHP function are given IN ORDER, NOT BY NAME
 *
 * @param functionName string, the name of the function to call (ie. method "ajax"+functionName of the controller)
 * @param params array, data to be serialized and sent via POST
 * @param extParams array (optional), data to be serialized and sent via GET
 *
 * TODO: possibility of calling a method from another controller
 * TODO: handle errors gracefully
 *
 */
function PHPDS_remoteCall(functionName, params, extParams) {
    var url = document.URL;
    if (extParams) {
        url = URI(url).addQuery(extParams).href();
    }
    return $.when($.ajax({
        url:url,
        dataType:'json',
        data:params,
        type:'POST',
        headers:{'X-Requested-Type':'json', 'X-Remote-Call':functionName},
        beforeSend:function (xhr) {
            xhr.setRequestHeader('X-Requested-Type', 'json');
            xhr.setRequestHeader('X-Remote-Call', functionName);
        }
    })).done(function (data_received, status, deferred) {
            if (deferred.status !== 200) {
                /*deferred.reject();
                 alert('Error ' + deferred.status);*/
            }
        }).fail(function (deferred, status) {
            if (deferred.status !== 200) {
                //deferred.reject();
                alert('Error! ' + deferred.statusText);
            }
        }
    );
}

/**
 * Apply default formatting to the objects inside the given root element (root element is optional, defaults to BODY)
 * @param root DOM object to assign.
 */
function PHPDS_documentReady (root) {
    if (!root) root = $(document);
    root.ajaxError(function(e, jqXHR, settings, exception) {
        var url = $(location).attr('href');
        if(jqXHR.status == 401) {
            location.href = url;
            throw new Error('Unauthorized');
        }
        if(jqXHR.status == 403) {
            location.href = url;
            throw new Error('Login Required');
        }
        if(jqXHR.status == 404) {
            location.href = url;
            throw new Error('Page not found');
        }
        if(jqXHR.status == 418) {
            location.href = url;
            throw new Error('Spam detected');
        }
    });
    root.ajaxComplete(function(event, XMLHttpRequest, ajaxOptions) {
        ajaxMessage(XMLHttpRequest);
        ajaxInputError(XMLHttpRequest);
    });
    root.ready(function() {
        $.pronto();
        $(window)
            .on("pronto.render", initPage)
            .on("pronto.load", destroyPage);
        initPage();
    });
}

function destroyPage() {
    // unbind events and remove plugins
}

function initPage() {
    // bind events and initialize plugins
}

(function ($) {
    $.fn.serializeObject = function(extendArray)
    {
        var o = {};
        if (extendArray !== undefined) {
            var name = $(extendArray).attr('name');
            o[name] = $(extendArray).val();
        }
        var a = this.serializeArray();
        $.each(a, function() {
            if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });
        return o;
    };
})(jQuery);

(function ($) {
    $.fn.confirmDeleteClick = function () {
        var bg = this;
        bg.on('click', ".confirm-delete-click", function () {
            var first = this;
            $(first).removeClass("confirm-delete-click btn-warning").addClass("pass-delete-click btn-danger via-ajax");
            return false;
        });
        bg.on('click', ".pass-delete-click", function () {
            var item = this;
            if ($(item).hasClass('disabled')) return false;
            $(item).addClass("disabled");
        });
    }
})(jQuery);

function ajaxInputError (request) {
    var json = request.getResponseHeader('ajaxInputErrorMessage');
    if (json) {
        var mobj = jQuery.parseJSON(json);
        $.each(mobj, function() {
            var field = this.field;
            var label_tag = field + '_ajaxlabel';
            $('span.' + label_tag).remove();
            if (this.type) {
                if (this.type) {
                    var notify_type;
                    switch (this.type) {
                        case "error":
                            notify_type = 'error';
                            break;
                        default:
                            notify_type = 'error';
                    }
                    $('[name="' + field + '"]').addClass(notify_type);
                    if (this.message != '' && !$('.' + label_tag).hasClass(label_tag)) {
                        $('[for="' + field + '"]').append('<span class="'+ label_tag +' text-error">: ' + this.message + '</span>');
                    }
                }
            }
        });
    }
}

function ajaxMessage (request, delaytime, fadeout) {
    delaytime = typeof delaytime !== 'undefined' ? delaytime : 500;
    fadeout = typeof fadeout !== 'undefined' ? fadeout : 1000;
    var json = request.getResponseHeader('ajaxResponseMessage');
    if (json) {
        var mobj = jQuery.parseJSON(json);
        for (var i = 0; i < mobj.length; i++) {
            if (mobj[i].type) {
                var notify_type, id_tmp;
                var kill = true;
                switch (mobj[i].type) {
                    case "ok":
                        notify_type = 'alert-success';
                        break;

                    case "info":
                        notify_type = 'alert-info';
                        break;

                    case "warning":
                    case "error":
                        notify_type = 'alert-error';
                        break;

                    case "critical":
                        notify_type = 'alert-error';
                        kill = false;
                        break;

                    default:
                        notify_type = 'alert-notice';
                        kill = false;
                }
                var notifyjq = $('#notify');
                notifyjq.append('<div class="alert ' + notify_type + ' fade in"><button type="button" class="close" data-dismiss="alert">&times;</button>' + mobj[i].message + '</div>');
                if (kill) {
                    $('.' + notify_type, notifyjq).delay(delaytime).fadeOut(fadeout);
                }
            }
        }
    }
}

(function ($) {
    $.fn.getAjaxDeleteClick = function (size) {
        size = typeof size !== 'undefined' ? size : 15;
        return this.each(function () {
            var bg = $(this);
            bg.on('click', ".get-ajax-delete-click", function () {
                var first = this;
                $(first).removeClass("get-ajax-delete-click").addClass("pass-ajax-delete-click btn-danger").parents("tr").addClass("error");
                $("i", first).removeClass("icon-remove").addClass("icon-trash icon-white");
                return false;
            });
            bg.on('click', ".pass-ajax-delete-click", function () {
                var item = this;
                var url = $(item).attr('href');
                if ($(item).hasClass('disabled')) return false;
                $(item).addClass("disabled");
                $("i", item).removeClass("icon-trash").append(spinner(size));
                $.get(url, function (data, textStatus, request) {
                    if (data === 'true') {
                        $(item).parents("tr").fadeOut('slow');
                    }
                });
                return false;
            });
        });
    }
})(jQuery);

/**
 * Check multiple checkboxes at once.
 */
(function ($) {
    $.fn.checkAllCheckbox = function () {
        var checkall = this;
        return this.each(function () {
            checkall.click(function () {
                var checkedStatus = this.checked;
                checkall.parents("form").find(':checkbox').each(function() {
                    $(this).prop('checked', checkedStatus);
                });
            });
        });
    }
})(jQuery);

/**
 * Plugin to only allow buttons to be pressed when certain checkboxes are pressed.
 */
(function ($) {
    $.fn.enableButtonWhenChecked = function (buttonwrapper) {
        if( typeof(buttonwrapper) === "undefined" || buttonwrapper === null ) buttonwrapper = ".toggle-disabled-buttons";
        return this.each(function () {
            var checkboxes = $("input[type='checkbox']", this);
            var submitButt = $(buttonwrapper + " button[type='submit']");
            checkboxes.click(function() {
                submitButt.attr("disabled", !checkboxes.is(":checked"));
            });
        });
    }
})(jQuery);


(function ($) {
    $.fn.singleValidate = function () {
        return this.each(function () {
            var fields = $(this);
            var url = $(location).attr('href');
            var fieldname = fields.attr('name');
            var fieldvalue = fields.attr('value');
            var identifier = fieldname + '_watch';
            var tmp_tag = identifier + '_ajaxtag';
            var label_tag = fieldname + '_ajaxlabel';
            $('span.' + label_tag).remove();

            var fieldwatch = {};
            $(fields).typeWatch({
                callback: function(value) {
                    fieldwatch[identifier] = value;
                    $.post(url, fieldwatch, function (data, textStatus, request) {
                        $("i." + tmp_tag).remove();
                        fields.removeClass('error success');
                        var parent_form = fields.parents("form");
                        if (data == 'true' && fieldvalue != value) {
                            fields.addClass('error');
                            fields.after('<i class="' + tmp_tag + ' icon-remove pull-right"></i>');
                            $('button[type="submit"]', parent_form).addClass("disabled");
                            $('span.' + label_tag).remove();
                        } else {
                            fields.addClass('success');
                            fields.after('<i class="' + tmp_tag + ' icon-ok pull-right"></i>');
                            $('button[type="submit"]', parent_form).removeClass("disabled");
                            $('span.' + label_tag).remove();
                        }
                        $(fields).focus();
                    });
                },
                elsedo: function(value) {
                    $("i." + tmp_tag).remove();
                    $('span.' + label_tag).remove();
                    fields.removeClass('error success');
                }
            });
        });
    }
})(jQuery);


/**
 * https://github.com/javierjulio/textarea-auto-expand
 */
(function ($) {
    $.fn.textareaAutoExpand = function () {
        return this.each(function () {
            var textarea = $(this);
            var height = textarea.height();
            var diff = parseInt(textarea.css('borderBottomWidth')) + parseInt(textarea.css('borderTopWidth')) +
                parseInt(textarea.css('paddingBottom')) + parseInt(textarea.css('paddingTop'));
            var hasInitialValue = (this.value.replace(/\s/g, '').length > 0);

            if (textarea.css('box-sizing') === 'border-box' ||
                textarea.css('-moz-box-sizing') === 'border-box' ||
                textarea.css('-webkit-box-sizing') === 'border-box') {
                height = textarea.outerHeight();

                if (this.scrollHeight + diff == height) // special case for Firefox where scrollHeight isn't full height on border-box
                    diff = 0;
            } else {
                diff = 0;
            }

            if (hasInitialValue) {
                textarea.height(this.scrollHeight);
            }

            textarea.on('scroll input keyup', function (event) { // keyup isn't necessary but when deleting text IE needs it to reset height properly
                if (event.keyCode == 13 && !event.shiftKey) {
                    // just allow default behavior to enter new line
                    if (this.value.replace(/\s/g, '').length == 0) {
                        event.stopImmediatePropagation();
                        event.stopPropagation();
                    }
                }

                textarea.height(0);
                //textarea.height(Math.max(height - diff, this.scrollHeight - diff));
                textarea.height(this.scrollHeight - diff);
            });
        });
    }
})(jQuery);

/**
 * Does simple name filtering for search fields that does not need filtering from database.
 */
(function ($) {
    $.fn.searchFilter = function () {

        return this.each(function () {
            var filterelement = $(this);

            //filter results based on query
            function filter(selector, query) {
                query = $.trim(query); //trim white space
                query = query.replace(/ /gi, '|'); //add OR for regex query

                $(selector).each(function() {
                    ($(this).text().search(new RegExp(query, "i")) < 0) ? $(this).hide().removeClass('tr-visible') : $(this).show().addClass('tr-visible');
                });

                if (!$(".tr-visible")[0]) {
                    $("thead").hide();
                    $(".quickfilter-no-results").fadeIn("slow");
                } else {
                    $("thead").fadeIn("slow");
                    $(".quickfilter-no-results").fadeOut("slow");
                }
            }

            $('tbody tr').addClass('visible');

            $(filterelement).keyup(function(event) {
                //if esc is pressed or nothing is entered
                if (event.keyCode == 27 || $(this).val() == '') {
                    //if esc is pressed we want to clear the value of search box
                    $(this).val('');

                    //we want each row to be visible because if nothing
                    //is entered then all rows are matched.
                    $('tbody tr').removeClass('visible').show().addClass('visible');
                }
                //if there is text, lets filter
                else {
                    filter('tbody tr', $(this).val());
                }
            });
        });
    }
})(jQuery);

/*
 *	TypeWatch 2.1
 *
 *	Examples/Docs: github.com/dennyferra/TypeWatch
 *
 *  Copyright(c) 2013
 *	Denny Ferrassoli - dennyferra.com
 *   Charles Christolini
 *
 *  Dual licensed under the MIT and GPL licenses:
 *  http://www.opensource.org/licenses/mit-license.php
 *  http://www.gnu.org/licenses/gpl.html
 */
(function(jQuery) {
    jQuery.fn.typeWatch = function(o) {
        // The default input types that are supported
        var _supportedInputTypes =
            ['TEXT', 'TEXTAREA', 'PASSWORD', 'TEL', 'SEARCH', 'URL', 'EMAIL', 'DATETIME', 'DATE', 'MONTH', 'WEEK', 'TIME', 'DATETIME-LOCAL', 'NUMBER', 'RANGE'];

        // Options
        var options = jQuery.extend({
            wait: 500,
            callback: function() { },
            elsedo: function() { },
            highlight: false,
            captureLength: 3,
            inputTypes: _supportedInputTypes
        }, o);

        function checkElement(timer, override) {
            var value = jQuery(timer.el).val();
            // Fire if text >= options.captureLength AND text != saved text OR if override AND text >= options.captureLength
            if ((value.length >= options.captureLength && value.toUpperCase() != timer.text)
                || (override && value.length >= options.captureLength))
            {
                timer.text = value.toUpperCase();
                timer.cb.call(timer.el, value);
            } else {
                timer.text = value.toUpperCase();
                timer.ed.call(timer.el, value);
            }
        };

        function watchElement(elem) {
            var elementType = elem.type.toUpperCase();
            if (jQuery.inArray(elementType, options.inputTypes) >= 0) {

                // Allocate timer element
                var timer = {
                    timer: null,
                    text: jQuery(elem).val().toUpperCase(),
                    cb: options.callback,
                    ed: options.elsedo,
                    el: elem,
                    wait: options.wait
                };

                // Set focus action (highlight)
                if (options.highlight) {
                    jQuery(elem).select();
                }

                // Key watcher / clear and reset the timer
                var startWatch = function(evt) {
                    var timerWait = timer.wait;
                    var overrideBool = false;
                    var evtElementType = this.type.toUpperCase();

                    // If enter key is pressed and not a TEXTAREA and matched inputTypes
                    if (evt.keyCode == 13 && evtElementType != 'TEXTAREA' && jQuery.inArray(evtElementType, options.inputTypes) >= 0) {
                        timerWait = 1;
                        overrideBool = true;
                    }

                    var timerCallbackFx = function() {
                        checkElement(timer, overrideBool)
                    }

                    // Clear timer
                    clearTimeout(timer.timer);
                    timer.timer = setTimeout(timerCallbackFx, timerWait);
                };

                jQuery(elem).keydown(startWatch);
            }
        };

        // Watch Each Element
        return this.each(function() {
            watchElement(this);
        });
    };
})(jQuery);

/*
 * Pronto Plugin
 * @author Ben Plum
 * @modified Jason Schoeman - Modified for use in PHPDevShell.
 * @version 0.5.3
 *
 * Copyright © 2012 Ben Plum <mr@benplum.com>
 * Released under the MIT License <http://www.opensource.org/licenses/mit-license.php>
 */

if (jQuery) (function($) {

    var supported = window.history && window.history.pushState && window.history.replaceState;
    var $window = $(window);
    var currentURL = '';

    // Default Options
    var options = {
        container: "#bg",
        selector: "a.first-child, a.child, button.via-ajax, a.via-ajax"
    };

    // Public Methods
    var pub = {
        supported: function() {
            return supported;
        }
    };

    // Private Methods

    // Init
    function _init(opts) {
        $.extend(options, opts || {});
        options.$body = $("body");
        options.$container = $(options.container);

        // Check for push/pop support
        if (!supported) {
            return;
        }

        history.replaceState({
            url: window.location.href,
            data: {
                "title": $("head").find("title").text(),
                "content": $(options.container).html()
            }
        }, "state-"+window.location.href, window.location.href);

        currentURL = window.location.href;

        // Bind state events
        $window.on("popstate", _onPop);
        options.$body.on("click.pronto", options.selector, _click);
    }

    // Handle link clicks
    function _click(e) {
        var link = e.currentTarget;
        // Ignore everything but normal click
        if (  (e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)
            || (window.location.protocol !== link.protocol || window.location.host !== link.host)
            || (link.hash && link.href.replace(link.hash, '') === window.location.href.replace(location.hash, '') || link.href === window.location.href + '#')
            ) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        $("#bg").fadeTo('fast', 0.3);
        $("#ajax-loader-art").fadeIn('slow');
        _request(link.href);
    }

    // Request new url
    function _request(url) {
        $window.trigger("pronto.request");
        // Call new content
        $.ajax({
            url: url + ((url.indexOf("?") > -1) ? "&via-ajax=true" : "?via-ajax=true"),
            dataType: "json",
            success: function(response) {
                _render(url, response, true);
            },
            error: function(response) {
                window.location.href = url;
            },
            complete: function() {
                $("#ajax-loader-art").hide();
                $("#bg").fadeTo(0, 0.3).fadeTo('fast', 1);
            }
        });
    }

    // Handle back button
    function _onPop(e) {
        var data = e.originalEvent.state;

        // Check if data exists
        if (data !== null && (data.url !== currentURL)) {
            _render(data.url, data.data, false);
        }
    }

    // Render HTML
    function _render(url, response, doPush) {
        // Reset scrollbar
        $window.trigger("pronto.load").scrollTop(0);

        // Trigger analytics page view
        _gaCaptureView(url);

        // Update DOM
        document.title = response.title;
        options.$container.html(response.content);

        // Push new states to the stack
        if (doPush) {
            history.pushState({
                url: url,
                data: response
            }, "state-"+url, url);
        }

        currentURL = url;
        $window.trigger("pronto.render");
    }

    // Google Analytics support
    function _gaCaptureView(url) {
        if (typeof _gaq === "undefined") _gaq = [];
        _gaq.push(['_trackPageview'], url);
    }

    // Define Plugin
    $.pronto = function(method) {
        if (pub[method]) {
            return pub[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return _init.apply(this, arguments);
        }
        return this;
    };
})(jQuery);

function spinner (size) {
    size = typeof size !== 'undefined' ? size : 15;
    return '<img class="ajax-spinner-image" src="themes/default/images/loader.gif" width="' + size + '" height="' + size + '" />';
}

