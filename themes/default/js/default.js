/**
 * This serves a namespace for PHPDevShell javascript utilities
 */
PHPDS = {};
PHPDS.remoteCallURL = document.URL;

/**
 * An exception-like class to handle RemoteCall situations
 *
 * @param deferred
 * @param ajax
 * @constructor
 */
PHPDS.RemoteCallException = function (deferred, ajax) {
    this.deferred = deferred;
    this.ajax = ajax;
    this.handled = false;
};

/**
 * a few URL-related utilities.
 *
 * @type {{}}
 */
PHPDS.url = {};

/**
 * Parse an URL to fetch the "search" (GET) parameters
 *
 * @param url
 * @returns {{}}
 */
PHPDS.url.decodeParams = function (url) {
    var args_enc, el, i, nameval, ret;
    ret = {};
    // use the DOM to parse the URL via an 'a' element
    el = document.createElement("a");
    el.href = url;
    // strip off initial ? on search and split
    args_enc = el.search.substring(1).split('&');
    for (i = 0; i < args_enc.length; i++) {
        // convert + into space, split on =, and then decode
        args_enc[i].replace(/\+/g, ' ');
        nameval = args_enc[i].split('=', 2);
        if (nameval[0]) {
            ret[decodeURIComponent(nameval[0])] = decodeURIComponent(nameval[1]);
        }
    }
    return ret;
};

/**
 * Inject (ie. add or overrides) "search" (GET) parameters into a URL
 *
 * @param params object of parameters
 * @param url string (optional) if empty is current URL is used
 * @returns string
 */
PHPDS.url.encodeParams = function (params, url) {
    var args_enc, el, name;
    if (!url) {
        url = document.URL;
    }
    el = document.createElement("a");
    el.href = url;
    args_enc = PHPDS.url.decodeParams(url);
    for (name in params) {
        if (params.hasOwnProperty(name)) {
            name = encodeURIComponent(name);
            params[name] = encodeURIComponent(params[name]);
            args_enc[name] = params[name];
        }
    }
    el.search = '?' + $.param(args_enc);
    return el.href;
};


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
 * From version 2.0 (PHPDevShell 3.5), the failure callback of the deferred is passed a PHPDS.RemoteCallException object ;
 *      it can set this object's field to "true" to prevent the top-level exception handler to kick in
 *
 * The resolve callback of the deferred is passed:
 *      - the result *data* returned by the ajax call
 *      - the textual state
 *      - the ajax object
 *
 * Caution: prior to PHP 5 the parameters fed to the PHP function are given IN ORDER, NOT BY NAME
 *
 * @param functionName string, the name of the function to call (ie. method "ajax"+functionName of the controller)
 * @param params array, data to be serialized and sent via POST
 * @param extParams array (optional), data to be serialized and sent via GET
 *
 * @return deferred
 *
 * TODO: possibility of calling a method from another controller
 *
 */
PHPDS.remoteCall = function (functionName, params, extParams) {
    var url = PHPDS.remoteCallURL;
    if (extParams) {
        url = PHPDS.url.encodeParams(extParams, url);
    }
    return jQuery.Deferred(function () {
        var self_deferred = this;
        $.when(
            $.ajax({
                url: url,
                dataType: 'json',
                data: params,
                type: 'POST',
                headers: {'X-Requested-Type': 'json', 'X-Remote-Call': functionName},
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-Requested-Type', 'json');
                    xhr.setRequestHeader('X-Remote-Call', functionName);
                }
            }).done(function (result, state, self_ajax) {
                    self_deferred.resolve(result, state, self_ajax);
                }).fail(function (self_ajax) {
                    PHPDS.errorHandler(new PHPDS.RemoteCallException(self_deferred, self_ajax));
                })
        );
    });
};

/**
 * This is the top-level exception handler, it can be called in several ways
 *
 * First, with no parameter, it installs itself - you MUST do that
 * Second, with a single function, it install this function as an user exception handler
 * Third, with single custom exception (currently only RemoteCall), it deals with it
 *
 * Else it's the actual error handler, called when an error occurs
 *
 * @param message
 * @param url
 * @param line
 * @param object
 * @returns true
 */
