
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
    return jQuery.when(jQuery.ajax({
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
    if (!root) root = jQuery(document);
    root.ajaxError(function(e, jqXHR, settings, exception) {
        var url = jQuery(location).attr('href');
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
        jQuery.pronto();
        jQuery(window)
            .on("pronto.render", initPage)
            .on("pronto.request", requestPage)
            .on("pronto.load", destroyPage);
        initPage();
    });
}

function destroyPage() {
    jQuery("#ajax-loader-art").hide();
    jQuery("#bg").stop().fadeTo('slow', 1);
}

function initPage() {
    if (jQuery('[data-via-ajax="delete-row"]')) {
        jQuery("tbody").viaAjaxDeleteRow();
    }
    $('textarea[data-action="auto-expand"]').autoExpand();
    $(".select-all-checkboxes").checkAllCheckbox();
}

function requestPage() {
    jQuery("#bg").fadeTo('slow', 0.2);
    jQuery("#ajax-loader-art").fadeIn('slow');
}

(function (jQuery) {
    jQuery.fn.serializeObject = function(extendArray)
    {
        var o = {};
        if (extendArray !== undefined) {
            var name = jQuery(extendArray).attr('name');
            o[name] = jQuery(extendArray).val();
        }
        var a = this.serializeArray();
        jQuery.each(a, function() {
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

(function (jQuery) {
    jQuery.fn.confirmDeleteClick = function () {
        var bg = this;
        bg.on('click', ".confirm-delete-click", function () {
            var first = this;
            jQuery(first).removeClass("confirm-delete-click btn-warning").addClass("pass-delete-click btn-danger");
            return false;
        });
        bg.on('click', ".pass-delete-click", function () {
            var item = this;
            if (jQuery(item).hasClass('disabled')) return false;
            jQuery(item).addClass("disabled");
        });
    }
})(jQuery);

function ajaxInputError (request) {
    var json = request.getResponseHeader('ajaxInputErrorMessage');
    if (json) {
        var mobj = jQuery.parseJSON(json);
        jQuery.each(mobj, function() {
            var field = this.field;
            var label_tag = field + '_ajaxlabel';
            jQuery('span.' + label_tag).remove();
            if (this.type) {
                var notify_type;
                switch (this.type) {
                    case "error":
                        notify_type = 'error';
                        break;
                    default:
                        notify_type = 'error';
                }
                jQuery('[name="' + field + '"]').addClass(notify_type);
                if (this.message != '' && !jQuery('.' + label_tag).hasClass(label_tag)) {
                    jQuery('[for="' + field + '"]').append('<span class="'+ label_tag +' text-error">: ' + this.message + '</span>');
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
                var notifyjq = jQuery('#notify');
                notifyjq.append('<div class="alert ' + notify_type + ' fade in"><button type="button" class="close" data-dismiss="alert">&times;</button>' + mobj[i].message + '</div>');
                if (kill) {
                    $('.' + notify_type, notifyjq).delay(delaytime).fadeOut(fadeout);
                }
            }
        }
    }
}

(function (jQuery) {
    jQuery.fn.viaAjaxDeleteRow = function (size) {
        size = typeof size !== 'undefined' ? size : 15;
        return this.each(function () {
            var bg = jQuery(this);
            bg.on('click', '[data-via-ajax="delete-row"]', function () {
                var first = this;
                jQuery(first).addClass("btn-danger").attr("data-via-ajax", "delete-row-ready").parents("tr").addClass("error");
                jQuery("i", first).removeClass("icon-remove").addClass("icon-trash icon-white");
                return false;
            });
            bg.on('click', '[data-via-ajax="delete-row-ready"]', function () {
                var item = this;
                var url = jQuery(item).attr('href');
                if (jQuery(item).hasClass('disabled')) return false;
                jQuery(item).addClass("disabled");
                jQuery("i", item).removeClass("icon-trash").append(spinner(size));
                jQuery.get(url, function (data, textStatus, request) {
                    if (data === 'true') {
                        jQuery(item).parents("tr").fadeOut('slow');
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
(function (jQuery) {
    jQuery.fn.checkAllCheckbox = function () {
        var checkall = this;
        return this.each(function () {
            checkall.click(function () {
                var checkedStatus = this.checked;
                checkall.parents("form").find(':checkbox').each(function() {
                    jQuery(this).prop('checked', checkedStatus);
                });
            });
        });
    }
})(jQuery);

/**
 * Plugin to only allow buttons to be pressed when certain checkboxes are pressed.
 */
(function (jQuery) {
    jQuery.fn.enableButtonWhenChecked = function (buttonwrapper) {
        if( typeof(buttonwrapper) === "undefined" || buttonwrapper === null ) buttonwrapper = ".toggle-disabled-buttons";
        return this.each(function () {
            var checkboxes = jQuery("input[type='checkbox']", this);
            var submitButt = jQuery(buttonwrapper + " button[type='submit']");
            checkboxes.click(function() {
                submitButt.attr("disabled", !checkboxes.is(":checked"));
            });
        });
    }
})(jQuery);


(function (jQuery) {
    jQuery.fn.singleValidate = function () {
        return this.each(function () {
            var fields = jQuery(this);
            var url = jQuery(location).attr('href');
            var fieldname = fields.attr('name');
            var fieldvalue = fields.attr('value');
            var identifier = fieldname + '_watch';
            var tmp_tag = identifier + '_ajaxtag';
            var label_tag = fieldname + '_ajaxlabel';
            jQuery('span.' + label_tag).remove();

            var fieldwatch = {};
            jQuery(fields).typeWatch({
                callback: function(value) {
                    fieldwatch[identifier] = value;
                    jQuery.post(url, fieldwatch, function (data, textStatus, request) {
                        jQuery("i." + tmp_tag).remove();
                        fields.removeClass('error success');
                        var parent_form = fields.parents("form");
                        if (data == 'true' && fieldvalue != value) {
                            fields.addClass('error');
                            fields.after('<i class="' + tmp_tag + ' icon-remove pull-right"></i>');
                            jQuery('button[type="submit"]', parent_form).addClass("disabled");
                            jQuery('span.' + label_tag).remove();
                        } else {
                            fields.addClass('success');
                            fields.after('<i class="' + tmp_tag + ' icon-ok pull-right"></i>');
                            jQuery('button[type="submit"]', parent_form).removeClass("disabled");
                            jQuery('span.' + label_tag).remove();
                        }
                        jQuery(fields).focus();
                    });
                },
                elsedo: function(value) {
                    jQuery("i." + tmp_tag).remove();
                    jQuery('span.' + label_tag).remove();
                    fields.removeClass('error success');
                }
            });
        });
    }
})(jQuery);


/**
 * https://github.com/javierjulio/textarea-auto-expand
 */
(function (jQuery) {
    jQuery.fn.autoExpand = function () {
        return this.each(function () {
            var textarea = jQuery(this);
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
(function (jQuery) {
    jQuery.fn.searchFilter = function () {
        return this.each(function () {
            var filterelement = jQuery(this);

            //filter results based on query
            function filter(selector, query) {
                query = jQuery.trim(query); //trim white space
                query = query.replace(/ /gi, '|'); //add OR for regex query

                jQuery(selector).each(function() {
                    (jQuery(this).text().search(new RegExp(query, "i")) < 0) ? jQuery(this).hide().removeClass('tr-visible') : jQuery(this).show().addClass('tr-visible');
                });

                var thead = jQuery("thead");
                var no_results = jQuery(".quickfilter-no-results");

                if (!jQuery(".tr-visible")[0]) {
                    thead.hide();
                    no_results.fadeIn("slow");
                } else {
                    thead.fadeIn("slow");
                    no_results.fadeOut("slow");
                }
            }

            jQuery('tbody tr').addClass('visible');
            jQuery(filterelement).keyup(function(event) {
                //if esc is pressed or nothing is entered
                if (event.keyCode == 27 || jQuery(this).val() == '') {
                    //if esc is pressed we want to clear the value of search box
                    jQuery(this).val('');
                    //we want each row to be visible because if nothing
                    //is entered then all rows are matched.
                    jQuery('tbody tr').removeClass('visible').show().addClass('visible');
                }
                //if there is text, lets filter
                else {
                    filter('tbody tr', jQuery(this).val());
                }
            });
        });
    }
})(jQuery);

/**
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
        }

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
                    };

                    // Clear timer
                    clearTimeout(timer.timer);
                    timer.timer = setTimeout(timerCallbackFx, timerWait);
                };

                jQuery(elem).keydown(startWatch);
            }
        }

        // Watch Each Element
        return this.each(function() {
            watchElement(this);
        });
    };
})(jQuery);

/**
 * Pronto Plugin
 * @author Ben Plum
 * @modified Jason Schoeman - Modified for use in PHPDevShell.
 * @version 0.5.3
 *
 * Copyright © 2012 Ben Plum <mr@benplum.com>
 * Released under the MIT License <http://www.opensource.org/licenses/mit-license.php>
 */
(function(jQuery) {

    var supported = window.history && window.history.pushState && window.history.replaceState;
    var $window = jQuery(window);
    var currentURL = '';

    // Default Options
    var options = {
        container: "#bg",
        selector: '[data-via-ajax="page"]'
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
        jQuery.extend(options, opts || {});
        options.$body = jQuery("body");
        options.$container = jQuery(options.container);

        // Check for push/pop support
        if (!supported) {
            return;
        }

        history.replaceState({
            url: window.location.href,
            data: {
                "title": jQuery("head").find("title").text(),
                "content": jQuery(options.container).html()
            }
        }, "state-" + window.location.href, window.location.href);

        currentURL = window.location.href;

        // Bind state events
        $window.on("popstate", _onPop);
        options.$body.on("click.pronto", options.selector, _click);
    }

    // Handle link clicks
    function _click(e) {
        var link = e.currentTarget;
        // Ignore everything but normal click
        if ((e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)
            || (window.location.protocol !== link.protocol || window.location.host !== link.host)
            || (link.hash && link.href.replace(link.hash, '') === window.location.href.replace(location.hash, '') || link.href === window.location.href + '#')
            ) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        _request(link.href);
    }

    // Request new url
    function _request(url) {
        $window.trigger("pronto.request");
        // Call new content
        jQuery.ajax({
            url: url + ((url.indexOf("?") > -1) ? "&via-ajax=page" : "?via-ajax=page"),
            success: function(response) {
                _render(url, response, true);
            },
            error: function(response) {
                window.location.href = url;
            },
            complete: function(jqXHR) {
                var response_ = jqXHR.getResponseHeader("ajaxAboutNode");
                if (response_) {
                    var repj = jQuery.parseJSON(response_);
                    document.title = repj.title;
                    jQuery("#nav li").removeClass("active");
                    jQuery("#nav li#menu_" +  repj.node_id).addClass("active");
                    jQuery(".dropdown").removeClass("open");
                }
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
        options.$container.html(response);
        // Push new states to the stack
        if (doPush) {
            history.pushState({
                url: url,
                data: response
            }, "state-" + url, url);
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
    jQuery.pronto = function(method) {
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

