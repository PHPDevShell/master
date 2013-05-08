<div id="nav">
    <div id="menu" class="navbar navbar-static-top navbar-inverse">
        <div class="navbar-inner">
            <!-- MENU AREA -->
            <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <ul class="nav pull-right">
                <?php $template->outputAltNav() ?>
                <li class="dropdown">
                    <a id="login-url" href="#" data-toggle="dropdown" class="dropdown-toggle">
                        <i class="icon-user icon-white"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <?php $template->outputLogin() ?>
                        </li>
                    </ul>
                </li>
                <?php $template->outputAltHome() ?>
            </ul>
            <div class="collapse nav-collapse">
                <ul id="main-nav" class="nav">
                    <?php $template->outputMenu() ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<div id="ajax-loader-art" class="fade">
    <div class="progress progress-striped active">
        <div id="progress-bar" class="bar"></div>
    </div>
</div>