PHPDS.errorHandler = function (message, url, line, object) {
    // first case, no parameter, initial setup
    if (!message) {
        window.onerror = function (message, url, line) {
            return PHPDS.errorHandler(message, url, line);
        };
        return true;
    }
    // second case, a user error handling function
    else if (typeof message == 'function') {
        this.userErrorHandler = message;
        return true;
    }
    // third case, a an exception from RemoteCall
    else if (message instanceof PHPDS.RemoteCallException) {
        /* @var PHPDS.RemoteCallException message */
        r = message.deferred.reject(message);
        if (message.handled) {
            return true;
        } else {
            object = message;
            message = 'Unhandled RemoteCall exception: ';
        }
    }

    if (this.userErrorHandler) {
        return this.userErrorHandler(message, url, line, object);
    } else {
        if (console && console.log) {
            console.log('PHPDS.errorHandler: ', message, ' | url: ', url, ' | line: ', line, ' | object: ', object);
        }
    }
    return true;
};

/**
 * Apply default formatting to the objects inside the given root element (root element is optional, defaults to BODY)
 * @param root DOM object to assign.
 */
PHPDS.documentReady = function (root) {
    if (!root) PHPDS.root = jQuery(document);
    PHPDS.ajaxErrorHandler();
    PHPDS.root.ajaxComplete(function (event, XMLHttpRequest, ajaxOptions) {
        PHPDS.ajaxMessage(XMLHttpRequest);
        PHPDS.ajaxInputError(XMLHttpRequest);
    });
    PHPDS.root.ready(function () {
        jQuery.pronto();
        jQuery(window)
            .on("pronto.render", PHPDS.initPage)
            .on("pronto.request", PHPDS.requestPage)
            .on("pronto.load", PHPDS.destroyPage);
        PHPDS.initPage();
    });
};

/**
 * Could be used in cases to do something as soon as page starts.
 */
PHPDS.initPage = function () {

};

/**
 * Loader when page is requested via Ajax.
 */
PHPDS.requestPage = function () {
    PHPDS.ajaxRequestBusy = true;
    PHPDS.queuedTimeout = setTimeout(function() {
        $('#ajax-loader-art').modal();
    }, 500);
};

/**
 * Destruct when page requested via Ajax ends.
 */
PHPDS.destroyPage = function () {
    clearTimeout(PHPDS.queuedTimeout);
    $("#progress-bar").text('');
    $('#ajax-loader-art').modal('hide');
    PHPDS.ajaxRequestBusy = false;
};

/**
 * How certain controller events are handled incase of Ajax request and page returns none code 200.
 */
PHPDS.ajaxErrorHandler = function () {
    PHPDS.root.ajaxError(function (e, jqXHR, settings, exception) {
        var url = jQuery(location).attr('href');
        if (jqXHR.status == 401) {
            location.href = url;
            throw new Error('Unauthorized');
        }
        if (jqXHR.status == 403) {
            location.href = url;
            throw new Error('Login Required');
        }
        if (jqXHR.status == 404) {
            //location.href = url;
        }
        if (jqXHR.status == 418) {
            location.href = url;
            throw new Error('Spam detected');
        }
    });
};

/**
 * Generic error handler for form fields view side, will check header for error messages and assignments and apply
 *
 * @param request
 * @returns {boolean}
 */
PHPDS.ajaxInputError = function (request) {
    var json = request.getResponseHeader('ajaxInputErrorMessage');
    if (json) {
        var mobj = jQuery.parseJSON(json);
        jQuery.each(mobj, function () {
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
                    jQuery('[for="' + field + '"]').append('<span class="' + label_tag + ' text-error"> &#10077;' + this.message + '&#10078;</span>');
                }
            }
        });
        return true;
    }
    return false;
};

