<?php
/**
 * These are the classes specific to MySQL
 */


class PHPDS_mysql extends PHPDS_genericDB
{
    /**
     * {@inheritDoc}
     */
    public function throwException($e = null, $code = 0)
    {
        $e = $this->factory('PHPDS_MySQLException', $e, $code);
        parent::throwException($e);
    }
}

/**
 * An exception handling MySQL error codes
 */
class PHPDS_MySQLException extends PHPDS_databaseException
{
    protected $ignoreLines = 4;

    /**
     * {@inheritDoc}
     */
    // CAUTION this declaration is NOT correct but PHP insists on this declaration, last param $dependancy is missing
    public function construct($message = "", $code = 0, $previous = null)
    {
        if ($code == 1045) {
            $this->ignoreLines = 5;
        }
        $msg = 'The MySQL database engine returned with an error' . ': "' . $message . '"';
        parent::construct($msg, $code, $previous);
    }

    public function hasCauses()
    {
        return in_array($this->getCode(), array(
            1044, 1045, // access denied
            0, // unknown error
            1049, // unknown database
            2002, // cannot connect
            1146 // table doesn't exist
        ));
    }

    public function getCauses()
    {
        $special = '';
        switch ($this->getCode()) {
            case 1044:
            case 1045:
                $special = 'db_access_denied';
                break;
            case 0:
                $special = 'db_unknown';
                break;
            case 1049:
                $special = 'db_unknown';
                break;
            case 2002:
                $special = 'db_silent';
                break;
            case 1146:
                $special = 'db_noexist';
                break;
        }

        $coding_error = array(
            'PHP Coding error interrupted query model, see uncaught exception below.',
            'This is normally nothing too serious just check your code and find the mistake you made by following the exception below.'
        );
        $phpds_not_installed = array(
            'You did not run the install script',
            'If you haven\'t run the installation procedure yet, you should <a href="other/service/index.php">run it</a> now.'
        );
        $db_wrong_cred = array(
            'It is possible that the wrong credentials have been given in the configuration file.',
            'Please check the content of your configuration file(s).'
        );
        $db_wrong_dbname = array(
            'It is possible that the wrong database name has been given in the configuration file.',
            'Please check the content of your configuration file(s).'
        );
        $db_down = array(
            'The server is not running or is firewalled.',
            'Please check if the database server is up and running and reachable from the webserver.'
        );
        $db_denies = array(
            'The server won\'t accept the database connection.',
            'Please check if the database server is configured to accept connection from the webserver.'
        );

        switch ($special) {
            case 'db_access_denied':
                $result = array(
                    'Access to the database was not granted using the parameters set in the configuration file.',
                    array($phpds_not_installed, $db_wrong_cred)
                );
                break;
            case 'db_silent':
                $result = array(
                    'Unable to connect to the database (the database server didn\'t answer our connection request)',
                    array($db_down, $db_denies, $db_wrong_cred)
                );
                break;
            case 'db_unknown':
                $result = array(
                    'The connection to the server is ok but the database could not be found.',
                    array($coding_error, $phpds_not_installed, $db_wrong_dbname)
                );
                break;
            case 'db_noexist':
                $result = array(
                    'The connection to the server is ok and the database is known but the table doesn\'t exists.',
                    array($phpds_not_installed, $db_wrong_dbname)
                );
                break;
            default:
                $result = array(
                    'Unknown special case.',
                    array()
                );
        }
        return $result;
    }


}
