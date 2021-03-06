<?php

interface iPHPDS_activableGUI
{
    public function construct();
    public function activate();
}

/**
 * Class responsible to deal with the visual representation of a page.
 *
 * Interact with various other components such as views, themes, ...
 *
 */
class PHPDS_template extends PHPDS_dependant
{
    /**
     * Contains script HTML data.
     *
     * @var string
     */
    public $HTML = '';
    /**
     * Adds content to head of page.
     * @var string
     */
    public $modifyHead = '';
    /**
     * Modify Output Text Logo
     * @var mixed
     */
    public $modifyOutputTextLogo = false;
    /**
     * Modify Output Logo
     * @var mixed
     */
    public $modifyOutputLogo = false;
    /**
     * Modify Output Heading
     * @var mixed
     */
    public $modifyOutputHeading = false;
    /**
     * Modify Output Time
     * @var mixed
     */
    public $modifyOutputTime = false;
    /**
     * Modify Output Login Link.
     * @var mixed
     */
    public $modifyOutputLoginLink = false;
    /**
     * Modify Output User.
     * @var mixed
     */
    public $modifyOutputUser = false;
    /**
     * Modify Output Role.
     * @var mixed
     */
    public $modifyOutputRole = false;
    /**
     * Modify Output Group.
     * @var mixed
     */
    public $modifyOutputGroup = false;
    /**
     * Modify Output Title.
     * @var mixed
     */
    public $modifyOutputTitle = false;
    /**
     * Modify Output Node.
     * @var mixed
     */
    public $modifyOutputMenu = false;
    /**
     * Modify Output Subnav.
     * @var mixed
     */
    public $modifyOutputSubnav = false;
    /**
     * Modify Output Footer.
     * @var mixed
     */
    public $modifyOutputFooter = false;
    /**
     * Modify Output Controller.
     * @var mixed
     */
    public $modifyOutputController = false;
    /**
     * Sends a message to login form.
     * @var string
     */
    public $loginMessage;
    /**
     * Stores module methods.
     *
     * @var object
     */
    public $mod;
    /**
     * Content Distribution Network.
     * If you are running a very large site, you might want to consider running a dedicated light http server (httpdlight, nginx) that
     * only serves static content like images and static files, call it a CDN if you like.
     * By adding a host here 'http://192.34.22.33/project/cdn', all images etc, of PHPDevShell will be loaded from this address.
     * @var string
     */
    public $CDN;
    /**
     * Contains the best name of a controller being executed.
     * this->heading() will precede node name.
     *
     * @var string
     */
    public $heading;

    /**
     * Main template system constructor.
     */
    public function construct()
    {
        $configuration = $this->configuration;
        if (!empty($configuration['static_content_host'])) {
            $this->CDN = $configuration['static_content_host'];
        } else {
            $this->CDN = isset($configuration['absolute_url']) ? $configuration['absolute_url'] : '';
        }
    }

    /**
     * Will add any css path to the <head></head> tags of your document.
     *
     * @param string $cssRelativePath
     * @param string $media
     */
    public function addCssFileToHead($cssRelativePath = '', $media = '')
    {
        if (is_array($cssRelativePath)) {
            foreach ($cssRelativePath as $cssRelativePath_) {
                if (!empty($cssRelativePath_))
                    $this->modifyHead .= $this->mod->cssFileToHead($this->CDN . '/' . $cssRelativePath_, $media);
            }
        } else {
            if (!empty($cssRelativePath))
                $this->modifyHead .= $this->mod->cssFileToHead($this->CDN . '/' . $cssRelativePath, $media);
        }
    }

    /**
     * Will add any js path to the <head></head> tags of your document.
     *
     * @param string $jsRelativePath
     */
    public function addJsFileToHead($jsRelativePath = '')
    {
        if (is_array($jsRelativePath)) {
            foreach ($jsRelativePath as $jsRelativePath_) {
                if (!empty($jsRelativePath_))
                    $this->modifyHead .= $this->mod->jsFileToHead($this->CDN . '/' . $jsRelativePath_);
            }
        } else {
            if (!empty($jsRelativePath))
                $this->modifyHead .= $this->mod->jsFileToHead($this->CDN . '/' . $jsRelativePath);
        }
    }