/**
 * Handles most common messages send from the controller and reflects them on the view side.
 * Will also handle standard PHPDS template messages e.g template->ok();
 *
 * @param request
 * @param delaytime
 * @param fadeout
 */
PHPDS.ajaxMessage = function (request, delaytime, fadeout) {
    delaytime = typeof delaytime !== 'undefined' ? delaytime : 1600;
    fadeout = typeof fadeout !== 'undefined' ? fadeout : 1000;
    var json = request.getResponseHeader('ajaxResponseMessage');
    if (json) {
        var mobj = jQuery.parseJSON(json);
        var notifyjq = jQuery('#notify');
        for (var i = 0; i < mobj.length; i++) {
            if (mobj[i].type) {
                var notify_type;
                switch (mobj[i].type) {
                    case "ok":
                        notify_type = 'alert-success';
                        break;

                    case "info":
                        notify_type = 'alert-info';
                        break;

                    case "warning":
                        notify_type = 'alert-notice';
                        delaytime = 3800;
                        break;

                    case "error":
                        notify_type = 'alert-error';
                        break;

                    case "critical":
                        notify_type = 'alert-error';
                        break;

                    default:
                        notify_type = 'alert-notice';
                }
                notifyjq.append('<div class="alert ' + notify_type + ' fade in"><button type="button" class="close" data-dismiss="alert">&times;</button>' + mobj[i].message + '</div>');
                if (notify_type !== 'alert-error') {
                    $('.' + notify_type, notifyjq).delay(delaytime).fadeOut(fadeout);
                }
            }
        }
    }
};

/**
 * Simple function to add a generic micro spinner to elements where required.
 */
PHPDS.spinner = function (size, color) {
    size = typeof size !== 'undefined' ? size : 15;
    color = typeof color !== 'undefined' ? 'white-loader' : 'loader';
    return '<img class="ajax-spinner-image" src="themes/default/images/' + color + '.gif" width="' + size + '" height="' + size + '" />';
};

/**
 * Serialize an object.
 */
