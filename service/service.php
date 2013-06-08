<?php

$HTMl = '';
$type = 'service';

require ('service.inc.php');
$TITLE = "$package Installer/Upgrader";

// Store everything in cache...
preparations();

/// main switch
headHTML();

displayIntro();

// end html
footHTML();

function displayIntro()
{
    global $version, $config;

    ?>
    <div class="row">
        <div class="span6">
            <h2>Fresh install</h2>

            <p><strong>If you want a fresh install</strong>, click "Install New Copy".
                The installer will check your system for minimal requirements and install the database tables.
                To enforce that only the owner of the site can continue with the installation, you will be asked several
                parameters found in the configuration file.
            </p>

            <p>
                <button onClick="parent.location='install.php'" value="install" class="btn btn-large btn-success">
                    Install New Copy
                </button>
            </p>
            <div class="alert alert-info">
                <?php echo $config['info'] ?>
            </div>
        </div>
        <div class="span6">
            <h2>Upgrade existing</h2>

            <p><strong>If you already have a previous version installed</strong> and want to update to
                the new version <?php echo $version ?>, click "Upgrade Existing Installation".
                The upgrade script will run a series of upgrade SQL commands and do changes to multiple tables making it
                compatible with latest version of the codebase.
            </p>

            <p>
                <button onClick="parent.location='install.php'" value="upgrade" class="btn btn-large btn-primary">
                    Upgrade Existing Installation
                </button>
            </p>
            <div class="alert alert-warning">
                <?php echo $config['note'] ?>
            </div>
        </div>
    </div>
<?php
}