    /**
     * Will add any content to the <head></head> tags of your document.
     *
     * @param string $giveHead
     */
    public function addToHead($giveHead = '')
    {
        $this->modifyHead .= $this->mod->addToHead($giveHead);
    }

    /**
     * Will add any js to the <head></head> tags of your document adding script tags.
     *
     * @param string $js
     */
    public function addJsToHead($js = '')
    {
        $this->modifyHead .= $this->mod->addJsToHead($js);
    }

    /**
     * Will add any css to the <head></head> tags of your document adding script tags.
     *
     * @param string $css
     */
    public function addCSSToHead($css = '')
    {
        $this->modifyHead .= $this->mod->addCssToHead($css);
    }

    /**
     * Will add any js path to where this tag is called from, best used in controller to add to view.
     *
     * @param bool   $return
     * @param string $jsRelativePath
     *
     * @return string|void
     */
    public function outputJsAsset($jsRelativePath = '', $return = false)
    {
        if ($return == false) {
            print $this->mod->jsAsset($this->CDN . '/' . $jsRelativePath);
        } else {
            return $this->mod->jsAsset($this->CDN . '/' . $jsRelativePath);
        }
        return null;
    }

    /**
     * Will add any css path to where this tag is called from, best used in controller to add to view.
     *
     * @param bool   $return
     * @param string $cssRelativePath
     * @param string $media
     *
     * @return string|void
     */
    public function outputCssAsset($cssRelativePath = '', $return = false, $media = '')
    {
        if ($return == false) {
            print $this->mod->cssAsset($this->CDN . '/' . $cssRelativePath, $media);
        } else {
            return $this->mod->cssAsset($this->CDN . '/' . $cssRelativePath, $media);
        }
        return null;
    }

    /**
     * Changes head output.
     *
     * @param boolean $return return or print out method
     * @return string|void
     */
    public function outputHead($return = false)
    {
        if (!empty($this->configuration['custom_css'])) {
            $this->addCssFileToHead($this->configuration['custom_css']);
        }

        // Check if we should return or print.
        if ($return == false) {
            // Simply output charset.
            print $this->modifyHead;
        } else {
            return $this->modifyHead;
        }
        return null;
    }

    /**
     * Prints current language identifier being used.
     *
     * @param boolean $return return or print out method
     * @return string|void
     */
    public function outputLanguage($return = false)
    {
        // Check if we should return or print.
        if ($return == false) {
            // Simply output charset.
            print $this->configuration['language'];
        } else {
            return $this->configuration['language'];
        }
        return null;
    }

    /**
     * Add elegant loading to controllers.
     *
     * @param boolean $return return or print out method
     * @return string|void
     */
    public function outputLoader($return = false)
    {
        // Check if we should return or print.
        if ($return == false) {
            // Simply output charset.
            print $this->mod->loader();
        } else {
            return $this->mod->loader();
        }
        return null;
    }

    /**
     * Prints charset.
     *
     * @param boolean $return return or print out method
     * @return string|void
     */
    public function outputCharset($return = false)
    {
        // Check if we should return or print.
        if ($return == false) {
            // Simply output charset.
            print $this->configuration['charset'];
        } else {
            return $this->configuration['charset'];
        }
        return null;
    }

    /**
     * Prints the active scripts title.
     */
    public function outputTitle()
    {
        // Check if output should be modified.
        if ($this->modifyOutputTitle == false) {
            $navigation = $this->navigation->navigation;
            if (isset($navigation[$this->configuration['m']]['node_name'])) {
                print $this->mod->title($navigation[$this->configuration['m']]['node_name'], $this->configuration['scripts_name_version']);
            } else {
                print $this->core->haltController['message'];
            }
        } else {
            print $this->modifyOutputTitle;
        }
    }