(function (jQuery) {
    jQuery.fn.serializeObject = function (extendArray) {
        var o = {};
        if (extendArray !== undefined) {
            var name = jQuery(extendArray).attr('name');
            o[name] = jQuery(extendArray).val();
        }
        var a = this.serializeArray();
        jQuery.each(a, function () {
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

/**
 * Handles generic form submissions while getting all fields values and serializing it.
 */
(function (jQuery) {
    jQuery.fn.viaAjaxSubmit = function (id) {
        var $this = this;
        var $url = $this.attr('action');
        var $id = jQuery(id);
        if (!$id.val()) {
            $id.parent().hide();
        }
        return this.each(function () {
            jQuery('button[type="submit"]', $this).on('click', function () {
                var item = this;
                if (jQuery(item).hasClass('disabled')) return false;
                jQuery(item).removeClass("btn-primary").addClass("btn-warning disabled");
                jQuery("i", item).removeClass("icon-ok").append(PHPDS.spinner());
                jQuery.post($url, $this.serializeObject(item), function (data, textStatus, request) {
                    jQuery(item).stop().queue(function (refresh) {
                        jQuery(this).removeClass("disabled btn-warning").addClass("btn-primary");
                        jQuery(".ajax-spinner-image").remove();
                        jQuery("i", this).addClass("icon-ok");
                        if (jQuery(item).attr("name") === 'copy') {
                            $id.val("").parent().fadeOut('slow');
                            data = 'false';
                        }
                        refresh();
                    });
                    if (data && data !== 'false') {
                        $id.val(data).parent().fadeIn('slow');
                    }
                });
                return false;
            });
        });
    }
})(jQuery);

/**
 * Generic plugin to execute delete clicks.
 */
(function (jQuery) {
    jQuery.fn.confirmDeleteURL = function () {
        var $this = this;
        var $parent = $this.parent();
        var $selector = $this.selector;
        return this.each(function () {
            $parent.one('click', $selector, function () {
                var first = this;
                jQuery(first).removeClass("btn-warning").addClass("btn-danger confirm-delete-ready");
                return false;
            });
            $parent.on('click', ".confirm-delete-ready", function () {
                var item = this;
                if (jQuery(item).hasClass('disabled')) return false;
                jQuery(item).addClass("disabled");
            });
        });
    }
})(jQuery);

/**
 * Handles the generic pagination search of PHPDS via ajax.
 */
(function (jQuery) {
    jQuery.fn.viaAjaxSearch = function (searchfield, searchbutton) {

        searchfield = typeof searchfield !== 'undefined' ? searchfield : '#search_field';
        searchbutton = typeof searchbutton !== 'undefined' ? searchbutton : '#search_button';

        var $this = this;
        var $selector = $this.selector;
        var $url = jQuery(this).parents("form").attr("action");
        $this.trigger('rowsUpdated');

        return this.each(function () {
            jQuery(searchfield).click(function () {
                jQuery(this).removeClass('active');
            });
            jQuery(searchbutton).on('click', function (event) {
                var value_ = jQuery(searchfield).val();
                sendForm($url, value_);
                event.stopImmediatePropagation();
                return false;
            });
            jQuery(searchfield).typeWatch({
                captureLength: 0,
                callback: function (value) {
                    sendForm($url, value);
                },
                elsedo: function (value) {

                }
            });
        });

        function sendForm(url, value) {
            requestPage();
            jQuery.post(url, {"search_field": value, "search": "Filter", "via-ajax": "page"}, function (data, textStatus, request) {
                var root = jQuery(data);
                var parent = jQuery($selector, root);
                var tbody = parent.find("tbody");
                var pagination = parent.find("#pagination-links");
                var noresults = parent.find("#no-results");
                if (noresults.find("div").length) {
                    jQuery("thead", $this).hide();
                } else {
                    jQuery("thead", $this).fadeIn('slow');
                }
                jQuery("tbody", $this).replaceWith(tbody);
                jQuery("#pagination-links", $this).replaceWith(pagination);
                jQuery("#no-results", $this).replaceWith(noresults);
                $this.trigger('rowsUpdated');
                destroyPage();
            });
        }
    }
})(jQuery);

/**
 * Generic ajax deletion of a row.
 */
(function (jQuery) {
    jQuery.fn.viaAjaxDeleteRow = function (size) {
        size = typeof size !== 'undefined' ? size : 15;
        var $this = this;
        return this.each(function () {
            var $parent = $this.parent();
            var $selector = $this.selector;
            $parent.one('click', $selector, function () {
                var first = this;
                jQuery(first).addClass("btn-danger delete-row-ready").parents("tr").addClass("error");
                jQuery("i", first).removeClass("icon-remove").addClass("icon-trash icon-white");
                return false;
            });
            $parent.on('click', ".delete-row-ready", function () {
                var item = this;
                var url = jQuery(item).attr('href');
                if (jQuery(item).hasClass('disabled')) return false;
                jQuery(item).addClass("disabled");
                jQuery("i", item).removeClass("icon-trash").append(PHPDS.spinner(size));
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
                checkall.parents("form").find(':checkbox').each(function () {
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
        if (typeof(buttonwrapper) === "undefined" || buttonwrapper === null) buttonwrapper = ".toggle-disabled-buttons";
        return this.each(function () {
            var checkboxes = jQuery("input[type='checkbox']", this);
            var submitButt = jQuery(buttonwrapper + " button[type='submit']");
            checkboxes.click(function () {
                submitButt.attr("disabled", !checkboxes.is(":checked"));
            });
        });
    }
})(jQuery);

/**
 * Validates a single form field via ajax via live typewatch.
 */
(function (jQuery) {
    jQuery.fn.singleValidate = function (activeid) {
        var root = this;
        if (typeof(activeid) === "undefined" || activeid === null) activeid = null;
        return this.each(function () {
            var $this = jQuery(this);
            var url = root.parents("form").attr('action');
            var fieldname = $this.attr('name');
            var fieldvalue = $this.attr('value');
            var identifier = fieldname + '_watch';
            var againstid = fieldname + '_id';
            var tmp_tag = identifier + '_ajaxtag';
            var label_tag = fieldname + '_ajaxlabel';
            jQuery('span.' + label_tag).remove();
            var fieldwatch = {};
            $this.typeWatch({
                callback: function (value) {
                    fieldwatch[identifier] = value;
                    if (activeid) {
                        fieldwatch[againstid] = jQuery(activeid).val();
                    }
                    jQuery.post(null, fieldwatch, function (data, textStatus, request) {
                        jQuery("i." + tmp_tag).remove();
                        $this.removeClass('error success');
                        var parent_form = $this.parents("form");
                        if (data == 'true' && fieldvalue != value) {
                            $this.addClass('error');
                            $this.after('<i class="' + tmp_tag + ' icon-remove pull-right"></i>');
                            //jQuery('button[type="submit"]', parent_form).addClass("disabled");
                            jQuery('span.' + label_tag).remove();
                        } else {
                            $this.addClass('success');
                            $this.after('<i class="' + tmp_tag + ' icon-ok pull-right"></i>');
                            jQuery('button[type="submit"]', parent_form).removeClass("disabled");
                            jQuery('span.' + label_tag).remove();
                        }
                        $this.focus();
                    });
                },
                elsedo: function (value) {
                    jQuery("i." + tmp_tag).remove();
                    jQuery('span.' + label_tag).remove();
                    $this.removeClass('error success');
                }
            });
        });
    }
})(jQuery);

/**
 * Does simple name filtering for search fields that does not need filtering from database.
 */
(function (jQuery) {
    jQuery.fn.searchFilter = function (typeofselector) {

        typeofselector = typeof typeofselector !== 'undefined' ? typeofselector : 'tbody tr';

        return this.each(function () {

            var filterelement = jQuery(this);

            //filter results based on query
            function filter(selector, query) {
                query = jQuery.trim(query); //trim white space
                query = query.replace(/ /gi, '|'); //add OR for regex query

                jQuery(selector).each(function () {
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

            jQuery(typeofselector).addClass('visible');
            jQuery(filterelement).keyup(function (event) {
                //if esc is pressed or nothing is entered
                if (event.keyCode == 27 || jQuery(this).val() == '') {
                    //if esc is pressed we want to clear the value of search box
                    jQuery(this).val('');
                    //we want each row to be visible because if nothing
                    //is entered then all rows are matched.
                    jQuery(typeofselector).removeClass('visible').show().addClass('visible');
                }
                //if there is text, lets filter
                else {
                    filter(typeofselector, jQuery(this).val());
                }
            });
        });
    }
})(jQuery);

/**
 *    TypeWatch 2.1
 *
 *    Examples/Docs: github.com/dennyferra/TypeWatch
 *
 *  Copyright(c) 2013
 *    Denny Ferrassoli - dennyferra.com
 *   Charles Christolini
 *
 *  Dual licensed under the MIT and GPL licenses:
 *  http://www.opensource.org/licenses/mit-license.php
 *  http://www.gnu.org/licenses/gpl.html
 */
(function (jQuery) {
    jQuery.fn.typeWatch = function (o) {
        // The default input types that are supported
        var _supportedInputTypes =
            ['TEXT', 'TEXTAREA', 'PASSWORD', 'TEL', 'SEARCH', 'URL', 'EMAIL', 'DATETIME', 'DATE', 'MONTH', 'WEEK', 'TIME', 'DATETIME-LOCAL', 'NUMBER', 'RANGE'];

        // Options
        var options = jQuery.extend({
            wait: 500,
            callback: function () {
            },
            elsedo: function () {
            },
            highlight: false,
            captureLength: 3,
            inputTypes: _supportedInputTypes
        }, o);

        function checkElement(timer, override) {
            var value = jQuery(timer.el).val();
            // Fire if text >= options.captureLength AND text != saved text OR if override AND text >= options.captureLength
            if ((value.length >= options.captureLength && value.toUpperCase() != timer.text) || (!override && value.length >= options.captureLength)) {
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
                var startWatch = function (evt) {
                    var timerWait = timer.wait;
                    var overrideBool = false;
                    var evtElementType = this.type.toUpperCase();

                    // If enter key is pressed and not a TEXTAREA and matched inputTypes
                    if (evt.keyCode == 13 && evtElementType != 'TEXTAREA' && jQuery.inArray(evtElementType, options.inputTypes) >= 0) {
                        timerWait = 1;
                        overrideBool = true;
                    }

                    var timerCallbackFx = function () {
                        checkElement(timer, overrideBool)
                    };

                    // Clear timer
                    clearTimeout(timer.timer);
                    timer.timer = setTimeout(timerCallbackFx, timerWait);
                };

                jQuery(elem).on('keydown input', startWatch);
            }
        }

        // Watch Each Element
        return this.each(function () {
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
(function (jQuery) {

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
        supported: function () {
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
            || (link.hash && link.href.replace(link.hash, '') === window.location.href.replace(location.hash, '')
            || link.href === window.location.href + '#')
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
            success: function (response) {
                _render(url, response, true);
            },
            error: function (response) {
                window.location.href = url;
            },
            complete: function (jqXHR) {
                var response_ = jqXHR.getResponseHeader("ajaxAboutNode");
                if (response_) {
                    var repj = jQuery.parseJSON(response_);
                    document.title = repj.title;
                    jQuery("#nav li").removeClass("active");
                    jQuery("#nav li#menu_" + repj.node_id).addClass("active");
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
    jQuery.pronto = function (method) {
        if (pub[method]) {
            return pub[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return _init.apply(this, arguments);
        }
        return this;
    };
})(jQuery);

/**
 * jQuery Autosize v1.16.7
 * (c) 2013 Jack Moore - jacklmoore.com
 * updated: 2013-02-11
 * license: http://www.opensource.org/licenses/mit-license.php
 */
(function ($) {
    var
        defaults = {
            className: 'autosizejs',
            append: '',
            callback: false
        },
        hidden = 'hidden',
        borderBox = 'border-box',
        lineHeight = 'lineHeight',
        supportsScrollHeight,

        // border:0 is unnecessary, but avoids a bug in FireFox on OSX (http://www.jacklmoore.com/autosize#comment-851)
        copy = '<textarea tabindex="-1" style="position:absolute; top:-999px; left:0; right:auto; bottom:auto; border:0; -moz-box-sizing:content-box; -webkit-box-sizing:content-box; box-sizing:content-box; word-wrap:break-word; height:0 !important; min-height:0 !important; width: 0; overflow:hidden;"/>',

        // line-height is conditionally included because IE7/IE8/old Opera do not return the correct value.
        copyStyle = [
            'fontFamily',
            'fontSize',
            'fontWeight',
            'fontStyle',
            'letterSpacing',
            'textTransform',
            'wordSpacing',
            'textIndent'
        ],
        oninput = 'oninput',
        onpropertychange = 'onpropertychange',

        // to keep track which textarea is being mirrored when adjust() is called.
        mirrored,

        // the mirror element, which is used to calculate what size the mirrored element should be.
        mirror = $(copy).data('autosize', true)[0];

    // test that line-height can be accurately copied.
    mirror.style.lineHeight = '99px';
    if ($(mirror).css(lineHeight) === '99px') {
        copyStyle.push(lineHeight);
    }
    mirror.style.lineHeight = '';

    $.fn.autosize = function (options) {
        options = $.extend({}, defaults, options || {});

        if (mirror.parentNode !== document.body) {
            $(document.body).append(mirror);

            mirror.value = "\n\n\n";
            mirror.scrollTop = 9e4;
            supportsScrollHeight = mirror.scrollHeight === mirror.scrollTop + mirror.clientHeight;
        }

        return this.each(function () {
            var
                ta = this,
                $ta = $(ta),
                minHeight,
                active,
                resize,
                boxOffset = 0,
                callback = $.isFunction(options.callback);

            if ($ta.data('autosize')) {
                // exit if autosize has already been applied, or if the textarea is the mirror element.
                return;
            }

            if ($ta.css('box-sizing') === borderBox || $ta.css('-moz-box-sizing') === borderBox || $ta.css('-webkit-box-sizing') === borderBox){
                boxOffset = $ta.outerHeight() - $ta.height();
            }

            minHeight = Math.max(parseInt($ta.css('minHeight'), 10) - boxOffset, $ta.height());

            resize = ($ta.css('resize') === 'none' || $ta.css('resize') === 'vertical') ? 'none' : 'horizontal';

            $ta.css({
                overflow: hidden,
                overflowY: hidden,
                wordWrap: 'break-word',
                resize: resize
            }).data('autosize', true);

            function initMirror() {
                mirrored = ta;
                mirror.className = options.className;

                // mirror is a duplicate textarea located off-screen that
                // is automatically updated to contain the same text as the
                // original textarea.  mirror always has a height of 0.
                // This gives a cross-browser supported way getting the actual
                // height of the text, through the scrollTop property.
                $.each(copyStyle, function(i, val) {
                    mirror.style[val] = $ta.css(val);
                });
            }

            // Using mainly bare JS in this function because it is going
            // to fire very often while typing, and needs to very efficient.
            function adjust() {
                var height, overflow, original;

                if (mirrored !== ta) {
                    initMirror();
                }

                // the active flag keeps IE from tripping all over itself.  Otherwise
                // actions in the adjust function will cause IE to call adjust again.
                if (!active) {
                    active = true;
                    mirror.value = ta.value + options.append;
                    mirror.style.overflowY = ta.style.overflowY;
                    original = parseInt(ta.style.height,10);

                    // Update the width in case the original textarea width has changed
                    // A floor of 0 is needed because IE8 returns a negative value for hidden textareas, raising an error.
                    mirror.style.width = Math.max($ta.width(), 0) + 'px';

                    if (supportsScrollHeight) {
                        height = mirror.scrollHeight;
                    } else { // IE6 & IE7
                        mirror.scrollTop = 0;
                        mirror.scrollTop = 9e4;
                        height = mirror.scrollTop;
                    }

                    var maxHeight = parseInt($ta.css('maxHeight'), 10);
                    // Opera returns '-1px' when max-height is set to 'none'.
                    maxHeight = maxHeight && maxHeight > 0 ? maxHeight : 9e4;
                    if (height > maxHeight) {
                        height = maxHeight;
                        overflow = 'scroll';
                    } else if (height < minHeight) {
                        height = minHeight;
                    }
                    height += boxOffset;
                    ta.style.overflowY = overflow || hidden;

                    if (original !== height) {
                        ta.style.height = height + 'px';
                        if (callback) {
                            options.callback.call(ta,ta);
                        }
                    }

                    // This small timeout gives IE a chance to draw it's scrollbar
                    // before adjust can be run again (prevents an infinite loop).
                    setTimeout(function () {
                        active = false;
                    }, 1);
                }
            }

            if (onpropertychange in ta) {
                if (oninput in ta) {
                    // Detects IE9.  IE9 does not fire onpropertychange or oninput for deletions,
                    // so binding to onkeyup to catch most of those occassions.  There is no way that I
                    // know of to detect something like 'cut' in IE9.
                    ta[oninput] = ta.onkeyup = adjust;
                } else {
                    // IE7 / IE8
                    ta[onpropertychange] = adjust;
                }
            } else {
                // Modern Browsers
                ta[oninput] = adjust;
            }

            $(window).on('resize', function(){
                active = false;
                adjust();
            });

            // Allow for manual triggering if needed.
            $ta.on('autosize', function() {
                active = false;
                adjust();
            });

            // Call adjust in case the textarea already contains text.
            adjust();
        });
    };
}(window.jQuery || window.Zepto));