    /**
     * This returns/prints the skin for inside theme usage.
     *
     * @param boolean|string $return return or print out method
     * @return string|void
     */
    public function outputSkin($return = 'print')
    {
        // Create HTML.
        $html = $this->configuration['skin'];

        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            print $html;
        } else if ($return === 'return' || $return == true) {
            return $html;
        }
        return null;
    }

    /**
     * This returns/prints the absolute url for inside theme usage.
     *
     * @param boolean|string $return return or print out method
     * @return string|void
     */
    public function outputAbsoluteURL($return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            print $this->CDN;
        } else if ($return === 'return' || $return == true) {
            return $this->CDN;
        }
        return null;
    }

    /**
     * This returns/prints the home url for inside theme usage.
     *
     * @param boolean|string $return return or print out method
     * @return string|void
     */
    public function outputHomeURL($return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            print $this->configuration['absolute_url'];
        } else if ($return === 'return' || $return == true) {
            return $this->configuration['absolute_url'];
        }
        return null;
    }

    /**
     * This returns/prints the meta keywords for inside theme usage.
     *
     * @param boolean|string $return return or print out method
     * @return string|void
     */
    public function outputMetaKeywords($return = 'print')
    {
        // Create HTML.
        $html = $this->configuration['meta_keywords'];

        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            print $html;
        } else if ($return === 'return' || $return == true) {
            return $html;
        }
        return null;
    }

    /**
     * This returns/prints the meta description for inside theme usage.
     *
     * @param boolean|string $return return or print out method
     * @return string|void
     */
    public function outputMetaDescription($return = 'print')
    {
        // Create HTML.
        $html = $this->configuration['meta_description'];

        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            print $html;
        } else if ($return === 'return' || $return == true) {
            return $html;
        }
        return null;
    }

    /**
     * Sets template time.
     */
    public function outputTime()
    {
        // Check if output should be modified.
        if ($this->modifyOutputTime === false) {
            // Output active info.
            print $this->mod->formatTimeDate($this->configuration['time']);
        } else {
            print $this->modifyOutputTime;
        }
    }

    /**
     * Sets template login link.
     */
    public function outputLoginLink()
    {
        $navigation    = $this->navigation;
        $configuration = $this->configuration;

        // Check if output should be modified.
        if ($this->modifyOutputLoginLink == false) {
            if ($this->user->isLoggedIn()) {
                $login_information = $this->mod->logOutInfo($navigation->buildURL($configuration['loginandout'], 'logout=1'), $configuration['user_display_name']);
            } else {
                $inoutpage         = isset($navigation->navigation[$configuration['loginandout']]) ?
                    $navigation->navigation[$configuration['loginandout']]['node_name'] : ___('Login');
                $login_information = $this->mod->logInInfo($navigation->buildURL($configuration['loginandout']), $inoutpage);
            }
            // Output active info.
            print $login_information;
        } else {
            print $this->modifyOutputLoginLink;
        }
    }

    /**
     * Sets template role.
     */
    public function outputRole()
    {
        // Check if output should be modified.
        if ($this->modifyOutputRole == false) {
            // Set active role.
            $active_role = '';
            if ($this->user->isLoggedIn())
                $active_role = $this->mod->role(___('Role'), $this->configuration['user_role_name']);
            // Output active info.
            print $active_role;
        } else {
            print $this->modifyOutputRole;
        }
    }

    /**
     * Prints "subnav" to the template system. Intended to be used by the engine.
     */
    public function outputSubnav()
    {
        // Check if output should be modified.
        if ($this->modifyOutputSubnav == false) {
            print $this->navigation->createSubnav();
        } else {
            print $this->modifyOutputSubnav;
        }
    }

    /**
     * Returns "nodes" to the template system. Intended to be used by the engine.
     */
    public function outputMenu()
    {
        // Check if output should be modified.
        if ($this->modifyOutputMenu == false) {
            print $this->navigation->createMenuStructure();
        } else {
            print $this->modifyOutputMenu;
        }
    }

    /**
     * Prints "output script" to the template system. Intended to be used by the engine.
     */
    public function outputScript()
    {
        $this->outputController();
    }

    /**
     * Prints "output script" to the template system. Intended to be used by the engine.
     */
    public function outputController()
    {
        if ($this->modifyOutputController == false) {
            print $this->core->data;
        } else {
            print $this->modifyOutputController;
        }
    }

    /**
     * Prints the active scripts title/heading.
     */
    public function outputName()
    {
        // Check if output should be modified.
        if ($this->modifyOutputTitle == false) {
            $navigation = $this->navigation->navigation;
            if (!empty($this->heading)) {
                print $this->mod->activeName($this->heading);
            } else if (isset($navigation[$this->configuration['m']]['node_name'])) {
                print $this->mod->activeName($navigation[$this->configuration['m']]['node_name']);
            } else {
                print $this->mod->activeName($this->core->haltController['message']);
            }
        } else {
            print $this->modifyOutputTitle;
        }
    }

    /**
     * Allows to assign different names for the active controller.
     */
    public function heading($heading)
    {
        $this->heading = $heading;
    }

    /**
     * This prints a heading description of the script being executed. Intended to be used by the developer.
     */
    public function outputHeading()
    {
        if ($this->modifyOutputHeading == false) {
            $navigation = $this->navigation->navigation;

            if (!empty($this->title)) {
                print $this->mod->heading($this->title);
            } else if (isset($navigation[$this->configuration['m']]['node_name'])) {
                print $this->mod->activeName($navigation[$this->configuration['m']]['node_name']);
            }
        } else {
            print $this->modifyOutputHeading;
        }
    }

    /**
     * Sets template system logo or name.
     */
    public function outputTextLogo()
    {
        // Check if output should be modified.
        if ($this->modifyOutputTextLogo == false) {
            // Output active info.
            print $this->configuration['scripts_name_version'];
        } else {
            print $this->modifyOutputTextLogo;
        }
    }

    /**
     * Prints the last footer string to the template system. Intended to be used by the engine.
     */
    public function outputFooter()
    {
        // Check if output should be modified.
        if ($this->modifyOutputFooter == false) {
            print $this->configuration['footer_notes'];
        } else {
            print $this->modifyOutputFooter;
        }
    }

    /**
     * Will add js code from configuration to theme closing body tag.
     */
    public function outputFooterJS()
    {
        print $this->configuration['footer_js'];
    }

    /**
     * This method is used to load a widget at into a certain location of your page.
     *
     * @param string $node_id_to_load
     * @param string $element_id
     * @param string $extend_url
     * @param string $settings
     *
     * @return bool
     */
    public function requestWidget($node_id_to_load, $element_id, $extend_url = '', $settings = '')
    {
        if (!empty($this->navigation->navigation["$node_id_to_load"])) {

            $widget_url = $this->navigation->buildURL($node_id_to_load, $extend_url, true);
            $text       = sprintf(___('Busy Loading <strong>%s</strong>...'), $this->navigation->navigation["$node_id_to_load"]['node_name']);

            // Widget ajax code...
            $JS = $this->mod->widget($widget_url, $element_id, $text, $settings);

            $this->addJsToHead($JS);

            return true;
        } else {
            return false;
        }
    }

    /**
     * This method is used to load ajax into a certain location of your page.
     *
     * @param string $node_id_to_load
     * @param string $element_id
     * @param string $extend_url
     * @param string $settings
     *
     * @return bool
     */
    public function requestAjax($node_id_to_load, $element_id, $extend_url = '', $settings = '')
    {
        if (!empty($this->navigation->navigation["$node_id_to_load"])) {

            $ajax_url = $this->navigation->buildURL($node_id_to_load, $extend_url, true);
            $text     = sprintf(___('Busy Loading <strong>%s</strong>...'), $this->navigation->navigation["$node_id_to_load"]['node_name']);

            // Ajax code...
            $JS = $this->mod->ajax($ajax_url, $element_id, $text, $settings);

            $this->addJsToHead($JS);

            return true;
        } else {
            return false;
        }
    }

    /**
     * This method is used to load a lightbox page.
     *
     * @param string $node_id_to_load
     * @param string $element_id
     * @param string $extend_url
     * @param string $settings
     *
     * @return bool
     */
    public function requestLightbox($node_id_to_load, $element_id, $extend_url = '', $settings = '')
    {
        if (!empty($this->navigation->navigation["$node_id_to_load"])) {

            $this->lightbox = true;

            $this->addJsFileToHead($this->mod->lightBoxScript());
            $this->addCssFileToHead($this->mod->lightBoxCss());

            $lightbox_url = $this->navigation->buildURL($node_id_to_load, $extend_url, true);

            // Jquery code...
            $JS = $this->mod->lightBox($element_id, $settings = '');

            $this->addJsToHead($JS);

            return $lightbox_url;
        } else {
            return false;
        }
    }

    /**
     * Pushes javascript to <head> for styling purposes.
     */
    public function styleButtons()
    {
        $this->addJSToHead($this->mod->styleButtons());
    }

    /**
     * Pushes javascript to <head> for validationg purposes.
     */
    public function validateForms()
    {
        $this->addJsFileToHead($this->mod->formsValidateJs());
        $this->addJSToHead($this->mod->formsValidate());
    }

    /**
     * Pushes javascript to <head> for styling purposes.
     */
    public function styleForms()
    {
        $this->addJSToHead($this->mod->styleForms());
    }

    /**
     * Pushes javascript to <head> for styling purposes.
     */
    public function styleFloatHeaders()
    {
        $this->addJsFileToHead($this->mod->styleFloatHeadersScript());
        $this->addJSToHead($this->mod->styleFloatHeaders());
    }

    /**
     * Pushes javascript to <head> for styling purposes.
     */
    public function styleTables()
    {
        $this->addJSToHead($this->mod->styleTables());
    }

    /**
     * Pushes javascript to <head> for styling purposes.
     */
    public function stylePagination()
    {
        $this->addJSToHead($this->mod->stylePagination());
    }

    /**
     * Pushes javascript to <select> for styling purposes.
     */
    public function styleSelect()
    {
        $this->addJsFileToHead($this->mod->styleSelectJs());
        $this->addJsToHead($this->mod->styleSelectHeader());

    }

    /**
     * Calls a single jquery-ui effect plugin and includes it inside head.
     */
    public function jqueryEffect($plugin)
    {
        foreach (func_get_args() as $plugin) {
            $this->addJsFileToHead($this->mod->jqueryEffect($plugin));
        }
    }

    /**
     * Calls a single jquery-ui plugin and includes it inside head.
     */
    public function jqueryUI($plugin)
    {
        foreach (func_get_args() as $plugin) {
            $this->addJsFileToHead($this->mod->jqueryUI($plugin));
        }
    }

    /**
     * Activate a GUI plugin, i.e. give the plugin the opportunity to do whatever is needed so be usable from the Javascript code
     *
     * @param string $plugin the name of the plugin
     * @param mixed $parameters (optional) parameters if the plugin have ones
     *
     * @return iPHPDS_activableGUI the plugin
     */
    public function activatePlugin($plugin, $parameters = null)
    {
        $parameters = func_get_args();
        $path = $this->classFactory->classFolder($plugin);

        $plugin = $this->factory(array('classname' => $plugin, 'factor' => 'singleton'), $path);
        if (is_a($plugin, 'iPHPDS_activableGUI')) {
            $plugin->activate($parameters);
        }

        return $plugin;
    }

    /**
     * Ability to call and display notifications pushed to the notification system.
     */
    public function outputNotifications()
    {
        $notifications = $this->notif->fetch();
        $mod           = $this->mod;

        if (!empty($notifications)) {
            $this->addJsFileToHead($mod->notificationsJs());
            foreach ($notifications as $notification) {
                if (is_array($notification)) {
                    switch ($notification[0]) {
                        case 'info':
                            $title = ___('Info');
                            break;
                        case 'error':
                            $title = ___('Error');
                            break;
                        case 'warning':
                            $title = ___('Warning');
                            break;
                        case 'ok':
                            $title = ___('Ok');
                            break;
                        case 'critical':
                            $title = ___('Critical');
                            break;
                        case 'notice':
                            $title = ___('Notice');
                            break;
                        case 'busy':
                            $title = ___('Busy');
                            break;
                        case 'message':
                            $title = ___('Message');
                            break;
                        case 'note':
                            $title = ___('Note');
                            break;
                        default:
                            $title = ___('Info');
                            break;
                    }
                    $this->addJsToHead($mod->notifications($title, $notification[1], $notification[0]));
                } else {
                    $this->addJsToHead($mod->notifications(___('Info'), $notification));
                }
            }
        }
    }

    /**
     * This method will load given png icon from icon database,
     *
     * @param string  $name    Icon name without extention.
     * @param string  $title   of given image.
     * @param int     $size    The size folder to look within.
     * @param string  $class   If an alternative class must be added to image.
     * @param string  $type    File type.
     * @param boolean $return  Default is false, if set true, the heading will return instead of print.
     * @return string|void
     */
    public function icon($name, $title = null, $size = 16, $class = 'class', $type = '.png', $return = true)
    {
        $navigation = $this->navigation->navigation;
        // Create icon dir.
        $script_url = $this->CDN . '/themes/' . $navigation[$this->configuration['m']]['theme_folder'] . '/images/icons-' . $size . '/' . $name . $type;
        if (empty ($title))
            $title = '';
        // Create HTML.
        $html = $this->mod->icon($script_url, $class, $title);

        // Return or print to browser.
        if ($return == false) {
            print $html;
        } else if ($return == true) {
            return $html;
        }
        return null;
    }

    /**
     * This returns/prints info of the script being executed. Intended to be used by the developer.
     *
     * @param string $information message
     * @param string $return
     * @return string|void
     */
    public function info($information, $return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('info', $information));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->info($information);
        }
        return null;
    }

    /**
     * This returns/prints a warning message regarding the active script. Intended to be used by the developer.
     *
     * @param string $warning message
     * @param string $return
     * @param string $log
     * @return string|void
     */
    public function warning($warning, $return = 'print', $log = 'log')
    {
        if ($log === true || $log == 'log') {
            // Log types are : ////////////////
            // 1 = OK /////////////////////////
            // 2 = Warning ////////////////////
            // 3 = Critical ///////////////////
            // 4 = Log-in /////////////////////
            // 5 = Log-out ////////////////////
            ///////////////////////////////////
            $log_type = 2; ////////////////////
            // Log the event //////////////////
            $this->user->logArray[] = array('log_type' => $log_type, 'log_description' => $warning);
        }
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('warning', $warning));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->warning($warning);
        }
        return null;
    }

    /**
     * This returns/prints a ok message regarding the active script. Intended to be used by the developer.
     *
     * @param string $ok message
     * @param string $return
     * @param string $log
     * @return string|void
     */
    public function ok($ok, $return = 'print', $log = 'log')
    {
        if ($log === true || $log == 'log') {
            // Log types are : ////////////////
            // 1 = OK /////////////////////////
            // 2 = Warning ////////////////////
            // 3 = Critical ///////////////////
            // 4 = Log-in /////////////////////
            // 5 = Log-out ////////////////////
            ///////////////////////////////////
            $log_type = 1; ////////////////////
            // Log the event //////////////////
            $this->user->logArray[] = array('log_type' => $log_type, 'log_description' => $ok);
        }
        // Create HTML.

        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('ok', $ok));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->ok($ok);
        }
        return null;
    }

    /**
     * This returns/prints a error message regarding the active script. Intended to be used by the developer where exceptions are caught.
     *
     * @param string $error message
     * @param string $return
     * @param string $log
     * @return string|void
     */
    public function error($error, $return = 'print', $log = 'log')
    {
        if ($log === true || $log == 'log') {
            // Log types are : ////////////////
            // 1 = OK /////////////////////////
            // 2 = Warning ////////////////////
            // 3 = Critical ///////////////////
            // 4 = Log-in /////////////////////
            // 5 = Log-out ////////////////////
            // 6 = Error //////////////////////
            ///////////////////////////////////
            $log_type = 6; ////////////////////
            // Log the event //////////////////
            $this->user->logArray[] = array('log_type' => $log_type, 'log_description' => $error);
        }
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('error', $error));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->error($error);
        }
        return null;
    }

    /**
     * This returns/prints a critical message regarding the active script. Intended to be used by the developer.
     *
     * @param string $critical message
     * @param string $return
     * @param string $log
     * @param string $mail
     * @return string|void
     * @throws PHPDS_exception
     */
    public function critical($critical, $return = 'print', $log = 'log', $mail = 'mailadmin')
    {
        if ($log === true || $log == 'log') {
            // Log types are : ////////////////
            // 1 = OK /////////////////////////
            // 2 = Warning ////////////////////
            // 3 = Critical ///////////////////
            // 4 = Log-in /////////////////////
            // 5 = Log-out ////////////////////
            ///////////////////////////////////
            $log_type = 3; ////////////////////
            // Log the event //////////////////
            $this->user->logArray[] = array('log_type' => $log_type, 'log_description' => $critical);
        }
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('critical', $critical));
            // Let the ajax controller know there was a critical error.
            if (PU_isAJAX()) PU_silentHeaderStatus(500, $critical);
        } else if ($return === 'return' || $return == true) {
            return $this->mod->critical($critical);
        }
        return null;
    }

    /**
     * This returns/prints a notice of the script being executed. Intended to be used by the developer.
     *
     * @param string $notice message
     * @param string $return
     * @return string|void
     */
    public function notice($notice, $return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('notice', $notice));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->notice($notice);
        }
        return null;
    }

    /**
     * This returns/prints a busy of the script being executed. Intended to be used by the developer.
     *
     * @param string $busy message
     * @param string $return
     * @return string|void
     */
    public function busy($busy, $return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('busy', $busy));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->busy($busy);
        }
        return null;
    }

    /**
     * This returns/prints a message of the script being executed. Intended to be used by the developer.
     *
     * @param string $message message
     * @param string $return
     * @return string|void
     */
    public function message($message, $return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('message', $message));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->message($message);
        }
        return null;
    }

    /**
     * This returns/prints a note of the script being executed. Intended to be used by the developer.
     *
     * @param string $note message
     * @param string $return
     * @return string|void
     */
    public function note($note, $return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            $this->notif->add(array('note', $note));
        } else if ($return === 'return' || $return == true) {
            return $this->mod->note($note);
        }
        return null;
    }

    /**
     * This returns/prints a heading of the script being executed. Intended to be used by the developer.
     *
     * @param string $scripthead message
     * @param string $return
     * @return string|void
     */
    public function scripthead($scripthead, $return = 'print')
    {
        // Return or print to browser.
        if ($return === 'print' || $return == false) {
            //print $html;
            $this->notif->add($scripthead);
        } else if ($return === 'return' || $return == true) {
            return $this->mod->scriptHead($scripthead);
        }
        return null;
    }

    /**
     * This creates an the [i] when over with mouse a popup with a message appears, this can be placed anywhere.
     * Intended to be used by the developer.
     *
     * @param string $text message
     * @param bool $print
     * @return string|void
     */
    public function tip($text, $print = false)
    {
        // This is yet another IE Fix !
        $text_clean = preg_replace('/"/', '', $text);
        $info       = $this->mod->toolTip($text_clean);
        if ($print == false) {
            return $info;
        } else {
            print $info;
        }
        return null;
    }

    /**
     * Login heading messages.
     *
     * @param bool $return
     * @return string|void
     */
    public function loginFormHeading($return = false)
    {
        $HTML    = '';
        $message = '';

        // Create headings for login.
        if (!empty($this->core->haltController)) {
            $this->heading(___('Authentication Required'));
        } else {
            // Get some default settings.
            $settings = $this->config->getSettings(array('login_message'));

            // Check if we have a login message to display.
            if (!empty($settings['login_message'])) {
                $login_message = $this->message(___($settings['login_message']), 'return');
            } else {
                $login_message = '';
            }

            $this->heading(___('Login'));
            $HTML .= $login_message;
            $HTML .= $message;
        }

        if ($return == false) {
            print $HTML;
        } else {
            return $HTML;
        }
        return null;
    }

    /**
     * Executes the login html stack.
     *
     * @param bool $return
     * @return string|void
     */
    public function loginForm($return = false)
    {
        $login_ = $this->auth->buildRequest();
        $HTML = $this->mod->loginForm(
            $login_['post_login_url'],
            ___('Username or Email'),
            ___('Password'),
            $login_['redirect_page'],
            $login_['lost_password_page_id'],
            ___('Lost Password?'),
            $login_['registration'],
            ___('Not registered yet?'),
            ___('Remember Me?'),
            $this->postValidation(),
            ___('Account Detail'),
            $login_['user_name'],
            __('Submit'));

        if ($return == false) {
            print $HTML;
        } else {
            return $HTML;
        }
        return null;
    }

    /**
     * This function creates tag view list with form input fields. Can also store it if available.
     *
     * @param string $object
     * @param string $target
     * @param array  $taggernames   Array of names posted by the tagger form.
     * @param array  $taggervalues  Array of values posted by the tagger form.
     * @param array  $taggerids     Array of updated ids posted by the tagger form.
     * @return array
     */
    public function tagArea($object, $target, $taggernames, $taggervalues, $taggerids)
    {
        $taglist = $this->tagger->tagArea($object, $target, $taggernames, $taggervalues, $taggerids);
        $tagview = $this->mod->taggerArea($taglist, ___('Tag Name'), ___('Tag Value'));

        return $tagview;
    }

    /**
     * Shows alternative logout information and preferences to existing logged in users.
     *
     * @param bool $return
     * @return string|void
     */
    public function outputLogin($return = false)
    {
        $configuration = $this->configuration;
        $nav           = $this->navigation;
        $mod           = $this->mod;
        $logouturl     = $nav->buildURL(null, 'logout=1');
        $logoutname    = ___('Log Out');

        if ($this->user->isLoggedIn()) {
            // Check if preferences page exists.
            $HTML = $mod->loggedInInfo(
                $configuration['user_display_name'],
                $logouturl, $logoutname,
                $mod->role($configuration['user_role_name']),
                $nav->navigation);
        } else {
            $HTML = $this->loginForm($return);
        }

        if ($return == false) {
            print $HTML;
        } else {
            return $HTML;
        }
        return null;
    }

    /**
     * An alternative way to add more custom links to your page, these are direct links to existing node items.
     * Contains an array of available links.
     *
     * @param bool $return
     * @return string|void
     */
    public function outputAltNav($return = false)
    {
        $nav = $this->navigation->navigation;

        if ($return == false) {
            print $this->mod->altNav($nav);
        } else {
            return $this->mod->altNav($nav);
        }
        return null;
    }

    /**
     * This provides a simple styled link depending if the user is logged in or not.
     *
     * @param bool $return
     * @return string|void
     */
    public function outputAltHome($return = false)
    {
        $home = $this->configuration[$this->user->isLoggedIn() ? 'front_page_id_in' : 'front_page_id'];
        if (! empty($this->navigation->navigation[$home])) {
            $nav = $this->navigation->navigation[$home];
        } else {
            $nav = '';
        }

        if ($return == false) {
            print $this->mod->altHome($nav);
        } else {
            return $this->mod->altHome($nav);
        }
        return null;
    }

    /**
     * Get and return the supposed to run template.
     *
     * @return string if not found, return default.
     */
    public function getTemplate()
    {
        $settings['default_template'] = '';

        // Check if the node has a defined template.
        if (!empty($this->navigation->navigation[$this->configuration['m']]['theme_folder'])) {
            $settings['default_template'] = $this->navigation->navigation[$this->configuration['m']]['theme_folder'];
        } else {
            // If not check if the gui system settings was set with a default template.
            $settings['default_template'] = $this->configuration['default_template'];
        }

        // Return the complete template.
        return $settings['default_template'];
    }

    /**
     * Returns some debug info to the frontend, at the bottom of the page.
     */
    public function debugInfo()
    {
        if ($this->configuration['page_loadtimes']) {
            if (!empty($this->core->themeFile)) {
                $memory_used = memory_get_usage();
                $time_spent  = intval((microtime(true) - $GLOBALS['start_time']) * 1000);
                return $this->mod->debug($this->db->queryCount,
                    number_format($memory_used / 1000000, 2, '.', ' '), $time_spent);
            }
        }
        return null;
    }

    /**
     * Prints some debug info to the frontend, at the bottom of the page.
     */
    public function outputDebugInfo()
    {
        print $this->debugInfo();
    }

    /**
     * Convert all HTML entities to their applicable characters.
     *
     * @param string $string_to_decode
     * @return string
     */
    public function htmlEntityDecode($string_to_decode)
    {
        // Decode characters.
        return html_entity_decode($string_to_decode, ENT_QUOTES, $this->configuration['charset']);
    }

    /**
     * Use inside your form brackets to send through a token validation to limit $this->post received from external pages.
     *
     * @return string Returns hidden input field.
     */
    public function postValidation()
    {
        return $this->validatePost();
    }

    /**
     * Use inside your form brackets to send through a token validation to limit $this->post received from external pages.
     *
     * @return string Returns hidden input field.
     */
    public function validatePost()
    {
        $token                              = md5(uniqid(rand(), true));
        $key                                = md5($this->configuration['crypt_key']);
        $_SESSION['token_validation'][$key] = $token;
        return $this->template->mod->securityToken($token);
    }

    /**
     * This is used in the search filter to validate $this->post made by the search form.
     *
     * @return string Returns hidden input field.
     */
    public function searchFormValidation()
    {
        $search_token                              = md5(uniqid(rand(), true));
        $search_key                                = md5(sha1($this->configuration['crypt_key']));
        $_SESSION['token_validation'][$search_key] = $search_token;
        return $this->template->mod->searchToken($search_token);
    }
}
